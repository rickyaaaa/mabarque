<?php

use App\Http\Controllers\AccountPageController;
use App\Http\Controllers\DashboardPageController;
use App\Http\Controllers\OverlayController;
use App\Http\Controllers\PublicChannelPageController;
use App\Http\Middleware\EnsureActiveBeta;
use App\Http\Middleware\EnsureSuperAdmin;
use App\Http\Middleware\RedirectSuperAdminToPanel;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

/*
 * Halaman web Mabarku. Data diambil/dikirim halaman lewat /api (session + CSRF, lihat public/js/api.js).
 * Satu login (/login) untuk semua; setelah masuk, tiap peran diarahkan ke areanya (User::homePath()).
 */

Route::get('/', fn () => Auth::check() ? redirect(Auth::user()->homePath()) : view('pages.landing'));

// ── Tamu ────────────────────────────────────────────────────────────────
Route::middleware('guest')->group(function (): void {
    Route::view('/login', 'pages.login')->name('login');
    Route::view('/signup', 'pages.signup')->name('signup');
});
// Bisa dibuka saat sudah login juga: link reset dari email harus selalu berfungsi.
Route::view('/forgot-password', 'pages.forgot-password')->name('password.request');

// ── Panel Super Admin (streamer biasa mendapat 404) ─────────────────────
Route::middleware(['auth', EnsureSuperAdmin::class])->group(function (): void {
    Route::view('/admin', 'pages.admin')->name('admin');
});

// ── Workspace streamer: sudah login (termasuk beta "pending") ───────────
Route::middleware(['auth', RedirectSuperAdminToPanel::class])->group(function (): void {
    Route::get('/account', AccountPageController::class)->name('account');
    Route::view('/profile', 'pages.profile')->name('profile');
});

// ── Workspace streamer: wajib akses beta aktif ──────────────────────────
Route::middleware(['auth', RedirectSuperAdminToPanel::class, EnsureActiveBeta::class])->group(function (): void {
    Route::get('/dashboard', [DashboardPageController::class, 'queue'])->name('dashboard');
    Route::view('/live-session', 'pages.live-session')->name('live-session');
    Route::get('/live-session/{liveSession}', \App\Http\Controllers\SessionArchiveController::class)->whereNumber('liveSession')->name('live-session.show');
    Route::get('/match-count', [DashboardPageController::class, 'matches'])->name('match-count');
    Route::view('/vip-packages', 'pages.vip-packages')->name('vip-packages');
    Route::view('/donations', 'pages.donations')->name('donations');
    Route::view('/overlay-settings', 'pages.overlay-settings')->name('overlay-settings');
});

// ── Overlay OBS (browser source, akses lewat token) ─────────────────────
Route::get('/o/{token}', [OverlayController::class, 'page'])->where('token', '[A-Za-z0-9]{20,100}')->name('overlay');
Route::get('/o/{token}/w/{widget}', [OverlayController::class, 'widget'])
    ->where(['token' => '[A-Za-z0-9]{20,100}', 'widget' => 'score|party|queue|packages|service-packages'])->name('overlay.widget');
Route::get('/o/{token}/bg/{file}', [OverlayController::class, 'background'])
    ->where(['token' => '[A-Za-z0-9]{20,100}', 'file' => '[A-Za-z0-9-]{1,80}\.(jpg|jpeg|png|webp|gif)'])->name('overlay.background');

// ── Halaman antrean publik /{slug} — paling akhir supaya tidak menutupi rute lain ──
Route::get('/{slug}', PublicChannelPageController::class)
    ->where('slug', '[A-Za-z0-9]+(?:-[A-Za-z0-9]+)*')
    ->name('channel.public');
