<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

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
}
