<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\ResetStreamerPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** Alur akun streamer: daftar, masuk/keluar, proteksi beta, dan reset password. */
class AuthFlowTest extends TestCase
{
    use RefreshDatabase;

    /** Header supaya Sanctum memperlakukan request sebagai SPA same-domain (session aktif). */
    private array $spa = ['Referer' => 'http://localhost', 'Accept' => 'application/json'];

    private function registerPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Nadia Gaming',
            'email' => 'nadia@example.com',
            'handle' => '@Nadia_Gaming',
            'password' => 'rahasia123',
            'password_confirmation' => 'rahasia123',
            'consent' => true,
        ], $overrides);
    }

    public function test_registration_creates_pending_streamer_with_default_overlay(): void
    {
        $response = $this->withHeaders($this->spa)->postJson('/api/auth/register', $this->registerPayload());

        $response->assertCreated()
            ->assertJsonPath('data.slug', 'nadia-gaming')
            ->assertJsonPath('data.beta_status', 'pending')
            ->assertJsonMissingPath('data.password');

        $user = User::firstWhere('email', 'nadia@example.com');
        $this->assertSame(User::ROLE_STREAMER, $user->role);
        $this->assertNotNull($user->overlaySetting);
        $this->assertNotSame('rahasia123', $user->password);
    }

    public function test_registration_rejects_duplicate_email_case_insensitively(): void
    {
        User::factory()->create(['email' => 'nadia@example.com']);

        $this->postJson('/api/auth/register', $this->registerPayload(['email' => '  NADIA@Example.com ']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_registration_rejects_reserved_or_taken_handles_and_role_injection(): void
    {
        User::factory()->create(['slug' => 'rrq']);

        $this->postJson('/api/auth/register', $this->registerPayload(['handle' => 'admin']))
            ->assertJsonValidationErrors(['handle']);
        $this->postJson('/api/auth/register', $this->registerPayload(['handle' => 'rrq']))
            ->assertJsonValidationErrors(['handle']);

        $this->postJson('/api/auth/register', $this->registerPayload(['role' => 'super_admin', 'beta_status' => 'active']))
            ->assertCreated()
            ->assertJsonPath('data.role', 'streamer')
            ->assertJsonPath('data.beta_status', 'pending');
    }

    public function test_login_logout_and_me(): void
    {
        $user = User::factory()->create(['email' => 'andra@example.com']);

        $this->withHeaders($this->spa)->postJson('/api/auth/login', ['email' => 'andra@example.com', 'password' => 'salah123'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.email.0', 'Email atau password salah.');

        $this->withHeaders($this->spa)->postJson('/api/auth/login', ['email' => 'ANDRA@example.com', 'password' => 'password123'])
            ->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('redirect', '/live-session');

        $this->withHeaders($this->spa)->getJson('/api/auth/me')->assertOk()->assertJsonPath('data.email', 'andra@example.com');

        $this->withHeaders($this->spa)->postJson('/api/auth/logout')->assertOk();
        $this->assertGuest('web');
    }

    public function test_login_is_rate_limited_after_five_failures(): void
    {
        User::factory()->create(['email' => 'andra@example.com']);

        foreach (range(1, 5) as $_) {
            $this->postJson('/api/auth/login', ['email' => 'andra@example.com', 'password' => 'salah123'])->assertUnprocessable();
        }

        $this->postJson('/api/auth/login', ['email' => 'andra@example.com', 'password' => 'password123'])->assertStatus(429);
    }

    public function test_inactive_account_cannot_log_in(): void
    {
        User::factory()->inactive()->create(['email' => 'off@example.com']);

        $this->postJson('/api/auth/login', ['email' => 'off@example.com', 'password' => 'password123'])
            ->assertUnprocessable();
        $this->assertGuest('web');
    }

    public function test_dashboard_routes_require_login_and_active_beta(): void
    {
        $this->getJson('/api/vip-packages')->assertUnauthorized();

        $this->actingAs(User::factory()->pending()->create())
            ->getJson('/api/vip-packages')
            ->assertForbidden()
            ->assertJsonPath('beta_status', 'pending');

        $this->actingAs(User::factory()->create())->getJson('/api/vip-packages')->assertOk();
    }

    public function test_pending_account_can_still_read_me_and_profile(): void
    {
        $this->actingAs(User::factory()->pending()->create())->getJson('/api/auth/me')->assertOk();
        $this->getJson('/api/profile')->assertOk();
    }

    public function test_forgot_password_is_generic_and_reset_rotates_password(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'andra@example.com']);

        $known = $this->postJson('/api/auth/forgot-password', ['email' => 'andra@example.com'])->assertOk()->json('message');
        $unknown = $this->postJson('/api/auth/forgot-password', ['email' => 'nobody@example.com'])->assertOk()->json('message');
        $this->assertSame($known, $unknown, 'Pesan harus sama supaya tidak membocorkan akun terdaftar.');

        $token = null;
        Notification::assertSentTo($user, ResetStreamerPassword::class, function (ResetStreamerPassword $notification) use (&$token, $user) {
            $token = $notification->token;

            return str_contains($notification->resetUrl($user), '/forgot-password?token=');
        });

        $this->postJson('/api/auth/reset-password', [
            'token' => 'token-palsu', 'email' => 'andra@example.com',
            'password' => 'BaruSekali9', 'password_confirmation' => 'BaruSekali9',
        ])->assertUnprocessable()->assertJsonValidationErrors(['token']);

        $this->postJson('/api/auth/reset-password', [
            'token' => $token, 'email' => 'andra@example.com',
            'password' => 'BaruSekali9', 'password_confirmation' => 'BaruSekali9',
        ])->assertOk();

        $this->postJson('/api/auth/login', ['email' => 'andra@example.com', 'password' => 'password123'])->assertUnprocessable();
        $this->postJson('/api/auth/login', ['email' => 'andra@example.com', 'password' => 'BaruSekali9'])->assertOk();
    }
}
