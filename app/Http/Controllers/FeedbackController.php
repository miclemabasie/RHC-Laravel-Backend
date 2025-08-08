<?php

namespace App\Http\Controllers;

use App\Models\Feedback;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class FeedbackController extends Controller
{
    /**
     * Get all feedback (with filters)
     */
    public function index(Request $request)
    {
        $user = $request->user();
        // For staff, show only their feedback unless they're admin
        if ($user->role !== 'admin') {
            $query = Feedback::where('user_id', $user->id);
        } else {
            $query = Feedback::with(['user', 'assignedTo']);
        }

        // Apply filters
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('priority')) {
            $query->where('priority', $request->priority);
        }

        if ($request->has('assigned_to')) {
            $query->where('assigned_to', $request->assigned_to);
        }

        $feedback = $query->orderBy('created_at', 'desc')->paginate(20);

        return response()->json($feedback);
    }

    /**
     * Submit new feedback
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'type' => 'required|in:suggestion,complaint,compliment,question,other',
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'priority' => 'sometimes|integer|between:1,5',
            'anonymous' => 'sometimes|boolean'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $feedback = Feedback::create([
            'user_id' => $request->user_id,
            'type' => $request->type,
            'title' => $request->title,
            'content' => $request->content,
            'priority' => $request->priority ?? 1,
            'anonymous' => $request->anonymous ?? false
        ]);

        return response()->json([
            'message' => 'Feedback submitted successfully',
            'feedback' => $feedback
        ], 201);
    }

    /**
     * Get specific feedback
     */
    public function show(Request $request, $id)
    {
        $feedback = Feedback::with(['user', 'assignedTo'])->find($id);

        if (!$feedback) {
            return response()->json(['message' => 'Feedback not found'], 404);
        }

        $user = $request->user();

        // Check if user can view this feedback
        if ($user->role !== 'admin' && $feedback->user_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json([
            'feedback' => $feedback
        ]);
    }

    /**
     * Update feedback (admin only)
     */
    public function update(Request $request, $id)
    {
        $feedback = Feedback::find($id);

        if (!$feedback) {
            return response()->json(['message' => 'Feedback not found'], 404);
        }

        // Only admin can update feedback
        if ($request->user()->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'status' => 'sometimes|in:open,in_progress,resolved,closed',
            'admin_notes' => 'sometimes|string',
            'assigned_to' => 'sometimes|uuid|exists:users,id',
            'priority' => 'sometimes|integer|between:1,5'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $feedback->update($request->only(['status', 'admin_notes', 'assigned_to', 'priority']));

        return response()->json([
            'message' => 'Feedback updated successfully',
            'feedback' => $feedback->load(['user', 'assignedTo'])
        ]);
    }

    /**
     * Get feedback statistics (admin only)
     */
    public function stats(Request $request)
    {
        if ($request->user()->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $total = Feedback::count();
        $byType = Feedback::selectRaw('type, count(*) as count')
            ->groupBy('type')
            ->get()
            ->pluck('count', 'type');

        $byStatus = Feedback::selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->get()
            ->pluck('count', 'status');

        $recent = Feedback::with(['user', 'assignedTo'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        return response()->json([
            'total' => $total,
            'by_type' => $byType,
            'by_status' => $byStatus,
            'recent' => $recent
        ]);
    }

    /**
     * Get my feedback (for staff)
     */
    public function myFeedback(Request $request)
    {
        $user = $request->user();

        $query = Feedback::where('user_id', $user->id);

        // Apply filters
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $feedback = $query->orderBy('created_at', 'desc')->paginate(20);

        return response()->json($feedback);
    }
}