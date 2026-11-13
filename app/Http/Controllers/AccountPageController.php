<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/** Halaman Akun Streamer (GET /account): identitas, status akses beta, dan ringkasan workspace dari database. */
class AccountPageController extends Controller
{
    private const PROVIDERS = [
        'saweria' => ['label' => 'Saweria', 'short' => 'S'],
        'sociabuzz' => ['label' => 'SociaBuzz', 'short' => 'SB'],
        'trakteer' => ['label' => 'Trakteer', 'short' => 'T'],
    ];

    public function __invoke(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();
        $activeSession = $user->liveSessions()->active()->latest('started_at')->first();

        $queueCounts = $activeSession
            ? $activeSession->queueEntries()->reorder()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status')
            : collect();

        $connections = $user->platformConnections()->get()->keyBy('provider');

        return view('pages.account', [
            'user' => $user,
            'activeSession' => $activeSession,
            'queueTotal' => (int) $queueCounts->only(['ready', 'offline', 'playing'])->sum(),
            'queueReady' => (int) ($queueCounts['ready'] ?? 0),
            'queueOffline' => (int) ($queueCounts['offline'] ?? 0),
            'matchesThisMonth' => $user->matchRecords()->where('played_at', '>=', now()->startOfMonth())->count(),
            'providers' => collect(self::PROVIDERS)->map(fn (array $meta, string $key) => $meta + ['connection' => $connections->get($key)]),
            'recentSessions' => $user->liveSessions()->where('status', 'ended')->latest('ended_at')->limit(3)->get(),
        ]);
    }
}
