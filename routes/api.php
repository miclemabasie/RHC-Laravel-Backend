<?php

use App\Http\Controllers\FeedbackController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\StaffController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\DocumentController;


/**
 * @OA\Info(
 *     version="1.0.0",
 *     title="RHC Backend API",
 *     description="API documentation for RHC backend"
 * )
 *
 * @OA\Server(
 *     url="http://localhost:8000/api",
 *     description="Local Development Server"
 * )
 *
 * @OA\SecurityScheme(
 *     securityScheme="bearerAuth",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="JWT"
 * )
 */


// Public routes
Route::post('/book-appointment', [AppointmentController::class, 'bookAppointment']);
Route::post('/invitation/accept/{token}', [InvitationController::class, 'acceptInvitation']);

Route::post('/bootstrap/admin', [AdminController::class, 'bootstrap']);

// Staff authentication
Route::post('/staff/login', [AuthController::class, 'login']);
Route::post('/staff/verify-mfa', [AuthController::class, 'verifyMfa']);




// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    // Authentication
    Route::post('/logout', [AuthController::class, 'logout']);

    // Staff routes
    Route::get('/staff/me', [StaffController::class, 'getProfile']);
    Route::put('/staff/me', [StaffController::class, 'updateProfile']);
    Route::get('/staff/me/employment-info', [StaffController::class, 'getEmploymentInfo']);
    Route::get('/staff/me/payslips', [StaffController::class, 'getMyPayslips']);
    Route::get('/staff/me/documents/{id}/download', [StaffController::class, 'downloadMyDocument']);

    // Appointment management
    Route::get('/appointments', [AppointmentController::class, 'getAppointments']);
    Route::put('/appointments/{id}', [AppointmentController::class, 'updateAppointment']);



    // Admin only routes
    Route::middleware('admin')->group(function () {
        // Staff management
        Route::get('/admin/staff', [AdminController::class, 'getAllStaff']);
        Route::get('/admin/staff/{id}', [AdminController::class, 'getStaff']);
        Route::post('/admin/staff/invite', [AdminController::class, 'inviteStaff']);
        Route::put('/admin/staff/{id}', [AdminController::class, 'updateStaff']);
        Route::post('/admin/staff/{id}/deactivate', [AdminController::class, 'deactivateStaff']);
        Route::post('/admin/staff/{id}/activate', [AdminController::class, 'activateStaff']);
        Route::delete('/admin/staff/{id}', [AdminController::class, 'deleteStaff']);
        Route::get('/feedback', [FeedbackController::class, 'index']);
        Route::get('/feedback/stats', [FeedbackController::class, 'stats']);

        // Document management
        Route::post('/admin/staff/{id}/documents', [DocumentController::class, 'uploadDocument']);
        Route::get('/admin/staff/{id}/documents', [DocumentController::class, 'getDocuments']);
        Route::get('/admin/staff/{id}/payslips', [DocumentController::class, 'getPayslips']);
        Route::get('/admin/documents/{id}', [DocumentController::class, 'getDocument']);
        Route::put('/admin/documents/{id}', [DocumentController::class, 'updateDocument']);
        Route::delete('/admin/documents/{id}', [DocumentController::class, 'deleteDocument']);
        Route::get('/admin/documents/{id}/download', [DocumentController::class, 'downloadDocument']);
        // Admin only routes
        Route::middleware('admin')->group(function () {
            Route::post('/invitations/send', [InvitationController::class, 'sendInvitation']);
            // Add more admin routes here
        });


    });

    // Feedback routes
    Route::get('/feedback', [FeedbackController::class, 'index']);
    Route::post('/feedback', [FeedbackController::class, 'store']);
    Route::get('/feedback/{id}', [FeedbackController::class, 'show']);
    Route::put('/feedback/{id}', [FeedbackController::class, 'update']);

    Route::get('/my-feedback', [FeedbackController::class, 'myFeedback']);
});

