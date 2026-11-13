<?php

namespace App\Http\Controllers;

use App\Models\LiveSession;
use App\Models\MatchRecord;
use App\Models\QueueEntry;
use App\Services\MatchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;

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
        $cacheKey = 'match:idempotency:'.$request->user()->getKey().':'.hash('sha256', $idempotencyKey);
        $cached = Cache::get($cacheKey);

        if ($cached !== null && ! isset($cached['processing'])) {
            return response()->json($cached, 200);
        }

        if (! Cache::add($cacheKey, ['processing' => true], now()->addHours(24))) {
            return response()->json(['message' => 'Permintaan dengan Idempotency-Key yang sama sedang diproses.'], 409);
        }

        try {
            $match = $matchService->record($queueEntry, $request->string('note')->value());
            $result = [
                'data' => [
                    'match' => $match->fresh(),
                    'matches_remaining' => $matchService->remaining($queueEntry),
                    'quota_status' => $matchService->quotaStatus($queueEntry),
                ],
            ];
        } catch (InvalidArgumentException $exception) {
            // Aturan bisnis (mis. jatah match habis) → 422, bukan 500.
            Cache::forget($cacheKey);

            return response()->json(['message' => $exception->getMessage()], 422);
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

        try {
            $entry = $matchService->undo($match);
        } catch (InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'queue_entry_id' => $entry->id,
            'matches_remaining' => $matchService->remaining($entry),
            'quota_status' => $matchService->quotaStatus($entry),
        ]);
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
