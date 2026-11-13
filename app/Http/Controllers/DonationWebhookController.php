<?php

namespace App\Http\Controllers;

use App\Models\PlatformConnection;
use App\Services\DonationWebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DonationWebhookController extends Controller
{
    public function store(Request $request, string $provider, PlatformConnection $platformConnection, DonationWebhookService $service): JsonResponse
    {
        $donation = $service->receive($platformConnection, $request);

        return response()->json([
            'data' => [
                'id' => $donation->id,
                'status' => $donation->status,
                'duplicate' => ! $donation->wasRecentlyCreated,
            ],
        ], $donation->wasRecentlyCreated ? 201 : 200);
    }
}
