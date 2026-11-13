<?php

namespace App\Http\Controllers;

use App\Models\PlatformConnection;
use App\Services\DonationWebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** POST /api/webhooks/donations/{provider}/{platformConnection} — dipanggil server Saweria/Trakteer/SociaBuzz. */
class DonationWebhookController extends Controller
{
    public function store(Request $request, string $provider, PlatformConnection $platformConnection, DonationWebhookService $service): JsonResponse
    {
        $result = $service->receive($platformConnection, $request);

        return response()->json($result->body, $result->status);
    }
}
