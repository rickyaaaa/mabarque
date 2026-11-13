<?php

namespace Tests\Feature;

use App\Livewire\AdminPanel;
use App\Models\User;
use App\Notifications\BetaAccessApproved;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

/** Login gabungan, area dipisah: Super Admin → /admin, streamer → workspace; panel persetujuan beta. */
class AdminPanelTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_redirects_each_role_to_its_area(): void
    {
        User::factory()->superAdmin()->create(['email' => 'boss@example.com']);
        User::factory()->create(['email' => 'andra@example.com']);
        User::factory()->pending()->create(['email' => 'baru@example.com']);

        foreach (['boss@example.com' => '/admin', 'andra@example.com' => '/live-session', 'baru@example.com' => '/account'] as $email => $path) {
            $this->postJson('/api/auth/login', ['email' => $email, 'password' => 'password123'])
                ->assertOk()->assertJsonPath('redirect', $path);
            auth()->guard('web')->logout();
        }
    }

    public function test_super_admin_is_kept_out_of_streamer_workspace(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create());

        $this->get('/')->assertRedirect('/admin');
        $this->get('/login')->assertRedirect('/admin');
        foreach (['/dashboard', '/live-session', '/donations', '/account', '/profile'] as $page) {
            $this->get($page)->assertRedirect('/admin');
        }
        $this->get('/admin')->assertOk()->assertSeeLivewire(AdminPanel::class);
    }

    public function test_admin_area_is_hidden_from_streamers_and_guests(): void
    {
        $this->get('/admin')->assertRedirect('/login');

        $this->actingAs(User::factory()->create());
        $this->get('/admin')->assertNotFound();
        Livewire::test(AdminPanel::class)->assertStatus(404);
    }

    public function test_panel_lists_pending_first_searches_and_approves(): void
    {
        Notification::fake();
        $this->actingAs(User::factory()->superAdmin()->create());
        $nadia = User::factory()->pending()->create(['channel_name' => 'Nadia Gaming', 'email' => 'nadia@example.com', 'slug' => 'nadia-gaming']);
        User::factory()->create(['channel_name' => 'Andra Rizky', 'slug' => 'andra']);

        $panel = Livewire::test(AdminPanel::class)
            ->assertSet('status', 'pending')
            ->assertSee('Nadia Gaming')
            ->assertDontSee('Andra Rizky')
            ->assertSeeHtml('Menunggu persetujuan <span>(1)</span>');

        $panel->call('setBetaStatus', $nadia->id, 'active')
            ->assertDispatched('notify', message: '@nadia-gaming disetujui — email pemberitahuan dikirim.')
            ->assertSee('Tidak ada akun yang menunggu persetujuan');

        $this->assertSame('active', $nadia->refresh()->beta_status);
        Notification::assertSentTo($nadia, BetaAccessApproved::class);

        $panel->call('setFilter', 'all')->set('search', 'andra')->assertSee('Andra Rizky')->assertDontSee('Nadia Gaming');
    }

    public function test_panel_deactivates_and_cannot_target_super_admins(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin);
        $andra = User::factory()->create(['slug' => 'andra']);

        Livewire::test(AdminPanel::class)->call('setFilter', 'active')->call('setBetaStatus', $andra->id, 'inactive');
        $this->assertSame('inactive', $andra->refresh()->beta_status);

        Livewire::test(AdminPanel::class)->call('setBetaStatus', $admin->id, 'inactive')->assertStatus(404);
        $this->assertSame('active', $admin->refresh()->beta_status);
    }

    public function test_panel_logout(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create());

        Livewire::test(AdminPanel::class)->call('logout')->assertRedirect('/login?notice=logout');
        $this->assertGuest('web');
    }
}
