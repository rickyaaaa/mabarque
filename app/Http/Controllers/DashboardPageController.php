<?php

namespace App\Http\Controllers;

use App\Services\LiveSessionService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Halaman sesi live yang membutuhkan sesi aktif: papan antrean (/dashboard) dan hitungan match (/match-count).
 * Tanpa sesi aktif, halaman menampilkan ajakan memulai sesi.
 */
class DashboardPageController extends Controller
{
    /** Papan antrean selalu bisa dibuka: saat belum live, menampilkan daftar sisa game untuk dikelola. */
    public function queue(Request $request, LiveSessionService $sessions): View
    {
        return view('pages.dashboard', ['session' => $sessions->queueSession($request->user())]);
    }

    public function matches(Request $request): View
    {
        return view('pages.match-count', ['session' => $this->activeSession($request)]);
    }

    private function activeSession(Request $request)
    {
        return $request->user()->liveSessions()->active()->latest('started_at')->first();
    }
}
