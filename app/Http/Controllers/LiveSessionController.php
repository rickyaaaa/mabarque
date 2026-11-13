<?php

namespace App\Http\Controllers;

use App\Models\LiveSession;
use App\Services\LiveSessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LiveSessionController extends Controller
{
    public function archives(Request $request, LiveSessionService $service): JsonResponse
    {
        return response()->json(['data' => $service->archived($request->user())]);
    }

    public function show(Request $request, LiveSession $liveSession, LiveSessionService $service): JsonResponse
    {
        abort_unless((int) $liveSession->user_id === (int) $request->user()->id, 404);

        return response()->json(['data' => $service->summary($liveSession)]);
    }
}
