<?php

namespace Tests\Feature;

use App\Livewire\ConnectionManager;
use App\Livewire\DonationInbox;
use App\Livewire\OverlaySettingsPanel;
use App\Livewire\PackageManager;
use App\Models\Donation;
use App\Models\LiveSession;
use App\Models\PlatformConnection;
use App\Models\QueueEntry;
use App\Models\User;
use App\Services\OverlayTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Tahap C frontend: paket VIP, donasi & koneksi, overlay OBS, dan halaman antrean publik. */
class StageCPagesTest extends TestCase
{
    use RefreshDatabase;
    use \Tests\Concerns\SendsProviderWebhooks;

    private const SECRET = 'rahasia-webhook-uji-0123456789';

    private User $streamer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->streamer = User::factory()->create(['slug' => 'andra', 'channel_name' => 'Andra Rizky']);
        $this->actingAs($this->streamer);
    }

    private function connection(string $status = 'active'): PlatformConnection
    {
        return PlatformConnection::create(['user_id' => $this->streamer->id, 'provider' => 'saweria', 'webhook_url' => '', 'signing_secret' => self::SECRET, 'status' => $status]);
    }

    /** Webhook Saweria dengan format asli (lihat SendsProviderWebhooks). */
    private function sendWebhook(PlatformConnection $connection, array $donation)
    {
        return $this->sendSaweria($connection, $this->saweriaPayload($donation['id'], $donation['donor_name'], $donation['amount'], ['message' => $donation['message'] ?? 'Semangat bang!']), self::SECRET);
    }

    public function test_donation_links_are_validated_and_shown_on_package_cards(): void
    {
        $this->streamer->vipPackages()->create(['name' => 'Mabar 2 game', 'price' => 25000, 'match_count' => 2]);

        $manager = Livewire::test(PackageManager::class)
            ->set('links.sociabuzz', 'https://evil.example.com/ganindraw')
            ->call('saveLinks')
            ->assertHasErrors(['links'])
            ->set('links.sociabuzz', 'sociabuzz.com/ganindraw/tribe')
            ->set('links.saweria', 'https://saweria.co/ganindraw')
            ->set('primaryLink', 'saweria')
            ->call('saveLinks')
            ->assertHasNoErrors()
            ->assertSet('links.sociabuzz', 'https://sociabuzz.com/ganindraw/tribe');

        $this->assertSame(['primary' => 'saweria', 'urls' => ['sociabuzz' => 'https://sociabuzz.com/ganindraw/tribe', 'saweria' => 'https://saweria.co/ganindraw']], $this->streamer->refresh()->donation_links);

        auth()->logout();
        $page = $this->get('/andra')->assertOk()
            ->assertSee('data-package-price="25000"', false)
            ->assertSee('Donasi sekarang')
            ->assertSee('href="https://saweria.co/ganindraw"', false)
            ->assertSee('Donasi di Saweria')
            ->assertSee('Donasi di SociaBuzz');
        // Tombol utama (Saweria) tampil paling depan.
        $this->assertLessThan(strpos($page->getContent(), 'Donasi di SociaBuzz'), strpos($page->getContent(), 'Donasi di Saweria'));

        // Dikosongkan → panel menjelaskan bahwa link belum dipasang.
        $this->actingAs($this->streamer);
        $manager->set('links', ['sociabuzz' => '', 'saweria' => '', 'trakteer' => ''])->call('saveLinks')->assertHasNoErrors();
        auth()->logout();
        $this->get('/andra')->assertSee('Streamer belum memasang link donasi')->assertSee('Lihat cara donasi');
    }

    public function test_donation_value_counts_all_money_received(): void
    {
        $connection = $this->connection();
        $this->streamer->vipPackages()->create(['name' => 'VIP 1', 'price' => 15000, 'match_count' => 1]);

        // Belum live: nilai donasi = semua donasi hari ini (termasuk yang di bawah harga paket).
        $this->sendWebhook($connection, ['id' => 'a', 'donor_name' => 'Raka', 'amount' => 15000, 'message' => 'mabar 1185010926'])->assertCreated();
        $this->sendWebhook($connection, ['id' => 'b', 'donor_name' => 'Nadia', 'amount' => 10000])->assertCreated(); // diabaikan
        Livewire::test(DonationInbox::class)->assertSee('Rp 25.000')->assertSee('semua donasi hari ini');

        // Saat live: semua donasi sejak sesi dimulai, termasuk yang perlu dicek.
        $session = app(\App\Services\LiveSessionService::class)->start($this->streamer, 'Live');
        $this->sendWebhook($connection, ['id' => 'c', 'donor_name' => 'Citra', 'amount' => 20000])->assertCreated(); // perlu dicek
        $this->sendWebhook($connection, ['id' => 'd', 'donor_name' => 'Dodi', 'amount' => 15000, 'message' => 'mabar'])->assertCreated();
        Livewire::test(DonationInbox::class)->assertSee('Rp 35.000')->assertSee('semua donasi sejak sesi dimulai');
        Livewire::test(\App\Livewire\LiveSessionPanel::class)->assertSee('Rp 35.000');
        Livewire::test(\App\Livewire\QueueBoard::class, ['session' => $session])->assertSeeHtml('<span>Donasi masuk</span><strong>Rp 35.000</strong>');
    }

    public function test_package_manager_create_edit_toggle_and_duplicate_price_guard(): void
    {
        $manager = Livewire::test(PackageManager::class)
            ->call('create')
            ->set('name', 'VIP 3 Match')->set('price', 35000)->set('matchCount', 3)
            ->call('save')
            ->assertHasNoErrors()
            ->assertDispatched('notify', message: 'Paket VIP 3 Match dibuat dan langsung aktif.')
            ->assertSee('VIP 3 Match');

        $manager->call('create')->set('name', 'Kembar')->set('price', 35000)->set('matchCount', 1)->call('save')
            ->assertHasErrors(['price']);

        $manager->call('create')->set('name', 'X')->set('price', 500)->set('matchCount', 0)->call('save')
            ->assertHasErrors(['name', 'price', 'matchCount']);

        $package = $this->streamer->vipPackages()->sole();
        $manager->call('edit', $package->id)->assertSet('name', 'VIP 3 Match')
            ->set('price', 30000)->call('save')->assertHasNoErrors();
        $this->assertSame(30000, $package->refresh()->price);

        $manager->call('toggle', $package->id);
        $this->assertFalse($package->refresh()->is_active);
        $this->get('/vip-packages')->assertOk()->assertSee('Disembunyikan');
    }

    public function test_package_manager_cannot_touch_other_streamers_packages(): void
    {
        $foreign = User::factory()->create()->vipPackages()->create(['name' => 'Punya orang', 'price' => 10000, 'match_count' => 1]);

        Livewire::test(PackageManager::class)->call('edit', $foreign->id)->assertStatus(404);
        Livewire::test(PackageManager::class)->call('toggle', $foreign->id)->assertStatus(404);
        Livewire::test(PackageManager::class)->call('delete', $foreign->id)->assertStatus(404);
        $this->assertTrue($foreign->refresh()->is_active);
    }

    public function test_package_manager_deletes_package_and_keeps_player_history(): void
    {
        $session = LiveSession::create(['user_id' => $this->streamer->id, 'title' => 'Mabar', 'status' => 'active', 'started_at' => now()]);
        $package = $this->streamer->vipPackages()->create(['name' => 'Follback IG', 'price' => 250000, 'match_count' => 1]);
        $entry = \App\Models\QueueEntry::create(['live_session_id' => $session->id, 'vip_package_id' => $package->id, 'player_name' => 'Budi', 'provider' => 'manual', 'matches_total' => 3, 'status' => 'ready', 'position' => 1]);

        Livewire::test(PackageManager::class)
            ->assertSeeHtml('wire:click="delete('.$package->id.')"')
            ->call('delete', $package->id)
            ->assertDispatched('notify', message: 'Paket Follback IG dihapus.')
            ->assertDontSee('Follback IG');

        $this->assertNull($package->fresh());
        $this->assertNull($entry->refresh()->vip_package_id);
        $this->assertSame(3, $entry->matches_total);
    }

    public function test_webhook_creates_notifications_and_inbox_shows_them(): void
    {
        $connection = $this->connection();
        LiveSession::create(['user_id' => $this->streamer->id, 'title' => 'Mabar', 'status' => 'active', 'started_at' => now()]);
        $this->streamer->vipPackages()->create(['name' => 'VIP 1', 'price' => 15000, 'match_count' => 1]);

        $this->sendWebhook($connection, ['id' => 'a', 'donor_name' => 'Raka ML', 'amount' => 15000, 'message' => 'mabar id 1185010926'])->assertCreated();
        $this->sendWebhook($connection, ['id' => 'b', 'donor_name' => 'Nadia', 'amount' => 12345])->assertCreated();
        $this->sendWebhook($connection, ['id' => 'b', 'donor_name' => 'Nadia', 'amount' => 12345])->assertOk(); // duplikat: tanpa notifikasi baru
        $this->sendWebhook($connection, ['id' => 'c', 'donor_name' => 'Citra', 'amount' => 20000])->assertCreated();

        // Mode semi-otomatis (default): pesan berisi ID → antrean; 12.345 di bawah paket termurah → diabaikan;
        // donasi biasa tanpa ID/"mabar" → perlu dicek streamer.
        $this->assertSame(['queued', 'received', 'review'], $this->streamer->donationNotifications()->oldest('id')->pluck('type')->all());
        $this->assertSame('ignored', Donation::firstWhere('external_id', 'b')->status);

        Livewire::test(DonationInbox::class)
            ->assertSee('Raka ML masuk antrean otomatis')
            ->assertSee('Nadia mengirim donasi')
            ->assertSee('Citra perlu dicek')
            ->assertSee('3 notifikasi baru')
            ->call('markAllRead')
            ->assertSee('0 notifikasi baru');
    }

    public function test_inbox_queues_review_donation_with_chosen_package_or_ignores(): void
    {
        $connection = $this->connection();
        $small = $this->streamer->vipPackages()->create(['name' => 'VIP 1', 'price' => 15000, 'match_count' => 1, 'sort_order' => 1]);
        $big = $this->streamer->vipPackages()->create(['name' => 'VIP 3', 'price' => 35000, 'match_count' => 3, 'sort_order' => 2]);
        $review = Donation::create(['platform_connection_id' => $connection->id, 'external_id' => 'x1', 'provider' => 'saweria', 'donor_name' => 'Nadia', 'amount' => 40000, 'status' => 'review']);
        $other = Donation::create(['platform_connection_id' => $connection->id, 'external_id' => 'x2', 'provider' => 'saweria', 'donor_name' => 'Bimo', 'amount' => 5000, 'status' => 'received']);

        $review->forceFill(['message' => 'mabar bang 1185010926 (2345)'])->save();
        $session = LiveSession::create(['user_id' => $this->streamer->id, 'title' => 'Mabar', 'status' => 'active', 'started_at' => now()]);

        // 40rb = 35rb (3 game), sisa 5rb tidak cukup paket 15rb → usulan 3 game; ID diambil dari pesan.
        $inbox = Livewire::test(DonationInbox::class)
            ->call('startQueue', $review->id)
            ->assertSet('queueMatches', 3)
            ->assertSet('queueMlbbId', '1185010926 (2345)')
            ->assertSee('Rp 35.000 = 3 game')
            ->set('queueMatches', 0)
            ->call('confirmQueue')
            ->assertHasErrors(['queueMatches'])
            ->set('queueMatches', 4)
            ->call('confirmQueue')
            ->assertHasNoErrors()->assertSet('queueingId', null)
            ->assertDispatched('notify', message: 'Nadia masuk antrean dengan 4 game.');

        $entry = QueueEntry::sole();
        $this->assertSame([4, '1185010926 (2345)', $big->id], [$entry->matches_total, $entry->mlbb_id, $entry->vip_package_id]);
        $this->assertSame('queued', $review->refresh()->status);
        $this->assertSame($session->id, $review->live_session_id);
        $this->assertSame(40000, $session->refresh()->total_donation);

        $inbox->call('ignore', $other->id);
        $this->assertSame('ignored', $other->refresh()->status);

        $inbox->call('setStatus', 'ignored')->assertSee('Bimo')->assertDontSee('Nadia');
    }

    public function test_connection_manager_saves_secret_shows_endpoint_and_pause_blocks_webhooks(): void
    {
        $manager = Livewire::test(ConnectionManager::class)
            ->call('open', 'saweria')
            ->call('save')
            ->assertHasErrors(['signingSecret']) // wajib saat pertama dibuat
            ->set('signingSecret', self::SECRET)
            ->set('label', 'Akun utama')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee('Menunggu webhook');

        $connection = $this->streamer->platformConnections()->sole();
        $this->assertSame('pending', $connection->status);
        $manager->assertSee(url("/api/webhooks/donations/saweria/{$connection->id}"))->assertDontSee(self::SECRET);

        $manager->call('togglePause', 'saweria');
        $this->assertSame('paused', $connection->refresh()->status);
        // Dijeda: tetap 2xx (Trakteer mematikan webhook setelah 3 respons >= 300), donasi tercatat "diabaikan".
        $this->sendWebhook($connection, ['id' => 'p1', 'donor_name' => 'Raka', 'amount' => 15000])->assertStatus(202);
        $this->assertSame('ignored', Donation::sole()->status);

        $manager->call('togglePause', 'saweria');
        $this->assertSame('pending', $connection->refresh()->status); // belum pernah menerima webhook
        $this->sendWebhook($connection, ['id' => 'p2', 'donor_name' => 'Raka', 'amount' => 15000])->assertCreated();
        $this->assertSame('active', $connection->refresh()->status);

        // Ubah label tanpa secret baru: secret lama tetap dipakai.
        $manager->call('open', 'saweria')->set('label', 'Ganti label')->call('save')->assertHasNoErrors();
        $this->assertSame(self::SECRET, $connection->refresh()->signing_secret);
    }

    public function test_overlay_settings_save_and_link_regeneration(): void
    {
        $panel = Livewire::test(OverlaySettingsPanel::class)
            ->set('accentColor', 'merah')
            ->call('save')
            ->assertHasErrors(['accentColor'])
            ->set('accentColor', '#6b63ff')
            ->set('fontScale', 120)
            ->set('maxPlayers', 3)
            ->set('transparentMode', false)
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee('Tersimpan');

        $settings = $this->streamer->refresh()->overlaySetting;
        $this->assertSame('#6B63FF', $settings->accent_color);
        $this->assertSame(120, $settings->font_scale);
        $this->assertFalse($settings->transparent_mode);

        $tokens = app(OverlayTokenService::class);
        $first = $tokens->current($this->streamer);
        $this->assertSame($first, $tokens->current($this->streamer)); // link stabil, bisa disalin ulang
        $panel->assertSee($tokens->url($first));

        $panel->call('regenerateLink');
        $second = $tokens->current($this->streamer->refresh());
        $this->assertNotSame($first, $second);
        $this->get("/o/{$first}")->assertNotFound();
        $this->get("/o/{$second}")->assertOk();
    }

    public function test_overlay_page_and_api_render_live_queue_without_internal_data(): void
    {
        $session = LiveSession::create(['user_id' => $this->streamer->id, 'title' => 'Mabar', 'status' => 'active', 'started_at' => now()]);
        QueueEntry::create(['live_session_id' => $session->id, 'player_name' => 'Raka ML', 'matches_total' => 3, 'status' => 'playing', 'position' => 1]);
        $token = app(OverlayTokenService::class)->current($this->streamer);

        auth()->logout(); // OBS membuka overlay tanpa login
        $this->get("/o/{$token}")->assertOk()->assertSee('Andra Rizky')->assertSee('Raka ML', false);
        $this->getJson("/api/overlay/{$token}")
            ->assertOk()
            ->assertJsonPath('data.queue.status', 'live')
            ->assertJsonPath('data.queue.entries.0.player_name', 'Raka ML')
            ->assertJsonMissingPath('data.overlay_settings.user_id')
            ->assertJsonMissingPath('data.overlay_settings.id');
        $this->get('/o/tokenpalsutokenpalsutokenpalsu')->assertNotFound();
    }

    public function test_public_page_renders_real_queue_packages_and_escapes_names(): void
    {
        $this->streamer->vipPackages()->create(['name' => 'VIP 5 Match', 'price' => 50000, 'match_count' => 5]);
        $session = LiveSession::create(['user_id' => $this->streamer->id, 'title' => 'Rabu malam', 'status' => 'active', 'started_at' => now()]);
        QueueEntry::create(['live_session_id' => $session->id, 'player_name' => 'Raka ML', 'matches_total' => 3, 'status' => 'playing', 'position' => 1]);
        QueueEntry::create(['live_session_id' => $session->id, 'player_name' => '<script>alert(1)</script>', 'matches_total' => 1, 'status' => 'ready', 'position' => 2]);

        auth()->logout();
        $this->get('/andra')
            ->assertOk()
            ->assertSee('Live sekarang')
            ->assertSee('Rabu malam')
            ->assertSee('VIP 5 Match')
            ->assertSee('Rp 50.000')
            ->assertSee('Raka ML')
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('Nisa Gaming'); // data tiruan lama sudah hilang

        // Tidak live: daftar sisa game tetap tampil (pengganti notepad streamer).
        $session->update(['status' => 'ended']);
        $this->get('/andra')->assertOk()->assertSee('Belum live')->assertSee('Sesi mabar VIP belum dibuka')->assertSee('Raka ML')->assertDontSee('Menunggu');

        // Setelah antrean dikosongkan, tidak ada lagi yang ditampilkan.
        $session->queueEntries()->update(['status' => 'done']);
        $this->get('/andra')->assertOk()->assertDontSee('Raka ML')->assertSee('Antrean masih kosong');
    }
}
