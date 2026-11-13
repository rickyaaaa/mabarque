<?php

namespace App\Http\Controllers;

use App\Models\MatchRecord;
use App\Models\QueueEntry;
use App\Models\LiveSession;
use App\Services\MatchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class MatchController extends Controller
{
    public function store(Request $request, QueueEntry $queueEntry, MatchService $matchService): JsonResponse
    {
        abort_unless($queueEntry->isOwnedBy($request->user()), 404);

        $idempotencyKey = trim((string) $request->header('Idempotency-Key'));

        if ($idempotencyKey === '') {
            return response()->json(['message' => 'Header Idempotency-Key wajib diisi.'], 422);
        }

        // Dikunci per streamer supaya key yang sama dari akun lain tidak bisa membaca hasil milik orang lain.
        $cacheKey = 'match:idempotency:' . $request->user()->getKey() . ':' . hash('sha256', $idempotencyKey);
        $cached = Cache::get($cacheKey);

        if ($cached !== null && !isset($cached['processing'])) {
            return response()->json($cached, 200);
        }

        if (!Cache::add($cacheKey, ['processing' => true], now()->addHours(24))) {
            return response()->json(['message' => 'Permintaan dengan Idempotency-Key yang sama sedang diproses.'], 409);
        }

        try {
            $result = DB::transaction(function () use ($queueEntry, $matchService, $request): array {
                $entry = QueueEntry::query()->whereKey($queueEntry->id)->lockForUpdate()->firstOrFail();
                $matchService->assertCanRecord($entry);

                $nextNumber = (int) $entry->matches()->max('match_number') + 1;
                $match = MatchRecord::query()->create([
                    'queue_entry_id' => $entry->id,
                    'live_session_id' => $entry->live_session_id,
                    'match_number' => $nextNumber,
                    'note' => $request->string('note')->trim()->value() ?: null,
                    'played_at' => now(),
                ]);

                $entry->increment('matches_played');

                return [
                    'data' => [
                        'match' => $match->fresh(),
                        'matches_remaining' => $matchService->remaining($entry->refresh()),
                        'quota_status' => $matchService->quotaStatus($entry),
                    ],
                ];
            });
        } catch (\Throwable $exception) {
            Cache::forget($cacheKey);
            throw $exception;
        }

        Cache::put($cacheKey, $result, now()->addHours(24));

        return response()->json($result, 201);
    }

    public function destroy(Request $request, MatchRecord $match, MatchService $matchService): JsonResponse
    {
        abort_unless($match->isOwnedBy($request->user()), 404);

        $result = DB::transaction(function () use ($match, $matchService): array {
            $record = MatchRecord::query()->whereKey($match->id)->lockForUpdate()->firstOrFail();
            $entry = QueueEntry::query()->whereKey($record->queue_entry_id)->lockForUpdate()->firstOrFail();

            if (!$matchService->canCorrect($entry)) {
                return ['message' => 'Tidak ada match tercatat yang bisa dikoreksi.', 'status' => 422];
            }

            $record->delete();
            $entry->decrement('matches_played');

            return [
                'data' => [
                    'queue_entry_id' => $entry->id,
                    'matches_remaining' => $matchService->remaining($entry->refresh()),
                    'quota_status' => $matchService->quotaStatus($entry),
                ],
                'status' => 200,
            ];
        });

        return response()->json($result['data'] ?? ['message' => $result['message']], $result['status']);
    }

    public function today(Request $request, LiveSession $liveSession): JsonResponse
    {
        abort_unless($liveSession->isOwnedBy($request->user()), 404);

        $matches = MatchRecord::query()
            ->with('queueEntry:id,player_name')
            ->whereDate('played_at', today())
            ->whereHas('queueEntry', fn ($query) => $query->where('live_session_id', $liveSession->id))
            ->latest('played_at')
            ->get();

        return response()->json(['data' => $matches]);
    }
}
