<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Hanya Super Admin yang boleh mengakses rute /api/admin/*. Pasang SETELAH "auth". */
class EnsureSuperAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        // 404 (bukan 403) supaya keberadaan panel admin tidak terlihat oleh streamer biasa.
        abort_unless($request->user()?->isSuperAdmin(), 404);

        return $next($request);
    }
}
