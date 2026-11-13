<?php

namespace App\Services;

use App\Models\LiveSession;
use App\Models\QueueEntry;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class QueueService
{
    public function current(LiveSession $session): ?QueueEntry
    {
        return $this->orderedEntries($session)
            ->first(fn (QueueEntry $entry): bool => $entry->status === 'playing')
            ?? $this->orderedEntries($session)->first(fn (QueueEntry $entry): bool => $entry->status === 'ready');
    }

    public function addManual(LiveSession $session, string $playerName, int $matchesTotal, int $donationAmount = 0): QueueEntry
    {
        $playerName = trim($playerName);

        if ($playerName === '') {
            throw new InvalidArgumentException('Nama pemain wajib diisi.');
        }

        if ($matchesTotal < 1) {
            throw new InvalidArgumentException('Jatah mabar minimal 1.');
        }

        if ($donationAmount < 0) {
            throw new InvalidArgumentException('Nominal donasi tidak boleh negatif.');
        }

        return DB::transaction(function () use ($session, $playerName, $matchesTotal, $donationAmount): QueueEntry {
            $position = ((int) $this->orderedEntries($session)->max('position')) + 1;

            $entry = $session->queueEntries()->create([
                'player_name' => $playerName,
                'provider' => 'manual',
                'donation_amount' => $donationAmount,
                'matches_total' => $matchesTotal,
                'matches_played' => 0,
                'status' => 'ready',
                'position' => $position,
                'joined_at' => now(),
            ]);

            $session->increment('total_players');

            return $entry;
        });
    }

    public function markPlaying(LiveSession $session, QueueEntry $entry): QueueEntry
    {
        return DB::transaction(function () use ($session, $entry): QueueEntry {
            $this->ensureEntryBelongsToSession($session, $entry);

            QueueEntry::query()
                ->where('live_session_id', $session->id)
                ->where('status', 'playing')
                ->update(['status' => 'ready']);

            $entry->update(['status' => 'playing']);

            return $entry->refresh();
        });
    }

    public function move(LiveSession $session, QueueEntry $entry, string $direction): void
    {
        DB::transaction(function () use ($session, $entry, $direction): void {
            $this->ensureEntryBelongsToSession($session, $entry);

            $entries = $this->orderedEntries($session)->values();
            $index = $entries->search(fn (QueueEntry $candidate): bool => $candidate->is($entry));
            $targetIndex = $direction === 'up' ? $index - 1 : $index + 1;

            if ($index === false || $targetIndex < 0 || $targetIndex >= $entries->count()) {
                return;
            }

            $target = $entries->get($targetIndex);
            $position = $entry->position;
            $entry->update(['position' => $target->position]);
            $target->update(['position' => $position]);
        });
    }

    public function skip(LiveSession $session, QueueEntry $entry): ?QueueEntry
    {
        return DB::transaction(function () use ($session, $entry): ?QueueEntry {
            $this->ensureEntryBelongsToSession($session, $entry);

            $lastPosition = (int) $this->orderedEntries($session)->max('position');
            $entry->update(['status' => 'skipped', 'position' => $lastPosition + 1]);
            $this->normalizePositions($session);

            return $this->promoteNextReady($session);
        });
    }

    public function remove(LiveSession $session, QueueEntry $entry): ?QueueEntry
    {
        return DB::transaction(function () use ($session, $entry): ?QueueEntry {
            $this->ensureEntryBelongsToSession($session, $entry);
            $wasPlaying = $entry->status === 'playing';
            $entry->delete();
            $this->normalizePositions($session);

            return $wasPlaying ? $this->promoteNextReady($session) : $this->current($session);
        });
    }

    public function normalizePositions(LiveSession $session): void
    {
        $this->orderedEntries($session)->values()->each(
            fn (QueueEntry $entry, int $index): QueueEntry => tap($entry)->update(['position' => $index + 1])
        );
    }

    private function promoteNextReady(LiveSession $session): ?QueueEntry
    {
        $next = $this->orderedEntries($session)->first(fn (QueueEntry $entry): bool => $entry->status === 'ready');

        if ($next === null) {
            return null;
        }

        $next->update(['status' => 'playing']);

        return $next->refresh();
    }

    private function orderedEntries(LiveSession $session): Collection
    {
        return $session->queueEntries()->get();
    }

    private function ensureEntryBelongsToSession(LiveSession $session, QueueEntry $entry): void
    {
        if ((int) $entry->live_session_id !== (int) $session->id) {
            throw new RuntimeException('Queue entry tidak termasuk dalam sesi live ini.');
        }
    }
}
