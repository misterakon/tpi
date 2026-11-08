<?php

namespace App\Http\Controllers\Organisation;

use App\Models\Agent;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/** Magistrats et greffiers : les personnes qui pourront être affectées aux formations du tribunal. */
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
                ['name' => 'nom', 'type' => 'text'], ['name' => 'prenoms', 'type' => 'text'], ['name' => 'sexe', 'type' => 'select'], ['name' => 'fonction', 'type' => 'select'],
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
            'fonction' => ['required', Rule::in(array_keys(Agent::FONCTIONS))],
            'matricule' => ['nullable', 'string', 'max:40', Rule::unique('agents', 'matricule')->ignore($element?->id)],
            'telephone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9 +().-]{6,30}$/'],
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'user_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('actif', true), Rule::unique('agents', 'user_id')->ignore($element?->id)->whereNull('deleted_at')],
            'actif' => ['nullable', 'boolean'],
        ];
    }

    protected function messages(): array
    {
        return [
            'nom.required' => 'Le nom est obligatoire.', 'prenoms.required' => 'Les prénoms sont obligatoires.',
            'sexe.in' => 'Sexe invalide.', 'fonction.required' => 'Choisissez une fonction.', 'fonction.in' => 'Fonction invalide.',
            'matricule.unique' => 'Ce matricule est déjà utilisé.',
            'telephone.regex' => 'Numéro de téléphone invalide.', 'email.email' => 'Adresse e-mail invalide.',
            'user_id.exists' => 'Compte introuvable ou inactif.', 'user_id.unique' => 'Ce compte est déjà relié à un autre agent.',
        ];
    }
}
