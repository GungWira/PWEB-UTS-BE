<?php

use App\Http\Controllers\Admin\CandidateController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\VoterController;
use App\Http\Controllers\Admin\VoterImportController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\LiveCountController;
use App\Http\Controllers\VoteController;
use Illuminate\Support\Facades\Route;

// --- Publik ---
Route::post('/auth/voter/login', [AuthController::class, 'loginVoter']);
Route::post('/auth/admin/login', [AuthController::class, 'loginAdmin']);
Route::get('/live-count', [LiveCountController::class, 'index']);

// --- Perlu token (peran apa pun) ---
Route::middleware('jwt')->group(function () {
    Route::get('/auth/me', [AuthController::class, 'me']);
});

// --- Pemilih ---
Route::middleware('jwt:voter')->group(function () {
    Route::get('/vote/candidates', [VoteController::class, 'candidates']);
    Route::post('/vote', [VoteController::class, 'store']);
});

// --- Admin ---
Route::middleware('jwt:admin')->prefix('admin')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index']);
    Route::post('/reset-election', [DashboardController::class, 'resetElection']);

    Route::get('/voters/template', [VoterImportController::class, 'template']);
    Route::post('/voters/import/preview', [VoterImportController::class, 'preview']);
    Route::post('/voters/import/confirm', [VoterImportController::class, 'confirm']);

    Route::post('/voters', [VoterController::class, 'store']);
    Route::post('/voters/{id}/reset', [VoterController::class, 'resetStatus']);
    Route::delete('/voters/{id}', [VoterController::class, 'destroy']);

    Route::post('/candidates', [CandidateController::class, 'store']);
    Route::post('/candidates/{id}', [CandidateController::class, 'update']);
    Route::delete('/candidates/{id}', [CandidateController::class, 'destroy']);
});
