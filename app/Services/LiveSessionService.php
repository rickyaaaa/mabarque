<?php

namespace App\Services;

use App\Models\LiveSession;
use App\Models\QueueEntry;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Collection;
use InvalidArgumentException;

class LiveSessionService
{
    /** Status pemain yang jatahnya belum habis (ikut pindah ke sesi berikutnya). */
    public const UNFINISHED = ['ready', 'offline', 'playing', 'skipped'];

    public function start(User $user, string $title): LiveSession
    {
        $title = trim($title);
        if ($title === '') {
            throw new InvalidArgumentException('Nama sesi wajib diisi.');
        }

        return DB::transaction(function () use ($user, $title): LiveSession {
            LiveSession::query()
                ->where('user_id', $user->id)
                ->where('status', 'active')
                ->update(['status' => 'ended', 'ended_at' => now()]);

            $session = LiveSession::query()->create([
                'user_id' => $user->id,
                'title' => $title,
                'status' => 'active',
                'started_at' => now(),
                'ended_at' => null,
                'total_players' => 0,
                'total_matches' => 0,
                'total_donation' => 0,
            ]);

            $this->carryOverUnfinished($user, $session);

            return $session;
        });
    }

    /**
     * Sisa game pemain tidak hangus saat sesi berganti: pemain yang jatahnya belum habis dipindah ke sesi
     * baru dengan urutan yang sama (sesi lama dulu, lalu posisi). Riwayat match tetap di sesi asalnya.
     * Hanya "Kosongkan antrean"/arsip yang benar-benar menghapus pemain dari antrean.
     */
    private function carryOverUnfinished(User $user, LiveSession $session): void
    {
        $entries = QueueEntry::query()
            ->join('live_sessions', 'live_sessions.id', '=', 'queue_entries.live_session_id')
            ->where('live_sessions.user_id', $user->id)
            ->where('live_sessions.id', '!=', $session->id)
            ->whereIn('queue_entries.status', self::UNFINISHED)
            ->orderBy('live_sessions.started_at')
            ->orderBy('live_sessions.id')
            ->orderBy('queue_entries.position')
            ->orderBy('queue_entries.id')
            ->select('queue_entries.*')
            ->get();

        foreach ($entries->values() as $index => $entry) {
            $entry->forceFill([
                'live_session_id' => $session->id,
                // Sesi baru = mulai segar: yang tadinya bermain/dilewati/offline kembali siap.
                'status' => 'ready',
                'position' => $index + 1,
            ])->save();
        }

        if ($entries->isNotEmpty()) {
            $session->forceFill(['total_players' => $entries->count()])->save();
        }
    }

    public function end(User $user, LiveSession $session): LiveSession
    {
        if ((int) $session->user_id !== (int) $user->id) {
            abort(404);
        }

        $session->forceFill([
            'status' => 'ended',
            'ended_at' => $session->ended_at ?? now(),
        ])->save();

        return $session->fresh();
    }

    public function archive(User $user, LiveSession $session): LiveSession
    {
        if ((int) $session->user_id !== (int) $user->id) {
            abort(404);
        }

        return DB::transaction(function () use ($session): LiveSession {
            $session->queueEntries()
                ->whereIn('status', ['ready', 'offline', 'playing', 'skipped'])
                ->update(['status' => 'done']);

            $session->forceFill([
                'status' => 'ended',
                'ended_at' => $session->ended_at ?? now(),
            ])->save();

            return $session->fresh();
        });
    }

    public function archived(User $user): Collection
    {
        return LiveSession::query()
            ->where('user_id', $user->id)
            ->where('status', 'ended')
            ->latest('ended_at')
            ->get();
    }

    public function summary(LiveSession $session): array
    {
        $session->loadCount([
            'queueEntries as queued_players_count' => fn ($query) => $query->whereIn('status', ['ready', 'offline', 'playing']),
            'matches as matches_count',
        ]);

        return [
            'id' => $session->id,
            'title' => $session->title,
            'status' => $session->status,
            'started_at' => $session->started_at,
            'ended_at' => $session->ended_at,
            'total_players' => (int) $session->total_players,
            'queued_players' => (int) $session->queued_players_count,
            'matches' => (int) $session->matches_count,
            'total_donation' => (int) $session->total_donation,
        ];
    }

    public function resetQueue(User $user, LiveSession $session): int
    {
        if ((int) $session->user_id !== (int) $user->id) {
            abort(404);
        }

        return DB::transaction(function () use ($session): int {
            return $session->queueEntries()
                ->whereIn('status', ['ready', 'offline', 'playing', 'skipped'])
                ->update(['status' => 'done']);
        });
    }
}
