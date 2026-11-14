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

    public const MAX_MATCHES = 999;

    public const MAX_IMPORT_LINES = 300;

    public function addManual(LiveSession $session, string $playerName, int $matchesTotal, int $donationAmount = 0): QueueEntry
    {
        return $this->addPlayer($session, [
            'player_name' => $playerName,
            'matches_total' => $matchesTotal,
            'donation_amount' => $donationAmount,
        ]);
    }

    /**
     * Tambah pemain manual. Nama boleh kosong bila ID MLBB diisi (dan sebaliknya).
     *
     * @param  array{player_name?: ?string, mlbb_id?: ?string, matches_total: int, service_type?: ?string, note?: ?string, donation_amount?: int}  $data
     */
    public function addPlayer(LiveSession $session, array $data): QueueEntry
    {
        $attributes = $this->playerAttributes($data);
        $matchesTotal = (int) ($data['matches_total'] ?? 0);
        $donationAmount = (int) ($data['donation_amount'] ?? 0);

        if ($matchesTotal < 1 || $matchesTotal > self::MAX_MATCHES) {
            throw new InvalidArgumentException('Jatah game harus 1–'.self::MAX_MATCHES.'.');
        }

        if ($donationAmount < 0) {
            throw new InvalidArgumentException('Nominal donasi tidak boleh negatif.');
        }

        return DB::transaction(function () use ($session, $attributes, $matchesTotal, $donationAmount): QueueEntry {
            $position = ((int) $this->orderedEntries($session)->max('position')) + 1;

            $entry = $session->queueEntries()->create($attributes + [
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

    /**
     * Ubah detail pemain (nama, ID, jenis, catatan) dan sisa game. Sisa 0 = jatah habis → pemain selesai.
     *
     * @param  array{player_name?: ?string, mlbb_id?: ?string, service_type?: ?string, note?: ?string, matches_remaining: int}  $data
     */
    public function updatePlayer(LiveSession $session, QueueEntry $entry, array $data): QueueEntry
    {
        $this->ensureEntryBelongsToSession($session, $entry);
        $attributes = $this->playerAttributes($data);
        $remaining = (int) ($data['matches_remaining'] ?? -1);

        if ($remaining < 0 || $remaining > self::MAX_MATCHES) {
            throw new InvalidArgumentException('Sisa game harus 0–'.self::MAX_MATCHES.'.');
        }

        return DB::transaction(function () use ($session, $entry, $attributes, $remaining): QueueEntry {
            $entry->update($attributes + ['matches_total' => $entry->matches_played + $remaining]);

            if ($remaining === 0 && $entry->status !== 'done') {
                $this->complete($session, $entry);
            }

            return $entry->refresh();
        });
    }

    /**
     * Tempel banyak pemain sekaligus, satu baris per pemain: `nama | ID MLBB | sisa game | jenis | catatan`.
     * Semua baris divalidasi dulu; satu baris salah = tidak ada yang dimasukkan.
     *
     * @return int jumlah pemain yang dimasukkan
     */
    public function importPlayers(LiveSession $session, string $text): int
    {
        $rows = [];
        $errors = [];
        $lines = preg_split('/\r\n|\r|\n/', $text) ?: [];

        foreach ($lines as $index => $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            $parts = array_map('trim', explode('|', $line));
            $matches = $parts[2] ?? '';

            try {
                if (! ctype_digit($matches)) {
                    throw new InvalidArgumentException('sisa game harus angka');
                }

                if ((int) $matches < 1 || (int) $matches > self::MAX_MATCHES) {
                    throw new InvalidArgumentException('sisa game harus 1–'.self::MAX_MATCHES);
                }

                $rows[] = $this->playerAttributes([
                    'player_name' => $parts[0] ?? '',
                    'mlbb_id' => $parts[1] ?? '',
                    'service_type' => $this->parseServiceType($parts[3] ?? ''),
                    'note' => implode(' | ', array_slice($parts, 4)),
                ]) + ['matches_total' => (int) $matches];
            } catch (InvalidArgumentException $exception) {
                $errors[] = 'Baris '.($index + 1).': '.lcfirst(rtrim($exception->getMessage(), '.'));
            }
        }

        if ($errors !== []) {
            $extra = count($errors) > 5 ? ' · dan '.(count($errors) - 5).' baris lain' : '';
            throw new InvalidArgumentException(implode(' · ', array_slice($errors, 0, 5)).$extra);
        }

        if ($rows === []) {
            throw new InvalidArgumentException('Belum ada baris pemain yang ditempel.');
        }

        if (count($rows) > self::MAX_IMPORT_LINES) {
            throw new InvalidArgumentException('Maksimal '.self::MAX_IMPORT_LINES.' pemain sekali tempel.');
        }

        return DB::transaction(function () use ($session, $rows): int {
            foreach ($rows as $row) {
                $this->addPlayer($session, $row);
            }

            return count($rows);
        });
    }

    /** Normalisasi & validasi detail pemain; dipakai tambah, edit, dan tempel banyak. */
    private function playerAttributes(array $data): array
    {
        $name = trim((string) preg_replace('/\s+/u', ' ', (string) ($data['player_name'] ?? '')));
        $mlbbId = trim((string) preg_replace('/\s+/', ' ', (string) ($data['mlbb_id'] ?? '')));
        $type = (string) ($data['service_type'] ?? '') ?: 'vip';
        $note = trim((string) ($data['note'] ?? ''));

        if ($name === '' && $mlbbId === '') {
            throw new InvalidArgumentException('Nama pemain atau ID MLBB wajib diisi.');
        }

        if (mb_strlen($name) > 120) {
            throw new InvalidArgumentException('Nama pemain maksimal 120 karakter.');
        }

        // ID MLBB: angka, boleh dengan server/zona, mis. "1185010926 (2345)".
        if ($mlbbId !== '' && ! preg_match('/^\d{4,20}( ?\(?\d{1,8}\)?)?$/', $mlbbId)) {
            throw new InvalidArgumentException('ID MLBB harus angka, boleh dengan server, mis. 1185010926 (2345).');
        }

        if (! array_key_exists($type, QueueEntry::SERVICE_TYPES)) {
            throw new InvalidArgumentException('Jenis harus Mabar VIP atau Mainin akun.');
        }

        if (mb_strlen($note) > 500) {
            throw new InvalidArgumentException('Catatan maksimal 500 karakter.');
        }

        return [
            'player_name' => $name,
            'mlbb_id' => $mlbbId !== '' ? $mlbbId : null,
            'service_type' => $type,
            'note' => $note !== '' ? $note : null,
        ];
    }

    private function parseServiceType(string $value): string
    {
        $value = mb_strtolower(trim($value));

        if ($value === '' || in_array($value, ['vip', 'mabar', 'mabar vip'], true)) {
            return 'vip';
        }

        if (in_array($value, ['joki', 'akun', 'mainin akun'], true)) {
            return 'joki';
        }

        throw new InvalidArgumentException('jenis "'.$value.'" tidak dikenal (pakai vip atau joki)');
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
            $wasPlaying = $entry->status === 'playing';

            $lastPosition = (int) $this->orderedEntries($session)->max('position');
            $entry->update(['status' => 'skipped', 'position' => $lastPosition + 1]);
            $this->normalizePositions($session);

            // Giliran hanya dioper bila yang dilewati sedang bermain; melewati pemain yang masih
            // menunggu tidak boleh menghasilkan dua pemain "playing" sekaligus.
            return $wasPlaying ? $this->promoteNextReady($session) : $this->current($session);
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

    /**
     * Tandai pemain selesai (jatah habis): pindah ke belakang dengan status "done"; bila ia sedang
     * bermain, giliran dioper ke pemain siap berikutnya.
     */
    public function complete(LiveSession $session, QueueEntry $entry): ?QueueEntry
    {
        return DB::transaction(function () use ($session, $entry): ?QueueEntry {
            $this->ensureEntryBelongsToSession($session, $entry);
            $wasPlaying = $entry->status === 'playing';

            $lastPosition = (int) $this->orderedEntries($session)->max('position');
            $entry->update(['status' => 'done', 'position' => $lastPosition + 1]);
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
