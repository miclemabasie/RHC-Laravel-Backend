<?php

namespace App\Http\Controllers;

use App\Models\Invitation;
use App\Models\User;
use App\Models\StaffProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;

class InvitationController extends Controller
{
    public function sendInvitation(Request $request)
    {
        // Only admin can send invitations
        if ($request->user()->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'email' => 'required|email|unique:users,email',
            'role' => 'required|in:staff,admin,hr,payroll',
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'job_title' => 'required|string|max:255',
            'department_unit' => 'required|string|max:255',
            'start_date' => 'required|date'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Check if invitation already exists
        $existingInvitation = Invitation::where('email', $request->email)
            ->where('status', 'pending')
            ->first();

        if ($existingInvitation) {
            return response()->json(['message' => 'Invitation already sent to this email'], 409);
        }

        // Create invitation
        $token = Str::random(60);
        $invitation = Invitation::create([
            'email' => $request->email,
            'token' => $token,
            'invited_by' => $request->user()->id,
            'role' => $request->role,
            'expires_at' => now()->addDays(7),
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'job_title' => $request->job_title,
            'department_unit' => $request->department_unit,
            'start_date' => $request->start_date,
        ]);

        // Send invitation email (implement your email service)
        // $this->sendInvitationEmail($request->email, $token);

        return response()->json([
            'message' => 'Invitation sent successfully',
            'token' => $token
            // 'invitation' => $invitation
        ], 201);
    }

    public function acceptInvitation(Request $request, $token)
    {
        $invitation = Invitation::where('token', $token)
            ->where('status', 'pending')
            ->where('expires_at', '>', now())
            ->firstOrFail();

        $validator = Validator::make($request->all(), [
            'password' => 'required|string|min:8|confirmed',
            'phone' => 'required|string|max:20'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Create user
        $user = User::create([
            'email' => $invitation->email,
            'name' => $invitation->first_name,
            'password' => Hash::make($request->password),
            'phone' => $request->phone,
            'role' => $invitation->role,

            'status' => 'active'
        ]);

        // Create staff profile
        StaffProfile::create([
            'user_id' => $user->id,
            'first_name' => $invitation->first_name,
            'last_name' => $invitation->last_name,
            'job_title' => $invitation->job_title,
            'department_unit' => $invitation->department_unit,
            'start_date' => $invitation->start_date
        ]);

        // Update invitation status
        $invitation->update(['status' => 'accepted']);

        return response()->json([
            'message' => 'Account created successfully. You can now login.',
            'user_id' => $user->id
        ], 201);
    }
}