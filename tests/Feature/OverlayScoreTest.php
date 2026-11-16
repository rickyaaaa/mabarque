<?php

namespace Tests\Feature;

use App\Http\Controllers\OverlayController;
use App\Livewire\OverlaySettingsPanel;
use App\Livewire\QueueBoard;
use App\Models\User;
use App\Services\LiveSessionService;
use App\Services\OverlayTokenService;
use App\Services\QueueService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Skor Win/Lose, harga paket di overlay, sensor dari papan antrean, dan menu yang dirapikan. */
class OverlayScoreTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create(['party_size' => 1]);
        $this->owner->vipPackages()->createMany([
            ['name' => 'Mabar 2 game', 'price' => 25000, 'match_count' => 2, 'sort_order' => 1],
            ['name' => 'Mabar 5 game', 'price' => 50000, 'match_count' => 5, 'sort_order' => 2],
            ['name' => 'Lama', 'price' => 10000, 'match_count' => 1, 'sort_order' => 3, 'is_active' => false],
        ]);
        $this->actingAs($this->owner);
    }

    public function test_compact_price(): void
    {
        $this->assertSame('25K', OverlayController::compactPrice(25000));
        $this->assertSame('12.5K', OverlayController::compactPrice(12500));
        $this->assertSame('1.5JT', OverlayController::compactPrice(1500000));
        $this->assertSame('500', OverlayController::compactPrice(500));
    }

    public function test_overlay_shows_score_and_active_packages_and_settings_can_hide_them(): void
    {
        $session = app(LiveSessionService::class)->start($this->owner, 'Live');
        $queue = app(QueueService::class);
        $queue->addManual($session, 'Dey', 3);
        $queue->fillParty($session);
        $queue->finishGame($session, 'win');
        $queue->finishGame($session, 'lose');
        $queue->finishGame($session, 'win');

        $token = app(OverlayTokenService::class)->current($this->owner);
        $this->getJson("/api/overlay/{$token}")->assertOk()
            ->assertJsonPath('data.score', ['wins' => 2, 'losses' => 1])
            ->assertJsonPath('data.packages', [['name' => 'Mabar 2 game', 'price' => '25K', 'games' => 2], ['name' => 'Mabar 5 game', 'price' => '50K', 'games' => 5]])
            ->assertJsonPath('data.overlay_settings.show_score', true)
            ->assertJsonPath('data.overlay_settings.show_packages', true);

        // Koreksi skor & sembunyikan dari overlay.
        Livewire::test(OverlaySettingsPanel::class)
            ->call('adjustScore', 'win', -1)
            ->call('adjustScore', 'loss', 1)
            ->assertSee('W 1')->assertSee('L 2')
            ->set('showScore', false)
            ->set('showPackages', false)
            ->call('save');

        $this->assertSame([1, 2], [$session->refresh()->wins, $session->losses]);
        $this->getJson("/api/overlay/{$token}")
            ->assertJsonPath('data.overlay_settings.show_score', false)
            ->assertJsonPath('data.overlay_settings.show_packages', false);

        Livewire::test(OverlaySettingsPanel::class)->call('resetScore');
        $this->assertSame([0, 0], [$session->refresh()->wins, $session->losses]);

        // Belum live: tidak ada skor.
        app(LiveSessionService::class)->end($this->owner, $session);
        $this->getJson("/api/overlay/{$token}")->assertJsonPath('data.score', null);
    }

    public function test_censor_can_be_toggled_from_queue_board_rules(): void
    {
        $session = app(LiveSessionService::class)->start($this->owner, 'Live');
        app(QueueService::class)->addManual($session, 'WangSukiJomok', 3);

        Livewire::test(QueueBoard::class, ['session' => $session])
            ->call('openRules')
            ->assertSet('censorNames', false)
            ->set('censorNames', true)
            ->call('saveRules')
            ->assertHasNoErrors();

        $this->assertTrue((bool) $this->owner->overlaySetting()->value('censor_names'));
        $this->getJson("/api/channels/{$this->owner->slug}/queue")->assertJsonPath('data.queue.0.player_name', 'WangSu*******');
        Livewire::test(OverlaySettingsPanel::class)->assertSet('censorNames', true); // satu pengaturan yang sama
    }

    public function test_overlay_shape_and_size_are_configurable_with_accurate_preview(): void
    {
        $token = app(OverlayTokenService::class)->current($this->owner);
        $this->getJson("/api/overlay/{$token}")
            ->assertJsonPath('data.overlay_settings.layout', 'vertical')
            ->assertJsonPath('data.overlay_settings.card_width', 440)
            ->assertJsonPath('data.overlay_settings.card_height', null);

        $panel = Livewire::test(OverlaySettingsPanel::class)
            ->call('applyPreset', 'bar-bottom')
            ->assertSet('layout', 'horizontal')
            ->assertSet('cardWidth', 1280)
            ->assertSet('autoHeight', false)
            ->assertSet('cardHeight', 140)
            ->assertSee('1320 × 180')                       // ukuran browser source OBS (kartu + 40)
            ->assertSee('preview=1&amp;layout=horizontal&amp;w=1280&amp;h=140', false)
            ->assertSee('belum disimpan')
            ->set('fontScale', 180)
            ->call('save')
            ->assertHasNoErrors();

        $this->getJson("/api/overlay/{$token}")
            ->assertJsonPath('data.overlay_settings.layout', 'horizontal')
            ->assertJsonPath('data.overlay_settings.card_width', 1280)
            ->assertJsonPath('data.overlay_settings.card_height', 140)
            ->assertJsonPath('data.overlay_settings.font_scale', 180);

        // Tinggi otomatis disimpan sebagai null; batas ukuran divalidasi.
        $panel->set('autoHeight', true)->call('save');
        $this->getJson("/api/overlay/{$token}")->assertJsonPath('data.overlay_settings.card_height', null);
        $panel->set('cardWidth', 5000)->call('save')->assertHasErrors(['cardWidth']);
        $panel->set('cardWidth', 600)->set('fontScale', 300)->call('save')->assertHasErrors(['fontScale']);

        // Jumlah pemain di overlay bebas 1–30.
        $panel->set('fontScale', 100)->set('maxPlayers', 17)->call('save')->assertHasNoErrors();
        $this->getJson("/api/overlay/{$token}")->assertJsonPath('data.overlay_settings.max_players', 17);
        $panel->set('maxPlayers', 0)->call('save')->assertHasErrors(['maxPlayers']);
        $panel->set('maxPlayers', 31)->call('save')->assertHasErrors(['maxPlayers']);
    }

    public function test_overlay_background_color_and_image_can_be_customized(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        $token = app(OverlayTokenService::class)->current($this->owner);
        $this->getJson("/api/overlay/{$token}")
            ->assertJsonPath('data.overlay_settings.background_color', null)
            ->assertJsonPath('data.overlay_settings.background_image_url', null);

        $panel = Livewire::test(OverlaySettingsPanel::class)
            ->set('backgroundColor', '#1f2a44')
            ->assertSee('bg=1f2a44', false)
            ->call('save')->assertHasNoErrors();
        $this->getJson("/api/overlay/{$token}")->assertJsonPath('data.overlay_settings.background_color', '#1F2A44');
        $panel->set('backgroundColor', 'biru')->call('save')->assertHasErrors(['backgroundColor']);

        // Gambar langsung tersimpan dan bisa dibuka lewat link overlay.
        $panel->set('backgroundColor', '#1F2A44')
            ->set('backgroundUpload', \Illuminate\Http\UploadedFile::fake()->image('latar.png', 800, 400))
            ->assertHasNoErrors();
        $path = $this->owner->overlaySetting()->first()->background_image;
        \Illuminate\Support\Facades\Storage::disk('local')->assertExists($path);
        $url = $this->getJson("/api/overlay/{$token}")->json('data.overlay_settings.background_image_url');
        $this->assertStringContainsString('/o/'.$token.'/bg/', $url);
        $this->get(parse_url($url, PHP_URL_PATH))->assertOk();
        $this->get('/o/'.$token.'/bg/bukan-punya-kamu.png')->assertNotFound();

        $panel->set('backgroundUpload', \Illuminate\Http\UploadedFile::fake()->create('virus.pdf', 10, 'application/pdf'))
            ->assertHasErrors(['backgroundUpload']);

        $panel->call('removeBackgroundImage');
        \Illuminate\Support\Facades\Storage::disk('local')->assertMissing($path);
        $this->getJson("/api/overlay/{$token}")->assertJsonPath('data.overlay_settings.background_image_url', null);
    }

    public function test_overlay_follows_browser_source_size_by_default(): void
    {
        $token = app(OverlayTokenService::class)->current($this->owner);
        $this->getJson("/api/overlay/{$token}")->assertJsonPath('data.overlay_settings.fit_source', true);

        $panel = Livewire::test(OverlaySettingsPanel::class)
            ->assertSet('fitSource', true)
            ->assertSee('Ikuti ukuran Browser Source')
            ->assertSee('fit=1', false)
            ->assertDontSee('id="card-width"', false)      // lebar/tinggi disembunyikan di mode ini
            ->set('layout', 'horizontal')
            ->assertSee('1280 × 160');                   // contoh Browser Source untuk bar

        $panel->set('fitSource', false)->assertSee('id="card-width"', false)->call('save')->assertHasNoErrors();
        $this->getJson("/api/overlay/{$token}")->assertJsonPath('data.overlay_settings.fit_source', false);
    }

    public function test_statistik_menu_is_gone(): void
    {
        $this->get('/dashboard')->assertOk()->assertDontSee('Statistik')->assertDontSee('href="/match-count"', false);
    }
}
