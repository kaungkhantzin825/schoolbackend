<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\UniversityController;
use App\Http\Controllers\Api\StudentController;
use App\Http\Controllers\Api\VerificationController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\DegreeController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Public routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Protect routes that clash with {university}
Route::get('/universities/search', [UniversityController::class, 'search']);
Route::post('/verify', [VerificationController::class, 'verify']);


// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    // Auth routes
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    // University routes
    Route::apiResource('universities', UniversityController::class)->except(['show']);
    Route::get('/universities/stats', [UniversityController::class, 'stats']);

    // Student routes
    Route::apiResource('students', StudentController::class);
    Route::post('/students/bulk-upload', [StudentController::class, 'bulkUpload']);

    // Verification routes
    Route::get('/verification-logs', [VerificationController::class, 'logs']);
    Route::get('/verification-logs/recent', [VerificationController::class, 'recentActivity']);

    // User management routes (Super Admin only)
    Route::apiResource('users', UserController::class);

    // Degree management routes
    Route::apiResource('degrees', DegreeController::class);
});

// Put wildcard route at the very bottom
Route::get('/universities/{university}', [UniversityController::class, 'show']);
