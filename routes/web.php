<?php

use App\Http\Controllers\AccountPageController;
use App\Http\Controllers\DashboardPageController;
use App\Http\Controllers\PublicChannelPageController;
use App\Http\Middleware\EnsureActiveBeta;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

/*
 * Halaman web MabarQueue. Data diambil/dikirim halaman lewat /api (session + CSRF, lihat public/js/api.js).
 */

Route::get('/', fn () => redirect(Auth::check() ? '/dashboard' : '/login'));

// ── Tamu ────────────────────────────────────────────────────────────────
Route::middleware('guest')->group(function (): void {
    Route::view('/login', 'pages.login')->name('login');
    Route::view('/signup', 'pages.signup')->name('signup');
});
// Bisa dibuka saat sudah login juga: link reset dari email harus selalu berfungsi.
Route::view('/forgot-password', 'pages.forgot-password')->name('password.request');

// ── Sudah login (termasuk beta "pending") ───────────────────────────────
Route::middleware('auth')->group(function (): void {
    Route::get('/account', AccountPageController::class)->name('account');
    Route::view('/profile', 'pages.profile')->name('profile');
});

// ── Dashboard streamer: wajib akses beta aktif ──────────────────────────
Route::middleware(['auth', EnsureActiveBeta::class])->group(function (): void {
    Route::get('/dashboard', [DashboardPageController::class, 'queue'])->name('dashboard');
    Route::view('/live-session', 'pages.live-session')->name('live-session');
    Route::get('/match-count', [DashboardPageController::class, 'matches'])->name('match-count');
    Route::view('/vip-packages', 'pages.vip-packages')->name('vip-packages');
    Route::view('/donations', 'pages.donations')->name('donations');
    Route::view('/overlay-settings', 'pages.overlay-settings')->name('overlay-settings');
});

// ── Halaman antrean publik /{slug} — paling akhir supaya tidak menutupi rute lain ──
Route::get('/{slug}', PublicChannelPageController::class)
    ->where('slug', '[A-Za-z0-9]+(?:-[A-Za-z0-9]+)*')
    ->name('channel.public');
