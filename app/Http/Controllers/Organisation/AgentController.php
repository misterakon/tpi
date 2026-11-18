<?php

namespace App\Http\Controllers\Organisation;

use App\Models\Agent;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Personnel du tribunal : magistrats, greffiers, assesseurs, personnel administratif et technique, membres extérieurs. */
class AgentController extends ReferentielController
{
    protected function modele(): string
    {
        return Agent::class;
    }

    protected function cle(): string
    {
        return 'personnel';
    }

    protected function nomAudit(): string
    {
        return 'agent';
    }

    protected function requete(): Builder
    {
        return Agent::query()->with('user:id,name,email')->orderBy('nom')->orderBy('prenoms');
    }

    protected function vue(): string
    {
        return 'organisation.personnel.index';
    }

    protected function libelle(Model $element): string
    {
        return $element->nomComplet();
    }

    protected function donneesVue(): array
    {
        return ['comptes' => User::query()->where('actif', true)->orderBy('name')->pluck('name', 'id')->all()];
    }

    protected function definition(): array
    {
        return [
            'singulier' => 'agent',
            'champs' => [
                ['name' => 'nom', 'type' => 'text'], ['name' => 'prenoms', 'type' => 'text'], ['name' => 'sexe', 'type' => 'select'], ['name' => 'categorie', 'type' => 'select'], ['name' => 'fonction', 'type' => 'select'],
                ['name' => 'matricule', 'type' => 'text'], ['name' => 'telephone', 'type' => 'text'], ['name' => 'email', 'type' => 'email'],
                ['name' => 'user_id', 'type' => 'select'], ['name' => 'actif', 'type' => 'checkbox'],
            ],
        ];
    }

    protected function regles(?Model $element): array
    {
        return [
            'nom' => ['required', 'string', 'max:80'],
            'prenoms' => ['required', 'string', 'max:120'],
            'sexe' => ['nullable', Rule::in(array_keys(Agent::SEXES))],
            'categorie' => ['required', Rule::in(array_keys(Agent::CATEGORIES))],
            'fonction' => ['required', Rule::in(array_keys(Agent::FONCTIONS))],
            'matricule' => ['nullable', 'string', 'max:40', Rule::unique('agents', 'matricule')->ignore($element?->id)],
            'telephone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9 +().-]{6,30}$/'],
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'user_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('actif', true), Rule::unique('agents', 'user_id')->ignore($element?->id)->whereNull('deleted_at')],
            'actif' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Fiche d'une personne (fenêtre ouverte en cliquant sur un nom dans « Composition des chambres ») :
     * ses coordonnées, où elle siège aujourd'hui et ses responsabilités de l'année en cours. Lecture seule.
     */
    public function fiche(int $id): JsonResponse
    {
        Gate::authorize('personnel.voir');
        $agent = Agent::with('user:id,name')->findOrFail($id);

        $affectations = $agent->affectationsEnCours()->map(fn ($a) => [
            'chambre_id' => $a->formation->chambre_id,
            'chambre' => $a->formation->chambre->libelle,
            'formation' => $a->formation->nom,
            'section' => $a->formation->chambre->section?->libelle,
            'role' => (\App\Models\Affectation::ROLES[$a->role] ?? $a->role).($a->suppleant ? ' suppléant' : ''),
            'chef_section' => (bool) $a->chef_section,
            'debut' => $a->debut?->format('d/m/Y'),
            'fin' => $a->fin?->format('d/m/Y'),
        ])->values();

        $services = $agent->servicesEnCours()->map(fn ($l) => ['service' => $l->service->libelle, 'fonction' => $l->fonction])->values();

        return response()->json([
            'id' => $agent->id,
            'nom' => $agent->nomComplet(),
            'categorie' => $agent->categorieLibelle(),
            'fonction' => $agent->fonctionLibelle(),
            'matricule' => $agent->matricule,
            'telephone' => $agent->telephone,
            'email' => $agent->email,
            'compte' => $agent->user?->name,
            'actif' => (bool) $agent->actif,
            'affectations' => $affectations,
            'services' => $services,
            'peut_modifier' => Gate::allows('personnel.modifier'),
        ]);
    }

    /** La fonction doit appartenir à la catégorie choisie (un informaticien n'est pas un juge). */
    protected function verifier(array $donnees, ?Model $element): void
    {
        if (! in_array($donnees['fonction'], Agent::CATEGORIES[$donnees['categorie']]['fonctions'], true)) {
            throw ValidationException::withMessages(['fonction' => 'Cette fonction ne correspond pas à la catégorie « '.Agent::CATEGORIES[$donnees['categorie']]['libelle'].' ».']);
        }
    }

    protected function messages(): array
    {
        return [
            'nom.required' => 'Le nom est obligatoire.', 'prenoms.required' => 'Les prénoms sont obligatoires.',
            'sexe.in' => 'Sexe invalide.', 'categorie.required' => 'Choisissez la catégorie.', 'categorie.in' => 'Catégorie invalide.', 'fonction.required' => 'Choisissez une fonction.', 'fonction.in' => 'Fonction invalide.',
            'matricule.unique' => 'Ce matricule est déjà utilisé.',
            'telephone.regex' => 'Numéro de téléphone invalide.', 'email.email' => 'Adresse e-mail invalide.',
            'user_id.exists' => 'Compte introuvable ou inactif.', 'user_id.unique' => 'Ce compte est déjà relié à un autre agent.',
        ];
    }
}
