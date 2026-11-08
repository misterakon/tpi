<?php

namespace App\Providers;

use App\Models\Profil;
use App\Models\User;
use App\Policies\ProfilPolicy;
use App\Policies\UserPolicy;
use App\Services\Reglages;
use App\Support\Droits;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Reglages::class);
    }

    public function boot(): void
    {
        if ($this->app->isProduction()) {
            URL::forceScheme('https');
        }

        // Réglages modifiables depuis l'application (page « Réglages ») : posés dans config() avant tout le reste.
        $this->app->make(Reglages::class)->appliquer();

        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Profil::class, ProfilPolicy::class);

        // Chaque droit du catalogue est utilisable avec @can('comptes.voir'), can:comptes.voir, etc.
        foreach (Droits::tous() as $droit) {
            Gate::define($droit, fn (User $user) => $user->peut($droit));
        }

        // Directives Blade : @role('Greffier') … @endrole, @hasanyrole('Greffier|Président') … @endhasanyrole, @admin … @endadmin
        Blade::if('role', fn (string $role) => auth()->user()?->hasRole($role) === true);
        Blade::if('hasrole', fn (string $role) => auth()->user()?->hasRole($role) === true);
        Blade::if('hasanyrole', fn (string|array $roles) => auth()->user()?->hasAnyRole($roles) === true);
        Blade::if('admin', fn () => auth()->user()?->estAdmin() === true);

        $this->limiteursDeDebit();
        $this->courrielMotDePasse();
    }

    /** Limites de débit : freinent les essais automatisés de mots de passe et de codes. */
    private function limiteursDeDebit(): void
    {
        $trop = fn (string $message) => fn (Request $r, array $headers) => response()->json([
            'message' => $message,
        ], 429, $headers);

        RateLimiter::for('connexion', fn (Request $r) => [
            Limit::perMinute(5)->by('connexion|'.mb_strtolower((string) $r->input('email')).'|'.$r->ip())
                ->response($trop('Trop de tentatives. Patientez une minute avant de réessayer.')),
            Limit::perMinute(30)->by('connexion-ip|'.$r->ip())
                ->response($trop('Trop de tentatives depuis ce poste. Patientez avant de réessayer.')),
        ]);

        RateLimiter::for('double-auth', fn (Request $r) => [
            Limit::perMinute(10)->by('2fa|'.$r->ip().'|'.($r->hasSession() ? $r->session()->getId() : ''))
                ->response($trop('Trop de tentatives. Patientez une minute avant de réessayer.')),
        ]);

        RateLimiter::for('deverrouillage', fn (Request $r) => [
            Limit::perMinute(10)->by('deverrouillage|'.($r->user()?->id ?? $r->ip()))
                ->response($trop('Trop de tentatives. Patientez une minute avant de réessayer.')),
        ]);

        RateLimiter::for('mot-de-passe', fn (Request $r) => [
            Limit::perMinute(5)->by('mdp|'.$r->ip())
                ->response($trop('Trop de demandes. Patientez une minute avant de réessayer.')),
        ]);
    }

    /** E-mail de réinitialisation du mot de passe, en français. */
    private function courrielMotDePasse(): void
    {
        ResetPassword::toMailUsing(function (object $notifiable, string $token) {
            $lien = url(route('password.reset', [
                'token' => $token,
                'email' => $notifiable->getEmailForPasswordReset(),
            ], false));
            $minutes = config('auth.passwords.'.config('auth.defaults.passwords').'.expire');

            return (new MailMessage)
                ->subject('Choisir un nouveau mot de passe — '.config('app.name'))
                ->view('emails.mot-de-passe', ['lien' => $lien, 'minutes' => $minutes]);
        });
    }
}
