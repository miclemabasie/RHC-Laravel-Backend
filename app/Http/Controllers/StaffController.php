<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\User;
use App\Models\StaffProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class StaffController extends Controller
{
    /**
     * @OA\Get(
     *     path="/staff/me",
     *     summary="Get logged-in staff profile",
     *     tags={"Staff"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Staff profile retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="id", type="integer", example=1),
     *             @OA\Property(property="name", type="string", example="John Doe"),
     *             @OA\Property(property="email", type="string", format="email", example="john@example.com"),
     *             @OA\Property(property="phone", type="string", example="+1234567890"),
     *             @OA\Property(
     *                 property="staff_profile",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="first_name", type="string", example="John"),
     *                 @OA\Property(property="last_name", type="string", example="Doe"),
     *                 @OA\Property(property="job_title", type="string", example="Developer"),
     *                 @OA\Property(property="department_unit", type="string", example="IT Department")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthenticated")
     *         )
     *     )
     * )
     */
    public function getProfile(Request $request)
    {
        Log::info('=== START getProfile ===');

        $user = $request->user()->load('staffProfile');

        return response()->json($user);
    }

    /**
     * @OA\Put(
     *     path="/staff/me",
     *     summary="Update logged-in staff profile",
     *     tags={"Staff"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 @OA\Property(property="name", type="string", example="John Doe"),
     *                 @OA\Property(property="phone", type="string", example="+1234567890"),
     *                 @OA\Property(property="first_name", type="string", example="John"),
     *                 @OA\Property(property="last_name", type="string", example="Doe"),
     *                 @OA\Property(property="job_title", type="string", example="Developer"),
     *                 @OA\Property(property="department_unit", type="string", example="IT Department")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Profile updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Profile updated successfully"),
     *             @OA\Property(
     *                 property="user",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="John Doe"),
     *                 @OA\Property(property="email", type="string", format="email", example="john@example.com"),
     *                 @OA\Property(property="phone", type="string", example="+1234567890"),
     *                 @OA\Property(
     *                     property="staff_profile",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="first_name", type="string", example="John"),
     *                     @OA\Property(property="last_name", type="string", example="Doe"),
     *                     @OA\Property(property="job_title", type="string", example="Developer"),
     *                     @OA\Property(property="department_unit", type="string", example="IT Department")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthenticated")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="errors", type="object", example={"name": {"The name field is required."}})
     *         )
     *     )
     * )
     */
    public function updateProfile(Request $request)
    {
        \Log::info('=== START updateProfile ===');
        \Log::info('Request Method: ' . $request->method());
        \Log::info('Content-Type: ' . $request->header('Content-Type'));
        \Log::info('Request Headers: ', $request->headers->all());

        // Debug: Check if this is an Insomnia request with custom boundary
        $contentType = $request->header('Content-Type');
        $isInsomniaRequest = str_contains($contentType, 'X-INSOMNIA-BOUNDARY');
        \Log::info('Is Insomnia request: ' . ($isInsomniaRequest ? 'YES' : 'NO'));

        // Log all request data
        \Log::info('Request All Data: ', $request->all());
        \Log::info('Request Files count: ' . count($request->allFiles()));
        \Log::info('Request Files keys: ', array_keys($request->allFiles()));

        // MANUAL FILE DETECTION FOR INSOMNIA
        $manualFiles = [];
        foreach ($request->allFiles() as $key => $file) {
            $manualFiles[$key] = [
                'name' => $file->getClientOriginalName(),
                'size' => $file->getSize(),
            ];
        }
        \Log::info('Manual files detection: ', $manualFiles);

        $user = $request->user();
        \Log::info('User ID: ' . $user->id);

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'phone' => 'sometimes|string|max:20',
            'first_name' => 'sometimes|string|max:255',
            'last_name' => 'sometimes|string|max:255',
            'job_title' => 'sometimes|string|max:255',
            'department_unit' => 'sometimes|string|max:255',
            'profile_photo' => 'sometimes|file|mimes:jpeg,png,jpg,gif|max:2048'
        ]);

        if ($validator->fails()) {
            \Log::error('Validation failed: ', $validator->errors()->toArray());
            return response()->json(['errors' => $validator->errors()], 422);
        }

        \Log::info('Validation passed');

        // Update user info
        if ($request->has('name') || $request->has('phone')) {
            $userData = $request->only(['name', 'phone']);
            $user->update($userData);
            \Log::info('User basic info updated');
        }

        // Update staff profile
        if ($user->staffProfile) {
            \Log::info('Staff profile exists for user');
            $updateData = $request->only([
                'first_name',
                'last_name',
                'job_title',
                'department_unit'
            ]);

            \Log::info('Update data (excluding photo): ', $updateData);

            // ENHANCED FILE HANDLING FOR INSOMNIA
            $profilePhoto = $request->file('profile_photo');
            \Log::info('Profile photo file object: ' . ($profilePhoto ? 'EXISTS' : 'NULL'));

            if ($profilePhoto) {
                \Log::info('=== START FILE PROCESSING ===');
                \Log::info('File details:', [
                    'original_name' => $profilePhoto->getClientOriginalName(),
                    'size' => $profilePhoto->getSize(),
                    'mime_type' => $profilePhoto->getMimeType(),
                    'extension' => $profilePhoto->getClientOriginalExtension(),
                    'is_valid' => $profilePhoto->isValid(),
                    'error' => $profilePhoto->getError(),
                ]);

                if (!$profilePhoto->isValid()) {
                    \Log::error('File is invalid: ' . $profilePhoto->getErrorMessage());
                } else {
                    // Delete old profile photo if exists
                    if ($user->staffProfile->profile_photo) {
                        $oldPhotoPath = str_replace('storage/', '', $user->staffProfile->profile_photo);
                        \Log::info('Deleting old photo: ' . $oldPhotoPath);

                        if (Storage::disk('public')->exists($oldPhotoPath)) {
                            Storage::disk('public')->delete($oldPhotoPath);
                            \Log::info('Old photo deleted');
                        }
                    }

                    // Generate filename and store in storage/app/public/profiles
                    $filename = 'profile_photo_' . time() . '_' . uniqid() . '.' . $profilePhoto->getClientOriginalExtension();
                    $storagePath = 'profiles';

                    \Log::info('Storing file as: ' . $filename);
                    \Log::info('Storage path: ' . $storagePath);

                    // Ensure directory exists
                    if (!Storage::disk('public')->exists($storagePath)) {
                        Storage::disk('public')->makeDirectory($storagePath);
                        \Log::info('Created directory: ' . $storagePath);
                    }

                    // Store the file
                    $path = $profilePhoto->storeAs($storagePath, $filename, 'public');
                    \Log::info('Storage returned path: ' . $path);

                    // Verify storage
                    if ($path && Storage::disk('public')->exists($path)) {
                        $fileSize = Storage::disk('public')->size($path);
                        $updateData['profile_photo'] = 'storage/' . $path;
                        \Log::info('File stored successfully! Size: ' . $fileSize . ' bytes');
                        \Log::info('Database path: ' . $updateData['profile_photo']);
                    } else {
                        \Log::error('File storage failed - path does not exist');
                    }
                }
                \Log::info('=== END FILE PROCESSING ===');
            } else {
                \Log::info('No profile_photo file found in request');
                \Log::info('Available files in request: ', array_keys($request->allFiles()));
            }

            \Log::info('Final update data: ', $updateData);
            $user->staffProfile->update($updateData);
            \Log::info('Staff profile updated');

            // Verify the update
            $user->load('staffProfile');
            \Log::info('Updated profile photo in database: ' . $user->staffProfile->profile_photo);
        }

        \Log::info('=== END updateProfile ===');

        return response()->json([
            'message' => 'Profile updated successfully',
            'user' => $user->load('staffProfile')
        ]);
    }
    /**
     * @OA\Get(
     *     path="/staff/me/employment-info",
     *     summary="Get staff employment information",
     *     tags={"Staff"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Employment information retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="documents",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="user_id", type="integer", example=1),
     *                     @OA\Property(property="type", type="string", example="contract"),
     *                     @OA\Property(property="file_path", type="string", example="documents/contract_123.pdf"),
     *                     @OA\Property(property="original_name", type="string", example="employment_contract.pdf"),
     *                     @OA\Property(property="created_at", type="string", format="date-time"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthenticated")
     *         )
     *     )
     * )
     */
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
     * @OA\Get(
     *     path="/staff/me/payslips",
     *     summary="Get payslips for the authenticated staff member",
     *     tags={"Staff"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Payslips retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="payslips",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="user_id", type="integer", example=1),
     *                     @OA\Property(property="type", type="string", example="payslip"),
     *                     @OA\Property(property="period", type="string", example="2023-11"),
     *                     @OA\Property(property="file_path", type="string", example="documents/payslip_2023_11.pdf"),
     *                     @OA\Property(property="original_name", type="string", example="November_2023_Payslip.pdf"),
     *                     @OA\Property(property="created_at", type="string", format="date-time"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthenticated")
     *         )
     *     )
     * )
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
     * @OA\Get(
     *     path="/staff/me/documents/{id}/download",
     *     summary="Download a document for the authenticated staff member",
     *     tags={"Staff"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Document ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Document downloaded successfully",
     *         @OA\MediaType(
     *             mediaType="application/octet-stream"
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthenticated")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Document not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Document not found")
     *         )
     *     )
     * )
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