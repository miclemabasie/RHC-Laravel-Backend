<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
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
        $staff = User::where('role', '!=', 'patient')->get();

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
    public function updateStaff(Request $request, $id)
    {
        $staff = User::where('id', $id)
            ->where('role', '!=', 'patient')
            ->firstOrFail();

        $validated = $request->validate([
            'name' => 'sometimes|string|max:100',
            'email' => ['sometimes', 'email', Rule::unique('users')->ignore($staff->id)],
            'phone' => 'nullable|string|max:20',
            'role' => ['sometimes', Rule::in(['staff', 'doctor', 'nurse', 'admin'])],
            'department' => 'nullable|string|max:100',
            'status' => ['sometimes', Rule::in(['active', 'inactive', 'pending'])],
        ]);

        $staff->update($validated);

        return response()->json([
            'message' => 'Staff updated successfully',
            'staff' => $staff
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
}