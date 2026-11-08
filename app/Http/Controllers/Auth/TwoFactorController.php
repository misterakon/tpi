<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\CodesSecours;
use App\Services\DoubleAuth;
use App\Services\Journal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class TwoFactorController extends Controller
{
    private const SESSION_ESSAIS_SECOURS = 'auth.essais_secours';

    public function __construct(private readonly DoubleAuth $doubleAuth, private readonly CodesSecours $codesSecours) {}

    public function show(Request $request): View|RedirectResponse
    {
        $user = $this->utilisateurEnAttente($request);
        if (! $user) {
            return redirect()->route('login');
        }

        return view('auth.two-factor', [
            'email' => $this->masquer($user->email),
            'validite' => (int) config('securite.double_auth.validite_minutes'),
            'attente' => $this->doubleAuth->attente($user),
        ]);
    }

    public function verify(Request $request): JsonResponse|RedirectResponse
    {
        $user = $this->utilisateurEnAttente($request);
        if (! $user) {
            return $this->retourConnexion($request);
        }

        $request->validate(['code' => ['required', 'string', 'regex:/^\d{6}$/']], [
            'code.required' => 'Saisissez les 6 chiffres du code.',
            'code.regex' => 'Le code comporte 6 chiffres.',
        ]);

        $resultat = $this->doubleAuth->verifier($user, $request->string('code')->toString());

        if ($resultat !== DoubleAuth::OK) {
            Journal::connexion(Journal::CODE_ECHEC, $user, false, $resultat, $user->email);

            if (in_array($resultat, [DoubleAuth::TROP_D_ESSAIS, DoubleAuth::EXPIRE, DoubleAuth::ABSENT], true)) {
                $request->session()->forget(LoginController::SESSION_ATTENTE_CODE);
            }

            throw ValidationException::withMessages(['code' => match ($resultat) {
                DoubleAuth::TROP_D_ESSAIS => 'Trop d\'essais. Reconnectez-vous pour recevoir un nouveau code.',
                DoubleAuth::EXPIRE => 'Ce code a expiré. Reconnectez-vous pour en recevoir un nouveau.',
                DoubleAuth::ABSENT => 'Aucun code actif. Reconnectez-vous.',
                default => 'Code incorrect.',
            }]);
        }

        // Compte désactivé ou verrouillé entre-temps : on refuse malgré le bon code.
        $user->refresh();
        if (! $user->peutSeConnecter()) {
            $request->session()->forget(LoginController::SESSION_ATTENTE_CODE);
            Journal::connexion(Journal::CONNEXION_ECHEC, $user, false, 'compte indisponible', $user->email);
            throw ValidationException::withMessages(['code' => 'Ce compte n\'est plus disponible. Contactez l\'administrateur.']);
        }

        // Ouverture de la session authentifiée, avec un nouvel identifiant de session.
        $request->session()->forget(LoginController::SESSION_ATTENTE_CODE);
        Auth::guard('web')->login($user);
        $request->session()->regenerate();
        $request->session()->put('derniere_activite', now()->timestamp);

        $user->forceFill(['last_login_at' => now(), 'last_login_ip' => $request->ip()])->save();

        Journal::connexion(Journal::CODE_VALIDE, $user, true, null, $user->email);
        Journal::connexion(Journal::CONNEXION_REUSSIE, $user, true, null, $user->email);

        return $request->expectsJson()
            ? response()->json(['redirect' => route('dashboard')])
            : redirect()->route('dashboard');
    }

    /** Connexion avec un code de secours, quand le code par e-mail n'arrive pas : même mot de passe d'abord, un code ne sert qu'une fois. */
    public function secours(Request $request): JsonResponse|RedirectResponse
    {
        $user = $this->utilisateurEnAttente($request);
        if (! $user) {
            return $this->retourConnexion($request);
        }

        $donnees = $request->validate(['code_secours' => ['required', 'string', 'max:20']], ['code_secours.required' => 'Saisissez un de vos codes de secours.']);

        $essais = (int) $request->session()->get(self::SESSION_ESSAIS_SECOURS, 0);
        $maximum = (int) config('securite.double_auth.tentatives_max');
        if ($essais >= $maximum) {
            $request->session()->forget([LoginController::SESSION_ATTENTE_CODE, self::SESSION_ESSAIS_SECOURS]);

            throw ValidationException::withMessages(['code_secours' => "Trop d'essais. Reconnectez-vous pour réessayer."]);
        }

        if (! $this->codesSecours->utiliser($user, $donnees['code_secours'], $request->ip())) {
            $request->session()->put(self::SESSION_ESSAIS_SECOURS, $essais + 1);
            Journal::connexion(Journal::CODE_SECOURS_ECHEC, $user, false, 'code de secours incorrect ou déjà utilisé', $user->email);

            throw ValidationException::withMessages(['code_secours' => 'Code de secours incorrect ou déjà utilisé.']);
        }

        $user->refresh();
        if (! $user->peutSeConnecter()) {
            $request->session()->forget([LoginController::SESSION_ATTENTE_CODE, self::SESSION_ESSAIS_SECOURS]);
            Journal::connexion(Journal::CONNEXION_ECHEC, $user, false, 'compte indisponible', $user->email);
            throw ValidationException::withMessages(['code_secours' => "Ce compte n'est plus disponible. Contactez l'administrateur."]);
        }

        $request->session()->forget([LoginController::SESSION_ATTENTE_CODE, self::SESSION_ESSAIS_SECOURS]);
        $this->doubleAuth->annuler($user);   // le code par e-mail en attente n'a plus d'objet
        Auth::guard('web')->login($user);
        $request->session()->regenerate();
        $request->session()->put('derniere_activite', now()->timestamp);
        $user->forceFill(['last_login_at' => now(), 'last_login_ip' => $request->ip()])->save();

        $etat = $this->codesSecours->etat($user);
        Journal::connexion(Journal::CODE_SECOURS_UTILISE, $user, true, null, $user->email, ['restants' => $etat['restants']]);
        Journal::connexion(Journal::CONNEXION_REUSSIE, $user, true, 'code de secours', $user->email);

        // Peu de codes restants : l'utilisateur est invité à en générer de nouveaux dès son arrivée.
        if ($etat['restants'] <= 2) {
            $request->session()->flash('warning', $etat['restants'] === 0 ? 'Vous avez utilisé votre dernier code de secours : générez-en de nouveaux dans « Mon compte ».' : 'Il vous reste '.$etat['restants'].' code(s) de secours : pensez à en générer de nouveaux dans « Mon compte ».');
        }

        return $request->expectsJson()
            ? response()->json(['redirect' => route('dashboard')])
            : redirect()->route('dashboard');
    }

    public function resend(Request $request): JsonResponse|RedirectResponse
    {
        $user = $this->utilisateurEnAttente($request);
        if (! $user) {
            return $this->retourConnexion($request);
        }

        $etat = $this->doubleAuth->renvoiPossible($user);

        if ($etat === DoubleAuth::RENVOI_LIMITE) {
            return response()->json(['message' => 'Nombre maximal d\'envois atteint. Reconnectez-vous.'], 429);
        }
        if ($etat === DoubleAuth::RENVOI_TROP_RAPIDE) {
            return response()->json(['message' => 'Patientez avant de demander un nouveau code.', 'attente' => $this->doubleAuth->attente($user)], 429);
        }

        try {
            $this->doubleAuth->envoyer($user, renvoi: true);
        } catch (Throwable $e) {
            Log::error('Renvoi du code impossible', ['user_id' => $user->id, 'erreur' => $e->getMessage()]);

            return response()->json(['message' => "Le code n'a pas pu être envoyé. Réessayez dans un instant."], 500);
        }

        Journal::connexion(Journal::CODE_RENVOYE, $user, true, null, $user->email);

        return response()->json([
            'message' => 'Un nouveau code vient d\'être envoyé.',
            'attente' => (int) config('securite.double_auth.renvoi_secondes'),
        ]);
    }

    /** Utilisateur ayant saisi son mot de passe et attendant son code (jamais un utilisateur déjà connecté). */
    private function utilisateurEnAttente(Request $request): ?User
    {
        $etat = $request->session()->get(LoginController::SESSION_ATTENTE_CODE);
        if (! is_array($etat) || empty($etat['user_id']) || empty($etat['debut'])) {
            return null;
        }

        $limite = (int) config('securite.double_auth.validite_minutes') * 60 * 2;
        if (now()->timestamp - (int) $etat['debut'] > $limite) {
            $request->session()->forget(LoginController::SESSION_ATTENTE_CODE);

            return null;
        }

        return User::find($etat['user_id']);
    }

    private function retourConnexion(Request $request): JsonResponse|RedirectResponse
    {
        return $request->expectsJson()
            ? response()->json(['redirect' => route('login')])
            : redirect()->route('login');
    }

    /** j***@exemple.ci : affiche assez pour reconnaître l'adresse, sans l'exposer en entier. */
    private function masquer(string $email): string
    {
        [$local, $domaine] = array_pad(explode('@', $email, 2), 2, '');

        return Str::substr($local, 0, 1).str_repeat('*', max(2, min(6, Str::length($local) - 1))).'@'.$domaine;
    }
}
