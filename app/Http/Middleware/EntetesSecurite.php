<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/*
 | En-têtes de sécurité envoyés avec chaque réponse.
 | La politique de contenu (CSP) autorise les scripts en ligne du thème actuel ;
 | elle sera resserrée quand les scripts auront été sortis des pages.
 */
class EntetesSecurite
{
    public function handle(Request $request, Closure $next): Response
    {
        $reponse = $next($request);

        // Seuls le PDF d'une ordonnance, un fichier du dossier et l'aperçu PDF d'un rôle peuvent être affichés dans un cadre, et uniquement par l'application elle-même.
        $integrable = $request->routeIs('organisation.ordonnances.lire', 'affaires.dossier.fichier') || ($request->routeIs('audiences.roles.export') && $request->boolean('apercu'));

        $csp = implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' 'unsafe-eval'",
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
            "font-src 'self' data: https://fonts.gstatic.com",
            "img-src 'self' data: blob:",
            "frame-src 'self' blob:",
            "connect-src 'self'",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            $integrable ? "frame-ancestors 'self'" : "frame-ancestors 'none'",
        ]);

        header_remove('X-Powered-By');   // ne pas annoncer la version de PHP
        $reponse->headers->set('Content-Security-Policy', $csp);
        $reponse->headers->set('X-Frame-Options', $integrable ? 'SAMEORIGIN' : 'DENY');
        $reponse->headers->set('X-Content-Type-Options', 'nosniff');
        $reponse->headers->set('Referrer-Policy', 'same-origin');
        $reponse->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(), usb=()');
        $reponse->headers->set('Cross-Origin-Opener-Policy', 'same-origin');
        $reponse->headers->set('Cross-Origin-Resource-Policy', 'same-origin');

        if ($request->isSecure()) {
            $reponse->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        // Pages du tribunal : jamais conservées en cache (ni réaffichées par le bouton « retour » après déconnexion).
        if (! $request->is('assets/*', 'build/*')) {
            $reponse->headers->set('Cache-Control', 'no-store, private');
            $reponse->headers->set('Pragma', 'no-cache');
        }

        return $reponse;
    }
}
