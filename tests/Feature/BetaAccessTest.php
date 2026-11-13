<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\BetaAccessApproved;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** Persetujuan akses private alpha: artisan command, API Super Admin, dan seeder demo. */
class BetaAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_beta_command_approves_pending_streamer_and_notifies(): void
    {
        Notification::fake();
        $nadia = User::factory()->pending()->create(['email' => 'nadia@example.com', 'slug' => 'nadia-gaming']);

        $this->artisan('mabarqueue:beta', ['user' => 'NADIA@example.com', 'status' => 'active'])
            ->expectsOutputToContain('sekarang berstatus active')
            ->assertSuccessful();

        $this->assertSame('active', $nadia->refresh()->beta_status);
        Notification::assertSentTo($nadia, BetaAccessApproved::class);

        // Pakai slug juga bisa; status sama tidak mengirim email lagi.
        $this->artisan('mabarqueue:beta', ['user' => 'nadia-gaming', 'status' => 'active'])
            ->expectsOutputToContain('tidak ada perubahan')
            ->assertSuccessful();
        Notification::assertSentToTimes($nadia, BetaAccessApproved::class, 1);
    }

    public function test_approved_streamer_can_reach_dashboard_and_deactivated_cannot(): void
    {
        Notification::fake();
        $nadia = User::factory()->pending()->create();

        $this->actingAs($nadia)->getJson('/api/vip-packages')->assertForbidden();

        $this->artisan('mabarqueue:beta', ['user' => $nadia->email, 'status' => 'active'])->assertSuccessful();
        $this->actingAs($nadia->refresh())->getJson('/api/vip-packages')->assertOk();

        $oldRememberToken = $nadia->remember_token;
        $this->artisan('mabarqueue:beta', ['user' => $nadia->email, 'status' => 'inactive'])->assertSuccessful();
        $nadia->refresh();
        $this->assertNotSame($oldRememberToken, $nadia->remember_token);
        $this->actingAs($nadia)->getJson('/api/vip-packages')->assertForbidden();
    }

    public function test_beta_command_rejects_bad_input_and_super_admin_target(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $streamer = User::factory()->pending()->create();

        $this->artisan('mabarqueue:beta', ['user' => 'tidak-ada@example.com', 'status' => 'active'])->assertFailed();
        $this->artisan('mabarqueue:beta', ['user' => $streamer->email, 'status' => 'approved'])->assertExitCode(2);
        $this->artisan('mabarqueue:beta', ['user' => $admin->email, 'status' => 'inactive'])->assertExitCode(2);
        $this->assertSame('active', $admin->refresh()->beta_status);
    }

    public function test_create_admin_command_with_generated_password(): void
    {
        $this->artisan('mabarqueue:create-admin', ['email' => 'Boss@Example.com', '--generate' => true])
            ->expectsOutputToContain('Super Admin siap: boss@example.com')
            ->assertSuccessful();

        $admin = User::firstWhere('email', 'boss@example.com');
        $this->assertTrue($admin->isSuperAdmin());
        $this->assertTrue($admin->hasActiveBeta());
        $this->assertStringStartsWith('sa-', $admin->slug);
    }

    public function test_create_admin_command_with_typed_password_and_validation(): void
    {
        $this->artisan('mabarqueue:create-admin', ['email' => 'boss@example.com'])
            ->expectsQuestion('Password Super Admin (min 12 karakter, huruf + angka)', 'pendek1')
            ->expectsQuestion('Ulangi password', 'pendek1')
            ->assertExitCode(2);
        $this->assertDatabaseMissing('users', ['email' => 'boss@example.com']);

        $this->artisan('mabarqueue:create-admin', ['email' => 'boss@example.com'])
            ->expectsQuestion('Password Super Admin (min 12 karakter, huruf + angka)', 'AdminKuat2026x')
            ->expectsQuestion('Ulangi password', 'AdminKuat2026x')
            ->assertSuccessful();
        $this->assertTrue(Hash::check('AdminKuat2026x', User::firstWhere('email', 'boss@example.com')->password));
    }

    public function test_streamers_command_lists_pending_queue(): void
    {
        User::factory()->pending()->create(['email' => 'nadia@example.com']);
        User::factory()->create(['email' => 'andra@example.com']);

        $this->artisan('mabarqueue:streamers', ['--status' => 'pending'])
            ->expectsOutputToContain('nadia@example.com')
            ->doesntExpectOutputToContain('andra@example.com')
            ->assertSuccessful();
    }

    public function test_admin_api_is_hidden_from_streamers_and_lets_super_admin_manage_beta(): void
    {
        Notification::fake();
        $streamer = User::factory()->create();
        $pending = User::factory()->pending()->create(['channel_name' => 'Nadia Gaming']);
        $admin = User::factory()->superAdmin()->create();

        $this->getJson('/api/admin/streamers')->assertUnauthorized();
        $this->actingAs($streamer)->getJson('/api/admin/streamers')->assertNotFound();
        $this->actingAs($streamer)->patchJson("/api/admin/streamers/{$pending->id}/beta-status", ['beta_status' => 'active'])->assertNotFound();

        $this->actingAs($admin)->getJson('/api/admin/streamers?status=pending')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.channel_name', 'Nadia Gaming')
            ->assertJsonMissingPath('data.0.password');

        $this->patchJson("/api/admin/streamers/{$pending->id}/beta-status", ['beta_status' => 'active'])
            ->assertOk()
            ->assertJsonPath('data.beta_status', 'active');
        Notification::assertSentTo($pending, BetaAccessApproved::class);

        $this->patchJson("/api/admin/streamers/{$pending->id}/beta-status", ['beta_status' => 'banned'])->assertUnprocessable();
        $this->patchJson("/api/admin/streamers/{$admin->id}/beta-status", ['beta_status' => 'inactive'])->assertNotFound();
    }

    public function test_demo_seeder_creates_working_accounts(): void
    {
        $this->seed();

        $this->assertTrue(User::firstWhere('email', 'admin@mabarqueue.test')->isSuperAdmin());
        $this->assertSame(3, User::firstWhere('slug', 'andrarizky')->vipPackages()->count());
        $this->assertSame('pending', User::firstWhere('slug', 'nadia-gaming')->beta_status);

        $this->postJson('/api/auth/login', ['email' => 'andra@mabarqueue.test', 'password' => 'password123'])->assertOk();
        $this->getJson('/api/channels/andrarizky/vip-packages')->assertOk()->assertJsonCount(3, 'data');
    }
}
