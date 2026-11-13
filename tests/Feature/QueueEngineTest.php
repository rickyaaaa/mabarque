<?php

namespace Tests\Feature;

use App\Livewire\QueueBoard;
use App\Models\LiveSession;
use App\Models\QueueEntry;
use App\Models\User;
use App\Services\QueueService;
use App\Services\QueueStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

/** Queue Engine: urutan, giliran sekarang, status siap/offline, geser/lewati/hapus, dan papan Livewire. */
class QueueEngineTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private LiveSession $session;

    private QueueService $queue;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create();
        $this->session = LiveSession::create(['user_id' => $this->owner->id, 'title' => 'Mabar malam', 'status' => 'active', 'started_at' => now()]);
        $this->queue = app(QueueService::class);
    }

    /** @return array<string, QueueEntry> */
    private function seedPlayers(string ...$names): array
    {
        $entries = [];
        foreach ($names as $name) {
            $entries[$name] = $this->queue->addManual($this->session, $name, 3);
        }

        return $entries;
    }

    /** @return array<string, array{int, string}> nama => [posisi, status] */
    private function board(): array
    {
        return $this->session->queueEntries()->get()
            ->mapWithKeys(fn (QueueEntry $e) => [$e->player_name => [$e->position, $e->status]])
            ->all();
    }

    public function test_add_manual_appends_in_order_and_counts_players(): void
    {
        $this->seedPlayers('Raka', 'Nadia', 'Bimo');

        $this->assertSame(['Raka' => [1, 'ready'], 'Nadia' => [2, 'ready'], 'Bimo' => [3, 'ready']], $this->board());
        $this->assertSame(3, $this->session->refresh()->total_players);
        $this->assertSame('manual', QueueEntry::first()->provider);
    }

    public function test_add_manual_rejects_invalid_input(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->queue->addManual($this->session, '   ', 1);
    }

    public function test_current_prefers_playing_then_first_ready(): void
    {
        ['Nadia' => $nadia] = $this->seedPlayers('Raka', 'Nadia');

        $this->assertSame('Raka', $this->queue->current($this->session)->player_name);

        $this->queue->markPlaying($this->session, $nadia);
        $this->assertSame('Nadia', $this->queue->current($this->session)->player_name);
    }

    public function test_mark_playing_keeps_only_one_player_playing(): void
    {
        ['Raka' => $raka, 'Nadia' => $nadia] = $this->seedPlayers('Raka', 'Nadia');

        $this->queue->markPlaying($this->session, $raka);
        $this->queue->markPlaying($this->session, $nadia);

        $this->assertSame(1, $this->session->queueEntries()->where('status', 'playing')->count());
        $this->assertSame('ready', $raka->refresh()->status);
    }

    public function test_move_swaps_neighbours_and_ignores_edges(): void
    {
        ['Raka' => $raka, 'Bimo' => $bimo] = $this->seedPlayers('Raka', 'Nadia', 'Bimo');

        $this->queue->move($this->session, $bimo, 'up');
        $this->assertSame(['Raka' => [1, 'ready'], 'Bimo' => [2, 'ready'], 'Nadia' => [3, 'ready']], $this->board());

        $this->queue->move($this->session, $raka->refresh(), 'up'); // sudah paling atas
        $this->assertSame(1, $raka->refresh()->position);
    }

    public function test_skipping_playing_player_passes_turn_to_next_ready(): void
    {
        ['Raka' => $raka] = $this->seedPlayers('Raka', 'Nadia', 'Bimo');
        $this->queue->markPlaying($this->session, $raka);

        $next = $this->queue->skip($this->session, $raka->refresh());

        $this->assertSame('Nadia', $next->player_name);
        $this->assertSame(['Nadia' => [1, 'playing'], 'Bimo' => [2, 'ready'], 'Raka' => [3, 'skipped']], $this->board());
    }

    public function test_skipping_waiting_player_does_not_create_second_playing_player(): void
    {
        ['Raka' => $raka, 'Nadia' => $nadia] = $this->seedPlayers('Raka', 'Nadia', 'Bimo');
        $this->queue->markPlaying($this->session, $raka);

        $this->queue->skip($this->session, $nadia);

        $this->assertSame(1, $this->session->queueEntries()->where('status', 'playing')->count());
        $this->assertSame('playing', $raka->refresh()->status);
        $this->assertSame([3, 'skipped'], $this->board()['Nadia']);
    }

    public function test_remove_playing_player_promotes_next_and_normalizes_positions(): void
    {
        ['Raka' => $raka] = $this->seedPlayers('Raka', 'Nadia', 'Bimo');
        $this->queue->markPlaying($this->session, $raka);

        $next = $this->queue->remove($this->session, $raka->refresh());

        $this->assertSame('Nadia', $next->player_name);
        $this->assertSame(['Nadia' => [1, 'playing'], 'Bimo' => [2, 'ready']], $this->board());
    }

    public function test_entries_from_another_session_are_rejected(): void
    {
        $other = LiveSession::create(['user_id' => $this->owner->id, 'title' => 'Sesi lain', 'status' => 'ended']);
        $foreign = $this->queue->addManual($other, 'Asing', 1);

        $this->expectException(RuntimeException::class);
        $this->queue->skip($this->session, $foreign);
    }

    public function test_status_toggle_rules(): void
    {
        ['Raka' => $raka, 'Nadia' => $nadia] = $this->seedPlayers('Raka', 'Nadia');
        $status = app(QueueStatusService::class);

        $this->assertSame('offline', $status->toggle($this->session, $nadia)->status);
        $this->assertSame('ready', $status->toggle($this->session, $nadia->refresh())->status);

        $this->queue->markPlaying($this->session, $raka);
        $this->expectException(InvalidArgumentException::class);
        $status->markOffline($this->session, $raka->refresh());
    }

    public function test_livewire_board_renders_and_manages_queue(): void
    {
        $this->seedPlayers('Raka');

        Livewire::actingAs($this->owner)
            ->test(QueueBoard::class, ['session' => $this->session])
            ->assertSee('Mabar malam')
            ->assertSee('Raka')
            ->set('playerName', 'Nadia')
            ->set('matchesTotal', 2)
            ->call('addPlayer')
            ->assertHasNoErrors()
            ->assertSee('Nadia')
            ->set('playerName', 'N')
            ->call('addPlayer')
            ->assertHasErrors(['playerName']);

        $this->assertSame(2, $this->session->queueEntries()->count());
    }

    public function test_livewire_board_shows_rule_violation_as_message_not_error_page(): void
    {
        ['Raka' => $raka] = $this->seedPlayers('Raka', 'Nadia');
        $this->queue->markPlaying($this->session, $raka);

        Livewire::actingAs($this->owner)
            ->test(QueueBoard::class, ['session' => $this->session])
            ->call('toggleStatus', $raka->id)
            ->assertHasErrors(['queue'])
            ->assertSee('tidak dapat diubah statusnya');

        $this->assertSame('playing', $raka->refresh()->status);
    }

    public function test_livewire_board_is_owner_only(): void
    {
        Livewire::actingAs(User::factory()->create())
            ->test(QueueBoard::class, ['session' => $this->session])
            ->assertStatus(404);
    }
}
