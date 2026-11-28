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

    public function test_queue_list_is_paged_by_ten_and_searchable(): void
    {
        $names = [];
        for ($i = 1; $i <= 23; $i++) {
            $names[sprintf('Pemain %02d', $i)] = 2;
        }
        $this->players($names);
        $this->session->queueEntries()->where('player_name', 'Pemain 17')->sole()->update(['mlbb_id' => '987654321']);
        $this->actingAs($this->owner);

        $board = Livewire::test(QueueBoard::class, ['session' => $this->session])
            ->assertSee('Pemain 10')->assertDontSee('Pemain 11')->assertSee('1–10 dari 23 pemain')
            ->call('goToPage', 3)->assertSee('Pemain 23')->assertDontSee('Pemain 10')->assertSee('21–23 dari 23 pemain')
            ->call('goToPage', 99)->assertSet('queuePage', 3)
            ->set('search', 'pemain 2')->assertSet('queuePage', 1)->assertSee('Pemain 20')->assertDontSee('Pemain 05')->assertSee('1–4 dari 4 hasil')
            ->set('search', '98765')->assertSee('Pemain 17')->assertDontSee('Pemain 16')
            ->set('search', 'tidak ada')->assertSee('Tidak ada pemain yang cocok');

        $board->call('clearSearch')->assertSet('search', '')->assertSee('Pemain 01');
    }

    public function test_bulk_delete_resets_select_all_when_the_next_players_appear(): void
    {
        $players = [];
        for ($i = 1; $i <= 16; $i++) {
            $players[sprintf('Pemain %02d', $i)] = 2;
        }
        $entries = $this->players($players);

        Livewire::actingAs($this->owner)->test(QueueBoard::class, ['session' => $this->session])
            ->call('toggleSelectAll')
            ->assertCount('selected', 10)
            ->assertSeeHtml('wire:key="select-all-table-checked"')
            ->assertSeeHtml('wire:key="select-all-cards-checked"')
            ->call('bulkRemove')
            ->assertSet('selected', [])
            ->assertSee('1–6 dari 6 pemain')
            ->assertSeeHtml('wire:key="select-all-table-unchecked"')
            ->assertSeeHtml('wire:key="select-all-cards-unchecked"')
            ->assertDontSee('6 dipilih')
            ->call('toggleSelectAll')
            ->assertSet('selected', array_values(array_map(fn (QueueEntry $entry) => (string) $entry->id, array_slice($entries, 10))))
            ->assertSee('6 dipilih')
            ->assertSeeHtml('wire:key="select-all-table-checked"')
            ->assertSeeHtml('wire:key="select-all-cards-checked"');
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

    public function test_finish_game_keeps_previously_empty_party_slots_open(): void
    {
        ['A' => $a, 'B' => $b, 'C' => $c, 'D' => $d, 'E' => $e] = $this->players(['A' => 3, 'B' => 2, 'C' => 4, 'D' => 5, 'E' => 6]);
        $board = Livewire::actingAs($this->owner)->test(QueueBoard::class, ['session' => $this->session])
            ->call('markPlaying', $a->id)
            ->call('markPlaying', $b->id)
            ->call('finishGame')
            ->assertDispatched('notify', message: 'Game selesai — dicatat untuk 2 pemain.')
            ->assertSee('Isi slot kosong');

        $this->assertSame(['A', 'B'], $this->partyNames());
        $this->assertSame([$a->id, $b->id], $this->session->matches()->orderBy('id')->pluck('queue_entry_id')->all());
        $this->assertSame([2, 1, 4, 5, 6], array_map(
            fn (QueueEntry $entry) => $entry->refresh()->matches_remaining, [$a, $b, $c, $d, $e],
        ));

        $board->call('markPlaying', $c->id)->call('startGame')->assertHasNoErrors();
        $this->assertSame(['A', 'B', 'C', 'D'], $this->partyNames());
    }

    public function test_finish_game_only_replaces_the_finished_player_and_prioritizes_next_game(): void
    {
        ['A' => $a, 'B' => $b, 'C' => $c, 'D' => $d, 'E' => $e] = $this->players(['A' => 1, 'B' => 3, 'C' => 4, 'D' => 5, 'E' => 6]);
        $this->queue->markPlaying($this->session, $a);
        $this->queue->markPlaying($this->session, $b);
        $this->queue->pickForNext($this->session, $d);
        $this->queue->pickForNext($this->session, $e);

        $result = $this->queue->finishGame($this->session);

        $this->assertSame(['B', 'D'], $this->partyNames());
        $this->assertSame([$d->id], $result['joined']->pluck('id')->all());
        $this->assertSame([$a->id, $b->id], $this->session->matches()->orderBy('id')->pluck('queue_entry_id')->all());
        $this->assertSame('done', $a->refresh()->status);
        $this->assertSame([0, 2, 4, 5, 6], array_map(
            fn (QueueEntry $entry) => $entry->refresh()->matches_remaining, [$a, $b, $c, $d, $e],
        ));
        $this->assertNull($d->next_order);
        $this->assertNotNull($e->next_order);
    }

    public function test_finish_game_only_replaces_the_player_who_reaches_the_session_cap(): void
    {
        $this->cap(2);
        ['A' => $a, 'B' => $b, 'C' => $c, 'D' => $d, 'E' => $e] = $this->players(['A' => 4, 'B' => 3, 'C' => 4, 'D' => 5, 'E' => 6]);
        $this->queue->markPlaying($this->session, $a);
        $this->queue->markPlaying($this->session, $b);
        Livewire::actingAs($this->owner)->test(QueueBoard::class, ['session' => $this->session])->call('finishMatch', $a->id)->assertHasNoErrors();

        $result = $this->queue->finishGame($this->session);

        $this->assertSame(['B', 'C'], $this->partyNames());
        $this->assertSame([$a->id], $result['capped']->pluck('id')->all());
        $this->assertSame([$c->id], $result['joined']->pluck('id')->all());
        $this->assertSame([$a->id, $a->id, $b->id], $this->session->matches()->orderBy('id')->pluck('queue_entry_id')->all());
        $this->assertSame('ready', $a->refresh()->status);
        $this->assertSame([2, 2, 4, 5, 6], array_map(
            fn (QueueEntry $entry) => $entry->refresh()->matches_remaining, [$a, $b, $c, $d, $e],
        ));
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
        // Seluruh party keluar → party kosong, tidak diisi otomatis; streamer menekan "Mulai game".
        $this->assertSame([], $this->partyNames());
        $this->assertTrue($result['party_emptied']);
        $this->queue->fillParty($this->session);
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

    /**
     * Kejadian 2026-11-29 (sesi ColdW): party berisi 1 orang (BANG ALEW, sisa 1 game). Setelah "Selesai game"
     * ALEW habis, lalu pemain nomor 1 antrean (Vampire) langsung masuk party tanpa diminta. Party yang habis
     * semua = game selesai: biarkan kosong sampai streamer menekan "Mulai game".
     */
    public function test_party_that_empties_after_a_game_is_not_refilled_automatically(): void
    {
        ['Vampire' => $vampire, 'BANG ALEW' => $alew] = $this->players(['Vampire' => 8, 'flux L' => 13, 'BANG ALEW' => 1]);
        $this->queue->markPlaying($this->session, $alew);

        $result = $this->queue->finishGame($this->session);

        $this->assertSame(['BANG ALEW'], $result['finished']->pluck('player_name')->all());
        $this->assertSame([], $result['joined']->pluck('player_name')->all());
        $this->assertTrue($result['party_emptied']);
        $this->assertSame([], $this->partyNames());
        $this->assertSame('ready', $vampire->refresh()->status);
        $this->assertSame(['Vampire', 'flux L'], $this->queue->nextUp($this->session)->pluck('player_name')->all());

        // Party yang masih berisi tetap diisi: 1 dari 2 habis → slotnya diganti pemain berikutnya.
        $this->owner->forceFill(['party_size' => 2])->save();
        $this->queue->fillParty($this->session); // Vampire & flux L
        $vampire->forceFill(['matches_total' => 1])->save();
        $result = $this->queue->finishGame($this->session->refresh());
        $this->assertFalse($result['party_emptied']);
        $this->assertSame(['Vampire'], $result['finished']->pluck('player_name')->all());
        $this->assertSame(['flux L'], $this->partyNames());
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

    public function test_manual_next_game_picks_go_first_then_auto_order(): void
    {
        $this->owner->forceFill(['party_size' => 2])->save();
        $this->cap(1);
        ['Pokedok' => $pokedok, 'Kyoto' => $kyoto, 'Indira' => $indira, 'Roti' => $roti] =
            $this->players(['Dey' => 3, 'Ir.fing' => 3, 'Indira' => 3, 'Pokedok' => 3, 'Kyoto' => 3, 'Roti' => [8, 'joki']]);

        // Kyoto dipilih duluan, lalu Pokedok → keduanya di depan urutan otomatis.
        $this->queue->pickForNext($this->session, $kyoto);
        $this->queue->pickForNext($this->session, $pokedok);
        $this->assertSame(['Kyoto', 'Pokedok', 'Dey', 'Ir.fing', 'Indira'], $this->queue->nextUp($this->session)->pluck('player_name')->all());

        // Next game maksimal sebanyak slot party; joki tidak bisa dipilih.
        foreach ([$indira, $roti] as $entry) {
            try {
                $this->queue->pickForNext($this->session, $entry);
                $this->fail('Seharusnya ditolak.');
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }

        // Mulai game: pilihan manual yang masuk party, pilihannya gugur setelah masuk.
        $this->assertSame(['Kyoto', 'Pokedok'], $this->queue->fillParty($this->session)->pluck('player_name')->all());
        $this->assertNull($kyoto->refresh()->next_order);

        // Pilihan manual boleh pemain yang sudah kena batas sesi; dibatalkan → kembali ke urutan otomatis.
        $this->queue->finishGame($this->session); // Kyoto & Pokedok kena batas 1 → party kosong
        $this->queue->fillParty($this->session); // "Mulai game" → Dey & Ir.fing main
        $this->queue->pickForNext($this->session, $kyoto);
        $this->assertSame(['Kyoto', 'Indira'], $this->queue->nextUp($this->session, 2)->pluck('player_name')->all());
        $this->queue->unpickForNext($this->session, $kyoto);
        $this->assertSame(['Indira'], $this->queue->nextUp($this->session, 2)->pluck('player_name')->all());

        // Pilihan gugur bila pemain ditandai offline, dan tidak ikut terbawa ke sesi berikutnya.
        $this->queue->pickForNext($this->session, $indira);
        app(\App\Services\QueueStatusService::class)->markOffline($this->session, $indira->refresh());
        $this->assertNull($indira->refresh()->next_order);
    }

    public function test_board_next_game_buttons(): void
    {
        ['Kyoto' => $kyoto] = $this->players(['Dey' => 2, 'Kyoto' => 2]);

        Livewire::actingAs($this->owner)->test(QueueBoard::class, ['session' => $this->session])
            ->call('pickNext', $kyoto->id)
            ->assertDispatched('notify', message: 'Kyoto masuk Next game.')
            ->assertSee('otomatis')
            ->call('unpickNext', $kyoto->id)
            ->assertDispatched('notify', message: 'Kyoto dibatalkan dari Next game.');

        $this->assertNull($kyoto->refresh()->next_order);
    }

    public function test_bulk_select_and_remove(): void
    {
        ['Dey' => $dey, 'Kyoto' => $kyoto, 'Roti' => $roti] = $this->players(['Dey' => 3, 'Ir.fing' => 2, 'Kyoto' => 2, 'Roti' => [8, 'joki']]);
        $this->queue->fillParty($this->session); // Dey, Ir.fing, Kyoto main
        $this->queue->finishGame($this->session); // Dey punya riwayat game

        $board = Livewire::actingAs($this->owner)->test(QueueBoard::class, ['session' => $this->session])
            ->call('toggleSelectAll')
            ->assertCount('selected', 4)
            ->assertSee('4 dipilih')
            ->call('toggleSelectAll')
            ->assertCount('selected', 0);

        // Filter "Mainin akun": centang semua hanya mengenai yang terlihat.
        $board->call('setFilter', 'joki')->call('toggleSelectAll')->assertSet('selected', [(string) $roti->id])
            ->assertDontSeeHtml('bulkStatus');

        // Hapus massal: yang punya riwayat ditandai selesai (rekap tetap utuh), yang belum dihapus.
        $board->call('setFilter', 'all')
            ->set('selected', [(string) $dey->id, (string) $roti->id])
            ->call('bulkRemove')
            ->assertDispatched('notify', message: '2 pemain dihapus dari antrean.')
            ->assertSet('selected', []);

        $this->assertSame('done', $dey->refresh()->status);
        $this->assertSame(1, $dey->matches()->count());
        $this->assertNull(QueueEntry::find($roti->id));
        $this->assertSame(['Ir.fing', 'Kyoto'], $this->session->queueEntries()->whereIn('status', ['ready', 'playing'])->pluck('player_name')->all());

        $board->call('bulkRemove')->assertHasErrors(['queue']); // tidak ada yang dicentang
    }

    public function test_filter_auto_vs_manual_and_removing_a_donor_marks_donation_ignored(): void
    {
        $this->players(['Manual Dey' => 2]);
        $connection = \App\Models\PlatformConnection::create(['user_id' => $this->owner->id, 'provider' => 'saweria', 'webhook_url' => '', 'signing_secret' => 'x', 'status' => 'active']);
        $donor = QueueEntry::create(['live_session_id' => $this->session->id, 'player_name' => 'Cuma Donasi', 'provider' => 'saweria', 'donation_amount' => 25000, 'matches_total' => 2, 'status' => 'ready', 'position' => 2]);
        $donation = \App\Models\Donation::create([
            'platform_connection_id' => $connection->id, 'live_session_id' => $this->session->id, 'queue_entry_id' => $donor->id,
            'external_id' => 'd1', 'provider' => 'saweria', 'donor_name' => 'Cuma Donasi', 'amount' => 25000, 'message' => 'semangat bang', 'status' => 'queued',
        ]);

        $board = Livewire::actingAs($this->owner)->test(QueueBoard::class, ['session' => $this->session])
            ->call('setFilter', 'auto')
            ->assertSee('Donasi Rp 25.000 · “semangat bang”')
            ->assertViewHas('entries', fn ($entries) => $entries->pluck('player_name')->all() === ['Cuma Donasi'])
            ->call('setFilter', 'manual')
            ->assertViewHas('entries', fn ($entries) => $entries->pluck('player_name')->all() === ['Manual Dey']);

        $this->players(['Roti' => [8, 'joki']]);
        $board->call('setFilter', 'vip')
            ->assertViewHas('entries', fn ($entries) => $entries->pluck('player_name')->sort()->values()->all() === ['Cuma Donasi', 'Manual Dey'])
            ->call('setFilter', 'joki')
            ->assertViewHas('entries', fn ($entries) => $entries->pluck('player_name')->all() === ['Roti']);

        // Ternyata cuma donasi biasa: hapus dari antrean → donasinya tercatat "Diabaikan".
        $board->call('setFilter', 'auto')->call('toggleSelectAll')->call('bulkRemove');
        $this->assertNull(QueueEntry::find($donor->id));
        $this->assertSame('ignored', $donation->refresh()->status);
    }

    public function test_joki_orders_have_their_own_status_flow_and_viewer_section(): void
    {
        $this->owner->forceFill(['party_size' => 2])->save();
        // Orang yang sama bisa order dua-duanya: Mabar VIP dan VIP Mainin akun → dua baris terpisah.
        $this->queue->importPlayers($this->session, "Roti |  | 3 | vip\nRoti |  |  | joki | akun utama\nDey |  | 2");
        [$rotiVip, $rotiJoki] = $this->session->queueEntries()->where('player_name', 'Roti')->orderBy('position')->get()->all();
        $this->assertSame(['vip', 'joki'], [$rotiVip->service_type, $rotiJoki->service_type]);

        $board = Livewire::actingAs($this->owner)->test(QueueBoard::class, ['session' => $this->session])
            ->assertSee('VIP Mainin akun')
            ->assertSee('Menunggu')
            ->call('startJoki', $rotiJoki->id)
            ->assertDispatched('notify', message: 'Akun Roti sedang dimainkan.')
            ->assertSee('Sedang dimainkan');

        // Akun yang sedang dimainkan tidak memakan slot party mabar.
        $this->assertSame(['Roti', 'Dey'], $this->queue->fillParty($this->session)->pluck('player_name')->all());
        $this->assertSame(2, $this->queue->party($this->session)->count());

        $this->getJson("/api/channels/{$this->owner->slug}/queue")->assertOk()
            ->assertJsonPath('data.joki', [['player_name' => 'Roti', 'state' => 'playing']])
            ->assertJsonPath('data.party.0.player_name', 'Roti')
            ->assertJsonCount(2, 'data.queue'); // daftar mabar tanpa pesanan joki

        $board->call('finishJoki', $rotiJoki->id)->assertDispatched('notify', message: 'Akun Roti selesai dimainkan.');
        $this->assertSame('done', $rotiJoki->refresh()->status);
        $this->assertSame(2, $this->queue->party($this->session)->count()); // party mabar tidak terganggu

        $this->get('/'.$this->owner->slug)->assertOk()->assertSee('VIP Mainin akun')->assertSee('Selesai ✓');

        // Pesanan selesai lebih dari 3 hari lalu tidak ditampilkan lagi.
        QueueEntry::whereKey($rotiJoki->id)->update(['updated_at' => now()->subDays(4)]);
        $this->getJson("/api/channels/{$this->owner->slug}/queue")->assertJsonPath('data.joki', []);
    }

    public function test_mvp_plays_again_without_losing_a_game_when_rule_is_on(): void
    {
        $this->owner->forceFill(['party_size' => 2])->save();
        $this->cap(1);
        ['Dey' => $dey, 'Kyoto' => $kyoto] = $this->players(['Dey' => 3, 'Kyoto' => 3, 'Indira' => 3]);
        $this->queue->fillParty($this->session);

        // Aturan mati: tombol MVP tidak tampil dan tanda MVP diabaikan.
        $board = Livewire::actingAs($this->owner)->test(QueueBoard::class, ['session' => $this->session])
            ->assertDontSeeHtml('wire:click="toggleMvp')
            ->call('toggleMvp', $dey->id)
            ->assertSet('mvpId', null);

        $board->call('openRules')->set('mvpFreeGame', true)->call('saveRules')->assertHasNoErrors()
            ->assertSeeHtml('wire:click="toggleMvp')
            ->call('toggleMvp', $dey->id)
            ->assertSet('mvpId', $dey->id)
            ->call('finishGame')
            ->assertDispatched('notify', message: 'Game selesai — dicatat untuk 2 pemain. ⭐ Dey MVP — main lagi, sisa tidak berkurang. Batas sesi: Kyoto. Masuk party: Indira.')
            ->assertSet('mvpId', null);

        // Dey: sisa tetap 3 dan tidak kena batas sesi (1) → tetap di party. Kyoto: berkurang & kena batas.
        $this->assertSame(3, $dey->refresh()->matches_remaining);
        $this->assertSame('playing', $dey->status);
        $this->assertSame(2, $kyoto->refresh()->matches_remaining);
        $this->assertSame(['Dey', 'Indira'], $this->partyNames());
        $this->assertTrue((bool) \App\Models\MatchRecord::query()->where('queue_entry_id', $dey->id)->value('is_mvp'));

        // Koreksi game MVP tidak menambah jatah.
        app(\App\Services\MatchService::class)->undoLatest($dey);
        $this->assertSame(3, $dey->refresh()->matches_remaining);

        // Rekap arsip menandai MVP.
        $this->queue->fillParty($this->session);
        $board->call('toggleMvp', $dey->id)->call('finishGame');
        app(LiveSessionService::class)->end($this->owner, $this->session);
        $this->get('/live-session/'.$this->session->id)->assertOk()->assertSee('Dey ⭐');
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

        // Selesai game = mengurangi jatah party; tidak mengubah skor.
        $board->call('finishGame')
            ->assertDispatched('notify', message: 'Game selesai — dicatat untuk 2 pemain. Habis: Ir.fing. Masuk party: Indira.');
        $this->assertSame(['Dey', 'Indira'], $this->partyNames());
        $this->assertSame([0, 0, 1], [$this->session->refresh()->wins, $this->session->losses, $this->session->total_matches]);

        // Menang/Kalah = penghitung skor saja, tidak mengurangi sisa game yang sedang main.
        $remaining = $this->queue->party($this->session)->map->matches_remaining->all();
        $board->call('addScore', 'win')
            ->assertDispatched('notify', message: 'Menang dicatat. Skor 1–0.')
            ->call('addScore', 'win')
            ->call('addScore', 'lose')
            ->assertSeeHtml('<span>Win – Lose</span><strong>2 – 1</strong>')
            ->call('undoScore', 'win')
            ->assertDispatched('notify', message: 'Skor dikoreksi: 1–1.');
        $this->assertSame([1, 1, 1], [$this->session->refresh()->wins, $this->session->losses, $this->session->total_matches]);
        $this->assertSame($remaining, $this->queue->party($this->session)->map->matches_remaining->all());

        // Keluarkan semua dari party sekaligus: slot kosong, sisa game & urutan tetap.
        $board->call('benchAll')->assertDispatched('notify', message: '2 pemain dikeluarkan dari party. Slot kosong — klik "Mulai game" untuk mengisi lagi.');
        $this->assertSame([], $this->partyNames());
        $this->assertSame($remaining, $this->session->queueEntries()->whereIn('player_name', ['Dey', 'Indira'])->get()->map->matches_remaining->all());

        $board->set('partySize', 9)->call('saveRules')->assertHasErrors(['partySize']);
    }

    public function test_public_queue_exposes_party_next_game_and_cap(): void
    {
        $this->owner->forceFill(['party_size' => 2])->save();
        $this->cap(1);
        $this->players(['Dey' => 2, 'Ir.fing' => 5, 'Indira' => 1, 'Pokedok' => 3]);
        $this->queue->fillParty($this->session);
        $this->queue->finishGame($this->session); // Dey & Ir.fing kena batas 1 → party kosong
        $this->queue->fillParty($this->session); // "Mulai game" → Indira & Pokedok main

        $this->getJson("/api/channels/{$this->owner->slug}/queue")->assertOk()
            ->assertJsonPath('data.party', [['player_name' => 'Indira', 'matches_remaining' => 1], ['player_name' => 'Pokedok', 'matches_remaining' => 3]])
            ->assertJsonPath('data.next', [])
            ->assertJsonPath('data.rules', ['party_size' => 2, 'session_cap' => 1])
            ->assertJsonPath('data.queue.2.player_name', 'Dey')
            ->assertJsonPath('data.queue.2.capped', true);

        $this->get('/'.$this->owner->slug)->assertOk()
            ->assertSee('Next game')
            ->assertSee('sisa 3 game')
            ->assertSee('Maks 1 game per orang tiap sesi live')
            ->assertDontSee('Maks sesi'); // label status tidak ditampilkan di daftar viewer
    }
}
