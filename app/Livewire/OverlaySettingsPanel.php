<?php

namespace App\Livewire;

use App\Http\Controllers\OverlayController;
use App\Models\OverlaySetting;
use App\Services\OverlayTokenService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Pengaturan overlay OBS (/overlay-settings): warna aksen, warna/gambar latar, ukuran huruf, jumlah pemain, now playing,
 * mode transparan — dengan pratinjau langsung dari antrean asli dan link browser source.
 */
class OverlaySettingsPanel extends Component
{
    use WithFileUploads;

    /** Batas ukuran gambar latar (KB). */
    public const MAX_IMAGE_KB = 5120;

    /** Jumlah pemain antrean yang tampil di overlay: bebas diatur streamer dalam rentang ini. */
    public const MIN_PLAYERS = 1;

    public const MAX_PLAYERS = 30;

    /** Ruang di sekitar kartu (padding halaman 16px × 2 + bayangan 7px), dibulatkan. */
    public const FRAME_PADDING = 40;

    public string $accentColor = '#C5F02E';

    /** Warna latar kartu overlay (putih bawaan). */
    public string $backgroundColor = '#FFFFFF';

    /** Tebal lapisan warna latar di atas gambar latar, 0–90%. */
    public int $backgroundDim = 35;

    /** Gambar latar yang baru dipilih (langsung diunggah & disimpan). */
    public $backgroundUpload = null;

    public int $fontScale = 100;

    public int $maxPlayers = 5;

    public bool $showNowPlaying = true;

    public bool $transparentMode = true;

    public bool $censorNames = false;

    public bool $showScore = true;

    public bool $showPackages = true;

    /** Sinkron OBS: mulai sesi saat OBS mulai streaming / akhiri saat OBS berhenti. */
    public bool $obsAutoStart = true;

    public bool $obsAutoEnd = false;

    /** Bentuk & ukuran kartu overlay (piksel). Tinggi otomatis = mengikuti isi. */
    public string $layout = 'vertical';

    /** true = overlay mengisi penuh Browser Source OBS (ukuran diatur di OBS); false = ukuran diatur di sini. */
    public bool $fitSource = true;

    public int $cardWidth = 440;

    public bool $autoHeight = true;

    public int $cardHeight = 160;

    public function mount(): void
    {
        $this->loadSaved();
    }

    public function save(): void
    {
        $this->accentColor = strtoupper(trim($this->accentColor));
        $this->backgroundColor = strtoupper(trim($this->backgroundColor));

        $validated = $this->validate([
            'accentColor' => ['required', 'regex:/^#[0-9A-F]{6}$/'],
            'backgroundColor' => ['required', 'regex:/^#[0-9A-F]{6}$/'],
            'backgroundDim' => ['required', 'integer', 'between:0,90'],
            'fontScale' => ['required', 'integer', 'between:60,200'],
            'maxPlayers' => ['required', 'integer', 'between:'.self::MIN_PLAYERS.','.self::MAX_PLAYERS],
            'showNowPlaying' => ['boolean'],
            'transparentMode' => ['boolean'],
            'censorNames' => ['boolean'],
            'showScore' => ['boolean'],
            'showPackages' => ['boolean'],
            'obsAutoStart' => ['boolean'],
            'obsAutoEnd' => ['boolean'],
            'layout' => ['required', 'in:vertical,horizontal'],
            'fitSource' => ['boolean'],
            'cardWidth' => ['required', 'integer', 'between:240,1920'],
            'autoHeight' => ['boolean'],
            'cardHeight' => ['required', 'integer', 'between:80,1080'],
        ], ['accentColor.regex' => 'Warna harus kode hex 6 digit, contoh #D82A1C.', 'backgroundColor.regex' => 'Warna latar harus kode hex 6 digit, contoh #111111.'], ['cardWidth' => 'lebar', 'cardHeight' => 'tinggi', 'backgroundDim' => 'lapisan warna']);

        auth()->user()->overlaySetting()->updateOrCreate(['user_id' => auth()->id()], [
            'accent_color' => $validated['accentColor'],
            'background_color' => $validated['backgroundColor'] === '#FFFFFF' ? null : $validated['backgroundColor'],
            'background_dim' => $validated['backgroundDim'],
            'font_scale' => $validated['fontScale'],
            'max_players' => $validated['maxPlayers'],
            'show_now_playing' => $validated['showNowPlaying'],
            'transparent_mode' => $validated['transparentMode'],
            'censor_names' => $validated['censorNames'],
            'show_score' => $validated['showScore'],
            'show_packages' => $validated['showPackages'],
            'obs_auto_start' => $validated['obsAutoStart'],
            'obs_auto_end' => $validated['obsAutoEnd'],
            'layout' => $validated['layout'],
            'fit_source' => $validated['fitSource'],
            'card_width' => $validated['cardWidth'],
            'card_height' => $validated['autoHeight'] ? null : $validated['cardHeight'],
        ]);

        $this->dispatch('notify', message: 'Pengaturan overlay disimpan. OBS memperbarui tampilan dalam beberapa detik.');
    }

    /** Gambar latar langsung disimpan begitu dipilih (file lama dihapus). */
    public function updatedBackgroundUpload(): void
    {
        $this->validate(
            ['backgroundUpload' => ['required', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:'.self::MAX_IMAGE_KB]],
            ['backgroundUpload.max' => 'Gambar maksimal 5 MB.', 'backgroundUpload.mimes' => 'Format gambar harus JPG, PNG, WEBP, atau GIF.', 'backgroundUpload.image' => 'File harus berupa gambar.'],
        );

        $user = auth()->user();
        $extension = strtolower($this->backgroundUpload->guessExtension() ?: $this->backgroundUpload->getClientOriginalExtension());
        $extension = in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true) ? $extension : 'png';
        $path = $this->backgroundUpload->storeAs('overlay-backgrounds', $user->id.'-'.Str::lower(Str::random(20)).'.'.$extension, 'local');

        $settings = $this->settingsRow();
        $old = $settings->background_image;
        $settings->update(['background_image' => $path]);

        if ($old && $old !== $path) {
            Storage::disk('local')->delete($old);
        }

        $this->backgroundUpload = null;
        $this->dispatch('notify', message: 'Gambar latar dipasang. OBS memperbarui tampilan dalam beberapa detik.');
    }

    public function removeBackgroundImage(): void
    {
        $settings = auth()->user()->overlaySetting()->first();

        if ($settings?->background_image) {
            Storage::disk('local')->delete($settings->background_image);
            $settings->update(['background_image' => null]);
            $this->dispatch('notify', message: 'Gambar latar dihapus.');
        }
    }

    private function settingsRow(): OverlaySetting
    {
        return auth()->user()->overlaySetting()->firstOrCreate(['user_id' => auth()->id()], ['accent_color' => '#C5F02E']);
    }

    public function resetToSaved(): void
    {
        $this->resetValidation();
        $this->loadSaved();
    }

    /** Koreksi skor Win/Lose sesi live (mis. salah klik Menang/Kalah). */
    public function adjustScore(string $type, int $delta): void
    {
        $session = auth()->user()->liveSessions()->active()->latest('started_at')->first();
        $column = $type === 'loss' ? 'losses' : 'wins';

        if ($session === null) {
            $this->dispatch('notify', message: 'Skor hanya bisa diubah saat sesi live berjalan.');

            return;
        }

        $session->forceFill([$column => max(0, (int) $session->{$column} + ($delta >= 0 ? 1 : -1))])->save();
    }

    public function resetScore(): void
    {
        auth()->user()->liveSessions()->active()->latest('started_at')->first()?->forceFill(['wins' => 0, 'losses' => 0])->save();
        $this->dispatch('notify', message: 'Skor sesi ini direset ke 0–0.');
    }

    public function regenerateLink(OverlayTokenService $tokens): void
    {
        $tokens->issue(auth()->user());
        $this->dispatch('notify', message: 'Link overlay baru dibuat. Ganti URL browser source di OBS — link lama sudah tidak berlaku.');
    }

    public function render(OverlayTokenService $tokens)
    {
        $user = auth()->user();
        $session = $user->liveSessions()->active()->latest('started_at')->first();
        $saved = $user->overlaySetting()->first(); // query baru: relasi yang ter-cache bisa basi setelah save()

        return view('livewire.overlay-settings-panel', [
            'overlayUrl' => $tokens->url($tokens->current($user)),
            'session' => $session,
            'dirty' => $saved === null || $this->snapshot() !== $this->snapshot($saved),
            'preview' => $this->previewFrame($tokens->url($tokens->current($user))),
            'channelName' => $user->channel_name,
            'backgroundImageUrl' => OverlayController::backgroundUrl($tokens->current($user), $saved?->background_image),
        ]);
    }

    private function loadSaved(): void
    {
        $saved = auth()->user()->overlaySetting()->first() ?? new OverlaySetting([
            'accent_color' => '#C5F02E', 'font_scale' => 100, 'max_players' => 5, 'show_now_playing' => true, 'transparent_mode' => true, 'censor_names' => false,
        ]);

        $this->accentColor = strtoupper($saved->accent_color ?: '#C5F02E');
        $this->backgroundColor = strtoupper($saved->background_color ?: '#FFFFFF');
        $this->backgroundDim = (int) ($saved->background_dim ?? 35);
        $this->fontScale = (int) $saved->font_scale;
        $this->maxPlayers = max(self::MIN_PLAYERS, min(self::MAX_PLAYERS, (int) ($saved->max_players ?: 5)));
        $this->showNowPlaying = (bool) $saved->show_now_playing;
        $this->transparentMode = (bool) $saved->transparent_mode;
        $this->censorNames = (bool) $saved->censor_names;
        $this->showScore = $saved->show_score === null ? true : (bool) $saved->show_score;
        $this->showPackages = $saved->show_packages === null ? true : (bool) $saved->show_packages;
        $this->obsAutoStart = $saved->obs_auto_start === null ? true : (bool) $saved->obs_auto_start;
        $this->obsAutoEnd = (bool) $saved->obs_auto_end;
        $this->layout = $saved->layout === 'horizontal' ? 'horizontal' : 'vertical';
        $this->fitSource = $saved->fit_source === null ? true : (bool) $saved->fit_source;
        $this->cardWidth = (int) ($saved->card_width ?: 440);
        $this->autoHeight = ! $saved->card_height;
        $this->cardHeight = (int) ($saved->card_height ?: ($this->layout === 'horizontal' ? 140 : 520));
    }

    /** Ukuran layout siap pakai (lebar × tinggi kartu). */
    public function applyPreset(string $preset): void
    {
        [$layout, $width, $height] = match ($preset) {
            'bar-bottom' => ['horizontal', 1280, 140],
            'bar-wide' => ['horizontal', 1920, 160],
            'card-big' => ['vertical', 600, null],
            default => ['vertical', 440, null],
        };

        $this->layout = $layout;
        $this->fitSource = false;
        $this->cardWidth = $width;
        $this->autoHeight = $height === null;
        $this->cardHeight = $height ?? $this->cardHeight;
    }

    /**
     * Pratinjau = overlay asli dalam iframe dengan pengaturan yang sedang diatur (belum disimpan),
     * diperkecil agar muat. Ukuran browser source OBS yang disarankan = kartu + ruang bayangan.
     */
    private function previewFrame(string $overlayUrl): array
    {
        $width = max(240, min(1920, $this->cardWidth));
        $height = $this->autoHeight ? null : max(80, min(1080, $this->cardHeight));
        $frameWidth = $width + self::FRAME_PADDING;
        $frameHeight = $height !== null ? $height + self::FRAME_PADDING : ($this->layout === 'horizontal' ? 220 : 640);

        // Mode ikuti Browser Source: pratinjau memakai contoh ukuran Browser Source untuk bentuk yang dipilih.
        if ($this->fitSource) {
            [$frameWidth, $frameHeight] = $this->layout === 'horizontal' ? [1280, 160] : [480, 600];
        }
        $fit = min(1, 400 / $frameWidth, 420 / $frameHeight);

        $query = http_build_query([
            'preview' => 1,
            'layout' => $this->layout,
            'w' => $width,
            'h' => $height ?? 'auto',
            'accent' => ltrim(preg_match('/^#[0-9A-Fa-f]{6}$/', $this->accentColor) ? $this->accentColor : '#C5F02E', '#'),
            'bg' => ltrim(preg_match('/^#[0-9A-Fa-f]{6}$/', $this->backgroundColor) ? $this->backgroundColor : '#FFFFFF', '#'),
            'dim' => max(0, min(90, $this->backgroundDim)),
            'scale' => max(60, min(200, $this->fontScale)),
            'players' => max(self::MIN_PLAYERS, min(self::MAX_PLAYERS, $this->maxPlayers)),
            'now' => (int) $this->showNowPlaying,
            'score' => (int) $this->showScore,
            'packages' => (int) $this->showPackages,
            'transparent' => (int) $this->transparentMode,
            'fit' => (int) $this->fitSource,
        ]);

        return [
            'src' => $overlayUrl.'?'.$query,
            'frame_width' => $frameWidth,
            'frame_height' => $frameHeight,
            'fit' => round($fit, 4),
            'obs_width' => $frameWidth,
            'obs_height' => $this->fitSource || $height !== null ? $frameHeight : null,
            'fit_source' => $this->fitSource,
        ];
    }

    private function snapshot(?OverlaySetting $saved = null): array
    {
        return $saved
            ? [strtoupper($saved->accent_color), (int) $saved->font_scale, (int) $saved->max_players, (bool) $saved->show_now_playing, (bool) $saved->transparent_mode, (bool) $saved->censor_names, $saved->show_score === null ? true : (bool) $saved->show_score, $saved->show_packages === null ? true : (bool) $saved->show_packages, $saved->layout === 'horizontal' ? 'horizontal' : 'vertical', (int) ($saved->card_width ?: 440), $saved->card_height ? (int) $saved->card_height : null, $saved->fit_source === null ? true : (bool) $saved->fit_source, strtoupper($saved->background_color ?: '#FFFFFF'), (int) ($saved->background_dim ?? 35), $saved->obs_auto_start === null ? true : (bool) $saved->obs_auto_start, (bool) $saved->obs_auto_end]
            : [strtoupper($this->accentColor), $this->fontScale, $this->maxPlayers, $this->showNowPlaying, $this->transparentMode, $this->censorNames, $this->showScore, $this->showPackages, $this->layout, $this->cardWidth, $this->autoHeight ? null : $this->cardHeight, $this->fitSource, strtoupper($this->backgroundColor), $this->backgroundDim, $this->obsAutoStart, $this->obsAutoEnd];
    }
}
