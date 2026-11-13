<?php

namespace Tests\Feature;

use App\Models\Donation;
use App\Models\LiveSession;
use App\Models\PlatformConnection;
use App\Models\User;
use App\Services\PlatformConnectionHealthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Webhook donasi → validasi signature → donasi tercatat → otomatis masuk antrean sesuai paket. */
class DonationWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'rahasia-webhook-uji-0123456789';

    private User $streamer;

    private PlatformConnection $connection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->streamer = User::factory()->create();
        $this->connection = PlatformConnection::create([
            'user_id' => $this->streamer->id, 'provider' => 'saweria', 'webhook_url' => 'https://example.test/hook',
            'signing_secret' => self::SECRET, 'status' => 'pending',
        ]);
        $this->streamer->vipPackages()->createMany([
            ['name' => 'VIP 1 Match', 'price' => 15000, 'match_count' => 1, 'sort_order' => 1],
            ['name' => 'VIP 3 Match', 'price' => 35000, 'match_count' => 3, 'sort_order' => 2],
            ['name' => 'VIP Lama', 'price' => 50000, 'match_count' => 5, 'sort_order' => 3, 'is_active' => false],
        ]);
    }

    private function openLiveSession(): LiveSession
    {
        return LiveSession::create(['user_id' => $this->streamer->id, 'title' => 'Mabar', 'status' => 'active', 'started_at' => now()]);
    }

    private function sendWebhook(array $payload, ?string $secret = self::SECRET, string $provider = 'saweria')
    {
        $headers = $secret === null ? [] : ['X-Webhook-Signature' => hash_hmac('sha256', json_encode($payload), $secret)];

        return $this->postJson("/api/webhooks/donations/{$provider}/{$this->connection->id}", $payload, $headers);
    }

    public function test_matching_donation_is_queued_with_package_quota(): void
    {
        $session = $this->openLiveSession();

        $this->sendWebhook(['id' => 'trx-1', 'donor_name' => 'Raka ML', 'amount' => 35000])
            ->assertCreated()
            ->assertJsonPath('data.status', 'queued')
            ->assertJsonPath('data.duplicate', false);

        $entry = $session->queueEntries()->sole();
        $this->assertSame('Raka ML', $entry->player_name);
        $this->assertSame('saweria', $entry->provider);
        $this->assertSame(3, $entry->matches_total);
        $this->assertSame('ready', $entry->status);

        $session->refresh();
        $this->assertSame(1, $session->total_players);
        $this->assertSame(35000, $session->total_donation);
        $this->assertSame('active', $this->connection->refresh()->status);
    }

    public function test_duplicate_webhook_is_idempotent(): void
    {
        $session = $this->openLiveSession();
        $payload = ['transaction_id' => 'trx-1', 'donator_name' => 'Raka ML', 'donation_amount' => 15000];

        $this->sendWebhook($payload)->assertCreated();
        $this->sendWebhook($payload)->assertOk()->assertJsonPath('data.duplicate', true);

        $this->assertSame(1, Donation::count());
        $this->assertSame(1, $session->queueEntries()->count());
        $this->assertSame(15000, $session->refresh()->total_donation);
    }

    public function test_unmatched_or_inactive_package_amount_goes_to_review(): void
    {
        $session = $this->openLiveSession();

        $this->sendWebhook(['id' => 'trx-1', 'donor_name' => 'Nadia', 'amount' => 20000])->assertJsonPath('data.status', 'review');
        $this->sendWebhook(['id' => 'trx-2', 'donor_name' => 'Bimo', 'amount' => 50000])->assertJsonPath('data.status', 'review');

        $this->assertSame(0, $session->queueEntries()->count());
    }

    public function test_donation_without_active_session_is_stored_but_not_queued(): void
    {
        $this->sendWebhook(['id' => 'trx-1', 'donor_name' => 'Raka', 'amount' => 15000])
            ->assertCreated()
            ->assertJsonPath('data.status', 'received');

        $this->assertNull(Donation::sole()->live_session_id);
    }

    public function test_webhook_rejects_bad_or_missing_signature(): void
    {
        $this->openLiveSession();
        $payload = ['id' => 'trx-1', 'donor_name' => 'Penyusup', 'amount' => 15000];

        $this->sendWebhook($payload, null)->assertUnauthorized();
        $this->sendWebhook($payload, 'secret-yang-salah-0000000000')->assertUnauthorized();
        $this->assertSame(0, Donation::count());
    }

    public function test_connection_without_secret_rejects_all_webhooks(): void
    {
        $this->openLiveSession();
        $this->connection->forceFill(['signing_secret' => null])->save();

        $this->sendWebhook(['id' => 'trx-1', 'donor_name' => 'Penyusup', 'amount' => 15000], null)->assertUnauthorized();
        $this->assertSame(0, Donation::count());
    }

    public function test_webhook_validates_provider_and_payload(): void
    {
        $this->sendWebhook(['id' => 'trx-1', 'donor_name' => 'Raka', 'amount' => 15000], provider: 'trakteer')
            ->assertUnprocessable()->assertJsonValidationErrors(['provider']);

        $this->sendWebhook(['id' => 'trx-1', 'donor_name' => 'Raka', 'amount' => -5])
            ->assertUnprocessable()->assertJsonValidationErrors(['payload']);

        $this->sendWebhook(['donor_name' => 'Raka', 'amount' => 15000])
            ->assertUnprocessable()->assertJsonValidationErrors(['payload']);
    }

    public function test_saving_connection_requires_secret_once_and_keeps_it_on_update(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->postJson('/api/platform-connections', ['provider' => 'trakteer', 'webhook_url' => 'https://example.test/a'])
            ->assertUnprocessable()->assertJsonValidationErrors(['signing_secret']);

        $this->postJson('/api/platform-connections', [
            'provider' => 'trakteer', 'webhook_url' => 'https://example.test/a', 'signing_secret' => self::SECRET,
        ])->assertCreated()->assertJsonMissingPath('data.signing_secret');

        // Update label/URL tanpa secret: secret lama harus tetap ada.
        $this->postJson('/api/platform-connections', [
            'provider' => 'trakteer', 'webhook_url' => 'https://example.test/b', 'label' => 'Trakteer utama',
        ])->assertCreated();

        $connection = $user->platformConnections()->sole();
        $this->assertSame(self::SECRET, $connection->signing_secret);
        $this->assertSame('https://example.test/b', $connection->webhook_url);
    }

    public function test_connection_health_check_blocks_internal_addresses(): void
    {
        Http::fake();
        $service = app(PlatformConnectionHealthService::class);

        foreach (['http://127.0.0.1/admin', 'http://169.254.169.254/latest/meta-data', 'http://10.0.0.5/', 'ftp://example.test/x'] as $url) {
            $this->connection->forceFill(['webhook_url' => $url])->save();
            $checked = $service->check($this->connection);

            $this->assertSame('error', $checked->status, $url);
            $this->assertNotNull($checked->last_error);
        }

        Http::assertNothingSent();
    }

    public function test_connection_health_check_calls_public_endpoint(): void
    {
        Http::fake(['*' => Http::response('', 200)]);
        $this->connection->forceFill(['webhook_url' => 'https://93.184.215.14/hook'])->save();

        $this->actingAs($this->streamer)
            ->postJson("/api/platform-connections/{$this->connection->id}/test")
            ->assertOk()
            ->assertJsonPath('data.status', 'active');

        Http::assertSentCount(1);
    }
}
