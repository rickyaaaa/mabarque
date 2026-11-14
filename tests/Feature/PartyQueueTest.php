<?php

namespace Tests\Feature;

use App\Livewire\QueueBoard;
use App\Models\LiveSession;
use App\Models\QueueEntry;
use App\Models\User;
use App\Services\LiveSessionService;
use App\Services\QueueService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Livewire\Livewire;
use Tests\TestCase;

/** Party (streamer + N viewer), "next game", dan batas game per pemain tiap sesi live. */
class PartyQueueTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private LiveSession $session;

    private QueueService $queue;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create(['party_size' => 4, 'session_match_cap' => 0]);
        $this->session = app(LiveSessionService::class)->start($this->owner, 'Live Minggu');
        $this->queue = app(QueueService::class);
    }

    /** @return array<string, QueueEntry> */
    private function players(array $players): array
    {
        $entries = [];
        foreach ($players as $name => $games) {
            [$games, $type] = is_array($games) ? $games : [$games, 'vip'];
            $entries[$name] = $this->queue->addPlayer($this->session, ['player_name' => $name, 'matches_total' => $games, 'service_type' => $type]);
        }

        return $entries;
    }

    private function cap(int $cap): void
    {
        $this->owner->forceFill(['session_match_cap' => $cap])->save();
        $this->session->unsetRelation('user');
    }

    private function partyNames(): array
    {
        return $this->queue->party($this->session)->pluck('player_name')->all();
    }

    public function test_party_fills_with_next_four_vip_players_skipping_joki(): void
    {
        $this->players(['Dey' => 2, 'Roti' => [8, 'joki'], 'Ir.fing' => 5, 'Indira' => 1, 'Pokedok' => 3, 'Kyoto' => 2]);

        $this->assertSame(['Dey', 'Ir.fing', 'Indira', 'Pokedok'], $this->queue->nextUp($this->session, 4)->pluck('player_name')->all());

        $joined = $this->queue->fillParty($this->session);
        $this->assertCount(4, $joined);
        $this->assertSame(['Dey', 'Ir.fing', 'Indira', 'Pokedok'], $this->partyNames());
        $this->assertSame(['Kyoto'], $this->queue->nextUp($this->session, 4)->pluck('player_name')->all());
    }

    public function test_finish_game_counts_one_session_match_and_refills_finished_slots(): void
    {
        ['Indira' => $indira] = $this->players(['Dey' => 2, 'Ir.fing' => 5, 'Indira' => 1, 'Pokedok' => 3, 'Kyoto' => 2]);
        $this->queue->fillParty($this->session);

        $result = $this->queue->finishGame($this->session);

        $this->assertSame(['Indira'], $result['finished']->pluck('player_name')->all());
        $this->assertSame(['Kyoto'], $result['joined']->pluck('player_name')->all());
        $this->assertSame('done', $indira->refresh()->status);
        $this->assertSame(['Dey', 'Ir.fing', 'Pokedok', 'Kyoto'], $this->partyNames());
        $this->assertSame(1, $this->session->refresh()->total_matches); // satu game party = satu match sesi
        $this->assertSame(1, QueueEntry::firstWhere('player_name', 'Dey')->matches_played);
    }

    public function test_session_cap_moves_player_out_of_party_and_skips_them_until_next_session(): void
    {
        $this->owner->forceFill(['party_size' => 2])->save();
        $this->cap(2);
        ['SHURAA' => $shuraa] = $this->players(['SHURAA' => 200, 'ZackW' => 181, 'Vampire' => 8]);
        $this->queue->fillParty($this->session);

        $this->queue->finishGame($this->session);
        $this->assertSame(['SHURAA', 'ZackW'], $this->partyNames());

        $result = $this->queue->finishGame($this->session);
        $this->assertSame(['SHURAA', 'ZackW'], $result['capped']->pluck('player_name')->all());
        $this->assertSame(['Vampire'], $this->partyNames());
        $this->assertSame('ready', $shuraa->refresh()->status);
        $this->assertSame(198, $shuraa->matches_remaining);
        $this->assertTrue($this->queue->isCapped($this->session, $shuraa));
        $this->assertSame([], $this->queue->nextUp($this->session)->pluck('player_name')->all());

        // Streamer tetap boleh memasukkan pemain yang kena batas secara manual.
        $this->queue->markPlaying($this->session, $shuraa);
        $this->assertSame(['Vampire', 'SHURAA'], $this->partyNames()); // urut posisi antrean

        // Sesi live berikutnya: hitungan batas mulai dari nol, sisa game tetap.
        $next = app(LiveSessionService::class)->start($this->owner, 'Live Senin');
        // Yang kena batas sudah pindah ke belakang → giliran adil: Vampire duluan.
        $this->assertSame(['Vampire', 'SHURAA', 'ZackW'], $this->queue->nextUp($next)->pluck('player_name')->all());
    }

    public function test_full_party_and_joki_cannot_be_added_manually(): void
    {
        ['Kyoto' => $kyoto, 'Roti' => $roti] = $this->players(['A' => 1, 'B' => 1, 'C' => 1, 'D' => 1, 'Kyoto' => 2, 'Roti' => [8, 'joki']]);
        $this->queue->fillParty($this->session);

        foreach ([$kyoto, $roti] as $entry) {
            try {
                $this->queue->markPlaying($this->session, $entry);
                $this->fail('Seharusnya ditolak.');
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->assertSame('ready', $kyoto->refresh()->status);
    }

    public function test_bench_keeps_order_and_leaves_the_slot_empty(): void
    {
        ['B' => $b] = $this->players(['A' => 3, 'B' => 3, 'C' => 3, 'D' => 3, 'E' => 3]);
        $this->queue->fillParty($this->session);

        $this->queue->bench($this->session, $b);

        // Slot dibiarkan kosong sampai streamer mengisinya sendiri.
        $this->assertSame(['A', 'C', 'D'], $this->partyNames());
        $this->assertSame(['ready', 2], [$b->refresh()->status, $b->position]);

        $this->assertSame(['B'], $this->queue->fillParty($this->session)->pluck('player_name')->all());
    }

    public function test_board_party_controls_and_rules(): void
    {
        $this->players(['Dey' => 2, 'Ir.fing' => 1, 'Indira' => 3]);

        $board = Livewire::actingAs($this->owner)->test(QueueBoard::class, ['session' => $this->session])
            ->assertSee('Slot kosong')
            ->call('openRules')
            ->assertSet('partySize', 4)
            ->set('partySize', 2)
            ->set('sessionCap', 3)
            ->call('saveRules')
            ->assertHasNoErrors()
            ->call('startGame')
            ->assertDispatched('notify', message: 'Masuk party: Dey, Ir.fing.')
            ->assertSee('maks 3 game per orang tiap sesi');

        $this->assertSame([2, 3], [$this->owner->refresh()->party_size, $this->owner->session_match_cap]);

        $board->call('finishGame')
            ->assertDispatched('notify', message: 'Game selesai dicatat untuk 2 pemain. Habis: Ir.fing. Masuk party: Indira.');
        $this->assertSame(['Dey', 'Indira'], $this->partyNames());

        $board->set('partySize', 9)->call('saveRules')->assertHasErrors(['partySize']);
    }

    public function test_public_queue_exposes_party_next_game_and_cap(): void
    {
        $this->owner->forceFill(['party_size' => 2])->save();
        $this->cap(1);
        $this->players(['Dey' => 2, 'Ir.fing' => 5, 'Indira' => 1, 'Pokedok' => 3]);
        $this->queue->fillParty($this->session);
        $this->queue->finishGame($this->session); // Dey & Ir.fing kena batas 1 → Indira & Pokedok main

        $this->getJson("/api/channels/{$this->owner->slug}/queue")->assertOk()
            ->assertJsonPath('data.party', ['Indira', 'Pokedok'])
            ->assertJsonPath('data.next', [])
            ->assertJsonPath('data.rules', ['party_size' => 2, 'session_cap' => 1])
            ->assertJsonPath('data.queue.2.player_name', 'Dey')
            ->assertJsonPath('data.queue.2.capped', true);

        $this->get('/'.$this->owner->slug)->assertOk()
            ->assertSee('Next game')
            ->assertSee('Maks 1 game per orang tiap sesi live')
            ->assertSee('Maks sesi');
    }
}
