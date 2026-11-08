<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\DoubleAuth;
use App\Services\Journal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class LoginController extends Controller
{
    public const SESSION_ATTENTE_CODE = 'auth.double_auth';

    private const MESSAGE_IDENTIFIANTS = 'Adresse e-mail ou mot de passe incorrect.';

    /** Empreinte bcrypt (coût 12) d'un mot de passe jeté : sert à dépenser le même temps quand l'adresse est inconnue. */
    private const EMPREINTE_FICTIVE = '$2y$12$XvNqGQjVAIEWPqGJ4p1xQOl7aWqY3oX1w5eJmX2D1H0m9yJ4m6n2G';

    public function __construct(private readonly DoubleAuth $doubleAuth) {}

    public function show(Request $request): View
    {
        return view('auth.login');
    }

    /**
     * Étape 1 : e-mail + mot de passe. Aucune session authentifiée n'est ouverte ici :
     * l'utilisateur n'est connecté qu'après validation du code reçu par e-mail.
     */
    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $donnees = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
        ]);

        $email = mb_strtolower(trim($donnees['email']));

        // 1. L'e-mail : existe-t-il un compte avec cette adresse ?
        $user = User::where('email', $email)->first();
        if (! $user) {
            // Même durée de calcul et même message qu'un mauvais mot de passe : on ne doit pas pouvoir deviner quelles adresses ont un compte.
            Hash::check($donnees['password'], self::EMPREINTE_FICTIVE);
            Journal::connexion(Journal::CONNEXION_ECHEC, null, false, 'compte inconnu', $email);
            $this->refuser('password', self::MESSAGE_IDENTIFIANTS);
        }

        // 2. Le mot de passe.
        if (! Hash::check($donnees['password'], $user->password)) {
            $this->enregistrerEchec($user, $email);
            $this->refuser('password', self::MESSAGE_IDENTIFIANTS);
        }

        // 3. Le statut du compte.
        if (! $user->actif) {
            Journal::connexion(Journal::CONNEXION_ECHEC, $user, false, 'compte inactif', $email);
            $this->refuser('email', "Ce compte est désactivé. Contactez l'administrateur.");
        }
        if ($user->estVerrouille()) {
            Journal::connexion(Journal::CONNEXION_ECHEC, $user, false, 'compte verrouillé', $email);
            $minutes = (int) max(1, ceil(now()->diffInMinutes($user->verrouille_jusqu_a)));
            $this->refuser('email', "Compte temporairement verrouillé. Réessayez dans {$minutes} minute(s).");
        }

        // Rehash si l'algorithme ou le coût a changé.
        if (Hash::needsRehash($user->password)) {
            $user->forceFill(['password' => $donnees['password']])->save();
        }
        $user->forceFill(['echecs_connexion' => 0, 'verrouille_jusqu_a' => null])->save();

        // Nouvel identifiant de session avant toute donnée : protège contre la fixation de session.
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        try {
            $this->doubleAuth->envoyer($user);
        } catch (Throwable $e) {
            Log::error('Envoi du code de connexion impossible', ['user_id' => $user->id, 'erreur' => $e->getMessage()]);
            $this->doubleAuth->annuler($user);
            Journal::connexion(Journal::CODE_ENVOYE, $user, false, 'envoi impossible', $email);
            $this->refuser('email', "Le code n'a pas pu être envoyé. Réessayez dans un instant ou contactez l'administrateur.");
        }

        $request->session()->put(self::SESSION_ATTENTE_CODE, ['user_id' => $user->id, 'debut' => now()->timestamp]);
        Journal::connexion(Journal::CODE_ENVOYE, $user, true, null, $email);

        return $request->expectsJson()
            ? response()->json(['redirect' => route('two-factor.show')])
            : redirect()->route('two-factor.show');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $user = $request->user();
        if ($user) {
            Journal::connexion(Journal::DECONNEXION, $user, true, 'volontaire', $user->email);
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', 'Vous êtes déconnecté.');
    }

    private function enregistrerEchec(?User $user, string $email): void
    {
        Journal::connexion(Journal::CONNEXION_ECHEC, $user, false, $user ? 'mot de passe incorrect' : 'compte inconnu', $email);

        if (! $user) {
            return;
        }

        $user->increment('echecs_connexion');
        if ($user->echecs_connexion >= (int) config('securite.echecs_max')) {
            $user->forceFill([
                'echecs_connexion' => 0,
                'verrouille_jusqu_a' => now()->addMinutes((int) config('securite.verrouillage_minutes')),
            ])->save();
            Journal::connexion(Journal::COMPTE_VERROUILLE, $user, false, 'trop d\'échecs', $email);
        }
    }

    private function refuser(string $champ, string $message): never
    {
        throw ValidationException::withMessages([$champ => $message]);
    }
}
