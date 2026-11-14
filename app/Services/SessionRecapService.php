<?php

namespace App\Services;

use App\Models\Donation;
use App\Models\LiveSession;
use App\Models\MatchRecord;
use App\Models\QueueEntry;
use Illuminate\Support\Collection;

/**
 * Rekap satu sesi live yang sudah selesai (hanya untuk dilihat): game yang dimainkan, siapa saja yang
 * ikut, donasi yang masuk, dan sisa game tiap pemain saat sesi berakhir.
 *
 * Pemain yang belum habis jatahnya sudah pindah ke sesi berikutnya, jadi rekap disusun dari riwayat match
 * (matches.live_session_id) dan donasi sesi itu — bukan dari isi antrean sekarang.
 */
class SessionRecapService
{
    public function for(LiveSession $session): array
    {
        $records = MatchRecord::query()
            ->with('queueEntry')
            ->where('live_session_id', $session->id)
            ->orderBy('played_at')
            ->orderBy('id')
            ->get()
            ->filter(fn (MatchRecord $record) => $record->queueEntry !== null)
            ->values();

        $donations = $session->donations()->orderBy('received_at')->orderBy('id')->get();
        $end = $session->ended_at ?? now();
        $games = $this->games($records);

        return [
            'games' => $games,
            'players' => $this->players($records, $end),
            'donations' => $donations,
            'summary' => [
                'duration' => $session->started_at && $session->ended_at ? $session->started_at->diff($session->ended_at) : null,
                'games' => $games->count(),
                'players' => $records->pluck('queue_entry_id')->unique()->count(),
                'donations' => $donations->count(),
                'donation_total' => (int) $donations->where('status', 'queued')->sum('amount'),
                'donation_all' => (int) $donations->sum('amount'),
            ],
        ];
    }

    /**
     * Satu "game" = match yang dicatat bersamaan (tombol "Selesai game" mencatat semua anggota party
     * pada detik yang sama); catat game satu pemain menjadi game tersendiri.
     *
     * @return Collection<int, array{number: int, time: \Illuminate\Support\Carbon, players: list<string>, note: ?string}>
     */
    private function games(Collection $records): Collection
    {
        return $records
            ->groupBy(fn (MatchRecord $record) => $record->played_at?->format('Y-m-d H:i:s') ?? 'r'.$record->id)
            ->values()
            ->map(fn (Collection $group, int $index) => [
                'number' => $index + 1,
                'time' => $group->first()->played_at,
                'players' => $group->map(fn (MatchRecord $record) => $record->queueEntry->display_name)->values()->all(),
                'note' => $group->pluck('note')->filter()->first(),
            ]);
    }

    /**
     * @return Collection<int, array{name: string, mlbb_id: ?string, service: string, played: int, remaining_at_end: int}>
     */
    private function players(Collection $records, $end): Collection
    {
        return $records
            ->groupBy('queue_entry_id')
            ->map(function (Collection $group) use ($end) {
                /** @var QueueEntry $entry */
                $entry = $group->first()->queueEntry;
                // Sisa saat sesi berakhir = jatah total − semua match pemain ini sampai sesi itu selesai.
                $playedUntilEnd = $entry->matches()->where('played_at', '<=', $end)->count();

                return [
                    'name' => $entry->display_name,
                    'mlbb_id' => trim((string) $entry->player_name) !== '' ? $entry->mlbb_id : null,
                    'service' => $entry->service_label,
                    'played' => $group->count(),
                    'remaining_at_end' => max(0, (int) $entry->matches_total - $playedUntilEnd),
                ];
            })
            ->sortByDesc('played')
            ->values();
    }
}
