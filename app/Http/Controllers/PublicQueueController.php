<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\PublicQueueService;
use App\Services\StreamerProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicQueueController extends Controller
{
    public function show(User $user, PublicQueueService $queueService, StreamerProfileService $profileService): JsonResponse
    {
        $profileService->assertPublic($user);

        return response()->json(['data' => $queueService->forUser($user) + ['channel' => $profileService->publicProfile($user)]]);
    }

    public function search(Request $request, User $user, PublicQueueService $queueService, StreamerProfileService $profileService): JsonResponse
    {
        $profileService->assertPublic($user);

        return response()->json(['data' => $queueService->search($user, $request->string('q')->trim()->value())]);
    }
}
