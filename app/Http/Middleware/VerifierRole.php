<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/* Utilisation : ->middleware('role:Greffier|Président') — l'utilisateur doit avoir l'un de ces profils. */
class VerifierRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        abort_unless($request->user()?->hasAnyRole($roles), 403, "Cette page est réservée à d'autres profils.");

        return $next($request);
    }
}
