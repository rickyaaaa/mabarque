<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Proteksi route dashboard streamer selama private alpha / closed beta:
 * hanya akun dengan beta_status "active" (atau Super Admin) yang boleh lewat.
 * Pasang SETELAH middleware "auth".
 */
class EnsureActiveBeta
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var User|null $user */
        $user = $request->user();

        if ($user === null) {
            abort(401);
        }

        if (! $user->hasActiveBeta()) {
            return response()->json([
                'message' => $user->beta_status === User::BETA_INACTIVE
                    ? 'Akun ini sedang dinonaktifkan.'
                    : 'Akses private alpha kamu masih menunggu persetujuan admin.',
                'beta_status' => $user->beta_status,
            ], 403);
        }

        return $next($request);
    }
}
