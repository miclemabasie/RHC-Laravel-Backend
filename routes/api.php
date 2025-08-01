<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\StaffController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\AdminController;

// Public routes
Route::post('/book-appointment', [AppointmentController::class, 'bookAppointment']);

Route::post('/bootstrap/admin', [AdminController::class, 'bootstrap']);

// Staff authentication
Route::post('/staff/login', [AuthController::class, 'login']);
Route::post('/staff/verify-mfa', [AuthController::class, 'verifyMfa']);

// Invitation acceptance (public)
Route::post('/invitation/accept/{token}', [InvitationController::class, 'acceptInvitation']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    // Authentication
    Route::post('/logout', [AuthController::class, 'logout']);

    // Staff routes
    Route::get('/staff/me', [StaffController::class, 'getProfile']);
    Route::put('/staff/me', [StaffController::class, 'updateProfile']);
    Route::get('/staff/me/employment-info', [StaffController::class, 'getEmploymentInfo']);

    // Appointment management (staff/admin)
    Route::get('/appointments', [AppointmentController::class, 'getAppointments']);
    Route::put('/appointments/{id}', [AppointmentController::class, 'updateAppointment']);

    // Admin only routes
    Route::middleware('admin')->group(function () {
        Route::post('/invitations/send', [InvitationController::class, 'sendInvitation']);
        // Add more admin routes here
    });


});