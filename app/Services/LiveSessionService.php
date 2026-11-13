<?php

namespace App\Services;

use App\Models\LiveSession;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Collection;
use InvalidArgumentException;

class LiveSessionService
{
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

            return LiveSession::query()->create([
                'user_id' => $user->id,
                'title' => $title,
                'status' => 'active',
                'started_at' => now(),
                'ended_at' => null,
                'total_players' => 0,
                'total_matches' => 0,
                'total_donation' => 0,
            ]);
        });
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
