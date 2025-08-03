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
     * Upload a document for a staff member
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
     * Get all documents for a staff member
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
     * Get a specific document
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
     * Download a document
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
     * Delete a document
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
     * Get all payslips for a staff member
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
     * Update a document
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