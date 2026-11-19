<?php

namespace Tests\Feature;

use App\Livewire\LiveSessionPanel;
use App\Livewire\MatchBoard;
use App\Livewire\QueueBoard;
use App\Models\LiveSession;
use App\Models\QueueEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Tahap B frontend: papan antrean, panel sesi live, dan hitungan match (Livewire). */
class LiveDashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $streamer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->streamer = User::factory()->create();
        $this->actingAs($this->streamer);
    }

    private function liveSession(): LiveSession
    {
        return LiveSession::create(['user_id' => $this->streamer->id, 'title' => 'Rabu malam', 'status' => 'active', 'started_at' => now()]);
    }

    private function player(LiveSession $session, string $name, int $quota, string $status = 'ready', int $position = 1): QueueEntry
    {
        return QueueEntry::create([
            'live_session_id' => $session->id, 'player_name' => $name, 'matches_total' => $quota, 'status' => $status, 'position' => $position,
        ]);
    }

    public function test_board_without_live_manages_players_but_not_the_party(): void
    {
        $this->get('/dashboard')->assertOk()->assertSee('Belum live');
        $standby = app(\App\Services\LiveSessionService::class)->queueSession($this->streamer);
        $this->assertSame('ended', $standby->status);

        $board = Livewire::test(QueueBoard::class, ['session' => $standby])
            ->set('playerName', 'Kiki ML')
            ->set('matchesTotal', 3)
            ->call('addPlayer')
            ->assertHasNoErrors()
            ->assertSee('Kiki ML')
            ->assertSee('Party diisi saat live')
            ->call('startGame')
            ->assertHasErrors(['queue'])
            ->assertSee('Mulai sesi live dulu untuk mengatur party');

        // Live dimulai (mis. dari tab lain): pemain ikut pindah, papan lama memuat ulang ke sesi baru.
        $live = app(\App\Services\LiveSessionService::class)->start($this->streamer, 'Live Senin');
        $this->assertSame(['Kiki ML'], $live->queueEntries()->pluck('player_name')->all());
        $board->call('refreshQueue')->assertRedirect('/dashboard');
    }

    public function test_archived_session_recap_is_view_only(): void
    {
        $this->streamer->forceFill(['party_size' => 2])->save();
        $sessions = app(\App\Services\LiveSessionService::class);
        $queue = app(\App\Services\QueueService::class);
        $session = $sessions->start($this->streamer, 'Mabar VIP Minggu');
        $queue->addPlayer($session, ['player_name' => 'Dey', 'mlbb_id' => '1185010926', 'matches_total' => 3]);
        $queue->addPlayer($session, ['player_name' => 'Indira', 'matches_total' => 1]);
        $queue->fillParty($session->refresh());
        $queue->finishGame($session); // Dey sisa 2, Indira habis

        $session->donations()->create([
            'platform_connection_id' => \App\Models\PlatformConnection::create(['user_id' => $this->streamer->id, 'provider' => 'saweria', 'webhook_url' => '', 'signing_secret' => 'x', 'status' => 'active'])->id,
            'external_id' => 'd1', 'provider' => 'saweria', 'donor_name' => 'Kyoto', 'amount' => 25000, 'message' => 'mabar 138765201', 'status' => 'queued', 'received_at' => now(),
        ]);

        $sessions->end($this->streamer, $session);
        $sessions->start($this->streamer, 'Live Senin'); // Dey pindah ke sesi baru, rekap tetap utuh

        $this->get('/live-session')->assertOk()->assertSee('/live-session/'.$session->id);
        $this->get('/live-session/'.$session->id)->assertOk()
            ->assertSee('Mabar VIP Minggu')
            ->assertSee('hanya dilihat')
            ->assertSee('Dey · Indira')        // lineup game 1
            ->assertSee('ID 1185010926')
            ->assertSee('2 game')              // sisa Dey saat sesi berakhir
            ->assertSee('Habis')               // Indira
            ->assertSee('Kyoto')
            ->assertSee('Rp 25.000')
            ->assertDontSee('wire:click');     // tidak ada aksi ubah

        $other = User::factory()->create();
        $this->actingAs($other)->get('/live-session/'.$session->id)->assertNotFound();

        $active = $this->streamer->liveSessions()->where('status', 'active')->first();
        $this->actingAs($this->streamer)->get('/live-session/'.$active->id)->assertRedirect('/dashboard');
    }

    public function test_pages_without_active_session_invite_to_start_one(): void
    {
        // Papan antrean tetap bisa dibuka tanpa live untuk mengelola daftar sisa game.
        $this->get('/dashboard')->assertOk()->assertSee('Belum live')->assertSee('Tempel banyak pemain')->assertSee('Mulai sesi live');
        $this->get('/match-count')->assertOk()->assertSee('Mulai sesi live dulu');
        $this->get('/live-session')->assertOk()->assertSee('Mulai sesi sekarang');
    }

    public function test_pages_with_active_session_render_livewire_components(): void
    {
        $session = $this->liveSession();
        $this->player($session, 'Raka ML', 3);

        $this->get('/dashboard')->assertOk()->assertSeeLivewire(QueueBoard::class)->assertSee('Raka ML')->assertSee('Rabu malam');
        $this->get('/match-count')->assertOk()->assertSeeLivewire(MatchBoard::class)->assertSee('Raka ML');
        $this->get('/live-session')->assertOk()->assertSeeLivewire(LiveSessionPanel::class)->assertSee('Rabu malam');
    }

    public function test_queue_board_finish_match_counts_and_passes_turn_when_quota_runs_out(): void
    {
        $session = $this->liveSession();
        $raka = $this->player($session, 'Raka ML', 2, 'playing', 1);
        $nadia = $this->player($session, 'Nadia', 3, 'ready', 2);
        $ida = $this->player($session, 'Ida', 4, 'ready', 3);

        $board = Livewire::test(QueueBoard::class, ['session' => $session])
            ->call('finishMatch', $raka->id)
            ->assertDispatched('notify', message: 'Game dicatat. Raka ML sisa 1 game.');

        $this->assertSame(1, $raka->refresh()->matches_played);
        $this->assertSame('playing', $raka->status);

        $board->call('finishMatch', $raka->id)
            ->assertDispatched('notify', message: 'Sisa game Raka ML habis. Masuk party: Nadia.')
            ->assertDontSee('Raka ML') // pemain selesai disembunyikan dari papan
            ->assertSeeHtml('<span>Match selesai</span><strong>2</strong>'); // header langsung ikut bertambah

        $this->assertSame('done', $raka->refresh()->status);
        $this->assertSame('playing', $nadia->refresh()->status);
        $this->assertSame(3, $nadia->matches_remaining);
        $this->assertSame('ready', $ida->refresh()->status);
        $this->assertSame(4, $ida->matches_remaining);
        $this->assertSame([$raka->id, $raka->id], $session->matches()->orderBy('id')->pluck('queue_entry_id')->all());
        $this->assertSame(2, $session->refresh()->total_matches);
    }

    public function test_queue_board_add_player_and_end_session(): void
    {
        $session = $this->liveSession();

        Livewire::test(QueueBoard::class, ['session' => $session])
            ->call('openAddPlayer')
            ->assertSet('showAddPlayer', true)
            ->set('playerName', 'Kiki ML')
            ->set('matchesTotal', 3)
            ->call('addPlayer')
            ->assertHasNoErrors()
            ->assertSet('showAddPlayer', false)
            ->assertDispatched('notify', message: 'Kiki ML masuk ke antrean.')
            ->assertSee('Kiki ML')
            ->call('endSession')
            ->assertRedirect('/live-session');

        $this->assertSame('ended', $session->refresh()->status);
    }

    public function test_queue_board_blocks_actions_after_session_ended_elsewhere(): void
    {
        $session = $this->liveSession();
        $raka = $this->player($session, 'Raka ML', 3);
        $board = Livewire::test(QueueBoard::class, ['session' => $session]);

        $session->update(['status' => 'ended']); // mis. diakhiri dari tab lain

        $board->call('markPlaying', $raka->id)->assertHasErrors(['queue']);
        $this->assertSame('ready', $raka->refresh()->status);
    }

    public function test_live_session_panel_start_end_reset_and_archives(): void
    {
        $panel = Livewire::test(LiveSessionPanel::class)
            ->set('title', 'X')
            ->call('startSession')
            ->assertHasErrors(['title'])
            ->set('title', 'Sabtu bareng viewer')
            ->call('startSession')
            ->assertHasNoErrors()
            ->assertSee('Sabtu bareng viewer')
            ->assertSee('Sedang live');

        $session = LiveSession::where('status', 'active')->sole();
        $this->player($session, 'Raka ML', 3);
        $this->player($session, 'Nadia', 1, 'offline', 2);

        $panel->call('resetQueue')->assertDispatched('notify', message: '2 pemain dikeluarkan dari antrean.');
        $this->assertSame(0, $session->queueEntries()->whereIn('status', ['ready', 'offline'])->count());

        $panel->call('endSession')->assertSee('Mulai sesi sekarang')->assertSee('Sabtu bareng viewer'); // kini di arsip
        $this->assertSame('ended', $session->refresh()->status);
    }

    public function test_live_session_panel_paginates_archives(): void
    {
        foreach (range(1, 7) as $i) {
            LiveSession::create(['user_id' => $this->streamer->id, 'title' => "Sesi lama {$i}", 'status' => 'ended', 'started_at' => now()->subDays($i), 'ended_at' => now()->subDays($i)->addHours(2)]);
        }

        Livewire::test(LiveSessionPanel::class)
            ->assertSee('Sesi lama 1')
            ->assertDontSee('Sesi lama 7')
            ->assertSee('Muat arsip lain')
            ->call('loadMoreArchives')
            ->assertSee('Sesi lama 7')
            ->assertDontSee('Muat arsip lain');
    }

    public function test_match_board_add_reduce_and_reopen_finished_player(): void
    {
        $session = $this->liveSession();
        $raka = $this->player($session, 'Raka ML', 1, 'playing');

        $board = Livewire::test(MatchBoard::class, ['session' => $session])
            ->call('reduceMatch', $raka->id)
            ->assertHasErrors(['match'])
            ->call('addMatch', $raka->id)
            ->assertDispatched('notify', message: 'Jatah Raka ML habis — ditandai selesai.')
            ->assertSee('Jatah habis')
            ->assertSee('Match ke-1');

        $this->assertSame('done', $raka->refresh()->status);

        $board->call('addMatch', $raka->id)->assertHasErrors(['match']); // jatah sudah habis

        $board->call('reduceMatch', $raka->id)
            ->assertDispatched('notify', message: 'Match dikoreksi. Raka ML kembali ke antrean.');

        $raka->refresh();
        $this->assertSame(0, $raka->matches_played);
        $this->assertSame('ready', $raka->status);
        $this->assertSame(0, $session->refresh()->total_matches);
    }

    public function test_components_are_owner_only(): void
    {
        $session = LiveSession::create(['user_id' => User::factory()->create()->id, 'title' => 'Bukan punyamu', 'status' => 'active']);

        Livewire::test(QueueBoard::class, ['session' => $session])->assertStatus(404);
        Livewire::test(MatchBoard::class, ['session' => $session])->assertStatus(404);
    }
}
