<?php

namespace App\Services;

use App\Models\LiveSession;
use App\Models\User;

class PublicQueueService
{
    public function forUser(User $user): array
    {
        $session = $user->liveSessions()
            ->active()
            ->with(['queueEntries' => fn ($query) => $query
                ->whereIn('status', ['ready', 'playing'])
                ->orderBy('position')
                ->limit(100)])
            ->first();

        return $this->serialize($session);
    }

    public function serialize(?LiveSession $session): array
    {
        if ($session === null) {
            return ['status' => 'offline', 'session' => null, 'queue' => []];
        }

        return [
            'status' => 'live',
            'session' => [
                'id' => $session->id,
                'title' => $session->title,
                'started_at' => $session->started_at,
                'total_players' => $session->total_players,
                'total_matches' => $session->total_matches,
            ],
            'current' => $session->queueEntries->firstWhere('status', 'playing')?->player_name,
            'queue' => $session->queueEntries->map(fn ($entry) => [
                'position' => $entry->position,
                'player_name' => $entry->player_name,
                'status' => $entry->status,
                'matches_remaining' => max(0, $entry->matches_total - $entry->matches_played),
                'provider' => $entry->provider,
            ])->values()->all(),
        ];
    }

    public function forLiveSession(LiveSession $session): array
    {
        return $this->serialize($session->load([
            'queueEntries' => fn ($query) => $query
                ->whereIn('status', ['ready', 'playing'])
                ->orderBy('position'),
        ]));
    }

    public function search(User $user, string $name): array
    {
        $queue = $this->forUser($user);
        $needle = mb_strtolower(trim($name));

        if ($needle === '') {
            return ['query' => '', 'matches' => []];
        }

        return [
            'query' => $name,
            'matches' => array_values(array_filter(
                $queue['queue'],
                fn (array $entry): bool => str_contains(mb_strtolower($entry['player_name']), $needle)
            )),
        ];
    }
}
