<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\User;
use App\Models\StaffProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

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


    /**
     * Get payslips for the authenticated staff member
     */
    public function getMyPayslips(Request $request)
    {
        $user = $request->user();

        $payslips = $user->documents()
            ->where('type', 'payslip')
            ->orderBy('period', 'desc')
            ->get();

        return response()->json([
            'payslips' => $payslips
        ]);
    }

    /**
     * Download a document for the authenticated staff member
     */
    public function downloadMyDocument(Request $request, $id)
    {
        $user = $request->user();

        $document = Document::where('id', $id)
            ->where('user_id', $user->id)
            ->first();

        if (!$document) {
            return response()->json(['message' => 'Document not found'], 404);
        }

        if (!Storage::exists($document->file_path)) {
            return response()->json(['message' => 'File not found'], 404);
        }

        return Storage::download($document->file_path, $document->original_name);
    }

}