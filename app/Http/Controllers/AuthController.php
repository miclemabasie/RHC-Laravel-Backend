<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\MFACode;
use App\Models\StaffProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        if ($user->status !== 'active') {
            return response()->json(['message' => 'Account is inactive'], 403);
        }

        // Generate MFA code
        $mfaCode = Str::random(6);
        $hashedCode = Hash::make($mfaCode);

        // Store MFA code
        MFACode::create([
            'user_id' => $user->id,
            'code_hash' => $hashedCode,
            'expires_at' => now()->addMinutes(10),
            'used' => false
        ]);

        // Send MFA code via SMS (implementation depends on your SMS provider)
        // $this->sendSMS($user->phone, "Your MFA code is: $mfaCode");

        return response()->json([
            'message' => 'MFA code sent to your phone',
            'user_id' => $user->id,
            'code' => $mfaCode
        ]);
    }

    public function verifyMfa(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|uuid',
            'code' => 'required|string|size:6'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = User::find($request->user_id);
        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        $mfaCode = MFACode::where('user_id', $user->id)
            ->where('used', false)
            ->where('expires_at', '>', now())
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$mfaCode || !Hash::check($request->code, $mfaCode->code_hash)) {
            $mfaCode->increment('attempts');
            return response()->json(['message' => 'Invalid MFA code'], 401);
        }

        // Mark MFA code as used
        $mfaCode->update(['used' => true]);

        // Create API token
        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'message' => 'Login successful',
            'token' => $token,
            'user' => $user->load('staffProfile')
        ]);
    }

    // public function logout(Request $request)
    // {
    //     $request->user()->currentAccessToken()->delete();
    //     return response()->json(['message' => 'Logged out successfully']);
    // }
}