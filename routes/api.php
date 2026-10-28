<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ActivityLogController;
use App\Http\Controllers\Api\ContactController;
use App\Http\Controllers\Api\UniversityController;
use App\Http\Controllers\Api\StudentController;
use App\Http\Controllers\Api\VerificationController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\DegreeController;
use App\Http\Controllers\Api\RegistrationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Public endpoints are rate limited — they are reachable by anyone and are
| the obvious targets for credential stuffing and scraping.
|
| NOTE: there is deliberately no public "register" route. Admin accounts are
| created by a Super Admin via /users; verifier accounts via /registrations.
| A public register endpoint that accepted a `role` would let anyone mint a
| super admin.
|
*/

// ── Public ──
// Named limiters live in RouteServiceProvider: they key on the account or the
// signed-in user rather than the raw IP, so shared office connections are not
// collectively locked out.
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');

// Password reset — public by necessity (the user cannot sign in). Throttled
// per account and per IP so the endpoint cannot be used to spam an inbox.
Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:password');
Route::post('/reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:password');

Route::get('/universities/search', [UniversityController::class, 'search'])->middleware('throttle:search');
Route::get('/universities/{university}/degrees', [DegreeController::class, 'byUniversity']);
Route::post('/registrations', [RegistrationController::class, 'store'])->middleware('throttle:registrations');

// Public contact form — delivered to the support mailbox. Throttled hard: it
// is an open relay into someone's inbox if it is not.
Route::post('/contact', [ContactController::class, 'store'])->middleware('throttle:contact');

// ── Authenticated ──
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/change-password', [AuthController::class, 'changePassword']);

    // Verification requires an account so every enquiry is attributable to a
    // named organisation — anonymous checks produced "Anonymous / Unknown"
    // rows that could not be audited.
    Route::post('/verify', [VerificationController::class, 'verify'])->middleware('throttle:verify');

    // Older Myanmar-language degrees that cannot be keyed into the search form
    // are raised by uploading the certificate scan instead. Always pending.
    Route::post('/verify/upload', [VerificationController::class, 'submitWithDocument'])->middleware('throttle:verify');

    // Own verification history — scoped per role inside the controller
    Route::get('/verification-logs', [VerificationController::class, 'logs']);
    Route::get('/verification-logs/recent', [VerificationController::class, 'recentActivity']);
    Route::post('/verification-logs/{verificationLog}/recheck', [VerificationController::class, 'recheck']);

    Route::get('/universities', [UniversityController::class, 'index']);

    // ── Registrar + Super Admin ──
    Route::middleware('role:super_admin,university_admin')->group(function () {
        Route::post('/verification-logs/{verificationLog}/resolve', [VerificationController::class, 'resolve']);

        Route::post('/students/upload-photo', [StudentController::class, 'uploadPhoto']);
        Route::post('/students/bulk-upload', [StudentController::class, 'bulkUpload']);
        Route::apiResource('students', StudentController::class);

        Route::apiResource('degrees', DegreeController::class);

        Route::get('/universities/stats', [UniversityController::class, 'stats']);
        Route::post('/universities/upload-logo', [UniversityController::class, 'uploadLogo']);
        Route::put('/universities/{university}', [UniversityController::class, 'update']);
        Route::patch('/universities/{university}', [UniversityController::class, 'update']);
    });

    // ── Super Admin only ──
    Route::middleware('role:super_admin')->group(function () {
        Route::apiResource('users', UserController::class);

        Route::post('/universities', [UniversityController::class, 'store']);
        Route::delete('/universities/{university}', [UniversityController::class, 'destroy']);

        Route::get('/registrations', [RegistrationController::class, 'index']);
        Route::patch('/registrations/{registrationRequest}', [RegistrationController::class, 'update']);

        // Account activity trail — read-only, platform-wide.
        Route::get('/activity-logs', [ActivityLogController::class, 'index']);
        Route::get('/activity-logs/summary', [ActivityLogController::class, 'summary']);
    });
});

// Wildcard last so it cannot shadow /universities/search or /universities/stats
Route::get('/universities/{university}', [UniversityController::class, 'show']);
