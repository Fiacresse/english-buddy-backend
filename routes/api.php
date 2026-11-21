<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\PlacementController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\StatsController;
use App\Http\Controllers\Api\VoiceController;
use Illuminate\Support\Facades\Route;

Route::middleware('throttle:10,1')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::patch('/profile', [ProfileController::class, 'update']);
    Route::put('/profile/password', [ProfileController::class, 'password']);
    Route::delete('/profile', [ProfileController::class, 'destroy']);

    Route::get('/placement', [PlacementController::class, 'questions']);
    Route::post('/placement', [PlacementController::class, 'submit'])->middleware('throttle:5,10');

    Route::get('/dashboard', [StatsController::class, 'dashboard']);
    Route::post('/lessons/{slug}/complete', [StatsController::class, 'completeLesson']);

    Route::get('/conversations', [ChatController::class, 'index']);
    Route::post('/conversations', [ChatController::class, 'store']);
    Route::get('/conversations/{conversation}', [ChatController::class, 'show']);
    Route::delete('/conversations/{conversation}', [ChatController::class, 'destroy']);
    Route::post('/conversations/{conversation}/messages', [ChatController::class, 'send'])->middleware('throttle:20,1');

    Route::post('/voice/session', [VoiceController::class, 'session'])->middleware('throttle:6,1');
    Route::post('/voice/log', [VoiceController::class, 'log']);
});
