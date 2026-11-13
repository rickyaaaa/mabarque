<?php

namespace Tests\Feature;

use App\Models\LiveSession;
use App\Models\QueueEntry;
use App\Models\User;
use App\Models\VipPackage;
use App\Services\OverlayTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Sesi live, hitungan match, overlay OBS, dan paket VIP lewat API dashboard. */
class SessionMatchOverlayTest extends TestCase
{
    use RefreshDatabase;

    private User $streamer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->streamer = User::factory()->create(['slug' => 'andra']);
        $this->actingAs($this->streamer);
    }

    private function entry(LiveSession $session, int $quota, string $status = 'ready'): QueueEntry
    {
        return QueueEntry::create([
            'live_session_id' => $session->id, 'player_name' => 'Raka ML', 'matches_total' => $quota, 'status' => $status, 'position' => 1,
        ]);
    }

    public function test_session_lifecycle_start_end_archive_reset(): void
    {
        $first = $this->postJson('/api/live-sessions', ['title' => 'Rabu malam'])->assertCreated()->json('data.id');
        $second = $this->postJson('/api/live-sessions', ['title' => 'Sesi kedua'])->assertCreated()->json('data.id');

        // Memulai sesi baru otomatis mengakhiri sesi aktif sebelumnya.
        $this->assertSame('ended', LiveSession::find($first)->status);
        $this->assertSame(1, LiveSession::where('status', 'active')->count());

        $session = LiveSession::find($second);
        $this->entry($session, 3);
        $this->entry($session, 2, 'offline');

        $this->postJson("/api/live-sessions/{$second}/reset-queue")->assertOk()->assertJsonPath('data.cleared', 2);
        $this->postJson("/api/live-sessions/{$second}/archive")->assertOk()->assertJsonPath('data.status', 'ended');
        $this->getJson('/api/live-sessions/archives')->assertOk()->assertJsonCount(2, 'data');

        $this->postJson('/api/live-sessions', ['title' => ''])->assertJsonValidationErrors(['title']);
    }

    public function test_session_actions_are_owner_only(): void
    {
        $foreign = LiveSession::create(['user_id' => User::factory()->create()->id, 'title' => 'Bukan punyamu', 'status' => 'active']);

        foreach (['end', 'archive', 'reset-queue'] as $action) {
            $this->postJson("/api/live-sessions/{$foreign->id}/{$action}")->assertNotFound();
        }
        $this->getJson("/api/live-sessions/{$foreign->id}")->assertNotFound();
        $this->assertSame('active', $foreign->refresh()->status);
    }

    public function test_match_recording_updates_quota_session_totals_and_is_idempotent(): void
    {
        $session = LiveSession::create(['user_id' => $this->streamer->id, 'title' => 'Mabar', 'status' => 'active']);
        $entry = $this->entry($session, 2);

        $first = $this->postJson("/api/queue-entries/{$entry->id}/matches", [], ['Idempotency-Key' => 'a'])
            ->assertCreated()->assertJsonPath('data.quota_status', 'low')->json('data.match.id');

        // Kirim ulang dengan key yang sama (mis. klik ganda) → hasil sama, tidak tercatat dua kali.
        $this->postJson("/api/queue-entries/{$entry->id}/matches", [], ['Idempotency-Key' => 'a'])
            ->assertOk()->assertJsonPath('data.match.id', $first);

        $this->postJson("/api/queue-entries/{$entry->id}/matches", [], ['Idempotency-Key' => 'b'])
            ->assertCreated()->assertJsonPath('data.matches_remaining', 0)->assertJsonPath('data.quota_status', 'empty');

        // Jatah habis → 422 (sebelumnya 500).
        $this->postJson("/api/queue-entries/{$entry->id}/matches", [], ['Idempotency-Key' => 'c'])
            ->assertUnprocessable()->assertJsonPath('message', 'Jatah match pemain ini sudah habis.');

        $this->postJson("/api/queue-entries/{$entry->id}/matches")->assertUnprocessable(); // tanpa Idempotency-Key

        $this->assertSame(2, $session->refresh()->total_matches);
        $this->getJson("/api/live-sessions/{$session->id}/matches/today")->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/api/channels/andra/queue')->assertJsonPath('data.session.total_matches', 2);

        $this->deleteJson("/api/matches/{$first}")->assertOk()->assertJsonPath('matches_remaining', 1);
        $this->assertSame(1, $session->refresh()->total_matches);
    }

    public function test_overlay_settings_and_token(): void
    {
        $this->putJson('/api/overlay-settings', [
            'accent_color' => '#6B63FF', 'font_scale' => 110, 'max_players' => 5, 'show_now_playing' => true, 'transparent_mode' => false,
        ])->assertOk()->assertJsonPath('data.font_scale', 110);

        $this->putJson('/api/overlay-settings', [
            'accent_color' => 'merah', 'font_scale' => 300, 'max_players' => 50, 'show_now_playing' => true, 'transparent_mode' => true,
        ])->assertJsonValidationErrors(['accent_color', 'font_scale', 'max_players']);

        $token = app(OverlayTokenService::class)->issue($this->streamer);

        $this->getJson("/api/overlay/{$token}")->assertOk()
            ->assertJsonPath('data.queue.status', 'offline')
            ->assertJsonPath('data.overlay_settings.accent_color', '#6B63FF');
        $this->getJson('/api/overlay/token-palsu')->assertNotFound();

        // Token baru menggantikan yang lama.
        app(OverlayTokenService::class)->issue($this->streamer);
        $this->getJson("/api/overlay/{$token}")->assertNotFound();
    }

    public function test_vip_packages_crud_toggle_ordering_and_public_list(): void
    {
        $this->postJson('/api/vip-packages', ['name' => 'VIP 3 Match', 'price' => 35000, 'match_count' => 3])->assertCreated()
            ->assertJsonPath('data.sort_order', 1)->assertJsonPath('data.is_active', true);
        $five = $this->postJson('/api/vip-packages', ['name' => 'VIP 5 Match', 'price' => 50000, 'match_count' => 5])->assertCreated()
            ->assertJsonPath('data.sort_order', 2)->json('data.id');

        $this->postJson('/api/vip-packages', ['name' => 'X', 'price' => 10, 'match_count' => 0])
            ->assertJsonValidationErrors(['name', 'price', 'match_count']);

        $this->putJson("/api/vip-packages/{$five}", ['name' => 'VIP 5 Match Promo', 'price' => 45000, 'match_count' => 5])
            ->assertOk()->assertJsonPath('data.price', 45000);
        $this->patchJson("/api/vip-packages/{$five}/toggle", ['is_active' => false])->assertOk()->assertJsonPath('data.is_active', false);

        $this->getJson('/api/vip-packages')->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/api/channels/andra/vip-packages')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'VIP 3 Match');

        $foreign = VipPackage::create(['user_id' => User::factory()->create()->id, 'name' => 'Punya orang', 'price' => 10000, 'match_count' => 1]);
        $this->putJson("/api/vip-packages/{$foreign->id}", ['name' => 'Dibajak', 'price' => 1000, 'match_count' => 1])->assertNotFound();
        $this->patchJson("/api/vip-packages/{$foreign->id}/toggle", ['is_active' => false])->assertNotFound();
        $this->assertSame('Punya orang', $foreign->refresh()->name);
    }

    public function test_donation_list_is_owner_scoped_and_clamps_page_size(): void
    {
        $this->getJson('/api/donations?per_page=-5')->assertOk()->assertJsonPath('data.per_page', 1);
        $this->getJson('/api/donations?per_page=5000')->assertOk()->assertJsonPath('data.per_page', 100);
    }
}
