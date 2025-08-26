<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class DocumentController extends Controller
{
    /**
     * @OA\Post(
     *     path="/documents/{userId}/upload",
     *     summary="Upload a document for a staff member",
     *     tags={"Documents"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="userId",
     *         in="path",
     *         required=true,
     *         description="ID of the staff member",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"file", "type"},
     *                 @OA\Property(
     *                     property="file",
     *                     type="string",
     *                     format="binary",
     *                     description="Document file to upload"
     *                 ),
     *                 @OA\Property(
     *                     property="type",
     *                     type="string",
     *                     enum={"contract", "payslip", "other"},
     *                     description="Type of document"
     *                 ),
     *                 @OA\Property(
     *                     property="description",
     *                     type="string",
     *                     maxLength=255,
     *                     description="Optional description of the document"
     *                 ),
     *                 @OA\Property(
     *                     property="period",
     *                     type="string",
     *                     maxLength=7,
     *                     description="Period for payslips (e.g., 2023-01)"
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Document uploaded successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Document uploaded successfully"),
     *             @OA\Property(
     *                 property="document",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="user_id", type="integer", example=1),
     *                 @OA\Property(property="type", type="string", example="payslip"),
     *                 @OA\Property(property="name", type="string", example="document"),
     *                 @OA\Property(property="file_path", type="string", example="documents/payslip/uuid.pdf"),
     *                 @OA\Property(property="original_name", type="string", example="payslip_november.pdf"),
     *                 @OA\Property(property="mime_type", type="string", example="application/pdf"),
     *                 @OA\Property(property="size", type="integer", example=10240),
     *                 @OA\Property(property="description", type="string", example="November 2023 payslip"),
     *                 @OA\Property(property="period", type="string", example="2023-11"),
     *                 @OA\Property(property="created_at", type="string", format="date-time"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time")
     *             )
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
     *             @OA\Property(property="errors", type="object", example={"file": {"The file field is required."}})
     *         )
     *     )
     * )
     */
    public function uploadDocument(Request $request, $userId)
    {
        // Validate request
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|max:10240', // 10MB max
            'type' => 'required|in:contract,payslip,other',
            'description' => 'nullable|string|max:255',
            'period' => 'nullable|string|max:7' // For payslips (e.g., 2023-01)
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Check if user exists
        $user = User::find($userId);
        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        // Handle file upload
        $file = $request->file('file');
        $originalName = $file->getClientOriginalName();
        $extension = $file->getClientOriginalExtension();
        $mimeType = $file->getMimeType();
        $size = $file->getSize();

        // Generate unique filename
        $filename = Str::uuid() . '.' . $extension;
        $path = 'documents/' . $request->type . '/' . $filename;

        // Store file
        Storage::put($path, file_get_contents($file));

        // Create document record
        $document = Document::create([
            'user_id' => $userId,
            'type' => $request->type,
            'name' => $request->name ?? pathinfo($originalName, PATHINFO_FILENAME),
            'file_path' => $path,
            'original_name' => $originalName,
            'mime_type' => $mimeType,
            'size' => $size,
            'description' => $request->description,
            'period' => $request->period
        ]);

        return response()->json([
            'message' => 'Document uploaded successfully',
            'document' => $document
        ], 201);
    }

    /**
     * @OA\Get(
     *     path="/documents/{userId}",
     *     summary="Get all documents for a staff member",
     *     tags={"Documents"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="userId",
     *         in="path",
     *         required=true,
     *         description="ID of the staff member",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="type",
     *         in="query",
     *         required=false,
     *         description="Filter by document type",
     *         @OA\Schema(type="string", enum={"contract", "payslip", "other"})
     *     ),
     *     @OA\Parameter(
     *         name="period",
     *         in="query",
     *         required=false,
     *         description="Filter by period (for payslips)",
     *         @OA\Schema(type="string", example="2023-11")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Documents retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="documents",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="user_id", type="integer", example=1),
     *                     @OA\Property(property="type", type="string", example="payslip"),
     *                     @OA\Property(property="name", type="string", example="document"),
     *                     @OA\Property(property="file_path", type="string", example="documents/payslip/uuid.pdf"),
     *                     @OA\Property(property="original_name", type="string", example="payslip_november.pdf"),
     *                     @OA\Property(property="mime_type", type="string", example="application/pdf"),
     *                     @OA\Property(property="size", type="integer", example=10240),
     *                     @OA\Property(property="description", type="string", example="November 2023 payslip"),
     *                     @OA\Property(property="period", type="string", example="2023-11"),
     *                     @OA\Property(property="created_at", type="string", format="date-time"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="User not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="User not found")
     *         )
     *     )
     * )
     */
    public function getDocuments($userId, Request $request)
    {
        // Check if user exists
        $user = User::find($userId);
        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        $query = Document::where('user_id', $userId);

        // Filter by type if provided
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        // Filter by period if provided (for payslips)
        if ($request->has('period')) {
            $query->where('period', $request->period);
        }

        $documents = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'documents' => $documents
        ]);
    }

    /**
     * @OA\Get(
     *     path="/documents/single/{id}",
     *     summary="Get a specific document",
     *     tags={"Documents"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the document",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Document retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="document",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="user_id", type="integer", example=1),
     *                 @OA\Property(property="type", type="string", example="payslip"),
     *                 @OA\Property(property="name", type="string", example="document"),
     *                 @OA\Property(property="file_path", type="string", example="documents/payslip/uuid.pdf"),
     *                 @OA\Property(property="original_name", type="string", example="payslip_november.pdf"),
     *                 @OA\Property(property="mime_type", type="string", example="application/pdf"),
     *                 @OA\Property(property="size", type="integer", example=10240),
     *                 @OA\Property(property="description", type="string", example="November 2023 payslip"),
     *                 @OA\Property(property="period", type="string", example="2023-11"),
     *                 @OA\Property(property="created_at", type="string", format="date-time"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time")
     *             )
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
    public function getDocument($id)
    {
        $document = Document::find($id);

        if (!$document) {
            return response()->json(['message' => 'Document not found'], 404);
        }

        return response()->json([
            'document' => $document
        ]);
    }

    /**
     * @OA\Get(
     *     path="/documents/{id}/download",
     *     summary="Download a document",
     *     tags={"Documents"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the document",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Document downloaded successfully",
     *         @OA\MediaType(mediaType="application/octet-stream")
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
    public function downloadDocument($id)
    {
        $document = Document::find($id);

        if (!$document) {
            return response()->json(['message' => 'Document not found'], 404);
        }

        if (!Storage::exists($document->file_path)) {
            return response()->json(['message' => 'File not found'], 404);
        }

        return Storage::download($document->file_path, $document->original_name);
    }

    /**
     * @OA\Delete(
     *     path="/documents/{id}",
     *     summary="Delete a document",
     *     tags={"Documents"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the document",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Document deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Document deleted successfully")
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
    public function deleteDocument($id)
    {
        $document = Document::find($id);

        if (!$document) {
            return response()->json(['message' => 'Document not found'], 404);
        }

        // Delete file from storage
        if (Storage::exists($document->file_path)) {
            Storage::delete($document->file_path);
        }

        // Delete record
        $document->delete();

        return response()->json([
            'message' => 'Document deleted successfully'
        ]);
    }

    /**
     * @OA\Get(
     *     path="/documents/{userId}/payslips",
     *     summary="Get all payslips for a staff member",
     *     tags={"Documents"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="userId",
     *         in="path",
     *         required=true,
     *         description="ID of the staff member",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="period",
     *         in="query",
     *         required=false,
     *         description="Filter by period",
     *         @OA\Schema(type="string", example="2023-11")
     *     ),
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
     *                     @OA\Property(property="name", type="string", example="document"),
     *                     @OA\Property(property="file_path", type="string", example="documents/payslip/uuid.pdf"),
     *                     @OA\Property(property="original_name", type="string", example="payslip_november.pdf"),
     *                     @OA\Property(property="mime_type", type="string", example="application/pdf"),
     *                     @OA\Property(property="size", type="integer", example=10240),
     *                     @OA\Property(property="description", type="string", example="November 2023 payslip"),
     *                     @OA\Property(property="period", type="string", example="2023-11"),
     *                     @OA\Property(property="created_at", type="string", format="date-time"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="User not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="User not found")
     *         )
     *     )
     * )
     */
    public function getPayslips($userId, Request $request)
    {
        // Check if user exists
        $user = User::find($userId);
        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        $query = Document::where('user_id', $userId)->where('type', 'payslip');

        // Filter by period if provided
        if ($request->has('period')) {
            $query->where('period', $request->period);
        }

        $payslips = $query->orderBy('period', 'desc')->get();

        return response()->json([
            'payslips' => $payslips
        ]);
    }

    /**
     * @OA\Put(
     *     path="/documents/{id}",
     *     summary="Update a document",
     *     tags={"Documents"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the document",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 @OA\Property(
     *                     property="name",
     *                     type="string",
     *                     maxLength=255,
     *                     description="Name of the document"
     *                 ),
     *                 @OA\Property(
     *                     property="description",
     *                     type="string",
     *                     maxLength=255,
     *                     description="Description of the document"
     *                 ),
     *                 @OA\Property(
     *                     property="period",
     *                     type="string",
     *                     maxLength=7,
     *                     description="Period for payslips (e.g., 2023-01)"
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Document updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Document updated successfully"),
     *             @OA\Property(
     *                 property="document",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="user_id", type="integer", example=1),
     *                 @OA\Property(property="type", type="string", example="payslip"),
     *                 @OA\Property(property="name", type="string", example="updated_document_name"),
     *                 @OA\Property(property="file_path", type="string", example="documents/payslip/uuid.pdf"),
     *                 @OA\Property(property="original_name", type="string", example="payslip_november.pdf"),
     *                 @OA\Property(property="mime_type", type="string", example="application/pdf"),
     *                 @OA\Property(property="size", type="integer", example=10240),
     *                 @OA\Property(property="description", type="string", example="Updated description"),
     *                 @OA\Property(property="period", type="string", example="2023-11"),
     *                 @OA\Property(property="created_at", type="string", format="date-time"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Document not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Document not found")
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
    public function updateDocument(Request $request, $id)
    {
        $document = Document::find($id);

        if (!$document) {
            return response()->json(['message' => 'Document not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string|max:255',
            'period' => 'nullable|string|max:7'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $document->update($request->only(['name', 'description', 'period']));

        return response()->json([
            'message' => 'Document updated successfully',
            'document' => $document
        ]);
    }
}