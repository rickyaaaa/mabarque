<?php

namespace App\Http\Controllers;

use App\Models\LiveSession;
use App\Services\SessionRecapService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Detail arsip sesi live (GET /live-session/{liveSession}): hanya dilihat, perubahan tetap dari papan antrean. */
class SessionArchiveController extends Controller
{
    public function __invoke(Request $request, LiveSession $liveSession, SessionRecapService $recaps): View|RedirectResponse
    {
        abort_unless($liveSession->isOwnedBy($request->user()), 404);

        // Sesi yang masih berjalan dikelola dari papan antrean.
        if ($liveSession->status === 'active') {
            return redirect('/dashboard');
        }

        return view('pages.session-archive', ['session' => $liveSession] + $recaps->for($liveSession));
    }
}
