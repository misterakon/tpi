<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\DoubleAuth;
use App\Services\Journal;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PasswordResetController extends Controller
{
    public function __construct(private readonly DoubleAuth $doubleAuth) {}

    public function request(): View
    {
        return view('auth.forgot-password');
    }

    /** Réponse identique que l'adresse existe ou non : on ne révèle pas quels comptes existent. */
    public function email(Request $request): JsonResponse|RedirectResponse
    {
        $donnees = $request->validate(['email' => ['required', 'string', 'email', 'max:255']]);
        $email = mb_strtolower(trim($donnees['email']));

        $user = User::where('email', $email)->where('actif', true)->first();
        if ($user) {
            // Après la réponse : l'envoi du courriel prend plusieurs secondes, et ce délai révélerait quelles adresses ont un compte.
            dispatch(fn () => Password::broker()->sendResetLink(['email' => $email]))->afterResponse();
        }

        Journal::connexion(Journal::MDP_LIEN_DEMANDE, $user, true, $user ? null : 'adresse inconnue ou compte inactif', $email);

        $message = 'Si cette adresse correspond à un compte actif, un lien vient d\'être envoyé.';

        return $request->expectsJson()
            ? response()->json(['message' => $message])
            : back()->with('status', $message);
    }

    public function create(Request $request): View
    {
        return view('auth.reset-password', ['request' => $request]);
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', 'confirmed', MotDePasse::regle()],
        ]);

        $utilisateurReinitialise = null;

        $statut = Password::broker()->reset(
            [
                'email' => mb_strtolower(trim($request->string('email')->toString())),
                'password' => $request->input('password'),
                'password_confirmation' => $request->input('password_confirmation'),
                'token' => $request->input('token'),
            ],
            function (User $user, string $motDePasse) use (&$utilisateurReinitialise) {
                if (! $user->actif) {
                    return;
                }
                $user->forceFill([
                    'password' => $motDePasse,
                    'remember_token' => Str::random(60),
                    'mot_de_passe_modifie_le' => now(),
                    'echecs_connexion' => 0,
                    'verrouille_jusqu_a' => null,
                ])->save();

                $this->doubleAuth->annuler($user);
                // Toute session ouverte avec l'ancien mot de passe est fermée.
                DB::table('sessions')->where('user_id', $user->id)->delete();

                event(new PasswordReset($user));
                $utilisateurReinitialise = $user;
            },
        );

        if ($statut !== Password::PASSWORD_RESET || ! $utilisateurReinitialise) {
            throw ValidationException::withMessages(['email' => 'Ce lien est invalide ou a expiré. Demandez-en un nouveau.']);
        }

        Journal::connexion(Journal::MDP_MODIFIE, $utilisateurReinitialise, true, 'réinitialisation par lien', $utilisateurReinitialise->email);
        Journal::audit('mot_de_passe_reinitialise', $utilisateurReinitialise, $utilisateurReinitialise->email, auteur: $utilisateurReinitialise);

        $request->session()->flash('status', 'Mot de passe enregistré. Vous pouvez vous connecter.');

        return $request->expectsJson()
            ? response()->json(['redirect' => route('login')])
            : redirect()->route('login');
    }
}
