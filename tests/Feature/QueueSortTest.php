<?php

namespace Tests\Feature;

use App\Livewire\QueueBoard;
use App\Models\LiveSession;
use App\Models\QueueEntry;
use App\Models\User;
use App\Services\QueueService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Daftar antrean: pilihan "Urutkan" (Urutan antrean / Terbaru masuk / Terlama masuk) — hanya tampilan,
 * giliran main tetap menurut position — dan label tanggal masuk di bawah nama pemain.
 */
class QueueSortTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private LiveSession $session;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-11-29 21:30:00'));
        $this->owner = User::factory()->create(['party_size' => 2]);
        $this->session = LiveSession::create(['user_id' => $this->owner->id, 'title' => 'Live', 'status' => 'active', 'started_at' => now()]);
        $this->actingAs($this->owner);
    }

    /** @param  array<string, array{0: string|null, 1?: string}>  $rows  nama => [joined_at, status] menurut position */
    private function players(array $rows): array
    {
        $entries = [];
        foreach (array_keys($rows) as $index => $name) {
            [$joined, $status] = $rows[$name] + [1 => 'ready'];
            $entries[$name] = QueueEntry::create([
                'live_session_id' => $this->session->id, 'player_name' => $name, 'matches_total' => 3, 'matches_played' => 0,
                'status' => $status, 'position' => $index + 1, 'joined_at' => $joined,
            ]);
        }

        return $entries;
    }

    private function names($board): array
    {
        return $board->viewData('entries')->pluck('player_name')->all();
    }

    public function test_sort_modes_reorder_the_list_and_keep_the_party_on_top(): void
    {
        // Position = urutan main; tanggal masuk sengaja tidak searah dengan position.
        $this->players([
            'Main' => ['2026-11-27 10:00:00', 'playing'],
            'Lama' => ['2026-11-20 10:00:00'],
            'Baru' => ['2026-11-29 21:00:00'],
            // Hasil tempel notepad: masuk di detik yang sama → seri diurutkan menurut urutan input (id).
            'Notepad A' => ['2026-11-22 19:20:36'],
            'Notepad B' => ['2026-11-22 19:20:36'],
        ]);

        $board = Livewire::test(QueueBoard::class, ['session' => $this->session]);
        $this->assertSame(['Main', 'Lama', 'Baru', 'Notepad A', 'Notepad B'], $this->names($board)); // default tidak berubah
        $board->assertDontSee('Urutan main tetap mengikuti antrean.');

        $board->set('sort', 'newest');
        $this->assertSame(['Main', 'Baru', 'Notepad B', 'Notepad A', 'Lama'], $this->names($board));
        $board->assertSee('Urutan main tetap mengikuti antrean.');

        $board->set('sort', 'oldest');
        $this->assertSame(['Main', 'Lama', 'Notepad A', 'Notepad B', 'Baru'], $this->names($board));

        $board->set('sort', 'acak');
        $this->assertSame('queue', $board->get('sort'));
        $this->assertSame(['Main', 'Lama', 'Baru', 'Notepad A', 'Notepad B'], $this->names($board));
    }

    public function test_sort_combines_with_filter_and_search_and_resets_the_page(): void
    {
        $rows = [];
        foreach (range(1, 12) as $i) {
            $rows["Viewer {$i}"] = [sprintf('2026-11-%02d 12:00:00', $i)];
        }
        $this->players($rows);

        $board = Livewire::test(QueueBoard::class, ['session' => $this->session])->call('goToPage', 2)->assertSet('queuePage', 2);
        $board->set('sort', 'newest')->assertSet('queuePage', 1);
        $this->assertSame('Viewer 12', $this->names($board)[0]);

        $board->set('search', 'Viewer 1');
        $this->assertSame(['Viewer 12', 'Viewer 11', 'Viewer 10', 'Viewer 1'], $this->names($board));
        $this->assertSame('newest', $board->get('sort')); // bertahan saat pencarian/polling

        $board->call('setFilter', 'manual')->call('$refresh');
        $this->assertSame(['Viewer 12', 'Viewer 11', 'Viewer 10', 'Viewer 1'], $this->names($board));
    }

    /** Urutan tampilan tidak boleh mengubah siapa yang main berikutnya. */
    public function test_sort_never_changes_who_plays_next(): void
    {
        $this->players(['Pertama' => ['2026-11-20 10:00:00'], 'Kedua' => ['2026-11-21 10:00:00'], 'Paling baru' => ['2026-11-29 21:00:00']]);

        Livewire::test(QueueBoard::class, ['session' => $this->session])->set('sort', 'newest')
            ->assertViewHas('nextUp', fn ($next) => $next->pluck('player_name')->all() === ['Pertama', 'Kedua']);

        $joined = app(QueueService::class)->fillParty($this->session);
        $this->assertSame(['Pertama', 'Kedua'], $joined->pluck('player_name')->all());
    }

    public function test_joined_label_formats(): void
    {
        $label = fn (?string $joined, ?string $created = null) => (new QueueEntry(['joined_at' => $joined]))
            ->forceFill(['created_at' => $created])->joinedLabel();

        $this->assertSame('masuk hari ini 19:05', $label('2026-11-29 19:05:00'));
        $this->assertSame('masuk kemarin 22:46', $label('2026-11-28 22:46:00'));
        $this->assertSame('masuk 22 Nov', $label('2026-11-22 19:20:36'));
        $this->assertSame('masuk 3 Mei', $label('2026-05-03 08:00:00'));
        $this->assertSame('masuk 22 Des 2025', $label('2025-12-22 10:00:00'));
        $this->assertSame('masuk 24 Nov', $label(null, '2026-11-24 09:00:00')); // data lama: pakai created_at
        $this->assertNull($label(null, null));

        $this->assertSame('Masuk antrean Minggu, 22 November 2026 19:20 WIB', (new QueueEntry(['joined_at' => '2026-11-22 19:20:36']))->joinedTitle());
    }

    public function test_board_shows_joined_label_under_the_name(): void
    {
        $this->players(['Raka' => ['2026-11-29 19:05:00'], 'Nadia' => ['2026-11-22 19:20:36']]);

        Livewire::test(QueueBoard::class, ['session' => $this->session])
            ->assertSee('masuk hari ini 19:05')
            ->assertSee('masuk 22 Nov')
            ->assertSeeHtml('title="Masuk antrean Minggu, 22 November 2026 19:20 WIB"')
            ->assertSeeHtml('<label for="queue-sort">Urutkan</label>');
    }
}
