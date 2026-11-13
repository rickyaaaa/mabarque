<?php

namespace App\Services;

use App\Models\LiveSession;
use App\Models\QueueEntry;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class QueueStatusService
{
    public function markReady(LiveSession $session, QueueEntry $entry): QueueEntry
    {
        return $this->setAvailability($session, $entry, 'ready');
    }

    public function markOffline(LiveSession $session, QueueEntry $entry): QueueEntry
    {
        return $this->setAvailability($session, $entry, 'offline');
    }

    public function toggle(LiveSession $session, QueueEntry $entry): QueueEntry
    {
        $nextStatus = $entry->status === 'offline' ? 'ready' : 'offline';

        return $this->setAvailability($session, $entry, $nextStatus);
    }

    private function setAvailability(LiveSession $session, QueueEntry $entry, string $status): QueueEntry
    {
        if (!in_array($status, ['ready', 'offline'], true)) {
            throw new InvalidArgumentException('Status antrean harus ready atau offline.');
        }

        if ((int) $entry->live_session_id !== (int) $session->id) {
            throw new RuntimeException('Queue entry tidak termasuk dalam sesi live ini.');
        }

        if (in_array($entry->status, ['playing', 'done', 'skipped'], true)) {
            throw new InvalidArgumentException('Pemain yang sedang bermain, sudah selesai, atau dilewati tidak dapat diubah statusnya.');
        }

        return DB::transaction(function () use ($entry, $status): QueueEntry {
            $entry->update(['status' => $status]);

            return $entry->refresh();
        });
    }
}
