<?php

namespace App\Livewire;

use App\Models\OverlaySetting;
use App\Services\OverlayTokenService;
use App\Services\QueueService;
use Livewire\Component;

/**
 * Pengaturan overlay OBS (/overlay-settings): warna aksen, ukuran huruf, jumlah pemain, now playing,
 * mode transparan — dengan pratinjau langsung dari antrean asli dan link browser source.
 */
class OverlaySettingsPanel extends Component
{
    public const PLAYER_OPTIONS = [3, 5, 8, 10];

    public string $accentColor = '#D82A1C';

    public int $fontScale = 100;

    public int $maxPlayers = 5;

    public bool $showNowPlaying = true;

    public bool $transparentMode = true;

    public function mount(): void
    {
        $this->loadSaved();
    }

    public function save(): void
    {
        $this->accentColor = strtoupper(trim($this->accentColor));

        $validated = $this->validate([
            'accentColor' => ['required', 'regex:/^#[0-9A-F]{6}$/'],
            'fontScale' => ['required', 'integer', 'between:80,130'],
            'maxPlayers' => ['required', 'integer', 'in:'.implode(',', self::PLAYER_OPTIONS)],
            'showNowPlaying' => ['boolean'],
            'transparentMode' => ['boolean'],
        ], ['accentColor.regex' => 'Warna harus kode hex 6 digit, contoh #D82A1C.']);

        auth()->user()->overlaySetting()->updateOrCreate(['user_id' => auth()->id()], [
            'accent_color' => $validated['accentColor'],
            'font_scale' => $validated['fontScale'],
            'max_players' => $validated['maxPlayers'],
            'show_now_playing' => $validated['showNowPlaying'],
            'transparent_mode' => $validated['transparentMode'],
        ]);

        $this->dispatch('notify', message: 'Pengaturan overlay disimpan. OBS memperbarui tampilan dalam beberapa detik.');
    }

    public function resetToSaved(): void
    {
        $this->resetValidation();
        $this->loadSaved();
    }

    public function regenerateLink(OverlayTokenService $tokens): void
    {
        $tokens->issue(auth()->user());
        $this->dispatch('notify', message: 'Link overlay baru dibuat. Ganti URL browser source di OBS — link lama sudah tidak berlaku.');
    }

    public function render(OverlayTokenService $tokens, QueueService $queue)
    {
        $user = auth()->user();
        $session = $user->liveSessions()->active()->latest('started_at')->first();
        $saved = $user->overlaySetting()->first(); // query baru: relasi yang ter-cache bisa basi setelah save()

        $entries = $session?->queueEntries()->whereIn('status', ['playing', 'ready'])->get() ?? collect();
        $current = $session ? $queue->current($session) : null;

        return view('livewire.overlay-settings-panel', [
            'overlayUrl' => $tokens->url($tokens->current($user)),
            'session' => $session,
            'current' => $current?->status === 'playing' ? $current : null,
            'upcoming' => $entries->where('status', 'ready')->take($this->maxPlayers)->values(),
            'dirty' => $saved === null || $this->snapshot() !== $this->snapshot($saved),
            'channelName' => $user->channel_name,
        ]);
    }

    private function loadSaved(): void
    {
        $saved = auth()->user()->overlaySetting()->first() ?? new OverlaySetting([
            'accent_color' => '#D82A1C', 'font_scale' => 100, 'max_players' => 5, 'show_now_playing' => true, 'transparent_mode' => true,
        ]);

        $this->accentColor = strtoupper($saved->accent_color ?: '#D82A1C');
        $this->fontScale = (int) $saved->font_scale;
        $this->maxPlayers = in_array((int) $saved->max_players, self::PLAYER_OPTIONS, true) ? (int) $saved->max_players : 5;
        $this->showNowPlaying = (bool) $saved->show_now_playing;
        $this->transparentMode = (bool) $saved->transparent_mode;
    }

    private function snapshot(?OverlaySetting $saved = null): array
    {
        return $saved
            ? [strtoupper($saved->accent_color), (int) $saved->font_scale, (int) $saved->max_players, (bool) $saved->show_now_playing, (bool) $saved->transparent_mode]
            : [strtoupper($this->accentColor), $this->fontScale, $this->maxPlayers, $this->showNowPlaying, $this->transparentMode];
    }
}
