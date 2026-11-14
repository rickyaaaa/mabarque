<?php

namespace App\Services;

use App\Models\Donation;
use App\Models\LiveSession;
use App\Models\MatchRecord;
use App\Models\QueueEntry;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class QueueService
{
    /** Batas party: streamer + 4 viewer (5-stack MLBB). */
    public const MAX_PARTY_SIZE = 4;

    /** Pemain pertama di party, atau (bila party kosong) pemain pertama yang akan masuk party. */
    public function current(LiveSession $session): ?QueueEntry
    {
        return $this->party($session)->first() ?? $this->nextUp($session, 1)->first();
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

        // VIP Mainin akun tidak memakai hitungan game: cukup Menunggu → Sedang dimainkan → Selesai.
        if ($attributes['service_type'] === 'joki') {
            $matchesTotal = max(1, $matchesTotal);
        }

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

        if ($attributes['service_type'] === 'joki') {
            $remaining = max(1, $this->remaining($entry));
        }

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
            // Baris kosong, komentar, dan pembatas blok kode (sering ikut tersalin dari chat) dilewati.
            if ($line === '' || str_starts_with($line, '#') || str_starts_with($line, '```')) {
                continue;
            }

            $parts = array_map('trim', explode('|', $line));
            $matches = $parts[2] ?? '';

            // VIP Mainin akun tidak memakai hitungan game: kolom sisa boleh kosong.
            if ($matches === '' && in_array(mb_strtolower($parts[3] ?? ''), ['joki', 'akun', 'mainin akun'], true)) {
                $matches = '1';
            }

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

    /**
     * Masukkan pemain ke party secara manual — boleh juga pemain yang sudah kena batas sesi (keputusan
     * streamer). Party berisi 1 orang: pemain yang sedang main ditukar. Party lebih besar yang penuh: ditolak.
     */
    public function markPlaying(LiveSession $session, QueueEntry $entry): QueueEntry
    {
        return DB::transaction(function () use ($session, $entry): QueueEntry {
            $this->ensureEntryBelongsToSession($session, $entry);
            $entry->refresh(); // status bisa berubah lewat pengisian party otomatis

            if ($entry->status === 'playing') {
                return $entry;
            }

            if ($entry->service_type === 'joki') {
                throw new InvalidArgumentException('Pemain "Mainin akun" dicatat terpisah, tidak masuk party mabar.');
            }

            if ($this->remaining($entry) < 1) {
                throw new InvalidArgumentException('Sisa game pemain ini sudah habis.');
            }

            $party = $this->party($session);
            $size = $this->partySize($session);

            if ($party->count() >= $size) {
                if ($size > 1) {
                    throw new InvalidArgumentException("Party sudah penuh ({$size}/{$size}). Keluarkan salah satu pemain dulu.");
                }

                $party->each(fn (QueueEntry $playing) => $playing->update(['status' => 'ready']));
            }

            $entry->update(['status' => 'playing']);

            return $entry->refresh();
        });
    }

    /**
     * Keluarkan pemain dari party tanpa mengubah urutannya. Slotnya sengaja DIBIARKAN KOSONG (mis. pemain
     * sedang gangguan jaringan dan mau ditunggu); streamer mengisinya sendiri lewat "Isi slot kosong".
     */
    public function bench(LiveSession $session, QueueEntry $entry): QueueEntry
    {
        return DB::transaction(function () use ($session, $entry): QueueEntry {
            $this->ensureEntryBelongsToSession($session, $entry);
            $entry->refresh();

            if ($entry->status !== 'playing') {
                throw new InvalidArgumentException('Pemain ini tidak sedang di party.');
            }

            $entry->update(['status' => 'ready']);

            return $entry->refresh();
        });
    }

    /**
     * Pilih pemain untuk "Next game" secara manual (maks sebanyak slot party). Saat party diisi,
     * pilihan manual diambil duluan sebelum urutan otomatis.
     */
    public function pickForNext(LiveSession $session, QueueEntry $entry): QueueEntry
    {
        return DB::transaction(function () use ($session, $entry): QueueEntry {
            $this->ensureEntryBelongsToSession($session, $entry);
            $entry->refresh();

            if ($entry->service_type === 'joki') {
                throw new InvalidArgumentException('Pemain "Mainin akun" dicatat terpisah, tidak masuk party mabar.');
            }

            if ($entry->status === 'playing') {
                throw new InvalidArgumentException('Pemain ini sedang main di party.');
            }

            if ($this->remaining($entry) < 1) {
                throw new InvalidArgumentException('Sisa game pemain ini sudah habis.');
            }

            if ($entry->isPickedForNext()) {
                return $entry;
            }

            $size = $this->partySize($session);
            if ($this->pickedForNext($session)->count() >= $size) {
                throw new InvalidArgumentException("Next game sudah penuh ({$size}/{$size}). Batalkan salah satu dulu.");
            }

            // Pemain offline/dilewati yang dipilih manual dianggap hadir lagi.
            $entry->forceFill(['status' => 'ready'])->save();
            $entry->forceFill(['next_order' => ((int) $session->queueEntries()->reorder()->max('next_order')) + 1])->save();

            return $entry->refresh();
        });
    }

    public function unpickForNext(LiveSession $session, QueueEntry $entry): QueueEntry
    {
        $this->ensureEntryBelongsToSession($session, $entry);
        $entry->forceFill(['next_order' => null])->save();

        return $entry->refresh();
    }

    /** @return Collection<int, QueueEntry> pilihan manual Next game (urut waktu dipilih) */
    public function pickedForNext(LiveSession $session): Collection
    {
        return $this->orderedEntries($session)
            ->filter(fn (QueueEntry $entry): bool => $entry->isPickedForNext())
            ->sortBy(fn (QueueEntry $entry) => [$entry->next_order, $entry->id])
            ->values();
    }

    // ── VIP Mainin akun (joki) ──────────────────────────────────────────

    /** Mulai memainkan akun pemesan (tampil "Sedang dimainkan" di halaman viewer). */
    public function startJoki(LiveSession $session, QueueEntry $entry): QueueEntry
    {
        $this->ensureEntryBelongsToSession($session, $entry);
        $entry->refresh();

        if ($entry->service_type !== 'joki') {
            throw new InvalidArgumentException('Hanya untuk pesanan VIP Mainin akun.');
        }

        $entry->update(['status' => 'playing']);

        return $entry->refresh();
    }

    /** Akun selesai dimainkan (checklist): pesanan selesai, tampil "Selesai" di halaman viewer. */
    public function finishJoki(LiveSession $session, QueueEntry $entry): QueueEntry
    {
        $this->ensureEntryBelongsToSession($session, $entry);
        $entry->refresh();

        if ($entry->service_type !== 'joki') {
            throw new InvalidArgumentException('Hanya untuk pesanan VIP Mainin akun.');
        }

        $this->complete($session, $entry);

        return $entry->refresh();
    }

    // ── Party & batas game per sesi ─────────────────────────────────────

    /** Jumlah viewer per game (streamer + N, 5-stack MLBB → maks 4). */
    public function partySize(LiveSession $session): int
    {
        $size = (int) ($session->user?->party_size ?? 4);

        return min(self::MAX_PARTY_SIZE, max(1, $size ?: 4));
    }

    /** Batas game per pemain dalam satu sesi live; 0 = tanpa batas. */
    public function sessionCap(LiveSession $session): int
    {
        return max(0, (int) ($session->user?->session_match_cap ?? 0));
    }

    /** @return array<int, int> id pemain => jumlah game yang sudah dimainkan di sesi ini */
    public function playedThisSession(LiveSession $session): array
    {
        return MatchRecord::query()
            ->where('live_session_id', $session->id)
            ->selectRaw('queue_entry_id, COUNT(*) as played')
            ->groupBy('queue_entry_id')
            ->pluck('played', 'queue_entry_id')
            ->map(fn ($played) => (int) $played)
            ->all();
    }

    /** Pemain mabar VIP yang sudah mencapai batas game sesi ini (joki tidak dibatasi). */
    public function isCapped(LiveSession $session, QueueEntry $entry, ?array $played = null): bool
    {
        $cap = $this->sessionCap($session);

        return $cap > 0
            && $entry->service_type !== 'joki'
            && (($played ?? $this->playedThisSession($session))[$entry->id] ?? 0) >= $cap;
    }

    /** @return Collection<int, QueueEntry> pemain yang sedang main (urut posisi) */
    public function party(LiveSession $session): Collection
    {
        return $this->orderedEntries($session)
            ->filter(fn (QueueEntry $entry): bool => $entry->status === 'playing' && $entry->service_type !== 'joki')
            ->values();
    }

    /**
     * Pemain berikutnya yang berhak masuk party: siap, mabar VIP, masih punya sisa, belum kena batas sesi.
     *
     * @return Collection<int, QueueEntry>
     */
    public function nextUp(LiveSession $session, ?int $limit = null, array $exclude = []): Collection
    {
        $played = $this->playedThisSession($session);
        $waiting = $this->orderedEntries($session)
            ->filter(fn (QueueEntry $entry): bool => $entry->status === 'ready'
                && $entry->service_type !== 'joki'
                && $this->remaining($entry) > 0
                && ! in_array($entry->id, $exclude, true));

        // Pilihan manual streamer duluan (urut waktu dipilih; boleh yang sudah kena batas sesi),
        // lalu sisanya otomatis menurut urutan antrean.
        $picked = $waiting->filter(fn (QueueEntry $entry): bool => $entry->next_order !== null)
            ->sortBy(fn (QueueEntry $entry) => [$entry->next_order, $entry->id]);
        $auto = $waiting->filter(fn (QueueEntry $entry): bool => $entry->next_order === null
            && ! $this->isCapped($session, $entry, $played));

        $eligible = $picked->concat($auto)->values();

        return $limit === null ? $eligible : $eligible->take($limit)->values();
    }

    /**
     * Isi slot party yang kosong dengan pemain berikutnya.
     *
     * @param  array<int>  $exclude  pemain yang baru dikeluarkan (jangan langsung dimasukkan lagi)
     * @return Collection<int, QueueEntry> pemain yang baru masuk party
     */
    public function fillParty(LiveSession $session, array $exclude = []): Collection
    {
        $free = $this->partySize($session) - $this->party($session)->count();

        if ($free < 1) {
            return collect();
        }

        return $this->nextUp($session, $free, $exclude)
            ->each(fn (QueueEntry $entry) => $entry->update(['status' => 'playing']))
            ->map(fn (QueueEntry $entry) => $entry->refresh());
    }

    /**
     * Game party selesai: setiap anggota party berkurang 1 game (dihitung 1 match sesi). Yang sisanya
     * habis → selesai; yang mencapai batas sesi → keluar party ke belakang antrean. Slot kosong lalu diisi.
     *
     * @return array{played: Collection<int, QueueEntry>, finished: Collection<int, QueueEntry>, capped: Collection<int, QueueEntry>, joined: Collection<int, QueueEntry>}
     */
    public function finishGame(LiveSession $session, ?string $result = null): array
    {
        $result = in_array($result, ['win', 'lose'], true) ? $result : null;

        return DB::transaction(function () use ($session, $result): array {
            $party = $this->party($session);

            if ($party->isEmpty()) {
                throw new InvalidArgumentException('Belum ada pemain di party. Mulai game dulu.');
            }

            $matches = app(MatchService::class);
            $party->each(fn (QueueEntry $entry) => $matches->record($entry, countSessionMatch: false, result: $result));
            LiveSession::query()->whereKey($session->id)->increment('total_matches');

            // Skor Win/Lose dihitung per game party (bukan per pemain).
            if ($result !== null) {
                LiveSession::query()->whereKey($session->id)->increment($result === 'win' ? 'wins' : 'losses');
            }

            $settled = $this->settleAfterMatch($session, $party);
            // Batas game = saat alami mengisi slot yang belum terisi (mis. donasi baru masuk selama game).
            $settled['joined'] = $settled['joined']->merge($this->fillParty($session));

            return ['played' => $party] + $settled;
        });
    }

    /**
     * Rapikan pemain setelah match dicatat (dipakai game party maupun catat match satu pemain).
     *
     * @param  Collection<int, QueueEntry>  $entries
     * @return array{finished: Collection<int, QueueEntry>, capped: Collection<int, QueueEntry>, joined: Collection<int, QueueEntry>}
     */
    public function settleAfterMatch(LiveSession $session, Collection $entries): array
    {
        $played = $this->playedThisSession($session);
        $finished = collect();
        $capped = collect();
        $freedSlot = false;
        $last = (int) $this->orderedEntries($session)->max('position');

        foreach ($entries as $entry) {
            $entry->refresh();
            $wasPlaying = $entry->status === 'playing' && $entry->service_type !== 'joki';

            if ($this->remaining($entry) < 1) {
                $entry->update(['status' => 'done', 'position' => ++$last]);
                $finished->push($entry);
                $freedSlot = $freedSlot || $wasPlaying;
            } elseif ($wasPlaying && $this->isCapped($session, $entry, $played)) {
                $entry->update(['status' => 'ready', 'position' => ++$last]);
                $capped->push($entry);
                $freedSlot = true;
            }
        }

        $this->normalizePositions($session);

        // Slot party hanya diisi ulang bila memang ada anggota party yang keluar.
        $joined = $freedSlot ? $this->fillParty($session, exclude: $capped->pluck('id')->all()) : collect();

        return ['finished' => $finished, 'capped' => $capped, 'joined' => $joined];
    }

    private function remaining(QueueEntry $entry): int
    {
        return max(0, (int) $entry->matches_total - (int) $entry->matches_played);
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
            $wasPlaying = $entry->status === 'playing' && $entry->service_type !== 'joki';

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
            $wasPlaying = $entry->status === 'playing' && $entry->service_type !== 'joki';
            $this->discard($entry);
            $this->normalizePositions($session);

            return $wasPlaying ? $this->promoteNextReady($session) : $this->current($session);
        });
    }

    /**
     * Hapus banyak pemain sekaligus. Slot party yang ditinggalkan dibiarkan kosong (diisi streamer
     * lewat "Isi slot kosong"), sama seperti mengeluarkan pemain dari party.
     *
     * @param  array<int>  $ids
     * @return int jumlah pemain yang dihapus
     */
    public function removeMany(LiveSession $session, array $ids): int
    {
        return DB::transaction(function () use ($session, $ids): int {
            $entries = $session->queueEntries()->whereKey($ids)->whereIn('status', LiveSessionService::UNFINISHED)->get();
            $entries->each(fn (QueueEntry $entry) => $this->discard($entry));
            $this->normalizePositions($session);

            return $entries->count();
        });
    }


    /**
     * Pemain yang sudah punya riwayat game tidak benar-benar dihapus — cukup ditandai selesai supaya
     * rekap arsip sesi lama tetap utuh. Pemain tanpa riwayat dihapus.
     */
    private function discard(QueueEntry $entry): void
    {
        // Pemain dari donasi dihapus (mis. ternyata cuma donasi biasa): donasinya ditandai "Diabaikan".
        Donation::query()->where('queue_entry_id', $entry->id)->where('status', 'queued')
            ->update(['status' => 'ignored', 'processed_at' => now()]);

        if ($entry->matches()->exists()) {
            $entry->update(['status' => 'done']);

            return;
        }

        $entry->delete();
    }

    /**
     * Tandai pemain selesai (jatah habis): pindah ke belakang dengan status "done"; bila ia sedang
     * bermain, giliran dioper ke pemain siap berikutnya.
     */
    public function complete(LiveSession $session, QueueEntry $entry): ?QueueEntry
    {
        return DB::transaction(function () use ($session, $entry): ?QueueEntry {
            $this->ensureEntryBelongsToSession($session, $entry);
            $wasPlaying = $entry->status === 'playing' && $entry->service_type !== 'joki';

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

    /** Slot party kosong karena anggotanya keluar → isi dengan pemain berikutnya. */
    private function promoteNextReady(LiveSession $session): ?QueueEntry
    {
        return $this->fillParty($session)->first();
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
