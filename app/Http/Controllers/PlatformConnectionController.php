<?php

namespace App\Http\Controllers;

use App\Http\Requests\PlatformConnectionRequest;
use App\Models\PlatformConnection;
use App\Services\PlatformConnectionHealthService;
use App\Services\PlatformConnectionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlatformConnectionController extends Controller
{
    public function index(Request $request, PlatformConnectionService $service): JsonResponse
    {
        return response()->json(['data' => $service->listForUser($request->user())]);
    }

    public function show(Request $request, PlatformConnection $platformConnection): JsonResponse
    {
        abort_unless($platformConnection->isOwnedBy($request->user()), 404);

        return response()->json(['data' => $platformConnection]);
    }

    public function store(PlatformConnectionRequest $request, PlatformConnectionService $service): JsonResponse
    {
        return response()->json(['data' => $service->save($request->user(), $request->validated())], 201);
    }

    public function test(Request $request, PlatformConnection $platformConnection, PlatformConnectionHealthService $healthService): JsonResponse
    {
        abort_unless($platformConnection->isOwnedBy($request->user()), 403);

        return response()->json(['data' => $healthService->check($platformConnection)]);
    }

    public function pause(Request $request, PlatformConnection $platformConnection, PlatformConnectionService $service): JsonResponse
    {
        return response()->json(['data' => $service->pause($request->user(), $platformConnection)]);
    }
}
