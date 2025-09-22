<?php

namespace App\Http\Controllers;

use App\Models\Invitation;
use App\Models\User;
use App\Models\StaffProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;

class InvitationController extends Controller
{

    /**
     * @OA\Post(
     *     path="/admin/staff/invite",
     *     summary="Invite new staff",
     *     tags={"Admin"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 required={"email", "role"},
     *                 @OA\Property(property="email", type="string", format="email", example="newstaff@example.com"),
     *                 @OA\Property(property="role", type="string", enum={"admin", "staff"}, example="staff"),
     *                 @OA\Property(property="name", type="string", example="New Staff Member")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Staff invited",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Invitation sent successfully")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Unauthorized"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */


    public function sendInvitation(Request $request)
    {
        Log::info("Sending invitation..");
        // Only admin can send invitations
        if ($request->user()->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        Log::info("Request Data");

        // log all the request data
        foreach ($request->all() as $key => $value) {
            Log::info($key . ': ' . $value);
        }

        $validator = Validator::make($request->all(), [
            'email' => 'required|email|unique:users,email',
            'role' => 'required|in:staff,admin,hr,payroll',
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            // 'job_title' => 'required|string|max:255',
            'department' => 'required|string|max:255',
            // 'start_date' => 'date'
        ]);

        $job_title = "Staff";




        if ($validator->fails()) {
            Log::info("Invitation has been !validated");
            // log all the error messages
            for ($i = 0; $i < count($validator->errors()); $i++) {
                Log::info($validator->errors()->all()[$i]);
            }
            return response()->json(['errors' => $validator->errors()], 422);
        }

        Log::info("Invitation has been validated");
        // Check if invitation already exists
        $existingInvitation = Invitation::where('email', $request->email)
            ->where('status', 'pending')
            ->first();

        if ($existingInvitation) {
            return response()->json(['message' => 'Invitation already sent to this email'], 409);
        }

        // custom startdate
        $startDate = date('Y-m-d', strtotime($request->start_date));
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
            'job_title' => $job_title,
            // 'job_title' => $request->job_title,
            'department_unit' => $request->department,
            'start_date' => $startDate
        ]);

        // Send invitation email (implement your email service)
        // $this->sendInvitationEmail($request->email, $token);


        // Log the returnd information to the log file
        Log::info("Invitation has been sent");
        Log::info("This is the token: " . $token);


        return response()->json([
            'message' => 'Invitation sent successfully',
            'token' => $token
            // 'invitation' => $invitation
        ], 201);
    }

    /**
     * @OA\Post(
     *     path="/invitation/accept/{token}",
     *     summary="Accept staff invitation",
     *     tags={"Invitations"},
     *     @OA\Parameter(
     *         name="token",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="string"),
     *         description="Invitation token"
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 required={"password", "password_confirmation"},
     *                 @OA\Property(property="password", type="string", format="password", example="secret123"),
     *                 @OA\Property(property="password_confirmation", type="string", format="password", example="secret123")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Invitation accepted",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Invitation accepted successfully")
     *         )
     *     ),
     *     @OA\Response(response=400, description="Invalid token"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */

    /**
     * @OA\Post(
     *     path="/bootstrap/admin",
     *     summary="Bootstrap first admin",
     *     tags={"Admin"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 required={"name", "email", "password", "password_confirmation"},
     *                 @OA\Property(property="name", type="string", example="Admin User"),
     *                 @OA\Property(property="email", type="string", format="email", example="admin@example.com"),
     *                 @OA\Property(property="password", type="string", format="password", example="secret123"),
     *                 @OA\Property(property="password_confirmation", type="string", format="password", example="secret123")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Admin created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Admin created successfully")
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */

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