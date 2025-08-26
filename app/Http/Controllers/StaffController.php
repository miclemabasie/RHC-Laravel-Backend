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