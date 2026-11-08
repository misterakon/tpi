<?php

namespace App\Http\Controllers\Organisation;

use App\Models\Salle;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/** Salles et lieux d'audience (salles n°3 et n°4, cabinets de juges, salle de réunion). */
class SalleController extends ReferentielController
{
    protected function modele(): string
    {
        return Salle::class;
    }

    protected function cle(): string
    {
        return 'salles';
    }

    protected function nomAudit(): string
    {
        return 'salle';
    }

    protected function requete(): Builder
    {
        return Salle::query()->orderBy('nom');
    }

    protected function vue(): string
    {
        return 'organisation.salles.index';
    }

    protected function libelle(Model $element): string
    {
        return $element->nom;
    }

    protected function definition(): array
    {
        return [
            'singulier' => 'salle',
            'champs' => [
                ['name' => 'nom', 'type' => 'text'], ['name' => 'type', 'type' => 'select'],
                ['name' => 'emplacement', 'type' => 'text'], ['name' => 'actif', 'type' => 'checkbox'],
            ],
        ];
    }

    protected function regles(?Model $element): array
    {
        return [
            'nom' => ['required', 'string', 'max:100', Rule::unique('salles', 'nom')->ignore($element?->id)],
            'type' => ['required', Rule::in(array_keys(Salle::TYPES))],
            'emplacement' => ['nullable', 'string', 'max:150'],
            'actif' => ['nullable', 'boolean'],
        ];
    }

    protected function messages(): array
    {
        return ['nom.required' => 'Le nom est obligatoire.', 'nom.unique' => 'Une salle porte déjà ce nom.', 'type.required' => 'Choisissez un type de lieu.', 'type.in' => 'Type de lieu invalide.'];
    }
}
