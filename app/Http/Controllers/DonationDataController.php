<?php

namespace App\Http\Controllers;

use App\Models\Donation;
use App\Models\LiveSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DonationDataController extends Controller
{
    public function donations(Request $request): JsonResponse
    {
        $query = Donation::query()
            ->whereHas('platformConnection', fn ($connection) => $connection->where('user_id', $request->user()->id))
            ->with(['vipPackage:id,name,price,match_count', 'queueEntry:id,player_name,position,status']);

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        if ($request->filled('provider')) {
            $query->where('provider', $request->string('provider')->toString());
        }

        return response()->json([
            'data' => $query->latest('received_at')->paginate(max(1, min($request->integer('per_page', 20), 100))),
        ]);
    }

    public function sessionDonations(Request $request, LiveSession $liveSession): JsonResponse
    {
        $this->authorizeSession($request, $liveSession);

        return response()->json([
            'data' => $liveSession->donations()->with(['vipPackage', 'queueEntry'])->latest('received_at')->get(),
        ]);
    }

    public function queue(Request $request, LiveSession $liveSession): JsonResponse
    {
        $this->authorizeSession($request, $liveSession);

        return response()->json([
            'data' => $liveSession->queueEntries()
                ->whereIn('status', ['ready', 'offline', 'playing', 'skipped'])
                ->with('vipPackage:id,name,price,match_count')
                ->orderBy('position')
                ->get(),
        ]);
    }

    private function authorizeSession(Request $request, LiveSession $liveSession): void
    {
        abort_unless((int) $liveSession->user_id === (int) $request->user()->id, 404);
    }
}
