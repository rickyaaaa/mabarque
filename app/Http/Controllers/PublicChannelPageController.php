<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\PublicQueueService;
use App\Services\StreamerProfileService;
use App\Services\VipPackageService;
use Illuminate\Contracts\View\View;

/** Halaman antrean publik penonton: GET /{slug} (tanpa akun). */
class PublicChannelPageController extends Controller
{
    public function __invoke(string $slug, StreamerProfileService $profiles, PublicQueueService $queues, VipPackageService $packages): View
    {
        $user = User::query()->where('slug', strtolower($slug))->first();
        abort_if($user === null || ! $profiles->isPublic($user), 404);

        return view('pages.public-queue', [
            'channel' => $profiles->publicProfile($user),
            'queue' => $queues->forUser($user),
            'packages' => $packages->listForUser($user, true),
        ]);
    }
}
