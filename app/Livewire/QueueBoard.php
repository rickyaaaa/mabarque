<?php

namespace App\Livewire;

use App\Models\LiveSession;
use App\Models\QueueEntry;
use App\Services\DonationQueueService;
use App\Services\LiveSessionService;
use App\Services\MatchService;
use App\Services\QueueService;
use App\Services\QueueStatusService;
use InvalidArgumentException;
use Livewire\Component;
use RuntimeException;

/**
 * Papan antrean sesi live (/dashboard): giliran sekarang, aksi cepat, daftar pemain, tambah pemain manual.
 * Polling tiap 5 detik supaya donasi yang masuk lewat webhook langsung terlihat.
 */
class QueueBoard extends Component
{
    /** Status yang tampil di papan; pemain "done" (jatah habis) disembunyikan. */
    private const VISIBLE = ['playing', 'ready', 'offline', 'skipped'];

    /** Filter daftar: otomatis = masuk dari donasi, manual = diinput streamer, joki = mainin akun. */
    private const FILTERS = ['all', 'vip', 'joki', 'auto', 'manual'];

    /** Batas game per orang per sesi yang bisa dipilih streamer (0 = tanpa batas). */
    public const MAX_SESSION_CAP = 20;

    public LiveSession $session;

    public string $filter = 'all';

    public string $playerName = '';

    public string $mlbbId = '';

    public int $matchesTotal = 1;

    public string $serviceType = 'vip';

    public string $note = '';

    public bool $showAddPlayer = false;

    /** Pemain yang sedang diedit (null = modal edit tertutup). */
    public ?int $editingId = null;

    public string $editName = '';

    public string $editMlbbId = '';

    public int $editRemaining = 0;

    public string $editType = 'vip';

    public string $editNote = '';

    public bool $showImport = false;

    public bool $showRules = false;

    public int $partySize = 4;

    public int $sessionCap = 0;

    public string $donationModeInput = 'smart';

    /** Sensor nama & ID di halaman viewer dan overlay (pengaturan yang sama dengan halaman Overlay). */
    public bool $censorNames = false;

    /** Aturan "MVP = main lagi" (form Aturan party). */
    public bool $mvpFreeGame = false;

    /** Pemain party yang ditandai MVP untuk game yang sedang berjalan. */
    public ?int $mvpId = null;

    /** Pemain yang dicentang untuk aksi massal (hapus / offline / siap). */
    public array $selected = [];

    public string $importText = '';

    public function mount(LiveSession $session): void
    {
        // Papan antrean hanya boleh dibuka pemilik sesi.
        abort_unless($session->isOwnedBy(auth()->user()), 404);

        $this->session = $session;
    }

    public function refreshQueue(): void
    {
        if ($this->redirectIfStale()) {
            return;
        }

        $this->session->refresh();
    }

    public function setFilter(string $filter): void
    {
        $this->filter = in_array($filter, self::FILTERS, true) ? $filter : 'all';
        $this->selected = [];
    }

    /** Centang semua pemain yang terlihat di filter sekarang, atau kosongkan bila semuanya sudah dicentang. */
    public function toggleSelectAll(): void
    {
        $visible = $this->visibleEntries()->pluck('id')->map(fn ($id) => (string) $id)->all();
        $this->selected = array_diff($visible, $this->selected) === [] ? [] : $visible;
    }

    public function clearSelection(): void
    {
        $this->selected = [];
    }

    public function bulkRemove(QueueService $queueService): void
    {
        $this->attempt(function () use ($queueService): string {
            $count = $queueService->removeMany($this->session, $this->selectedIds());
            $this->selected = [];

            return "{$count} pemain dihapus dari antrean.";
        });
    }


    /** @return array<int> */
    private function selectedIds(): array
    {
        // Hanya yang terlihat di filter sekarang — pemain yang tersembunyi tidak ikut terhapus diam-diam.
        $ids = array_values(array_intersect(
            array_unique(array_map('intval', $this->selected)),
            $this->visibleEntries()->pluck('id')->all(),
        ));

        if ($ids === []) {
            throw new InvalidArgumentException('Belum ada pemain yang dicentang.');
        }

        return $ids;
    }

    private function visibleEntries()
    {
        $all = $this->session->queueEntries()->with('donation')->whereIn('status', self::VISIBLE)->get()
            ->sortBy(fn (QueueEntry $entry) => [$entry->status === 'playing' ? 0 : 1, $entry->position])
            ->values();

        return match ($this->filter) {
            'all' => $all,
            'joki' => $all->where('service_type', 'joki')->values(),
            'vip' => $all->where('service_type', '!=', 'joki')->values(),
            'auto' => $all->where('provider', '!=', 'manual')->values(),
            'manual' => $all->where('provider', 'manual')->values(),
            default => $all,
        };
    }

    public function openAddPlayer(): void
    {
        $this->resetValidation();
        $this->showAddPlayer = true;
    }

    public function closeAddPlayer(): void
    {
        $this->showAddPlayer = false;
    }

    public function addPlayer(QueueService $queueService): void
    {
        $this->validate([
            'playerName' => ['nullable', 'string', 'min:2', 'max:120'],
            'mlbbId' => ['nullable', 'string', 'max:40'],
            'matchesTotal' => ['required', 'integer', 'min:1', 'max:'.QueueService::MAX_MATCHES],
            'serviceType' => ['required', 'in:'.implode(',', array_keys(QueueEntry::SERVICE_TYPES))],
            'note' => ['nullable', 'string', 'max:500'],
        ], [], ['playerName' => 'nama pemain', 'mlbbId' => 'ID MLBB', 'matchesTotal' => 'jatah game', 'note' => 'catatan']);

        $this->attemptInModal('playerForm', function () use ($queueService): string {
            $entry = $queueService->addPlayer($this->session, [
                'player_name' => $this->playerName,
                'mlbb_id' => $this->mlbbId,
                'matches_total' => $this->matchesTotal,
                'service_type' => $this->serviceType,
                'note' => $this->note,
            ]);
            $this->reset(['playerName', 'mlbbId', 'note', 'showAddPlayer']);
            $this->matchesTotal = 1;
            $this->serviceType = 'vip';
            $this->filter = 'all';

            return "{$entry->display_name} masuk ke antrean.";
        });
    }

    public function openEdit(int $entryId): void
    {
        $entry = $this->entry($entryId);
        $this->resetValidation();
        $this->editingId = $entry->id;
        $this->editName = (string) $entry->player_name;
        $this->editMlbbId = (string) $entry->mlbb_id;
        $this->editRemaining = $entry->matches_remaining;
        $this->editType = $entry->service_type ?: 'vip';
        $this->editNote = (string) $entry->note;
    }

    public function closeEdit(): void
    {
        $this->editingId = null;
    }

    public function savePlayer(QueueService $queueService): void
    {
        $this->validate([
            'editName' => ['nullable', 'string', 'min:2', 'max:120'],
            'editMlbbId' => ['nullable', 'string', 'max:40'],
            'editRemaining' => ['required', 'integer', 'min:0', 'max:'.QueueService::MAX_MATCHES],
            'editType' => ['required', 'in:'.implode(',', array_keys(QueueEntry::SERVICE_TYPES))],
            'editNote' => ['nullable', 'string', 'max:500'],
        ], [], ['editName' => 'nama pemain', 'editMlbbId' => 'ID MLBB', 'editRemaining' => 'sisa game', 'editNote' => 'catatan']);

        $this->attemptInModal('editForm', function () use ($queueService): string {
            $entry = $queueService->updatePlayer($this->session, $this->entry((int) $this->editingId), [
                'player_name' => $this->editName,
                'mlbb_id' => $this->editMlbbId,
                'matches_remaining' => $this->editRemaining,
                'service_type' => $this->editType,
                'note' => $this->editNote,
            ]);
            $this->editingId = null;

            return $entry->status === 'done' ? "Sisa game {$entry->display_name} habis — pemain selesai." : "Data {$entry->display_name} disimpan.";
        });
    }

    public function openImport(): void
    {
        $this->resetValidation();
        $this->showImport = true;
    }

    public function closeImport(): void
    {
        $this->showImport = false;
    }

    public function importPlayers(QueueService $queueService): void
    {
        $this->attemptInModal('importForm', function () use ($queueService): string {
            $count = $queueService->importPlayers($this->session, $this->importText);
            $this->reset(['importText', 'showImport']);
            $this->filter = 'all';

            return "{$count} pemain masuk ke antrean.";
        });
    }

    public function markPlaying(int $entryId, QueueService $queueService): void
    {
        $this->attemptLive(function () use ($entryId, $queueService): string {
            $entry = $queueService->markPlaying($this->session, $this->entry($entryId));

            return "{$entry->display_name} masuk party.";
        });
    }

    /**
     * Catat 1 game untuk SATU pemain (mis. jasa mainin akun, atau koreksi party). Jatah habis → selesai;
     * kena batas sesi → keluar party. Slot kosong diisi pemain berikutnya.
     */
    public function finishMatch(int $entryId, MatchService $matches, QueueService $queueService): void
    {
        $this->attemptLive(function () use ($entryId, $matches, $queueService): string {
            $entry = $this->entry($entryId);
            $matches->record($entry);
            $result = $queueService->settleAfterMatch($this->session, collect([$entry]));

            return match (true) {
                $result['finished']->isNotEmpty() => "Sisa game {$entry->display_name} habis.",
                $result['capped']->isNotEmpty() => "{$entry->display_name} sudah mencapai batas game sesi ini.",
                default => "Game dicatat. {$entry->display_name} sisa {$matches->remaining($entry->refresh())} game.",
            }.$this->joinedMessage($result['joined']);
        });
    }

    /** Isi party dengan pemain berikutnya (awal live, atau saat ada slot kosong). */
    public function startGame(QueueService $queueService): void
    {
        $this->attemptLive(function () use ($queueService): string {
            $joined = $queueService->fillParty($this->session);

            if ($joined->isEmpty()) {
                throw new InvalidArgumentException($queueService->party($this->session)->isEmpty()
                    ? 'Belum ada pemain yang bisa masuk party (cek sisa game, status, atau batas sesi).'
                    : 'Party sudah penuh.');
            }

            return 'Masuk party: '.$joined->pluck('display_name')->join(', ').'.';
        });
    }

    /** Game party selesai: semua anggota party berkurang 1 game. */
    /** Tandai/lepas MVP game ini (satu pemain per game). Hanya bila aturan "MVP = main lagi" aktif. */
    public function toggleMvp(int $entryId, QueueService $queueService): void
    {
        if (! $queueService->mvpFreeGame($this->session) || ! $queueService->party($this->session)->contains('id', $entryId)) {
            $this->mvpId = null;

            return;
        }

        $this->mvpId = $this->mvpId === $entryId ? null : $entryId;
    }

    /** Game party selesai dengan hasil: "win", "lose", atau kosong (mis. remake / tidak dihitung). */
    public function finishGame(QueueService $queueService, string $outcome = ''): void
    {
        $this->attemptLive(function () use ($queueService, $outcome): string {
            $outcome = in_array($outcome, ['win', 'lose'], true) ? $outcome : null;
            $result = $queueService->finishGame($this->session, $outcome, $this->mvpId);
            $this->mvpId = null;
            $label = ['win' => 'Menang', 'lose' => 'Kalah'][$outcome] ?? 'Game selesai';
            $message = $label.' — dicatat untuk '.$result['played']->count().' pemain.';

            if ($result['mvp']) {
                $message .= " ⭐ {$result['mvp']->display_name} MVP — main lagi, sisa tidak berkurang.";
            }

            if ($result['finished']->isNotEmpty()) {
                $message .= ' Habis: '.$result['finished']->pluck('display_name')->join(', ').'.';
            }

            if ($result['capped']->isNotEmpty()) {
                $message .= ' Batas sesi: '.$result['capped']->pluck('display_name')->join(', ').'.';
            }

            return $message.$this->joinedMessage($result['joined']);
        });
    }

    /** VIP Mainin akun: mulai memainkan akun pemesan. Boleh tanpa live (dikerjakan di luar stream). */
    public function startJoki(int $entryId, QueueService $queueService): void
    {
        $this->attempt(function () use ($entryId, $queueService): string {
            $entry = $queueService->startJoki($this->session, $this->entry($entryId));

            return "Akun {$entry->display_name} sedang dimainkan.";
        });
    }

    /** VIP Mainin akun: selesai dimainkan (checklist). */
    public function finishJoki(int $entryId, QueueService $queueService): void
    {
        $this->attempt(function () use ($entryId, $queueService): string {
            $entry = $queueService->finishJoki($this->session, $this->entry($entryId));

            return "Akun {$entry->display_name} selesai dimainkan.";
        });
    }

    /** Pilih pemain untuk Next game secara manual. */
    public function pickNext(int $entryId, QueueService $queueService): void
    {
        $this->attemptLive(function () use ($entryId, $queueService): string {
            $entry = $queueService->pickForNext($this->session, $this->entry($entryId));

            return "{$entry->display_name} masuk Next game.";
        });
    }

    public function unpickNext(int $entryId, QueueService $queueService): void
    {
        $this->attemptLive(function () use ($entryId, $queueService): string {
            $entry = $queueService->unpickForNext($this->session, $this->entry($entryId));

            return "{$entry->display_name} dibatalkan dari Next game.";
        });
    }

    /** Keluarkan semua pemain dari party sekaligus; slot dibiarkan kosong. */
    public function benchAll(QueueService $queueService): void
    {
        $this->attemptLive(function () use ($queueService): string {
            $count = $queueService->benchAll($this->session);
            $this->mvpId = null;

            return $count > 0 ? "{$count} pemain dikeluarkan dari party. Slot kosong — klik \"Mulai game\" untuk mengisi lagi." : 'Party sudah kosong.';
        });
    }

    /** Skor Win/Lose: hanya menambah skor sesi, tidak mengurangi sisa game pemain. */
    public function addScore(string $result, QueueService $queueService): void
    {
        $this->attemptLive(function () use ($result, $queueService): string {
            $session = $queueService->adjustScore($this->session, $result === 'lose' ? 'lose' : 'win');

            return ($result === 'lose' ? 'Kalah' : 'Menang')." dicatat. Skor {$session->wins}–{$session->losses}.";
        });
    }

    /** Koreksi skor (salah klik). */
    public function undoScore(string $result, QueueService $queueService): void
    {
        $this->attemptLive(function () use ($result, $queueService): string {
            $session = $queueService->adjustScore($this->session, $result === 'lose' ? 'lose' : 'win', -1);

            return "Skor dikoreksi: {$session->wins}–{$session->losses}.";
        });
    }

    /** Keluarkan pemain dari party (urutannya tetap); slotnya dibiarkan kosong. */
    public function bench(int $entryId, QueueService $queueService): void
    {
        $this->attemptLive(function () use ($entryId, $queueService): string {
            $entry = $queueService->bench($this->session, $this->entry($entryId));

            return "{$entry->display_name} keluar dari party. Slotnya kosong — klik \"Isi slot kosong\" kalau mau diisi.";
        });
    }

    public function openRules(): void
    {
        $user = auth()->user();
        $this->resetValidation();
        $this->partySize = (int) ($user->party_size ?: 4);
        $this->sessionCap = (int) $user->session_match_cap;
        $this->donationModeInput = array_key_exists((string) $user->donation_queue_mode, DonationQueueService::MODES) ? $user->donation_queue_mode : 'smart';
        $this->censorNames = (bool) $user->overlaySetting()->value('censor_names');
        $this->mvpFreeGame = (bool) $user->mvp_free_game;
        $this->showRules = true;
    }

    public function closeRules(): void
    {
        $this->showRules = false;
    }

    public function saveRules(): void
    {
        $this->validate([
            'partySize' => ['required', 'integer', 'min:1', 'max:'.QueueService::MAX_PARTY_SIZE],
            'sessionCap' => ['required', 'integer', 'min:0', 'max:'.self::MAX_SESSION_CAP],
            'donationModeInput' => ['required', 'in:'.implode(',', array_keys(DonationQueueService::MODES))],
        ], [], ['partySize' => 'jumlah pemain per game', 'sessionCap' => 'batas game per sesi', 'donationModeInput' => 'mode donasi']);

        auth()->user()->forceFill([
            'party_size' => $this->partySize,
            'session_match_cap' => $this->sessionCap,
            'donation_queue_mode' => $this->donationModeInput,
            'mvp_free_game' => $this->mvpFreeGame,
        ])->save();
        if (! $this->mvpFreeGame) {
            $this->mvpId = null;
        }
        auth()->user()->overlaySetting()->updateOrCreate(['user_id' => auth()->id()], ['censor_names' => $this->censorNames]);
        $this->session->unsetRelation('user');
        $this->showRules = false;
        $this->dispatch('notify', message: 'Aturan party disimpan: '.$this->partySize.' pemain per game, '
            .($this->sessionCap > 0 ? "maks {$this->sessionCap} game per orang tiap sesi." : 'tanpa batas game per sesi.'));
    }

    private function joinedMessage($joined): string
    {
        return $joined->isNotEmpty() ? ' Masuk party: '.$joined->pluck('display_name')->join(', ').'.' : '';
    }

    public function toggleStatus(int $entryId, QueueStatusService $statusService): void
    {
        $this->attempt(function () use ($entryId, $statusService): string {
            $entry = $statusService->toggle($this->session, $this->entry($entryId));

            return $entry->status === 'offline' ? "{$entry->display_name} ditandai offline." : "{$entry->display_name} siap bermain.";
        });
    }

    public function move(int $entryId, string $direction, QueueService $queueService): void
    {
        $direction = in_array($direction, ['up', 'down'], true) ? $direction : 'up';
        $this->attempt(function () use ($entryId, $direction, $queueService): ?string {
            $queueService->move($this->session, $this->entry($entryId), $direction);

            return null;
        });
    }

    public function skip(int $entryId, QueueService $queueService): void
    {
        $this->attemptLive(function () use ($entryId, $queueService): string {
            $entry = $this->entry($entryId);
            $next = $queueService->skip($this->session, $entry);

            return "{$entry->display_name} dilewati."
                .($next && $next->status === 'playing' && $next->isNot($entry) ? " Masuk party: {$next->display_name}." : '');
        });
    }

    public function remove(int $entryId, QueueService $queueService): void
    {
        $this->attempt(function () use ($entryId, $queueService): string {
            $entry = $this->entry($entryId);
            $queueService->remove($this->session, $entry);

            return "{$entry->display_name} dihapus dari antrean.";
        });
    }

    public function endSession(LiveSessionService $sessions): void
    {
        $sessions->end(auth()->user(), $this->session);
        session()->flash('notice', 'Sesi diakhiri. Ringkasannya tersimpan di arsip.');
        $this->redirect('/live-session', navigate: false);
    }

    public function render()
    {
        $this->session->loadMissing('user');
        // Anggota party dipin di paling atas, sisanya mengikuti urutan antrean.
        $all = $this->session->queueEntries()->with('donation')->whereIn('status', self::VISIBLE)->get()
            ->sortBy(fn (QueueEntry $entry) => [$entry->status === 'playing' ? 0 : 1, $entry->position])
            ->values();
        $queue = app(QueueService::class);
        $party = $all->filter(fn (QueueEntry $entry) => $entry->status === 'playing' && $entry->service_type !== 'joki')->values();
        $size = $queue->partySize($this->session);

        return view('livewire.queue-board', [
            'entries' => match ($this->filter) {
                'all' => $all,
                'joki' => $all->where('service_type', 'joki')->values(),
                'vip' => $all->where('service_type', '!=', 'joki')->values(),
                'auto' => $all->where('provider', '!=', 'manual')->values(),
                'manual' => $all->where('provider', 'manual')->values(),
                default => $all,
            },
            'counts' => [
                'all' => $all->count(),
                'auto' => $all->where('provider', '!=', 'manual')->count(),
                'manual' => $all->where('provider', 'manual')->count(),
                'joki' => $all->where('service_type', 'joki')->count(),
                'vip' => $all->where('service_type', '!=', 'joki')->count(),
            ],
            'party' => $party,
            'partySizeLimit' => $size,
            'nextUp' => $queue->nextUp($this->session, $size),
            'played' => $queue->playedThisSession($this->session),
            'cap' => $queue->sessionCap($this->session),
            'isLive' => $this->session->status === 'active',
            'donationValue' => (int) $this->session->donations()->where('donations.status', '!=', 'refunded')->sum('amount'),
            'mvpEnabled' => $queue->mvpFreeGame($this->session),
            'donationMode' => $this->session->user?->donation_queue_mode ?: 'smart',
            'matches' => app(MatchService::class),
        ]);
    }

    /**
     * Jalankan aksi antrean; pelanggaran aturan (mis. mengubah status pemain yang sedang bermain)
     * ditampilkan sebagai pesan, bukan halaman error saat streamer sedang live.
     */
    private function attempt(callable $action): void
    {
        $this->resetErrorBag('queue');

        if ($this->redirectIfStale()) {
            return;
        }

        try {
            $message = $action();
            $this->session->refresh(); // total_matches/total_players berubah lewat query langsung
            $this->dispatch('queue-updated');

            if ($message) {
                $this->dispatch('notify', message: $message);
            }
        } catch (InvalidArgumentException|RuntimeException $exception) {
            $this->addError('queue', $exception->getMessage());
        }
    }

    /** Aksi party/catat game: hanya saat sesi live aktif. */
    private function attemptLive(callable $action): void
    {
        if ($this->session->refresh()->status !== 'active') {
            $this->resetErrorBag('queue');
            $this->addError('queue', 'Mulai sesi live dulu untuk mengatur party dan mencatat game.');

            return;
        }

        $this->attempt($action);
    }

    /**
     * Papan ini memegang sesi tertentu. Bila sesi itu sudah bukan sesi antrean terkini (live baru dimulai
     * di tab lain, atau sesi live diakhiri), muat ulang supaya tidak mengubah data di sesi yang salah.
     */
    private function redirectIfStale(): bool
    {
        $current = app(LiveSessionService::class)->queueSession(auth()->user());

        if ($current->is($this->session)) {
            $this->session->refresh();

            return false;
        }

        $this->redirect('/dashboard', navigate: false);

        return true;
    }

    /** Seperti attempt(), tetapi pesan kesalahan tampil di dalam modal yang sedang terbuka. */
    private function attemptInModal(string $errorKey, callable $action): void
    {
        $this->resetErrorBag($errorKey);
        $this->attempt(function () use ($errorKey, $action): ?string {
            try {
                return $action();
            } catch (InvalidArgumentException $exception) {
                $this->addError($errorKey, $exception->getMessage());

                return null;
            }
        });
    }

    private function entry(int $entryId): QueueEntry
    {
        return $this->session->queueEntries()->whereKey($entryId)->firstOrFail();
    }
}
