<?php

namespace Tests\Feature;

use App\Models\Donation;
use App\Models\LiveSession;
use App\Models\PlatformConnection;
use App\Livewire\DonationInbox;
use App\Models\User;
use App\Models\WebhookDelivery;
use App\Services\PlatformConnectionHealthService;
use App\Services\Webhooks\SaweriaAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\Concerns\SendsProviderWebhooks;
use Tests\TestCase;

/**
 * Webhook donasi dengan format asli tiap provider → verifikasi → donasi tercatat → masuk antrean sesuai paket.
 * Saweria: Saweria-Callback-Signature (HMAC 5 field, stream key). Trakteer: X-Webhook-Token statis.
 */
class DonationWebhookTest extends TestCase
{
    use RefreshDatabase;
    use SendsProviderWebhooks;

    private const KEY = 'saweria-stream-key-uji-123';

    private const TRAKTEER_TOKEN = 'trakteer-token-uji-456';

    private User $streamer;

    private PlatformConnection $saweria;

    protected function setUp(): void
    {
        parent::setUp();

        // Test adapter & kelipatan paket memakai mode "otomatis semua"; mode semi-otomatis diuji terpisah.
        $this->streamer = User::factory()->create(['donation_queue_mode' => 'auto']);
        $this->saweria = $this->connect('saweria', self::KEY);
        $this->streamer->vipPackages()->createMany([
            ['name' => 'VIP 1 Match', 'price' => 15000, 'match_count' => 1, 'sort_order' => 1],
            ['name' => 'VIP 3 Match', 'price' => 35000, 'match_count' => 3, 'sort_order' => 2],
            ['name' => 'VIP Lama', 'price' => 50000, 'match_count' => 5, 'sort_order' => 3, 'is_active' => false],
        ]);
    }

    private function connect(string $provider, string $credential, string $status = 'pending'): PlatformConnection
    {
        return PlatformConnection::create([
            'user_id' => $this->streamer->id, 'provider' => $provider, 'webhook_url' => '', 'signing_secret' => $credential, 'status' => $status,
        ]);
    }

    private function openLiveSession(): LiveSession
    {
        return LiveSession::create(['user_id' => $this->streamer->id, 'title' => 'Mabar', 'status' => 'active', 'started_at' => now()]);
    }

    /** Kasus 2026-11-17: streamer pasang "Follback IG 600rb" — tidak boleh jadi game. */
    public function test_reward_package_donation_is_recorded_as_reward_not_games(): void
    {
        $session = $this->openLiveSession();
        $follback = app(\App\Services\VipPackageService::class)->add($this->streamer, ['name' => 'Follback IG', 'kind' => 'reward', 'reward_label' => 'Follback IG', 'price' => 600000, 'match_count' => 7]);
        $this->assertSame(0, $follback->match_count);

        // 625rb: paket termahal yang muat = reward → reward saja, sisa 25rb tidak dihitung (opsi A).
        $this->sendSaweria($this->saweria, $this->saweriaPayload('sw-r1', 'Raka', 625000, ['message' => 'ig: @raka.ml']), self::KEY)
            ->assertCreated()->assertJsonPath('data.status', 'reward');
        $donation = Donation::where('external_id', 'sw-r1')->sole();
        $this->assertSame([$follback->id, null], [$donation->vip_package_id, $donation->queue_entry_id]);
        $this->assertSame(0, $session->queueEntries()->count());
        $this->assertSame('reward', $donation->notifications()->sole()->type);
        $this->assertSame('Follback IG', $donation->notifications()->sole()->context['reward']);

        // Di bawah harga reward: dihitung paket mabar seperti biasa (35rb×15 + 15rb = 46 game), tanpa reward.
        $this->sendSaweria($this->saweria, $this->saweriaPayload('sw-r2', 'Dey', 540000), self::KEY)
            ->assertCreated()->assertJsonPath('data.status', 'queued');
        $this->assertSame(46, $session->queueEntries()->sole()->matches_total);

        // Papan antrean menampilkan reward yang belum diberikan; selesai → hilang.
        $this->actingAs($this->streamer);
        Livewire::test(\App\Livewire\QueueBoard::class, ['session' => $session])
            ->assertSee('Reward donasi')->assertSee('ig: @raka.ml')
            ->call('finishReward', $donation->id)
            ->assertDontSee('Reward donasi');
        $this->assertNotNull($donation->refresh()->reward_done_at);

        // Overlay & halaman viewer menulis rewardnya, bukan "1 game".
        $token = app(\App\Services\OverlayTokenService::class)->current($this->streamer);
        $this->assertContains('Follback IG', collect($this->getJson("/api/overlay/{$token}")->json('data.packages'))->pluck('gets')->all());
    }

    public function test_review_donation_can_be_recorded_as_reward_from_the_inbox(): void
    {
        $this->streamer->forceFill(['donation_queue_mode' => 'manual'])->save();
        $this->openLiveSession();
        $follback = $this->streamer->vipPackages()->create(['name' => 'Follback IG', 'kind' => 'reward', 'reward_label' => 'Follback IG', 'price' => 600000, 'match_count' => 0]);

        $this->sendSaweria($this->saweria, $this->saweriaPayload('sw-r3', 'Nadia', 600000), self::KEY)
            ->assertCreated()->assertJsonPath('data.status', 'review');
        $donation = Donation::where('external_id', 'sw-r3')->sole();

        $this->actingAs($this->streamer);
        Livewire::test(\App\Livewire\DonationInbox::class)
            ->call('startQueue', $donation->id)
            ->assertSet('queueRewardId', $follback->id)
            ->call('confirmReward', $follback->id)
            ->assertDispatched('notify', message: 'Nadia dicatat dapat reward: Follback IG.')
            ->call('toggleRewardDone', $donation->id);

        $this->assertSame('reward', $donation->refresh()->status);
        $this->assertNotNull($donation->reward_done_at);
    }

    // ── Saweria ──────────────────────────────────────────────────────────

    public function test_saweria_signature_formula_matches_the_reference_library(): void
    {
        // Rumus saweria-webhook-express: HMAC-SHA256(stream key, version + id + amount_raw + donator_name + donator_email).
        $payload = ['version' => '2021.07', 'id' => 'abc', 'amount_raw' => 69420, 'donator_name' => 'Someguy', 'donator_email' => 'someguy@example.com'];

        $this->assertSame(
            hash_hmac('sha256', '2021.07abc69420Someguysomeguy@example.com', 'kunci'),
            SaweriaAdapter::sign($payload, 'kunci')
        );
        // Email kosong ikut digabung sebagai string kosong (seperti Array.join di JavaScript).
        $this->assertSame(hash_hmac('sha256', '2021.07abc69420Someguy', 'kunci'), SaweriaAdapter::sign([...$payload, 'donator_email' => null], 'kunci'));
    }

    public function test_saweria_donation_matching_a_package_is_queued(): void
    {
        $session = $this->openLiveSession();

        $this->sendSaweria($this->saweria, $this->saweriaPayload('sw-1', 'Raka ML', 35000), self::KEY)
            ->assertCreated()
            ->assertJsonPath('data.status', 'queued')
            ->assertJsonPath('data.duplicate', false);

        $entry = $session->queueEntries()->sole();
        $this->assertSame('Raka ML', $entry->player_name);
        $this->assertSame(3, $entry->matches_total);
        $this->assertSame('saweria', $entry->provider);
        $this->assertSame(35000, $session->refresh()->total_donation);
        $this->assertSame('rakaml@example.com', Donation::sole()->donor_email);
        $this->assertSame('active', $this->saweria->refresh()->status);
    }

    public function test_saweria_rejects_missing_or_wrong_signature_and_tampered_amount(): void
    {
        $this->openLiveSession();
        $payload = $this->saweriaPayload('sw-1', 'Penyusup', 15000);

        $this->sendSaweria($this->saweria, $payload, null)->assertUnauthorized();
        $this->sendSaweria($this->saweria, $payload, 'stream-key-salah')->assertUnauthorized();

        // Nominal diubah setelah ditandatangani → signature tidak cocok lagi.
        $signature = SaweriaAdapter::sign($payload, self::KEY);
        $this->postJson("/api/webhooks/donations/saweria/{$this->saweria->id}", [...$payload, 'amount_raw' => 50000], [SaweriaAdapter::SIGNATURE_HEADER => $signature])
            ->assertUnauthorized();

        $this->assertSame(0, Donation::count());
        $this->assertStringContainsString('Stream key', $this->saweria->refresh()->last_error);
    }

    public function test_duplicate_delivery_is_idempotent(): void
    {
        $session = $this->openLiveSession();
        $payload = $this->saweriaPayload('sw-1', 'Raka ML', 15000);

        $this->sendSaweria($this->saweria, $payload, self::KEY)->assertCreated();
        $this->sendSaweria($this->saweria, $payload, self::KEY)->assertOk()->assertJsonPath('data.duplicate', true);

        $this->assertSame(1, Donation::count());
        $this->assertSame(1, $session->queueEntries()->count());
    }

    public function test_amount_is_converted_to_games_by_package_multiples_and_small_donations_are_ignored(): void
    {
        // Belum live: donasi tetap masuk daftar sisa game (sesi penampung) dan ikut ke live berikutnya.
        $this->sendSaweria($this->saweria, $this->saweriaPayload('sw-0', 'Sebelum live', 15000), self::KEY)
            ->assertCreated()->assertJsonPath('data.status', 'queued');
        $standby = Donation::firstWhere('external_id', 'sw-0')->liveSession;
        $this->assertSame(['ended', 'Antrean sebelum live'], [$standby->status, $standby->title]);

        // Paket aktif: 15rb = 1 game, 35rb = 3 game (paket 50rb nonaktif tidak dipakai).
        $session = app(\App\Services\LiveSessionService::class)->start($this->streamer, 'Mabar');
        $this->assertSame(['Sebelum live'], $session->queueEntries()->pluck('player_name')->all());
        $this->sendSaweria($this->saweria, $this->saweriaPayload('sw-1', 'Nadia', 20000), self::KEY)->assertJsonPath('data.status', 'queued'); // 15rb, sisa 5rb hangus
        $this->sendSaweria($this->saweria, $this->saweriaPayload('sw-2', 'Bimo', 50000), self::KEY)->assertJsonPath('data.status', 'queued'); // 35rb + 15rb
        $this->sendSaweria($this->saweria, $this->saweriaPayload('sw-3', 'Citra', 70000), self::KEY)->assertJsonPath('data.status', 'queued'); // 35rb × 2
        $this->sendSaweria($this->saweria, $this->saweriaPayload('sw-4', 'Dodi', 10000), self::KEY)->assertJsonPath('data.status', 'ignored'); // < 15rb

        $this->assertSame(['Sebelum live' => 1, 'Nadia' => 1, 'Bimo' => 4, 'Citra' => 6], $session->queueEntries()->pluck('matches_total', 'player_name')->all());
        $this->assertNotNull(Donation::firstWhere('external_id', 'sw-4')->processed_at);
    }

    public function test_smart_mode_queues_only_donations_whose_message_mentions_an_id_or_mabar(): void
    {
        $this->streamer->forceFill(['donation_queue_mode' => 'smart'])->save();
        $session = $this->openLiveSession();

        $send = fn (string $id, string $message, int $amount = 35000) => $this->sendSaweria($this->saweria, $this->saweriaPayload($id, 'Donatur '.$id, $amount, ['message' => $message]), self::KEY);

        $send('s1', 'mabar bang, id 1185010926 (2345)')->assertJsonPath('data.status', 'queued');
        $send('s2', 'Mabar dong')->assertJsonPath('data.status', 'queued');
        $send('s3', 'ID: 138765201')->assertJsonPath('data.status', 'queued');
        $send('s4', 'Semangat bang! sehat selalu')->assertJsonPath('data.status', 'review'); // donasi biasa
        $send('s5', 'mabar', 10000)->assertJsonPath('data.status', 'ignored'); // di bawah paket termurah

        $entries = $session->queueEntries()->get()->keyBy('player_name');
        $this->assertSame('1185010926 (2345)', $entries['Donatur s1']->mlbb_id);
        $this->assertNull($entries['Donatur s2']->mlbb_id);
        $this->assertSame('138765201', $entries['Donatur s3']->mlbb_id);
        $this->assertSame('Semangat bang! sehat selalu', Donation::firstWhere('external_id', 's4')->message);

        $this->streamer->forceFill(['donation_queue_mode' => 'manual'])->save();
        $send('s6', 'mabar id 1185010926')->assertJsonPath('data.status', 'review');
    }

    public function test_message_parsing_helpers(): void
    {
        $id = fn (?string $m) => \App\Services\DonationQueueService::mlbbIdFrom($m);

        $this->assertSame('1185010926', $id('id 1185010926'));
        $this->assertSame('1185010926 (2345)', $id('1185010926(2345) mabar'));
        $this->assertSame('1185010926 (2345)', $id('ID: 1185010926 [2345]'));
        $this->assertNull($id('donasi 50000 semangat')); // 5 digit: bukan ID
        $this->assertNull($id(null));
        $this->assertTrue(\App\Services\DonationQueueService::wantsMabar('VIP bang'));
        $this->assertFalse(\App\Services\DonationQueueService::wantsMabar('semangat bang'));
    }

    public function test_package_multiples_match_streamer_pricing(): void
    {
        $user = User::factory()->create();
        $packages = collect([
            $user->vipPackages()->create(['name' => 'Mabar 2 game', 'price' => 25000, 'match_count' => 2]),
            $user->vipPackages()->create(['name' => 'Mabar 5 game', 'price' => 50000, 'match_count' => 5]),
        ]);

        $games = fn (int $amount) => \App\Services\DonationQueueService::matchesFor($amount, $packages)[1] ?? 0;

        $this->assertSame(0, $games(24999));
        $this->assertSame(2, $games(25000));
        $this->assertSame(2, $games(49000));
        $this->assertSame(5, $games(50000));
        $this->assertSame(5, $games(60000));
        $this->assertSame(7, $games(75000));
        $this->assertSame(10, $games(100000));
    }

    public function test_non_donation_events_and_broken_payloads(): void
    {
        $this->sendSaweria($this->saweria, $this->saweriaPayload('sw-1', 'Raka', 15000, ['type' => 'subscription']), self::KEY)
            ->assertStatus(202)->assertJsonPath('data.status', 'ignored');

        // Payload rusak tetap 2xx (provider tidak berhenti mengirim), tercatat sebagai kiriman gagal dibaca.
        $this->sendSaweria($this->saweria, $this->saweriaPayload('sw-2', 'Raka', 15000, ['amount_raw' => null]), self::KEY)
            ->assertStatus(202)->assertJsonPath('data.status', 'unreadable');

        $this->sendSaweria($this->saweria, $this->saweriaPayload('sw-3', 'Raka', 15000), self::KEY)->assertCreated();
        $this->assertSame(1, Donation::count());
        $this->assertSame(['ignored', 'unreadable', 'donation'], WebhookDelivery::query()->orderBy('id')->pluck('result')->all());
    }

    public function test_provider_in_url_must_match_connection(): void
    {
        $payload = $this->trakteerPayload('tr-1', 'Raka', 15000);
        $this->postJson("/api/webhooks/donations/trakteer/{$this->saweria->id}", $payload, ['X-Webhook-Token' => self::KEY])
            ->assertUnprocessable()->assertJsonValidationErrors(['provider']);
    }

    // ── Trakteer ─────────────────────────────────────────────────────────

    public function test_trakteer_tip_with_valid_token_is_queued_using_total_price(): void
    {
        $trakteer = $this->connect('trakteer', self::TRAKTEER_TOKEN);
        $session = $this->openLiveSession();

        // 3 kopi × Rp 5.000 = price 15000 → cocok dengan paket VIP 1 Match.
        $this->sendTrakteer($trakteer, $this->trakteerPayload('tr-1', 'Egis', 15000, ['quantity' => 3]), self::TRAKTEER_TOKEN)
            ->assertCreated()->assertJsonPath('data.status', 'queued');

        $this->assertSame('Egis', $session->queueEntries()->sole()->player_name);
        $this->sendTrakteer($trakteer, $this->trakteerPayload('tr-2', 'Egis', 15000), 'token-salah')->assertUnauthorized();
        $this->sendTrakteer($trakteer, $this->trakteerPayload('tr-3', 'Egis', 15000), null)->assertUnauthorized();
    }

    public function test_trakteer_test_button_payload_verifies_connection_without_queueing(): void
    {
        $trakteer = $this->connect('trakteer', self::TRAKTEER_TOKEN);
        $session = $this->openLiveSession();

        $this->sendTrakteer($trakteer, $this->trakteerPayload('test-xxxxxxxx-xxxx', 'Egis', 15000), self::TRAKTEER_TOKEN)
            ->assertOk()->assertJsonPath('data.status', 'test');

        $this->assertSame(0, Donation::count());
        $this->assertSame(0, $session->queueEntries()->count());
        $trakteer->refresh();
        $this->assertSame('active', $trakteer->status);
        $this->assertNotNull($trakteer->last_received_at);
    }

    public function test_paused_connection_answers_2xx_and_records_donation_as_ignored(): void
    {
        // Trakteer mematikan webhook setelah 3 respons >= 300, jadi jeda tidak boleh membalas 409.
        $trakteer = $this->connect('trakteer', self::TRAKTEER_TOKEN, 'paused');
        $session = $this->openLiveSession();

        $this->sendTrakteer($trakteer, $this->trakteerPayload('tr-1', 'Egis', 15000), self::TRAKTEER_TOKEN)
            ->assertStatus(202)->assertJsonPath('data.status', 'ignored');

        $this->assertSame('ignored', Donation::sole()->status);
        $this->assertSame(0, $session->queueEntries()->count());
        $this->assertSame('paused', $trakteer->refresh()->status); // tetap dijeda
    }

    // ── SociaBuzz (format sementara) ─────────────────────────────────────

    public function test_sociabuzz_accepts_token_in_known_header_candidates(): void
    {
        $sociabuzz = $this->connect('sociabuzz', 'sociabuzz-token-uji');
        $session = $this->openLiveSession();

        $this->sendSociaBuzz($sociabuzz, ['id' => 'sb-1', 'supporter' => 'Raka', 'amount' => 15000, 'currency' => 'IDR'], 'sociabuzz-token-uji')
            ->assertCreated()->assertJsonPath('data.status', 'queued');
        $this->sendSociaBuzz($sociabuzz, ['id' => 'sb-2', 'supporter' => 'Nadia', 'amount' => 15000, 'currency' => 'IDR'], 'sociabuzz-token-uji', 'x-webhook-token')
            ->assertCreated();
        $this->sendSociaBuzz($sociabuzz, ['id' => 'sb-3', 'supporter' => 'X', 'amount' => 15000], 'token-salah')->assertUnauthorized();

        // Header dengan nama yang tidak dikenal, atau token di field data: tetap diterima bila nilainya persis sama.
        $this->sendSociaBuzz($sociabuzz, ['id' => 'sb-4', 'supporter' => 'Citra', 'amount' => 15000], 'sociabuzz-token-uji', 'x-callback-token')
            ->assertCreated();
        $this->sendSociaBuzz($sociabuzz, ['id' => 'sb-5', 'supporter' => 'Dodi', 'amount' => 15000, 'secret_key' => 'sociabuzz-token-uji'], null)
            ->assertCreated();
        $this->sendSociaBuzz($sociabuzz, ['id' => 'sb-6', 'supporter' => 'Eko', 'amount' => 15000, 'note' => 'sociabuzz-token-uji-bukan'], 'x', 'x-callback-token')
            ->assertUnauthorized(); // mirip tapi tidak persis sama → ditolak

        $this->assertSame(4, $session->queueEntries()->count());
    }

    /** Format asli SociaBuzz (dari "Test Notifikasi" 2026-11-17): uji hanya menandai koneksi, donasi asli tercatat. */
    public function test_sociabuzz_real_format_test_notification_and_donation(): void
    {
        $sociabuzz = $this->connect('sociabuzz', 'sbwhook-uji-123');
        $session = $this->openLiveSession();
        $real = fn (array $overrides = []) => array_merge([
            'id' => '1664565323', 'amount' => 10000, 'currency' => 'IDR', 'amount_settled' => 10000, 'currency_settled' => 'IDR',
            'media_type' => null, 'media_url' => null, 'supporter' => 'Jessica', 'email_supporter' => 'jessica@example.com',
            'message' => 'Ini hanya test notifikasi', 'item' => ['name' => 'Kopi', 'qty' => 1],
            'level' => ['title' => 'Pilihan Dukungan', 'price' => 10000, 'description' => 'Ini hanya test Pilihan Dukungan', 'answer_question' => null, 'answer_address' => null],
            'vote' => ['title' => 'Pilih yg mana?', 'choose' => 'test'],
            'content' => ['title' => 'test', 'link' => 'https://sociabuzz.com/example/c/01/test'],
            'created_at' => '2026-11-17T00:22:56+07:00',
        ], $overrides);

        // Tombol "Test Notifikasi": koneksi aktif, tidak ada donasi tercatat.
        $this->sendSociaBuzz($sociabuzz, $real(), 'sbwhook-uji-123')->assertOk()->assertJsonPath('data.status', 'test');
        $this->assertSame(0, \App\Models\Donation::count());
        $this->assertSame('active', $sociabuzz->refresh()->status);

        // Donasi asli dengan format yang sama.
        $this->sendSociaBuzz($sociabuzz, $real([
            'id' => '1700000001', 'amount' => 35000, 'amount_settled' => 33250, 'supporter' => 'Kyoto',
            'email_supporter' => 'kyoto@gmail.com', 'message' => 'mabar bang 138765201',
        ]), 'sbwhook-uji-123')->assertCreated()->assertJsonPath('data.status', 'queued');

        $donation = \App\Models\Donation::sole();
        $this->assertSame(['Kyoto', 35000, 'kyoto@gmail.com', 'mabar bang 138765201'], [$donation->donor_name, $donation->amount, $donation->donor_email, $donation->message]);
        $this->assertSame([3, '138765201'], [$session->queueEntries()->sole()->matches_total, $session->queueEntries()->sole()->mlbb_id]);
    }

    /** Kejadian 2026-11-17: `id` SociaBuzz sama untuk tes & donasi asli, sehingga donasi asli dianggap duplikat. */
    public function test_sociabuzz_donations_sharing_the_same_id_are_all_recorded(): void
    {
        $sociabuzz = $this->connect('sociabuzz', 'sbwhook-uji-123');
        $this->streamer->vipPackages()->create(['name' => '2 ribu', 'price' => 2000, 'match_count' => 2, 'sort_order' => 9]);
        $session = $this->openLiveSession();
        $payload = fn (array $overrides) => array_merge([
            'id' => '1664565323', 'amount' => 2000, 'currency' => 'IDR', 'amount_settled' => 2000, 'currency_settled' => 'IDR',
            'media_type' => null, 'media_url' => null, 'supporter' => 'Jessica', 'email_supporter' => 'jessica@example.com',
            'message' => 'Ini hanya test notifikasi', 'created_at' => '2026-11-17T00:22:56+07:00',
        ], $overrides);

        // Data tes lama yang sudah terlanjur tercatat dengan id yang sama (sebelum perbaikan).
        Donation::create(['platform_connection_id' => $sociabuzz->id, 'external_id' => '1664565323', 'provider' => 'sociabuzz', 'donor_name' => 'Jessica', 'amount' => 10000, 'status' => 'received']);

        $first = $payload(['supporter' => 'Ricky Adryan Achmad', 'email_supporter' => 'ricky@gmail.com', 'message' => '23588423',
            'media_url' => 'https://music.youtube.com/watch?v=x', 'created_at' => '2026-11-17T00:25:22+07:00']);
        $this->sendSociaBuzz($sociabuzz, $first, 'sbwhook-uji-123')->assertCreated()->assertJsonPath('data.status', 'queued');

        $second = $payload(['supporter' => 'Kyoto', 'email_supporter' => 'kyoto@gmail.com', 'message' => 'mabar 138765201', 'created_at' => '2026-11-17T00:31:02+07:00']);
        $this->sendSociaBuzz($sociabuzz, $second, 'sbwhook-uji-123')->assertCreated()->assertJsonPath('data.status', 'queued');

        // Kiriman ulang yang identik tetap terdeteksi sebagai duplikat.
        $this->sendSociaBuzz($sociabuzz, $first, 'sbwhook-uji-123')->assertOk()->assertJsonPath('data.duplicate', true);

        $entries = $session->queueEntries()->orderBy('position')->get();
        $this->assertSame(['Ricky Adryan Achmad', 'Kyoto'], $entries->pluck('player_name')->all());
        $this->assertSame([2, 2], $entries->pluck('matches_total')->all());
        $this->assertSame(['23588423', '138765201'], $entries->pluck('mlbb_id')->all());
    }

    public function test_sociabuzz_non_idr_goes_to_review_and_sample_is_stored_masked(): void
    {
        $sociabuzz = $this->connect('sociabuzz', 'sociabuzz-token-uji');
        $this->openLiveSession();

        $this->sendSociaBuzz($sociabuzz, ['id' => 'sb-1', 'supporter' => 'Tom', 'amount' => 15000, 'currency' => 'USD'], 'sociabuzz-token-uji')
            ->assertCreated()->assertJsonPath('data.status', 'review');

        $sample = $sociabuzz->refresh()->last_payload;
        $this->assertSame('Tom', $sample['payload']['supporter']);
        $this->assertSame('***', $sample['headers']['sb-webhook-token']);
        $this->assertStringNotContainsString('sociabuzz-token-uji', json_encode($sample));
    }

    /** Kejadian 2026-11-17: viewer Malaysia donasi 25 MYR tapi tercatat Rp 25. */
    public function test_sociabuzz_foreign_currency_uses_settled_rupiah_and_keeps_original(): void
    {
        $sociabuzz = $this->connect('sociabuzz', 'sociabuzz-token-uji');
        $this->openLiveSession();

        $this->sendSociaBuzz($sociabuzz, [
            'id' => '1664565323', 'amount' => 25, 'currency' => 'MYR', 'amount_settled' => 89300, 'currency_settled' => 'IDR',
            'supporter' => 'Roti comeback', 'email_supporter' => 'roti@gmail.com', 'message' => 'mabar bang 138765201',
        ], 'sociabuzz-token-uji')->assertCreated()->assertJsonPath('data.status', 'review');

        $donation = Donation::sole();
        $this->assertSame([89300, 'MYR', 25.0], [$donation->amount, $donation->currency, $donation->original_amount]);
        $this->assertSame('MYR 25 · ≈ Rp 89.300', $donation->amountLabel());
        $this->assertSame('MYR', $donation->notifications()->sole()->context['currency']);

        // Tanpa nilai rupiah dari platform: jangan dianggap Rp 25, cukup tampilkan mata uang aslinya.
        $this->sendSociaBuzz($sociabuzz, ['id' => 'x', 'amount' => 12.5, 'currency' => 'USD', 'supporter' => 'Tom', 'created_at' => '2026-11-17T01:00:00+07:00'], 'sociabuzz-token-uji')
            ->assertCreated()->assertJsonPath('data.status', 'review');
        $usd = Donation::where('donor_name', 'Tom')->sole();
        $this->assertSame([0, 'USD 12,50'], [$usd->amount, $usd->amountLabel()]);
    }

    /** Format tak dikenal: tetap 2xx, koneksi tidak dianggap mati, kiriman tercatat & tampil di halaman Donasi. */
    public function test_sociabuzz_unknown_format_is_answered_2xx_and_kept_as_failed_delivery(): void
    {
        $sociabuzz = $this->connect('sociabuzz', 'sociabuzz-token-uji');

        $this->sendSociaBuzz($sociabuzz, ['donatur' => ['nama' => 'Raka'], 'nilai' => '15k'], 'sociabuzz-token-uji')
            ->assertStatus(202)->assertJsonPath('data.status', 'unreadable');

        $sociabuzz->refresh();
        $this->assertSame('active', $sociabuzz->status);
        $this->assertNotNull($sociabuzz->last_received_at);
        $this->assertStringContainsString('gagal dibaca', (string) $sociabuzz->last_error);
        $this->assertSame('Raka', $sociabuzz->last_payload['payload']['donatur']['nama']);

        $delivery = WebhookDelivery::sole();
        $this->assertSame(['unreadable', 202, null], [$delivery->result, $delivery->http_status, $delivery->resolved_at]);
        $this->assertSame('Raka', $delivery->body()['donatur']['nama']);
        $this->assertSame(0, Donation::count());

        $this->actingAs($this->streamer);
        Livewire::test(DonationInbox::class)
            ->assertSee('1 webhook gagal dibaca')
            ->assertSee('SociaBuzz: Webhook')
            ->call('resolveDelivery', $delivery->id)
            ->assertDontSee('webhook gagal dibaca');
        $this->assertNotNull($delivery->refresh()->resolved_at);
    }

    /**
     * Kejadian 2026-11-28 21:49: donasi SociaBuzz MYR 0,25 (≈ Rp 1.035) ditolak 422 karena round(0,25) = 0,
     * lalu SociaBuzz berhenti mengirim semua donasi berikutnya. Payload di bawah = payload aslinya.
     */
    public function test_incident_sociabuzz_myr_below_one_is_recorded_and_next_donation_still_arrives(): void
    {
        $sociabuzz = $this->connect('sociabuzz', 'sbwhook-uji-123');
        $this->openLiveSession();

        $this->sendSociaBuzz($sociabuzz, [
            'id' => '1664565323', 'amount' => 0.25, 'currency' => 'MYR', 'amount_settled' => 1035, 'currency_settled' => 'IDR',
            'media_type' => null, 'media_url' => 'https://music.youtube.com/watch?v=i4Xw5VvziTA', 'supporter' => 'Mednes',
            'email_supporter' => 'mednes@gmail.com', 'message' => null, 'created_at' => '2026-11-28T21:49:24+07:00',
        ], 'sbwhook-uji-123')->assertCreated()->assertJsonPath('data.status', 'review');

        $donation = Donation::sole();
        $this->assertSame([1035, 'MYR', 0.25], [$donation->amount, $donation->currency, $donation->original_amount]);
        $this->assertSame('active', $sociabuzz->refresh()->status);
        $this->assertNull($sociabuzz->last_error);

        $this->sendSociaBuzz($sociabuzz, [
            'id' => '1664565323', 'amount' => 15000, 'currency' => 'IDR', 'supporter' => 'Viewer Berikutnya', 'created_at' => '2026-11-28T21:55:00+07:00',
        ], 'sbwhook-uji-123')->assertCreated()->assertJsonPath('data.status', 'queued');
        $this->assertSame(2, Donation::count());
    }

    public function test_sociabuzz_accepts_anonymous_supporter_formatted_amounts_and_string_test_flag(): void
    {
        $sociabuzz = $this->connect('sociabuzz', 'sbwhook-uji-123');
        $session = $this->openLiveSession();
        $send = fn (array $payload) => $this->sendSociaBuzz($sociabuzz, $payload + ['id' => '1664565323', 'currency' => 'IDR'], 'sbwhook-uji-123');

        $send(['supporter' => '', 'amount' => 15000, 'created_at' => '2026-11-28T22:00:00+07:00'])->assertCreated();
        $send(['supporter' => 'Raka', 'amount' => 'Rp 15.000', 'created_at' => '2026-11-28T22:01:00+07:00'])->assertCreated();
        $send(['supporter' => 'Nadia', 'amount' => '35,000.00', 'created_at' => '2026-11-28T22:02:00+07:00'])->assertCreated();
        $send(['supporter' => 'Citra', 'amount' => '15000.00', 'created_at' => '2026-11-28T22:03:00+07:00'])->assertCreated();
        // "false" dari provider bukan tanda notifikasi uji.
        $send(['supporter' => 'Dodi', 'amount' => 15000, 'is_test' => 'false', 'created_at' => '2026-11-28T22:04:00+07:00'])
            ->assertCreated()->assertJsonPath('data.status', 'queued');

        $this->assertSame(['Anonim', 'Raka', 'Nadia', 'Citra', 'Dodi'], Donation::query()->orderBy('id')->pluck('donor_name')->all());
        $this->assertSame([15000, 15000, 35000, 15000, 15000], Donation::query()->orderBy('id')->pluck('amount')->all());
        $this->assertSame([1, 1, 3, 1, 1], $session->queueEntries()->orderBy('position')->pluck('matches_total')->all());
    }

    public function test_amount_parser_reads_provider_number_formats(): void
    {
        $cases = [
            [15000, 15000.0], [0.25, 0.25], ['15000', 15000.0], ['15.000', 15000.0], ['Rp 15.000', 15000.0], ['1.035', 1035.0],
            ['35,000.00', 35000.0], ['15000.00', 15000.0], ['10.000,50', 10000.5], ['12,50', 12.5], ['0.25', 0.25], ['1.250.000', 1250000.0],
            ['abc', null], ['', null], [null, null], [['x'], null],
        ];

        foreach ($cases as [$input, $expected]) {
            $this->assertSame($expected, \App\Services\Webhooks\Amount::parse($input), 'input: '.json_encode($input));
        }
    }

    /** Error tak terduga saat memproses: tetap 2xx, tercatat di log + jurnal + delivery, lalu bisa diproses ulang. */
    public function test_unexpected_error_still_answers_2xx_is_logged_and_can_be_replayed(): void
    {
        $session = $this->openLiveSession();
        \Illuminate\Support\Facades\Log::spy();
        $this->mock(\App\Services\DonationQueueService::class, fn ($mock) => $mock->shouldReceive('process')->once()->andThrow(new \RuntimeException('disk penuh')));

        $this->sendSaweria($this->saweria, $this->saweriaPayload('sw-err', 'Raka', 15000), self::KEY)
            ->assertStatus(202)->assertJsonPath('data.status', 'failed');

        $this->assertSame(0, Donation::count()); // transaksi dibatalkan
        $delivery = WebhookDelivery::sole();
        $this->assertSame(['failed', 202, 'disk penuh'], [$delivery->result, $delivery->http_status, $delivery->error]);
        \Illuminate\Support\Facades\Log::shouldHaveReceived('error')->withArgs(fn ($message, $context) => $message === 'Webhook donasi gagal diproses' && $context['webhook_delivery_id'] === $delivery->id);

        $journal = file(\App\Services\DonationWebhookService::journalPath(now()), FILE_IGNORE_NEW_LINES);
        $this->assertCount(1, $journal);
        $this->assertSame(['sw-err', true], [json_decode($journal[0], true)['payload']['id'], json_decode($journal[0], true)['verified']]);

        // Masalah selesai → proses ulang: donasi tercatat & masuk antrean sesi yang berjalan saat itu.
        $this->app->forgetInstance(\App\Services\DonationQueueService::class);
        $result = app(\App\Services\DonationWebhookService::class)->replayDelivery($delivery);

        $this->assertSame('donation', $result->result());
        $this->assertSame(['queued', $session->id], [Donation::sole()->status, Donation::sole()->live_session_id]);
        $this->assertNotNull($delivery->refresh()->resolved_at);
        $this->assertSame(Donation::sole()->id, $delivery->donation_id);
    }

    public function test_replay_command_is_idempotent_and_recovers_from_journal(): void
    {
        $this->openLiveSession();
        $this->sendSaweria($this->saweria, $this->saweriaPayload('sw-1', 'Raka', 15000), self::KEY)->assertCreated();
        $this->sendSaweria($this->saweria, $this->saweriaPayload('sw-2', 'Hilang', 5000), self::KEY)->assertCreated(); // diabaikan (di bawah paket)
        $this->sendSaweria($this->saweria, $this->saweriaPayload('sw-3', 'Penyusup', 15000), 'kunci-salah')->assertUnauthorized();

        // Simulasi donasi yang tidak sempat tersimpan: hanya ada di jurnal.
        Donation::where('external_id', 'sw-2')->delete();

        $this->artisan('webhooks:replay', ['target' => now()->format('Y-m-d'), '--dry-run' => true])->assertSuccessful();
        $this->assertSame(1, Donation::count());

        $this->artisan('webhooks:replay', ['target' => now()->format('Y-m-d')])->assertSuccessful();
        $this->assertSame(['sw-1', 'sw-2'], Donation::query()->orderBy('external_id')->pluck('external_id')->all()); // penyusup tidak ikut

        // Jalankan lagi: tidak ada yang dobel.
        $this->artisan('webhooks:replay', ['target' => now()->format('Y-m-d')])->assertSuccessful();
        $this->artisan('webhooks:replay', ['target' => 'failed'])->expectsOutput('Tidak ada kiriman yang cocok.')->assertSuccessful();
        $this->assertSame(2, Donation::count());
        $this->assertSame(1, \App\Models\QueueEntry::count());
    }

    public function test_inbox_counts_all_donations_status_line_and_session_card_survive_midnight(): void
    {
        $this->actingAs($this->streamer);
        Livewire::test(DonationInbox::class)->assertSee('Menunggu webhook pertama');

        $this->travelTo(now()->setDate(2026, 11, 28)->setTime(21, 26));
        app(\App\Services\LiveSessionService::class)->start($this->streamer, 'Mabar VIP Sabtu');
        $this->travelTo(now()->setTime(21, 30));
        $this->sendSaweria($this->saweria, $this->saweriaPayload('sw-1', 'Raka', 15000), self::KEY)->assertCreated();

        Donation::insert(collect(range(1, 205))->map(fn ($i) => [
            'platform_connection_id' => $this->saweria->id, 'external_id' => "old-{$i}", 'provider' => 'saweria', 'donor_name' => "Lama {$i}",
            'amount' => 1000, 'currency' => 'IDR', 'status' => 'ignored', 'received_at' => now()->subDays(3), 'created_at' => now(), 'updated_at' => now(),
        ])->all());

        // Lewat tengah malam, sesi masih live: angka tidak ter-reset jadi 0.
        $this->travelTo(now()->setDate(2026, 11, 29)->setTime(0, 15));
        Livewire::test(DonationInbox::class)
            ->assertSeeHtml('<span>Donasi sesi ini</span><strong>1</strong>')
            ->assertSeeHtml('Semua <span>(206)</span>')
            ->assertSeeHtml('Diabaikan <span>(205)</span>')
            ->assertSee('Webhook aktif · terakhir diterima 21:30 WIB');
    }

    /** Daftar "Donasi terbaru" 10 per halaman, seperti daftar antrean. */
    public function test_inbox_lists_donations_ten_per_page(): void
    {
        $this->actingAs($this->streamer);
        Donation::insert(collect(range(1, 25))->map(fn ($i) => [
            'platform_connection_id' => $this->saweria->id, 'external_id' => "d-{$i}", 'provider' => 'saweria', 'donor_name' => sprintf('Donatur %02d', $i),
            'amount' => 1000, 'currency' => 'IDR', 'status' => $i <= 3 ? 'review' : 'ignored',
            'received_at' => now()->subMinutes(100 - $i), 'created_at' => now(), 'updated_at' => now(),
        ])->all());

        $inbox = Livewire::test(DonationInbox::class)
            ->assertSee('1–10 dari 25 donasi')
            ->assertSee('Donatur 25')->assertSee('Donatur 16')->assertDontSee('Donatur 15') // terbaru dulu
            ->assertSeeHtml('aria-label="Halaman daftar donasi"');
        $this->assertCount(10, $inbox->viewData('donations'));

        $inbox->call('goToPage', 3)->assertSee('21–25 dari 25 donasi')->assertSee('Donatur 01')->assertDontSee('Donatur 06');
        $this->assertCount(5, $inbox->viewData('donations'));

        // Halaman kebablasan dijepit ke halaman terakhir; ganti filter kembali ke halaman 1.
        $inbox->call('goToPage', 99)->assertSet('page', 3);
        $inbox->call('setStatus', 'review')->assertSet('page', 1)->assertSee('1–3 dari 3 donasi')
            ->assertDontSeeHtml('aria-label="Halaman daftar donasi"'); // satu halaman → tanpa tombol
    }

    public function test_connection_without_credential_rejects_all_webhooks(): void
    {
        $this->openLiveSession();
        $this->saweria->forceFill(['signing_secret' => null])->save();

        $this->sendSaweria($this->saweria, $this->saweriaPayload('sw-1', 'Penyusup', 15000), '')->assertUnauthorized();
        $this->assertSame(0, Donation::count());
    }

    // ── Koneksi & uji koneksi ────────────────────────────────────────────

    public function test_saving_connection_requires_secret_once_and_keeps_it_on_update(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->postJson('/api/platform-connections', ['provider' => 'trakteer', 'webhook_url' => 'https://example.test/a'])
            ->assertUnprocessable()->assertJsonValidationErrors(['signing_secret']);

        $this->postJson('/api/platform-connections', [
            'provider' => 'trakteer', 'webhook_url' => 'https://example.test/a', 'signing_secret' => self::TRAKTEER_TOKEN.'-panjang',
        ])->assertCreated()->assertJsonMissingPath('data.signing_secret')->assertJsonMissingPath('data.last_payload');

        $this->postJson('/api/platform-connections', ['provider' => 'trakteer', 'webhook_url' => 'https://example.test/b', 'label' => 'Trakteer utama'])
            ->assertCreated();

        $this->assertSame(self::TRAKTEER_TOKEN.'-panjang', $user->platformConnections()->sole()->signing_secret);
    }

    public function test_connection_health_check_blocks_internal_addresses(): void
    {
        Http::fake();
        $service = app(PlatformConnectionHealthService::class);

        foreach (['http://127.0.0.1/admin', 'http://169.254.169.254/latest/meta-data', 'http://10.0.0.5/', 'ftp://example.test/x'] as $url) {
            $this->saweria->forceFill(['webhook_url' => $url])->save();
            $checked = $service->check($this->saweria);

            $this->assertSame('error', $checked->status, $url);
        }

        Http::assertNothingSent();
    }

    public function test_connection_health_check_calls_public_endpoint(): void
    {
        Http::fake(['*' => Http::response('', 200)]);
        $this->saweria->forceFill(['webhook_url' => 'https://93.184.215.14/hook'])->save();

        $this->actingAs($this->streamer)
            ->postJson("/api/platform-connections/{$this->saweria->id}/test")
            ->assertOk()
            ->assertJsonPath('data.status', 'active');
    }
}
