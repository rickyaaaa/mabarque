<?php

namespace App\Services;

use App\Models\LiveSession;
use App\Models\MatchRecord;
use App\Models\QueueEntry;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Aturan & pencatatan match pemain VIP. Dipakai bersama oleh API (MatchController)
 * dan komponen Livewire (papan antrean, hitungan match) supaya hitungan selalu konsisten.
 */
class MatchService
{
    public function remaining(QueueEntry $entry): int
    {
        return max(0, (int) $entry->matches_total - (int) $entry->matches_played);
    }

    public function canRecord(QueueEntry $entry): bool
    {
        return $this->remaining($entry) > 0;
    }

    public function canCorrect(QueueEntry $entry): bool
    {
        return (int) $entry->matches_played > 0;
    }

    public function quotaStatus(QueueEntry $entry): string
    {
        $remaining = $this->remaining($entry);

        return $remaining === 0 ? 'empty' : ($remaining <= 2 ? 'low' : 'ok');
    }

    public function assertCanRecord(QueueEntry $entry): void
    {
        if (! $this->canRecord($entry)) {
            throw new InvalidArgumentException('Jatah match pemain ini sudah habis.');
        }
    }

    /**
     * Catat satu match selesai: kunci baris pemain, tolak bila jatah habis, nomori match berikutnya,
     * lalu naikkan matches_played pemain dan total_matches sesi.
     *
     * @throws InvalidArgumentException jatah habis
     */
    public function record(QueueEntry $entry, ?string $note = null): MatchRecord
    {
        return DB::transaction(function () use ($entry, $note): MatchRecord {
            $locked = QueueEntry::query()->whereKey($entry->id)->lockForUpdate()->firstOrFail();
            $this->assertCanRecord($locked);

            $match = MatchRecord::query()->create([
                'queue_entry_id' => $locked->id,
                'live_session_id' => $locked->live_session_id,
                'match_number' => (int) $locked->matches()->max('match_number') + 1,
                'note' => filled($note) ? trim($note) : null,
                'played_at' => now(),
            ]);

            $locked->increment('matches_played');
            LiveSession::query()->whereKey($locked->live_session_id)->increment('total_matches');
            $entry->refresh();

            return $match;
        });
    }

    /**
     * Koreksi: hapus satu match tertentu dan kembalikan jatahnya.
     *
     * @throws InvalidArgumentException tidak ada match yang bisa dikoreksi
     */
    public function undo(MatchRecord $match): QueueEntry
    {
        return DB::transaction(function () use ($match): QueueEntry {
            $record = MatchRecord::query()->whereKey($match->id)->lockForUpdate()->firstOrFail();
            $entry = QueueEntry::query()->whereKey($record->queue_entry_id)->lockForUpdate()->firstOrFail();

            if (! $this->canCorrect($entry)) {
                throw new InvalidArgumentException('Tidak ada match tercatat yang bisa dikoreksi.');
            }

            $record->delete();
            $entry->decrement('matches_played');
            LiveSession::query()->whereKey($entry->live_session_id)->where('total_matches', '>', 0)->decrement('total_matches');

            return $entry->refresh();
        });
    }

    /**
     * Koreksi match terakhir milik pemain (tombol "−1").
     *
     * @throws InvalidArgumentException belum ada match tercatat
     */
    public function undoLatest(QueueEntry $entry): QueueEntry
    {
        $latest = $entry->matches()->latest('match_number')->first();

        if ($latest === null) {
            throw new InvalidArgumentException('Belum ada match tercatat untuk pemain ini.');
        }

        return $this->undo($latest);
    }
}
