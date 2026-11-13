<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\BetaAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Panel Super Admin — akses beta streamer.
 *
 * GET   /api/admin/streamers?status=pending&search=…   daftar streamer
 * PATCH /api/admin/streamers/{user}/beta-status         { "beta_status": "active|pending|inactive" }
 */
class StreamerAccessController extends Controller
{
    public function index(Request $request, BetaAccessService $service): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['nullable', Rule::in(BetaAccessService::STATUSES)],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $page = $service->listStreamers($validated['status'] ?? null, $validated['search'] ?? null);

        return response()->json([
            'data' => $page->getCollection()->map(fn (User $user) => $service->present($user))->values(),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
        ]);
    }

    public function updateStatus(Request $request, User $user, BetaAccessService $service): JsonResponse
    {
        abort_unless($user->isStreamer(), 404);

        $validated = $request->validate([
            'beta_status' => ['required', Rule::in(BetaAccessService::STATUSES)],
        ]);

        $changed = $service->setStatus($user, $validated['beta_status']);

        return response()->json([
            'data' => $service->present($user->refresh()),
            'message' => $changed ? 'Status beta diperbarui.' : 'Status beta tidak berubah.',
        ]);
    }
}
