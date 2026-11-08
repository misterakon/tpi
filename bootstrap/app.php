<?php

use App\Http\Middleware\DeconnexionInactivite;
use App\Http\Middleware\EntetesSecurite;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\AuthenticateSession;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->appendToGroup('web', EntetesSecurite::class);

        $middleware->alias([
            'inactivite' => DeconnexionInactivite::class,
            'auth.session' => AuthenticateSession::class,
            'role' => \App\Http\Middleware\VerifierRole::class,
        ]);

        // Un visiteur non connecté va à la connexion ; un utilisateur connecté qui ouvre /login va au tableau de bord.
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('dashboard'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
