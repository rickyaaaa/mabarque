<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Halaman sesi live yang membutuhkan sesi aktif: papan antrean (/dashboard) dan hitungan match (/match-count).
 * Tanpa sesi aktif, halaman menampilkan ajakan memulai sesi.
 */
class DashboardPageController extends Controller
{
    public function queue(Request $request): View
    {
        return view('pages.dashboard', ['session' => $this->activeSession($request)]);
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
