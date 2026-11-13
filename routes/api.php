<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\RegisteredStreamerController;
use App\Http\Controllers\MatchController;
use App\Http\Controllers\StreamerProfileController;
use App\Http\Controllers\Admin\StreamerAccessController;
use App\Http\Middleware\EnsureActiveBeta;
use App\Http\Middleware\EnsureSuperAdmin;
use Illuminate\Support\Facades\Route;

// ── Super Admin: persetujuan akses beta streamer ────────────────────────
Route::middleware(['auth', EnsureSuperAdmin::class])->prefix('admin')->group(function (): void {
    Route::get('/streamers', [StreamerAccessController::class, 'index']);
    Route::patch('/streamers/{user}/beta-status', [StreamerAccessController::class, 'updateStatus']);
});

// ── Auth (tamu) ─────────────────────────────────────────────────────────
Route::middleware('guest')->group(function (): void {
    Route::post('/auth/register', [RegisteredStreamerController::class, 'store'])->middleware('throttle:6,1');
    Route::post('/auth/login', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:10,1');
    Route::post('/auth/forgot-password', [PasswordResetController::class, 'sendLink'])->middleware('throttle:5,1');
    Route::post('/auth/reset-password', [PasswordResetController::class, 'reset'])->middleware('throttle:6,1');
});

// ── Publik (tanpa akun): webhook provider, overlay OBS, halaman antrean penonton ──
Route::post('/webhooks/donations/{provider}/{platformConnection}', [\App\Http\Controllers\DonationWebhookController::class, 'store'])
    ->whereIn('provider', ['saweria', 'sociabuzz', 'trakteer']);
Route::get('/overlay/{token}', [\App\Http\Controllers\OverlayController::class, 'show']);
Route::get('/channels/{user:slug}', [StreamerProfileController::class, 'publicShow']);
Route::get('/channels/{user:slug}/vip-packages', [\App\Http\Controllers\PublicVipPackageController::class, 'index']);
Route::get('/channels/{user:slug}/queue', [\App\Http\Controllers\PublicQueueController::class, 'show']);
Route::get('/channels/{user:slug}/queue/search', [\App\Http\Controllers\PublicQueueController::class, 'search']);

// ── Sudah login (termasuk akun beta "pending"): cek akun & keluar ───────
Route::middleware('auth')->group(function (): void {
    Route::get('/auth/me', [AuthenticatedSessionController::class, 'show']);
    Route::post('/auth/logout', [AuthenticatedSessionController::class, 'destroy']);
    // Profil boleh diatur sejak akun masih "pending", supaya siap saat akses beta disetujui.
    Route::get('/profile', [StreamerProfileController::class, 'show']);
    Route::put('/profile', [StreamerProfileController::class, 'update']);
});

// ── Dashboard streamer: wajib login + akses beta aktif; data dicek kepemilikannya per resource ──
Route::middleware(['auth', EnsureActiveBeta::class])->group(function (): void {
    Route::get('/platform-connections', [\App\Http\Controllers\PlatformConnectionController::class, 'index']);
    Route::get('/platform-connections/{platformConnection}', [\App\Http\Controllers\PlatformConnectionController::class, 'show']);
    Route::post('/platform-connections', [\App\Http\Controllers\PlatformConnectionController::class, 'store']);
    Route::post('/platform-connections/{platformConnection}/test', [\App\Http\Controllers\PlatformConnectionController::class, 'test']);
    Route::patch('/platform-connections/{platformConnection}/pause', [\App\Http\Controllers\PlatformConnectionController::class, 'pause']);
    Route::get('/donations', [\App\Http\Controllers\DonationDataController::class, 'donations']);
    Route::get('/live-sessions/archives', [\App\Http\Controllers\LiveSessionController::class, 'archives']);
    Route::get('/live-sessions/{liveSession}', [\App\Http\Controllers\LiveSessionController::class, 'show']);
    Route::get('/live-sessions/{liveSession}/donations', [\App\Http\Controllers\DonationDataController::class, 'sessionDonations']);
    Route::get('/live-sessions/{liveSession}/queue', [\App\Http\Controllers\DonationDataController::class, 'queue']);
    Route::get('/live-sessions/{liveSession}/matches/today', [MatchController::class, 'today']);
    Route::post('/queue-entries/{queueEntry}/matches', [MatchController::class, 'store']);
    Route::delete('/matches/{match}', [MatchController::class, 'destroy']);
    Route::get('/overlay-settings', [\App\Http\Controllers\OverlaySettingsController::class, 'show']);
    Route::put('/overlay-settings', [\App\Http\Controllers\OverlaySettingsController::class, 'update']);
    Route::apiResource('vip-packages', \App\Http\Controllers\VipPackageController::class)->only(['index', 'store', 'update']);
    Route::patch('/vip-packages/{vipPackage}/toggle', [\App\Http\Controllers\VipPackageController::class, 'toggle']);
});
