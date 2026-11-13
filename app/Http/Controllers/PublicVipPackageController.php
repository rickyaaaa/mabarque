<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\StreamerProfileService;
use App\Services\VipPackageService;
use Illuminate\Http\JsonResponse;

class PublicVipPackageController extends Controller
{
    public function index(User $user, VipPackageService $packageService, StreamerProfileService $profileService): JsonResponse
    {
        $profileService->assertPublic($user);

        return response()->json([
            'data' => $packageService->listForUser($user, true)->map(fn ($package) => [
                'name' => $package->name,
                'price' => $package->price,
                'match_count' => $package->match_count,
            ])->values(),
        ]);
    }
}
