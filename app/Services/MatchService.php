<?php

namespace App\Services;

use App\Models\QueueEntry;
use InvalidArgumentException;

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
        if (!$this->canRecord($entry)) {
            throw new InvalidArgumentException('Jatah match pemain ini sudah habis.');
        }
    }
}
