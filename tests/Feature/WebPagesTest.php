<?php

namespace Tests\Feature;

use App\Models\LiveSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Halaman web: proteksi login/beta, halaman publik /{slug}, dan aset prototipe yang dipindah ke public/. */
class WebPagesTest extends TestCase
{
    use RefreshDatabase;

    private const DASHBOARD_PAGES = ['/dashboard', '/live-session', '/match-count', '/vip-packages', '/donations', '/overlay-settings'];

    public function test_root_redirects_by_auth_state(): void
    {
        $this->get('/')->assertRedirect('/login');
        $this->actingAs(User::factory()->create())->get('/')->assertRedirect('/dashboard');
    }

    public function test_guest_pages_render_with_csrf_and_api_helper(): void
    {
        foreach (['/login', '/signup', '/forgot-password'] as $page) {
            $this->get($page)->assertOk()->assertSee('name="csrf-token"', false)->assertSee('js/api.js', false);
        }
    }

    public function test_logged_in_user_is_sent_away_from_login_and_signup(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/login')->assertRedirect('/live-session');
        $this->get('/signup')->assertRedirect('/live-session');
        $this->get('/forgot-password')->assertOk(); // link reset dari email harus tetap bisa dibuka
    }

    public function test_dashboard_pages_require_login(): void
    {
        foreach ([...self::DASHBOARD_PAGES, '/account', '/profile'] as $page) {
            $this->get($page)->assertRedirect('/login');
        }
    }

    public function test_pending_streamer_is_redirected_to_account_with_notice(): void
    {
        $this->actingAs(User::factory()->pending()->create());

        foreach (self::DASHBOARD_PAGES as $page) {
            $this->get($page)->assertRedirect('/account')->assertSessionHas('beta_notice');
        }

        $this->get('/account')->assertOk()->assertSee('Menunggu persetujuan');
        $this->get('/profile')->assertOk();
    }

    public function test_active_streamer_can_open_every_page(): void
    {
        $this->actingAs(User::factory()->create());

        foreach ([...self::DASHBOARD_PAGES, '/account', '/profile'] as $page) {
            $this->get($page)->assertOk();
        }
    }

    public function test_account_page_shows_real_workspace_data(): void
    {
        $user = User::factory()->create(['channel_name' => 'Andra Rizky', 'slug' => 'andra', 'email' => 'andra@example.com']);
        $session = LiveSession::create(['user_id' => $user->id, 'title' => 'Rabu malam bareng viewer', 'status' => 'active', 'started_at' => now()]);
        $session->queueEntries()->createMany([
            ['player_name' => 'Raka', 'matches_total' => 3, 'status' => 'ready', 'position' => 1],
            ['player_name' => 'Nadia', 'matches_total' => 1, 'status' => 'offline', 'position' => 2],
        ]);

        $this->actingAs($user)->get('/account')
            ->assertOk()
            ->assertSee('Andra Rizky')
            ->assertSee('@andra')
            ->assertSee('andra@example.com')
            ->assertSee('Rabu malam bareng viewer')
            ->assertSee('2 pemain')
            ->assertSee('1 siap bermain · 1 offline')
            ->assertSee('Belum terhubung')
            ->assertDontSee('Data tiruan');
    }

    public function test_public_channel_page_by_slug(): void
    {
        User::factory()->create(['slug' => 'andra', 'channel_name' => 'Andra Rizky']);
        User::factory()->pending()->create(['slug' => 'baru']);

        $this->get('/andra')->assertOk()->assertSee('Andra Rizky')->assertSee('data-channel-id="andra"', false);
        $this->get('/ANDRA')->assertOk();
        $this->get('/baru')->assertNotFound();
        $this->get('/tidak-ada')->assertNotFound();
    }

    public function test_page_paths_are_reserved_slugs(): void
    {
        $this->postJson('/api/auth/register', [
            'name' => 'Penyusup', 'email' => 'x@example.com', 'handle' => 'dashboard',
            'password' => 'rahasia123', 'password_confirmation' => 'rahasia123', 'consent' => true,
        ])->assertJsonValidationErrors(['handle']);
    }

    public function test_prototype_assets_are_served_from_public(): void
    {
        foreach (['css/styles.css', 'css/login.css', 'js/api.js', 'js/login.js', 'js/profile-settings.js'] as $asset) {
            $this->assertFileExists(public_path($asset));
        }
    }
}
