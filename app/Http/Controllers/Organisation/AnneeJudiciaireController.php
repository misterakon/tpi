<?php

namespace App\Http\Controllers\Organisation;

use App\Models\AnneeJudiciaire;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Années judiciaires : l'organisation du tribunal (ordonnance) change chaque année. Une seule année est « courante ». */
class AnneeJudiciaireController extends ReferentielController
{
    protected function modele(): string
    {
        return AnneeJudiciaire::class;
    }

    protected function cle(): string
    {
        return 'annees';
    }

    protected function nomAudit(): string
    {
        return 'annee';
    }

    protected function requete(): Builder
    {
        return AnneeJudiciaire::query()->orderByDesc('debut');
    }

    protected function vue(): string
    {
        return 'organisation.annees.index';
    }

    protected function libelle(Model $element): string
    {
        return $element->libelle;
    }

    protected function definition(): array
    {
        return [
            'singulier' => 'année judiciaire',
            'champs' => [
                ['name' => 'libelle', 'type' => 'text'], ['name' => 'debut', 'type' => 'date'], ['name' => 'fin', 'type' => 'date'],
                ['name' => 'ordonnance', 'type' => 'text'], ['name' => 'courante', 'type' => 'checkbox'],
            ],
        ];
    }

    protected function regles(?Model $element): array
    {
        return [
            'libelle' => ['required', 'string', 'max:20', Rule::unique('annees_judiciaires', 'libelle')->ignore($element?->id)],
            'debut' => ['required', 'date'],
            'fin' => ['required', 'date', 'after:debut'],
            'ordonnance' => ['nullable', 'string', 'max:120'],
            'courante' => ['nullable', 'boolean'],
        ];
    }

    protected function messages(): array
    {
        return [
            'libelle.required' => "Le libellé de l'année est obligatoire.", 'libelle.unique' => 'Cette année existe déjà.',
            'debut.required' => 'La date de début est obligatoire.', 'fin.required' => 'La date de fin est obligatoire.',
            'fin.after' => 'La fin doit être après le début.',
        ];
    }

    /** Deux années judiciaires ne peuvent pas se chevaucher. */
    protected function verifier(array $donnees, ?Model $element): void
    {
        $chevauche = AnneeJudiciaire::query()
            ->when($element, fn (Builder $q) => $q->whereKeyNot($element->getKey()))
            ->where('debut', '<=', $donnees['fin'])->where('fin', '>=', $donnees['debut'])->first();

        if ($chevauche) {
            throw ValidationException::withMessages(['debut' => "Ces dates chevauchent l'année « {$chevauche->libelle} »."]);
        }
    }

    protected function enregistrer(array $donnees, ?Model $element): Model
    {
        $element = parent::enregistrer($donnees, $element);
        if ($element->courante) {
            AnneeJudiciaire::whereKeyNot($element->getKey())->where('courante', true)->update(['courante' => false]);
        }

        return $element;
    }

    protected function refusSuppression(Model $element): ?string
    {
        return $element->courante ? "L'année en cours ne peut pas être supprimée : désignez d'abord une autre année comme « en cours »." : null;
    }
}
