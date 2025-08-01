<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\StaffProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class StaffController extends Controller
{
    public function getProfile(Request $request)
    {
        $user = $request->user()->load('staffProfile');
        return response()->json($user);
    }

    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'phone' => 'sometimes|string|max:20',
            'first_name' => 'sometimes|string|max:255',
            'last_name' => 'sometimes|string|max:255',
            'job_title' => 'sometimes|string|max:255',
            'department_unit' => 'sometimes|string|max:255'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Update user info
        if ($request->has('name') || $request->has('phone')) {
            $user->update($request->only(['name', 'phone']));
        }

        // Update staff profile
        if ($user->staffProfile) {
            $user->staffProfile->update($request->only([
                'first_name',
                'last_name',
                'job_title',
                'department_unit'
            ]));
        }

        return response()->json([
            'message' => 'Profile updated successfully',
            'user' => $user->load('staffProfile')
        ]);
    }

    public function getEmploymentInfo(Request $request)
    {
        $user = $request->user();
        // Get documents available to the staff
        $documents = $user->documents()
            ->whereIn('type', ['contract', 'payslip'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'documents' => $documents
        ]);
    }
}