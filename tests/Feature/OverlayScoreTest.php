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

    public function test_statistik_menu_is_gone(): void
    {
        $this->get('/dashboard')->assertOk()->assertDontSee('Statistik')->assertDontSee('href="/match-count"', false);
    }
}
