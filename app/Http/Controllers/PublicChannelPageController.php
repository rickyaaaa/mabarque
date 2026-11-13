<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\StreamerProfileService;
use Illuminate\Contracts\View\View;

/** Halaman antrean publik penonton: GET /{slug} (tanpa akun). */
class PublicChannelPageController extends Controller
{
    public function __invoke(string $slug, StreamerProfileService $profiles): View
    {
        $user = User::query()->where('slug', strtolower($slug))->first();
        abort_if($user === null || ! $profiles->isPublic($user), 404);

        return view('pages.public-queue', ['channel' => $profiles->publicProfile($user)]);
    }
}
