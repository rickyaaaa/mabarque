<?php

namespace Tests\Feature;

use App\Models\Donation;
use App\Models\LiveSession;
use App\Models\PlatformConnection;
use App\Models\User;
use App\Services\PlatformConnectionHealthService;
use App\Services\Webhooks\SaweriaAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
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

        $this->streamer = User::factory()->create();
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

    public function test_unmatched_amount_goes_to_review_and_no_session_is_stored_only(): void
    {
        $this->sendSaweria($this->saweria, $this->saweriaPayload('sw-0', 'Sebelum live', 15000), self::KEY)
            ->assertCreated()->assertJsonPath('data.status', 'received');
        $this->assertNull(Donation::firstWhere('external_id', 'sw-0')->live_session_id);

        $session = $this->openLiveSession();
        $this->sendSaweria($this->saweria, $this->saweriaPayload('sw-1', 'Nadia', 20000), self::KEY)->assertJsonPath('data.status', 'review');
        $this->sendSaweria($this->saweria, $this->saweriaPayload('sw-2', 'Bimo', 50000), self::KEY)->assertJsonPath('data.status', 'review'); // paket nonaktif
        $this->assertSame(0, $session->queueEntries()->count());
    }

    public function test_non_donation_events_and_broken_payloads(): void
    {
        $this->sendSaweria($this->saweria, $this->saweriaPayload('sw-1', 'Raka', 15000, ['type' => 'subscription']), self::KEY)
            ->assertStatus(202)->assertJsonPath('data.status', 'ignored');

        $this->sendSaweria($this->saweria, $this->saweriaPayload('sw-2', '', 15000), self::KEY)
            ->assertUnprocessable()->assertJsonValidationErrors(['payload']);

        $this->sendSaweria($this->saweria, $this->saweriaPayload('sw-3', 'Raka', 15000), self::KEY)->assertCreated();
        $this->assertSame(1, Donation::count());
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

        $this->assertSame(2, $session->queueEntries()->count());
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

    public function test_sociabuzz_unknown_format_is_rejected_but_sample_kept_for_matching(): void
    {
        $sociabuzz = $this->connect('sociabuzz', 'sociabuzz-token-uji');

        $this->sendSociaBuzz($sociabuzz, ['donatur' => ['nama' => 'Raka'], 'nilai' => '15k'], 'sociabuzz-token-uji')
            ->assertUnprocessable();

        $sociabuzz->refresh();
        $this->assertSame('error', $sociabuzz->status);
        $this->assertSame('Raka', $sociabuzz->last_payload['payload']['donatur']['nama']);
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
