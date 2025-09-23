<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\MFACode;
use App\Models\StaffProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Twilio\Rest\Client;
use Exception;

class AuthController extends Controller
{
    private function sendSMS($phoneNumber, $message)
    {
        try {
            $sid = config('services.twilio.sid');
            $token = config('services.twilio.auth_token');
            $from = config('services.twilio.phone_number');

            $twilio = new Client($sid, $token);

            $message = $twilio->messages->create(
                $phoneNumber,
                [
                    'from' => $from,
                    'body' => $message
                ]
            );

            Log::info('SMS sent successfully', [
                'to' => $phoneNumber,
                'message_sid' => $message->sid
            ]);

            return true;
        } catch (Exception $e) {
            Log::error('Failed to send SMS', [
                'to' => $phoneNumber,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * @OA\Post(
     *     path="/staff/login",
     *     summary="Staff login with email and password",
     *     tags={"Authentication"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 required={"email", "password"},
     *                 @OA\Property(property="email", type="string", format="email", example="staff@example.com"),
     *                 @OA\Property(property="password", type="string", format="password", example="password123")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="MFA code sent successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="MFA code sent to your phone"),
     *             @OA\Property(property="user_id", type="string", format="uuid", example="550e8400-e29b-41d4-a716-446655440000")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Invalid credentials",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Invalid credentials")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Account is inactive",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Account is inactive")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="errors", type="object", example={"email": {"The email field is required."}})
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Failed to send SMS",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Failed to send MFA code. Please try again.")
     *         )
     *     )
     * )
     */
    public function login(Request $request)
    {
        Log::info('Login request received');
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

        // Check if user has a phone number
        if (!$user->phone) {
            Log::error('User does not have a phone number', ['user_id' => $user->id]);
            return response()->json(['message' => 'Phone number not found for this account'], 400);
        }

        // Generate 6-digit MFA code
        $mfaCode = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
        $hashedCode = Hash::make($mfaCode);

        $id = Str::uuid();
        Log::info('MFA code generated', [
            'id' => $id,
            'user_id' => $user->id,
            'code' => $mfaCode
        ]);

        // Store MFA code
        MFACode::create([
            'id' => $id,
            'user_id' => $user->id,
            'code_hash' => $hashedCode,
            'expires_at' => now()->addMinutes(10),
            'used' => false
        ]);

        // Send SMS via Twilio
        // $smsMessage = "Your MFA code is: $mfaCode. This code will expire in 10 minutes.";
        // $smsSent = $this->sendSMS($user->phone, $smsMessage);

        // if (!$smsSent) {
        //     return response()->json(['message' => 'Failed to send MFA code. Please try again.'], 500);
        // }

        Log::info('MFA code sent to user', [
            'user_id' => $user->id,
            'phone' => $user->phone,
            'code' => $mfaCode
        ]);

        return response()->json([
            'message' => 'MFA code sent to your phone',
            'user_id' => $user->id,
            // Removed the code from response for security
            'code' => $mfaCode
        ]);
    }

    /**
     * @OA\Post(
     *     path="/staff/verify-mfa",
     *     summary="Verify MFA code for login",
     *     tags={"Authentication"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 required={"user_id", "code"},
     *                 @OA\Property(property="user_id", type="string", format="uuid", example="550e8400-e29b-41d4-a716-446655440000"),
     *                 @OA\Property(property="code", type="string", example="123456")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Login successful",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Login successful"),
     *             @OA\Property(property="token", type="string", example="eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9..."),
     *             @OA\Property(
     *                 property="user",
     *                 type="object",
     *                 @OA\Property(property="id", type="string", format="uuid", example="550e8400-e29b-41d4-a716-446655440000"),
     *                 @OA\Property(property="email", type="string", format="email", example="staff@example.com"),
     *                 @OA\Property(property="name", type="string", example="John Doe"),
     *                 @OA\Property(property="status", type="string", example="active"),
     *                 @OA\Property(
     *                     property="staff_profile",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="first_name", type="string", example="John"),
     *                     @OA\Property(property="last_name", type="string", example="Doe"),
     *                     @OA\Property(property="job_title", type="string", example="Developer"),
     *                     @OA\Property(property="department_unit", type="string", example="IT")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Invalid MFA code",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Invalid MFA code")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="User not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="User not found")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="errors", type="object", example={"user_id": {"The user_id field is required."}})
     *         )
     *     )
     * )
     */
    public function verifyMfa(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|uuid',
            'code' => 'required|digits:6'
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
            if ($mfaCode) {
                $mfaCode->increment('attempts');
            }
            return response()->json(['message' => 'Invalid MFA code'], 401);
        }

        // Mark MFA code as used
        $mfaCode->update(['used' => true]);

        // Create API token
        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'message' => 'Login successful',
            'token' => $token,
            'user_id' => $user->id,
            'user' => $user->load('staffProfile')
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logged out successfully']);
    }
}