<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\VerificationController;
use App\Http\Controllers\UserController;

Route::group([
    'middleware' => 'api',
    'prefix' => 'auth'
], function ($router) {
    // User Management API
    Route::get('users', [UserController::class, 'index']);
    Route::put('users/{id}/role', [UserController::class, 'updateRole']);
    // Auth
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login'])->name('login');
    Route::post('logout', [AuthController::class, 'logout'])->middleware('auth:api');
    Route::post('refresh', [AuthController::class, 'refresh'])->middleware('auth:api');
    Route::get('me', [AuthController::class, 'me'])->middleware('auth:api');
    
    // Endpoint for Project Service to fetch multiple users by ID
    Route::post('users/batch', [AuthController::class, 'getUsersBatch']); // Keep public or use specific inter-service auth if needed
    
    // Endpoint for Project Service to manage manual participants
    Route::post('users/find-or-create', [AuthController::class, 'findOrCreate']);
    Route::post('users/find-by-email', [AuthController::class, 'findByEmail']);
    Route::post('users/search', [AuthController::class, 'search']);

    // Profile Management (Requires Auth)
    Route::middleware('auth:api')->group(function () {
        Route::post('profile', [ProfileController::class, 'updateProfile']); // Use POST for multipart/form-data
        Route::put('profile/password', [ProfileController::class, 'updatePassword']);
        
        // Email Verification (Resend)
        Route::post('email/resend', [VerificationController::class, 'resend']);
    });

    // Password Reset (Public)
    Route::post('password/email', [PasswordResetController::class, 'sendResetLinkEmail']);
    Route::post('password/reset', [PasswordResetController::class, 'reset']);

    // Email Verification (Public, Link clicked from email)
    Route::get('email/verify', [VerificationController::class, 'verify']);
});
