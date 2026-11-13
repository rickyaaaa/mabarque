<?php

namespace App\Http\Controllers;

use App\Models\LiveSession;
use App\Services\LiveSessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Sesi live mabar milik streamer.
 *
 * GET  /api/live-sessions/archives              sesi yang sudah selesai
 * POST /api/live-sessions                       mulai sesi baru (sesi aktif lama otomatis diakhiri)
 * GET  /api/live-sessions/{id}                  ringkasan sesi
 * POST /api/live-sessions/{id}/end              akhiri sesi
 * POST /api/live-sessions/{id}/archive          akhiri & tandai sisa pemain selesai
 * POST /api/live-sessions/{id}/reset-queue      kosongkan antrean (pemain tersisa → done)
 */
class LiveSessionController extends Controller
{
    public function archives(Request $request, LiveSessionService $service): JsonResponse
    {
        return response()->json(['data' => $service->archived($request->user())]);
    }

    public function store(Request $request, LiveSessionService $service): JsonResponse
    {
        $validated = $request->validate(['title' => ['required', 'string', 'min:3', 'max:120']]);
        $session = $service->start($request->user(), $validated['title']);

        return response()->json(['data' => $service->summary($session)], 201);
    }

    public function show(Request $request, LiveSession $liveSession, LiveSessionService $service): JsonResponse
    {
        abort_unless($liveSession->isOwnedBy($request->user()), 404);

        return response()->json(['data' => $service->summary($liveSession)]);
    }

    public function end(Request $request, LiveSession $liveSession, LiveSessionService $service): JsonResponse
    {
        return response()->json(['data' => $service->summary($service->end($request->user(), $liveSession))]);
    }

    public function archive(Request $request, LiveSession $liveSession, LiveSessionService $service): JsonResponse
    {
        return response()->json(['data' => $service->summary($service->archive($request->user(), $liveSession))]);
    }

    public function resetQueue(Request $request, LiveSession $liveSession, LiveSessionService $service): JsonResponse
    {
        $cleared = $service->resetQueue($request->user(), $liveSession);

        return response()->json(['data' => ['cleared' => $cleared], 'message' => "{$cleared} pemain dikeluarkan dari antrean."]);
    }
}
