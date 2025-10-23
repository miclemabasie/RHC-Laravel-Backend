<?php

namespace App\Http\Controllers;

use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class AdminController extends Controller
{
    /**
     * Bootstrap the very first admin account.
     */
    public function bootstrap(Request $request)
    {
        // Check secret key
        if ($request->header('X-ADMIN-KEY') !== config('app.admin_bootstrap_key')) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        // Prevent creating multiple admins this way
        if (User::where('role', 'admin')->exists()) {
            return response()->json(['message' => 'Admin already exists'], 403);
        }

        // Validate input
        $validated = $request->validate([
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'phone' => 'nullable|string|max:20',
            'name' => 'nullable|string|max:100',
        ]);

        // Create the admin with UUID
        $admin = User::create([
            'id' => Str::uuid(),
            'email' => strtolower($validated['email']),
            'password' => Hash::make($validated['password']),
            'phone' => $validated['phone'] ?? null,
            'name' => $validated['name'] ?? null,
            'role' => 'admin',
            'status' => 'active',
        ]);

        StaffProfile::create([
            'user_id' => $admin->id,
            'first_name' => $admin->name,
            'job_title' => $admin->job_title,
            'department_unit' => "Administration",
            'start_date' => "01-01-2025"
        ]);

        return response()->json([
            'message' => 'Admin account created successfully',
            'admin' => [
                'id' => $admin->id,
                'email' => $admin->email,
                'role' => $admin->role,
            ]
        ], 201);
    }

    /**
     * Get all staff members
     */
    public function getAllStaff(Request $request)
    {
        $staff = User::where('role', '!=', 'patient')
            ->with('staffProfile')
            ->get();

        return response()->json([
            'data' => $staff,
            'total' => $staff->count()
        ]);
    }

    /**
     * Get a specific staff member
     */
    public function getStaff($id)
    {
        $staff = User::where('id', $id)
            ->where('role', '!=', 'patient')
            ->firstOrFail();

        return response()->json($staff);
    }

    /**
     * Invite a new staff member
     */


    /**
     * Update a staff member
     */
    public function updateStaff(Request $request, $userId)
    {
        \Log::info('=== START updateStaff ===');
        \Log::info('Request Method: ' . $request->method());
        \Log::info('Content-Type: ' . $request->header('Content-Type'));
        \Log::info('Target User ID: ' . $userId);

        // Get the authenticated user
        $authUser = $request->user();
        \Log::info('Authenticated User ID: ' . $authUser->id);
        \Log::info('Authenticated User Role: ' . $authUser->role);

        // Check if user is admin
        if ($authUser->role !== 'admin') {
            \Log::warning('Unauthorized access attempt by non-admin user: ' . $authUser->id);
            return response()->json([
                'message' => 'Unauthorized. Only administrators can update staff profiles.'
            ], 403);
        }

        // Find the target user to update
        $targetUser = User::find($userId);
        if (!$targetUser) {
            \Log::error('Target user not found: ' . $userId);
            return response()->json([
                'message' => 'User not found.'
            ], 404);
        }

        \Log::info('Target user found: ' . $targetUser->email);

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'phone' => 'sometimes|string|max:20',
            'first_name' => 'sometimes|string|max:255',
            'last_name' => 'sometimes|string|max:255',
            'job_title' => 'sometimes|string|max:255',
            'department_unit' => 'sometimes|string|max:255',
            'profile_photo' => 'sometimes|file|mimes:jpeg,png,jpg,gif|max:2048',
            'role' => 'sometimes|string|in:staff,hr,payroll,admin', // Added role validation
            'status' => 'sometimes|string|in:active,inactive' // Added status validation
        ]);

        if ($validator->fails()) {
            \Log::error('Validation failed: ', $validator->errors()->toArray());
            return response()->json(['errors' => $validator->errors()], 422);
        }

        \Log::info('Validation passed');

        // Update user info
        $userData = [];
        $userFields = ['name', 'phone', 'role', 'status'];

        foreach ($userFields as $field) {
            if ($request->has($field)) {
                $userData[$field] = $request->$field;
            }
        }

        if (!empty($userData)) {
            $targetUser->update($userData);
            \Log::info('User basic info updated: ', $userData);
        }

        // Update staff profile
        if ($targetUser->staffProfile) {
            \Log::info('Staff profile exists for target user');

            $staffProfileData = [];

            // Staff profile fields
            $staffFields = ['first_name', 'last_name', 'job_title', 'department_unit'];
            foreach ($staffFields as $field) {
                if ($request->has($field)) {
                    $staffProfileData[$field] = $request->$field;
                }
            }

            \Log::info('Staff profile update data (excluding photo): ', $staffProfileData);

            // Handle profile photo upload
            if ($request->hasFile('profile_photo')) {
                $profilePhoto = $request->file('profile_photo');
                \Log::info('Profile photo file detected', [
                    'original_name' => $profilePhoto->getClientOriginalName(),
                    'size' => $profilePhoto->getSize(),
                    'mime_type' => $profilePhoto->getMimeType(),
                ]);

                if ($profilePhoto->isValid()) {
                    // Delete old profile photo if exists
                    if ($targetUser->staffProfile->profile_photo) {
                        $oldPhotoPath = str_replace('storage/', '', $targetUser->staffProfile->profile_photo);
                        \Log::info('Deleting old photo: ' . $oldPhotoPath);

                        if (Storage::disk('public')->exists($oldPhotoPath)) {
                            Storage::disk('public')->delete($oldPhotoPath);
                            \Log::info('Old photo deleted');
                        }
                    }

                    // Generate filename and store
                    $filename = 'profile_photo_' . time() . '_' . uniqid() . '.' . $profilePhoto->getClientOriginalExtension();
                    $storagePath = 'profiles';

                    \Log::info('Storing file as: ' . $filename);
                    $path = $profilePhoto->storeAs($storagePath, $filename, 'public');

                    if ($path && Storage::disk('public')->exists($path)) {
                        $staffProfileData['profile_photo'] = 'storage/' . $path;
                        \Log::info('File stored successfully: ' . $staffProfileData['profile_photo']);
                    } else {
                        \Log::error('File storage failed');
                    }
                } else {
                    \Log::error('File is invalid: ' . $profilePhoto->getErrorMessage());
                }
            } else {
                \Log::info('No profile_photo file found in request');
            }

            \Log::info('Final staff profile update data: ', $staffProfileData);

            // Update staff profile with all data
            $targetUser->staffProfile->update($staffProfileData);
            \Log::info('Staff profile updated');

            // Verify the update
            $targetUser->load('staffProfile');
            \Log::info('Updated profile photo in database: ' . $targetUser->staffProfile->profile_photo);
        } else {
            \Log::info('No staff profile found for target user');

            // Optionally create staff profile if it doesn't exist
            if ($request->hasAny(['first_name', 'last_name', 'job_title', 'department_unit'])) {
                $staffProfileData = [
                    'user_id' => $targetUser->id,
                    'first_name' => $request->first_name ?? $targetUser->name,
                    'last_name' => $request->last_name ?? null,
                    'job_title' => $request->job_title ?? null,
                    'department_unit' => $request->department_unit ?? 'General',
                    'start_date' => now()->format('Y-m-d'),
                ];

                StaffProfile::create($staffProfileData);
                \Log::info('Created new staff profile for user');
                $targetUser->load('staffProfile');
            }
        }

        \Log::info('=== END updateStaff ===');

        return response()->json([
            'message' => 'Staff profile updated successfully',
            'user' => $targetUser->load('staffProfile')
        ]);
    }

    /**
     * Deactivate a staff member
     */
    public function deactivateStaff($id)
    {
        $staff = User::where('id', $id)
            ->where('role', '!=', 'patient')
            ->firstOrFail();

        // Prevent deactivating the last admin
        if ($staff->role === 'admin' && User::where('role', 'admin')->where('status', 'active')->count() <= 1) {
            return response()->json([
                'message' => 'Cannot deactivate the last active admin'
            ], 422);
        }

        $staff->update(['status' => 'inactive']);

        return response()->json([
            'message' => 'Staff deactivated successfully',
            'staff' => $staff
        ]);
    }

    /**
     * Activate a staff member
     */
    public function activateStaff($id)
    {
        $staff = User::where('id', $id)
            ->where('role', '!=', 'patient')
            ->firstOrFail();

        $staff->update(['status' => 'active']);

        return response()->json([
            'message' => 'Staff activated successfully',
            'staff' => $staff
        ]);
    }

    /**
     * Delete a staff member
     */
    public function deleteStaff($id)
    {
        $staff = User::where('id', $id)
            ->where('role', '!=', 'patient')
            ->firstOrFail();

        // Prevent deleting the last admin
        if ($staff->role === 'admin' && User::where('role', 'admin')->count() <= 1) {
            return response()->json([
                'message' => 'Cannot delete the last admin'
            ], 422);
        }

        $staff->delete();

        return response()->json([
            'message' => 'Staff deleted successfully'
        ]);
    }


    public function getDashboardStats(Request $request)
    {
        \Log::info('=== START getDashboardStats ===');

        // Check if user is admin
        $user = $request->user();
        if ($user->role !== 'admin') {
            \Log::warning('Unauthorized access attempt by non-admin user: ' . $user->id);
            return response()->json([
                'message' => 'Unauthorized. Only administrators can access dashboard stats.'
            ], 403);
        }

        try {
            // Total Staff (excluding patients)
            $totalStaff = User::where('role', '!=', 'patient')->count();

            // Appointments Today (you'll need to adjust based on your Appointment model)
            $appointmentsToday = 0;
            if (class_exists('App\\Models\\Appointment')) {
                $appointmentsToday = \App\Models\Appointment::where('status', 'pending')->count();
            }

            // Pending Documents (adjust based on your Document model structure)
            $pendingDocuments = 0;
            if (class_exists('App\\Models\\Document')) {
                $pendingDocuments = \App\Models\Document::where('type', 'payslip')->count();
            }

            // New Feedback (adjust based on your Feedback model)
            $newFeedback = 0;
            if (class_exists('App\\Models\\Feedback')) {
                $newFeedback = \App\Models\Feedback::where('status', 'open')->count();
            }

            $stats = [
                'total_staff' => $totalStaff,
                'appointments_today' => $appointmentsToday,
                'pending_documents' => $pendingDocuments,
                'new_feedback' => $newFeedback,
            ];

            \Log::info('Dashboard stats fetched successfully: ', $stats);
            \Log::info('=== END getDashboardStats ===');

            return response()->json([
                'success' => true,
                'stats' => $stats,
                'last_updated' => now()->toISOString()
            ]);

        } catch (\Exception $e) {
            \Log::error('Error fetching dashboard stats: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch dashboard statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}