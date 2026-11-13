<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Super Admin tidak punya stream, jadi halaman workspace streamer dialihkan ke panel admin (/admin).
 * Login tetap satu (/login); pemisahan dilakukan per area.
 */
class RedirectSuperAdminToPanel
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->isSuperAdmin()) {
            return redirect('/admin');
        }

        return $next($request);
    }
}
