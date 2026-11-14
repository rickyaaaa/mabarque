<?php

namespace Tests\Feature;

use App\Livewire\OverlaySettingsPanel;
use App\Livewire\QueueBoard;
use App\Models\LiveSession;
use App\Models\User;
use App\Services\LiveSessionService;
use App\Services\OverlayTokenService;
use App\Services\PublicQueueService;
use App\Services\QueueService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Sisa game per pemain: terbawa antar sesi, detail pemain (ID MLBB, jenis, catatan), tempel banyak,
 * edit, dan sensor nama/ID di tampilan publik.
 */
class PlayerBalanceTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private LiveSession $session;

    private QueueService $queue;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create();
        $this->session = app(LiveSessionService::class)->start($this->owner, 'Live Senin');
        $this->queue = app(QueueService::class);
    }

    public function test_unfinished_players_carry_over_to_the_next_session_in_order(): void
    {
        $first = $this->queue->addManual($this->session, 'Vampire', 8);
        $done = $this->queue->addManual($this->session, 'Selesai', 1);
        $second = $this->queue->addManual($this->session, 'flux L', 13);
        $this->queue->markPlaying($this->session, $second);
        $this->queue->complete($this->session, $done);

        $sessions = app(LiveSessionService::class);
        $sessions->end($this->owner, $this->session);
        $next = $sessions->start($this->owner, 'Live Selasa');

        $this->assertSame(['Vampire', 'flux L'], $next->queueEntries()->pluck('player_name')->all());
        $this->assertSame(['ready', 'ready'], $next->queueEntries()->pluck('status')->all());
        $this->assertSame([1, 2], $next->queueEntries()->pluck('position')->all());
        $this->assertSame(2, $next->fresh()->total_players);
        $this->assertSame('done', $done->refresh()->status);
        $this->assertSame($this->session->id, $done->live_session_id); // yang selesai tetap di sesi lama
        $this->assertSame($next->id, $first->refresh()->live_session_id);
    }

    public function test_reset_queue_clears_players_so_they_do_not_carry_over(): void
    {
        $this->queue->addManual($this->session, 'Vampire', 8);
        $sessions = app(LiveSessionService::class);
        $sessions->resetQueue($this->owner, $this->session);

        $this->assertSame(0, $sessions->start($this->owner, 'Live Selasa')->queueEntries()->count());
    }

    public function test_player_details_validation(): void
    {
        $entry = $this->queue->addPlayer($this->session, ['player_name' => '', 'mlbb_id' => '960663270', 'matches_total' => 3]);
        $this->assertSame('ID 960663270', $entry->display_name);
        $this->assertSame('vip', $entry->service_type);

        $joki = $this->queue->addPlayer($this->session, ['player_name' => 'BANG ROTI', 'matches_total' => 8, 'service_type' => 'joki', 'note' => 'mulai Minggu']);
        $this->assertSame('Mainin akun', $joki->service_label);
        $this->assertNull($joki->mlbb_id);

        $big = $this->queue->addPlayer($this->session, ['player_name' => 'SHURAA', 'matches_total' => 200]);
        $this->assertSame(200, $big->matches_remaining);

        foreach ([
            ['player_name' => '', 'mlbb_id' => '', 'matches_total' => 1],
            ['player_name' => 'X', 'mlbb_id' => 'abc', 'matches_total' => 1],
            ['player_name' => 'X', 'matches_total' => 1000],
            ['player_name' => 'X', 'matches_total' => 1, 'service_type' => 'lainnya'],
        ] as $invalid) {
            try {
                $this->queue->addPlayer($this->session, $invalid);
                $this->fail('Seharusnya ditolak: '.json_encode($invalid));
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_import_players_from_pasted_lines(): void
    {
        $count = $this->queue->importPlayers($this->session, implode("\n", [
            '```',
            '# nama | ID | sisa | jenis | catatan',
            'Vampire |  | 8 |  |',
            'CELLSEA | 1185010926 | 2 | vip |',
            ' | 960663270 | 3',
            '',
            'BANG ROTI |  | 8 | joki | dimainin akunnya mulai Minggu',
            'ethan |  | 2 | vip | +2 game gratis | buat siapa saja',
        ]));

        $this->assertSame(5, $count);
        $entries = $this->session->queueEntries()->get();
        $this->assertSame(['Vampire', 'CELLSEA', '', 'BANG ROTI', 'ethan'], $entries->pluck('player_name')->all());
        $this->assertSame([8, 2, 3, 8, 2], $entries->pluck('matches_total')->all());
        $this->assertSame('1185010926', $entries[1]->mlbb_id);
        $this->assertSame('joki', $entries[3]->service_type);
        $this->assertSame('+2 game gratis | buat siapa saja', $entries[4]->note);
    }

    public function test_import_is_all_or_nothing_and_reports_line_numbers(): void
    {
        try {
            $this->queue->importPlayers($this->session, "Vampire |  | 8\nflux L |  | banyak\nDims | abc | 4");
            $this->fail('Seharusnya ditolak.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('Baris 2', $exception->getMessage());
            $this->assertStringContainsString('Baris 3', $exception->getMessage());
        }

        $this->assertSame(0, $this->session->queueEntries()->count());
    }

    public function test_board_add_edit_and_import(): void
    {
        $board = Livewire::actingAs($this->owner)->test(QueueBoard::class, ['session' => $this->session])
            ->set('playerName', '')
            ->set('mlbbId', '138765201')
            ->set('matchesTotal', 150)
            ->set('note', 'ID dari komentar')
            ->call('addPlayer')
            ->assertHasNoErrors()
            ->assertSee('ID 138765201');

        $entry = $this->session->queueEntries()->sole();
        $this->assertSame(['vip', 150, 'ID dari komentar'], [$entry->service_type, $entry->matches_total, $entry->note]);

        $board->call('openEdit', $entry->id)
            ->assertSet('editRemaining', 150)
            ->set('editName', 'bang ryan')
            ->set('editRemaining', 1)
            ->call('savePlayer')
            ->assertHasNoErrors()
            ->assertSet('editingId', null)
            ->assertSee('bang ryan');
        $this->assertSame(1, $entry->refresh()->matches_remaining);

        $board->call('openEdit', $entry->id)->set('editRemaining', 0)->call('savePlayer');
        $this->assertSame('done', $entry->refresh()->status);

        $board->call('openImport')
            ->set('importText', "Juki Nikol Apis |  | 26\nElman |  | 1 |  | ID belum ada")
            ->call('importPlayers')
            ->assertHasNoErrors()
            ->assertSet('showImport', false)
            ->assertSee('Juki Nikol Apis');

        $board->call('openImport')->set('importText', 'Rusak | | x')->call('importPlayers')->assertHasErrors(['importForm']);
    }

    public function test_censor_hides_second_half_of_names_and_ids_everywhere_public(): void
    {
        $this->assertSame('WangSu*******', PublicQueueService::censor('WangSukiJomok'));
        $this->assertSame('11850*****', PublicQueueService::censor('1185010926'));

        $this->queue->addPlayer($this->session, ['player_name' => 'WangSukiJomok', 'mlbb_id' => '1185010926', 'matches_total' => 58, 'note' => 'rahasia streamer']);
        $this->queue->addPlayer($this->session, ['player_name' => '', 'mlbb_id' => '960663270', 'matches_total' => 3]);

        // Tanpa sensor: nama & ID lengkap, catatan tidak pernah dikirim.
        $this->getJson("/api/channels/{$this->owner->slug}/queue")->assertOk()
            ->assertJsonPath('data.queue.0.player_name', 'WangSukiJomok')
            ->assertJsonPath('data.queue.0.mlbb_id', '1185010926')
            ->assertJsonMissingPath('data.queue.0.note');

        Livewire::actingAs($this->owner)->test(OverlaySettingsPanel::class)
            ->set('censorNames', true)
            ->call('save')
            ->assertHasNoErrors();

        $response = $this->getJson("/api/channels/{$this->owner->slug}/queue")->assertOk()
            ->assertJsonPath('data.queue.0.player_name', 'WangSu*******')
            ->assertJsonPath('data.queue.0.mlbb_id', '11850*****')
            ->assertJsonPath('data.queue.1.player_name', 'ID 9606*****');
        $this->assertStringNotContainsString('WangSukiJomok', $response->getContent());
        $this->assertStringNotContainsString('1185010926', $response->getContent());
        $this->assertStringNotContainsString('rahasia streamer', $response->getContent());

        $this->get('/'.$this->owner->slug)->assertOk()->assertSee('WangSu*******')->assertDontSee('WangSukiJomok')->assertDontSee('1185010926');

        $token = app(OverlayTokenService::class)->current($this->owner);
        $overlay = $this->getJson("/api/overlay/{$token}")->assertOk();
        $this->assertStringNotContainsString('WangSukiJomok', $overlay->getContent());

        // Pencarian tetap memakai nama/ID asli (pemilik tahu datanya sendiri), hasilnya versi tersensor.
        $this->getJson("/api/channels/{$this->owner->slug}/queue/search?q=1185010926")->assertOk()
            ->assertJsonPath('data.matches.0.player_name', 'WangSu*******');
    }

    public function test_public_queue_shows_balances_while_offline_but_overlay_does_not(): void
    {
        $this->queue->addManual($this->session, 'Vampire', 8);
        app(LiveSessionService::class)->end($this->owner, $this->session);

        $this->getJson("/api/channels/{$this->owner->slug}/queue")->assertOk()
            ->assertJsonPath('data.status', 'offline')
            ->assertJsonPath('data.queue.0.player_name', 'Vampire')
            ->assertJsonPath('data.queue.0.status', 'offline')
            ->assertJsonPath('data.queue.0.matches_remaining', 8);

        $token = app(OverlayTokenService::class)->current($this->owner);
        $this->getJson("/api/overlay/{$token}")->assertOk()->assertJsonPath('data.queue.entries', []);
    }
}
