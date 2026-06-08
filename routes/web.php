<?php


use App\Http\Controllers\Api\V1\GoogleAuthController;
use Illuminate\Support\Facades\Route;

// Route::get('/index',[GoogleAuthController::class, 'index']);
// Route::get('/auth/google', [GoogleAuthController::class, 'redirectGoogle']);
// Route::get('/auth/google/callback', [GoogleAuthController::class, 'handleGoogleCallback']);// routes/web.php
// routes/web.php
Route::get('/run-scheduler', function (Request $request) {
    if ($request->header('X-Scheduler-Token') !== env('SCHEDULER_TOKEN')) {
        return response()->json(['message' => 'Unauthorized'], 401);
    }

    Artisan::call('discounts:check-expired');
    return response()->json(['message' => 'Scheduler ran successfully']);
});