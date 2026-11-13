<?php

namespace Tests\Feature;

use App\Models\LiveSession;
use App\Models\MatchRecord;
use App\Models\PlatformConnection;
use App\Models\QueueEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Profil streamer, halaman publik by slug, dan isolasi data antar-streamer. */
class ProfileAndOwnershipTest extends TestCase
{
    use RefreshDatabase;

    private function sessionWithEntry(User $owner): array
    {
        $session = LiveSession::create(['user_id' => $owner->id, 'title' => 'Mabar malam', 'status' => 'active', 'started_at' => now()]);
        $entry = QueueEntry::create([
            'live_session_id' => $session->id, 'player_name' => 'Raka ML', 'matches_total' => 3, 'status' => 'ready', 'position' => 1,
        ]);

        return [$session, $entry];
    }

    public function test_profile_update_validates_and_saves(): void
    {
        $user = User::factory()->create(['slug' => 'andra']);
        User::factory()->create(['slug' => 'rrq']);

        $this->actingAs($user)->putJson('/api/profile', [
            'channel_name' => 'Andra Rizky', 'slug' => 'rrq', 'profile_accent' => '#f8a917',
        ])->assertJsonValidationErrors(['slug']);

        $this->putJson('/api/profile', [
            'channel_name' => 'Andra Rizky', 'slug' => 'Andra-Live', 'tagline' => 'Push mythic bareng viewer',
            'main_role' => 'Jungler', 'live_schedule' => 'Rabu & Sabtu, 19:00 WIB',
            'avatar_initials' => 'ar', 'profile_accent' => '#6B63FF',
        ])->assertOk()
            ->assertJsonPath('data.slug', 'andra-live')
            ->assertJsonPath('data.main_role', 'jungler')
            ->assertJsonPath('data.avatar_initials', 'AR')
            ->assertJsonPath('data.profile_accent', '#6b63ff');

        // Slug lama milik sendiri boleh dipakai lagi (unique mengabaikan diri sendiri).
        $this->putJson('/api/profile', ['channel_name' => 'Andra Rizky', 'slug' => 'andra-live', 'profile_accent' => '#6b63ff'])->assertOk();
    }

    public function test_public_channel_shows_only_safe_fields_and_hides_pending_accounts(): void
    {
        $active = User::factory()->create(['slug' => 'andra', 'channel_name' => 'Andra Rizky', 'main_role' => 'mid']);
        User::factory()->pending()->create(['slug' => 'baru']);

        $this->getJson('/api/channels/andra')
            ->assertOk()
            ->assertJsonPath('data.channel_name', 'Andra Rizky')
            ->assertJsonPath('data.main_role', 'Mid Lane')
            ->assertJsonPath('data.is_live', false)
            ->assertJsonMissingPath('data.email')
            ->assertJsonMissingPath('data.beta_status');

        $this->getJson('/api/channels/baru')->assertNotFound();
        $this->getJson('/api/channels/baru/queue')->assertNotFound();
        $this->getJson('/api/channels/'.$active->id)->assertNotFound(); // ID angka tidak lagi bisa dipakai

        $this->sessionWithEntry($active);
        $this->getJson('/api/channels/andra/queue')
            ->assertOk()
            ->assertJsonPath('data.status', 'live')
            ->assertJsonPath('data.channel.is_live', true)
            ->assertJsonPath('data.queue.0.player_name', 'Raka ML');
    }

    public function test_match_routes_are_protected_and_owner_scoped(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        [$session, $entry] = $this->sessionWithEntry($owner);

        $this->postJson("/api/queue-entries/{$entry->id}/matches", [], ['Idempotency-Key' => 'k1'])->assertUnauthorized();

        $this->actingAs($intruder)
            ->postJson("/api/queue-entries/{$entry->id}/matches", [], ['Idempotency-Key' => 'k1'])
            ->assertNotFound();
        $this->getJson("/api/live-sessions/{$session->id}/matches/today")->assertNotFound();

        $this->actingAs($owner)
            ->postJson("/api/queue-entries/{$entry->id}/matches", [], ['Idempotency-Key' => 'k1'])
            ->assertCreated()
            ->assertJsonPath('data.matches_remaining', 2);

        $match = MatchRecord::firstOrFail();
        $this->actingAs($intruder)->deleteJson("/api/matches/{$match->id}")->assertNotFound();
        $this->actingAs($owner)->deleteJson("/api/matches/{$match->id}")->assertOk()->assertJsonPath('matches_remaining', 3);
    }

    public function test_platform_connections_are_owner_scoped(): void
    {
        $owner = User::factory()->create();
        $connection = PlatformConnection::create([
            'user_id' => $owner->id, 'provider' => 'saweria', 'webhook_url' => 'https://example.test/hook', 'status' => 'active',
        ]);

        $this->actingAs(User::factory()->create())->getJson("/api/platform-connections/{$connection->id}")->assertNotFound();
        $this->actingAs($owner)->getJson("/api/platform-connections/{$connection->id}")->assertOk()->assertJsonMissingPath('data.signing_secret');
    }

    public function test_user_ownership_helpers(): void
    {
        $owner = User::factory()->create();
        [$session, $entry] = $this->sessionWithEntry($owner);
        $other = User::factory()->create();

        $this->assertTrue($owner->owns($session));
        $this->assertTrue($owner->owns($entry));
        $this->assertFalse($other->owns($entry));
        $this->assertSame(1, QueueEntry::ownedBy($owner)->count());
        $this->assertSame(0, QueueEntry::ownedBy($other)->count());
        $this->assertSame(1, $owner->queueEntries()->count());
    }
}
