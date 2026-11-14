<?php

namespace App\Services;

use App\Models\LiveSession;
use App\Models\QueueEntry;
use App\Models\User;

/**
 * Data antrean untuk tampilan publik (halaman viewer /{slug} dan overlay OBS).
 *
 * - Saat live: pemain sesi aktif yang jatahnya belum habis.
 * - Saat tidak live: daftar sisa game dari sesi terakhir tetap tampil (pengganti "notepad" streamer),
 *   karena sisa game terbawa ke sesi berikutnya (LiveSessionService::start).
 * - Catatan streamer dan nominal donasi tidak pernah dikirim. Bila sensor aktif, separuh akhir nama dan
 *   ID disamarkan DI SERVER — nilai aslinya tidak pernah sampai ke browser viewer.
 */
class PublicQueueService
{
    private const LIMIT = 300;

    public function forUser(User $user): array
    {
        $censor = (bool) $user->overlaySetting()->value('censor_names');
        $active = $user->liveSessions()->active()->latest('started_at')->first();
        $session = $active ?? $user->liveSessions()->latest('started_at')->latest('id')->first();

        if ($session === null) {
            return $this->serialize(null, $censor);
        }

        $session->load(['queueEntries' => fn ($query) => $query
            ->whereIn('status', LiveSessionService::UNFINISHED)
            ->limit(self::LIMIT)]);

        return $this->serialize($session, $censor, $active !== null);
    }

    public function serialize(?LiveSession $session, bool $censor = false, bool $live = true): array
    {
        if ($session === null || (! $live && $session->queueEntries->isEmpty())) {
            return ['status' => 'offline', 'session' => null, 'rules' => null, 'current' => null, 'party' => [], 'next' => [], 'queue' => []];
        }

        $queueService = app(QueueService::class);
        $played = $live ? $queueService->playedThisSession($session) : [];
        $size = $queueService->partySize($session);
        // Urutan masuk party berikutnya (siap, mabar VIP, belum kena batas sesi).
        $nextRank = $live ? $queueService->nextUp($session)->pluck('id')->flip()->all() : [];

        $queue = $session->queueEntries->map(fn (QueueEntry $entry) => [
            'position' => $entry->position,
            'player_name' => $this->displayName($entry, $censor),
            'mlbb_id' => $entry->mlbb_id ? ($censor ? self::censor($entry->mlbb_id) : $entry->mlbb_id) : null,
            'status' => $live ? $entry->status : 'offline',
            'matches_remaining' => max(0, $entry->matches_total - $entry->matches_played),
            'played_this_session' => $played[$entry->id] ?? 0,
            'capped' => $live && $entry->status !== 'playing' && $queueService->isCapped($session, $entry, $played),
            'next_rank' => isset($nextRank[$entry->id]) ? $nextRank[$entry->id] + 1 : null,
            'provider' => $entry->provider,
            'service_type' => $entry->service_type ?: 'vip',
            'joined_at' => $entry->joined_at?->toIso8601String(),
        ])->values();

        $party = $queue->where('status', 'playing')->pluck('player_name')->values()->all();

        return [
            'status' => $live ? 'live' : 'offline',
            'session' => $live ? [
                'id' => $session->id,
                'title' => $session->title,
                'started_at' => $session->started_at,
                'total_players' => $session->total_players,
                'total_matches' => $session->total_matches,
            ] : null,
            'rules' => ['party_size' => $size, 'session_cap' => $queueService->sessionCap($session)],
            'current' => $party[0] ?? null,
            'party' => $party,
            'next' => $queue->whereNotNull('next_rank')->sortBy('next_rank')->take($size)->pluck('player_name')->values()->all(),
            'queue' => $queue->all(),
        ];
    }

    public function search(User $user, string $name): array
    {
        $needle = mb_strtolower(trim($name));

        if ($needle === '') {
            return ['query' => '', 'matches' => []];
        }

        // Cocokkan ke nama & ID ASLI (pemilik tahu namanya sendiri), tetapi kembalikan versi publiknya.
        $active = $user->liveSessions()->active()->latest('started_at')->first()
            ?? $user->liveSessions()->latest('started_at')->latest('id')->first();
        $queue = $this->forUser($user)['queue'];

        $ids = $active?->queueEntries()
            ->whereIn('status', LiveSessionService::UNFINISHED)
            ->get()
            ->filter(fn (QueueEntry $entry): bool => str_contains(mb_strtolower((string) $entry->player_name), $needle)
                || ($entry->mlbb_id && str_contains($entry->mlbb_id, $needle)))
            ->pluck('position')
            ->all() ?? [];

        return [
            'query' => $name,
            'matches' => array_values(array_filter($queue, fn (array $entry): bool => in_array($entry['position'], $ids, true))),
        ];
    }

    /** Separuh akhir disamarkan: "WangSukiJomok" → "WangSu*******", "1185010926" → "11850*****". */
    public static function censor(string $value): string
    {
        $length = mb_strlen($value);
        $visible = max(1, intdiv($length, 2));

        return $length <= 1 ? '*' : mb_substr($value, 0, $visible).str_repeat('*', $length - $visible);
    }

    private function displayName(QueueEntry $entry, bool $censor): string
    {
        $name = trim((string) $entry->player_name);

        if ($name !== '') {
            return $censor ? self::censor($name) : $name;
        }

        return $entry->mlbb_id ? 'ID '.($censor ? self::censor($entry->mlbb_id) : $entry->mlbb_id) : 'Tanpa nama';
    }
}
