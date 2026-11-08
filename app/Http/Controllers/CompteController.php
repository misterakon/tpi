<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Auth\MotDePasse;
use App\Mail\CompteCree;
use App\Models\Profil;
use App\Models\User;
use App\Services\Journal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

/** Utilisateurs (comptes). Chaque action vérifie le droit du profil et les protections (UserPolicy). */
class CompteController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', User::class);

        return view('utilisateur.compte', [
            'comptes' => User::with('profil', 'agent')->orderBy('name')->get(),
            'profils' => $this->profilsAttribuables($request->user()),
        ]);
    }

    public function show(User $user): JsonResponse
    {
        Gate::authorize('update', $user);

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'profil_id' => $user->profil_id,
            'actif' => $user->actif,
        ]);
    }

    /** Création (id_ vide) ou modification (id_ renseigné). */
    public function store(Request $request): RedirectResponse
    {
        $cible = $request->filled('id_') ? User::findOrFail($request->input('id_')) : null;
        $cible ? Gate::authorize('update', $cible) : Gate::authorize('create', User::class);

        $acteur = $request->user();
        $donnees = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($cible?->id)],
            'password' => ['nullable', 'string', MotDePasse::regle()],
            'profil_id' => ['required', 'integer', Rule::in($this->profilsAttribuables($acteur)->pluck('id')->all())],
            'actif' => ['required', 'boolean'],
        ], [
            'name.required' => 'Le nom est obligatoire.',
            'email.required' => "L'adresse e-mail est obligatoire.",
            'email.unique' => 'Cette adresse e-mail est déjà utilisée par un autre compte.',
            'profil_id.required' => 'Choisissez un profil.',
            'profil_id.in' => "Ce profil n'est pas attribuable.",
        ]);

        $email = mb_strtolower(trim($donnees['email']));

        return $cible
            ? $this->modifier($request, $cible, $donnees, $email)
            : $this->creer($request, $donnees, $email);
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        abort_unless($request->user()->peut('comptes.supprimer'), 403);   // d'abord le droit : sinon les messages ci-dessous révèlent à n'importe qui quels comptes existent ou sont protégés
        if ($request->user()->is($user)) {
            return response()->json(['status' => false, 'message' => 'Vous ne pouvez pas supprimer votre propre compte.']);
        }
        if ($user->protege) {
            return response()->json(['status' => false, 'message' => 'Ce compte est protégé : il ne peut pas être supprimé.']);
        }
        Gate::authorize('delete', $user);

        $avant = ['name' => $user->name, 'email' => $user->email, 'profil_id' => $user->profil_id];

        DB::transaction(function () use ($user) {
            // L'adresse est libérée (elle pourra servir à un nouveau compte) ; l'historique reste dans les journaux.
            $user->forceFill([
                'actif' => false,
                'email' => 'supprime.'.$user->id.'.'.$user->email,
            ])->save();
            DB::table('sessions')->where('user_id', $user->id)->delete();
            $user->delete();
        });

        Journal::audit('compte_supprime', $user, $avant['email'], $avant);

        return response()->json(['status' => true, 'message' => 'Compte supprimé.']);
    }

    /** Annule tous les codes de secours d'un compte (codes perdus ou compromis) : l'administrateur ne les voit jamais, l'utilisateur en génère de nouveaux. */
    public function revoquerCodes(User $user, \App\Services\CodesSecours $codes): JsonResponse
    {
        Gate::authorize('resetPassword', $user);

        $n = $codes->revoquer($user);
        Journal::audit('codes_secours_revoques', $user, $user->email, [], ['codes_annules' => $n]);

        return response()->json(['status' => true, 'message' => $n > 0 ? 'Les codes de secours de '.$user->name.' sont annulés. Il pourra en générer de nouveaux dans « Mon compte ».' : $user->name." n'avait aucun code de secours."]);
    }

    /** Envoie à l'utilisateur un lien pour choisir son mot de passe. */
    public function lien(User $user): JsonResponse
    {
        Gate::authorize('resetPassword', $user);

        $statut = Password::broker()->sendResetLink(['email' => $user->email]);
        Journal::audit('lien_mot_de_passe_envoye', $user, $user->email);

        return response()->json($statut === Password::RESET_LINK_SENT
            ? ['status' => true, 'message' => 'Le lien a été envoyé à '.$user->email.'.']
            : ['status' => false, 'message' => 'Un lien a déjà été envoyé récemment. Patientez une minute avant de réessayer.']);
    }

    private function creer(Request $request, array $donnees, string $email): RedirectResponse
    {
        $choisi = filled($donnees['password'] ?? null);

        $user = new User;
        $user->forceFill([
            'name' => trim($donnees['name']),
            'email' => $email,
            // Sans mot de passe saisi : un mot de passe aléatoire que personne ne connaît ; l'utilisateur choisira le sien par le lien.
            'password' => $choisi ? $donnees['password'] : Str::random(48),
            'profil_id' => $donnees['profil_id'],
            'protege' => false,
            'actif' => (bool) $donnees['actif'],
            'mot_de_passe_modifie_le' => $choisi ? now() : null,
        ])->save();

        Journal::audit('compte_cree', $user, $email, [], ['name' => $user->name, 'email' => $email, 'profil_id' => $user->profil_id, 'actif' => $user->actif]);

        $envoye = true;
        try {
            $lien = $choisi ? null : url(route('password.reset', ['token' => Password::broker()->createToken($user), 'email' => $email], false));
            Mail::to($email)->send(new CompteCree(
                $user->name, $email, $user->profil->nom, $lien, (int) config('auth.passwords.users.expire'),
            ));
        } catch (Throwable $e) {
            $envoye = false;
            Log::error('Courriel de création de compte non envoyé', ['user_id' => $user->id, 'erreur' => $e->getMessage()]);
        }

        return redirect()->route('utilisateur.compte')->with(
            $envoye ? 'success' : 'error',
            $envoye ? 'Compte créé. Un courriel a été envoyé à '.$email.'.' : "Compte créé, mais le courriel n'a pas pu être envoyé : utilisez le bouton « lien de mot de passe » pour réessayer.",
        );
    }

    private function modifier(Request $request, User $cible, array $donnees, string $email): RedirectResponse
    {
        $soiMeme = $request->user()->is($cible);

        // On ne peut pas se retirer ses propres droits ni se désactiver (risque de s'enfermer dehors).
        if ($soiMeme && ((int) $donnees['profil_id'] !== $cible->profil_id || ! $donnees['actif'])) {
            throw ValidationException::withMessages(['profil_id' => 'Vous ne pouvez pas modifier votre propre profil ni votre statut.']);
        }

        $avant = ['name' => $cible->name, 'email' => $cible->email, 'profil_id' => $cible->profil_id, 'actif' => $cible->actif];
        $cible->forceFill([
            'name' => trim($donnees['name']),
            'email' => $email,
            'profil_id' => $donnees['profil_id'],
            'actif' => (bool) $donnees['actif'],
        ]);

        $motDePasseChange = filled($donnees['password'] ?? null);
        if ($motDePasseChange) {
            $cible->forceFill(['password' => $donnees['password'], 'mot_de_passe_modifie_le' => now(), 'echecs_connexion' => 0, 'verrouille_jusqu_a' => null]);
        }
        $cible->save();

        // Compte désactivé, profil changé ou mot de passe changé : ses sessions ouvertes sont fermées.
        if ($motDePasseChange || ! $cible->actif || $avant['profil_id'] !== $cible->profil_id) {
            DB::table('sessions')->where('user_id', $cible->id)->where('id', '!=', $request->session()->getId())->delete();
        }

        $apres = ['name' => $cible->name, 'email' => $cible->email, 'profil_id' => $cible->profil_id, 'actif' => $cible->actif];
        Journal::audit($motDePasseChange ? 'compte_modifie_mot_de_passe_change' : 'compte_modifie', $cible, $cible->email, $avant, $apres);

        return redirect()->route('utilisateur.compte')->with('success', 'Compte modifié.');
    }

    /** Profils que l'utilisateur connecté peut attribuer (le profil Admin seulement à un admin). */
    private function profilsAttribuables(User $acteur)
    {
        return Profil::orderByDesc('systeme')->orderBy('nom')
            ->when(! $acteur->estAdmin(), fn ($q) => $q->where('systeme', false))
            ->get();
    }
}
