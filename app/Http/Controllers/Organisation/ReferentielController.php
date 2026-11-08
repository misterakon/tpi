<?php

namespace App\Http\Controllers\Organisation;

use App\Http\Controllers\Controller;
use App\Services\Journal;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/*
 | Logique commune (enregistrement, validation, journal d'audit, suppression) des écrans d'organisation du tribunal.
 | Chaque page a ses propres vues (liste, fenêtre de saisie, script) ; ce contrôleur ne décrit que les données.
 |
 | Droits : « {cle}.voir », « {cle}.creer », « {cle}.modifier », « {cle}.supprimer » (catalogue des droits).
 | Routes : organisation.{cle}, .get, .store, .delete. Chaque écriture est inscrite au journal d'audit.
 */
abstract class ReferentielController extends Controller
{
    /** @return class-string<Model> */
    abstract protected function modele(): string;

    /** Clé des droits et des routes (ex. « salles »). */
    abstract protected function cle(): string;

    /** Nom de l'élément dans le journal d'audit (ex. « salle » donne salle_cree, salle_modifie, salle_supprime). */
    abstract protected function nomAudit(): string;

    /** Vue de la liste (chaque page a ses propres fichiers : liste, fenêtre de saisie, script). */
    abstract protected function vue(): string;

    /** @return array{singulier: string, champs: list<array{name: string, type: string}>} champs du formulaire */
    abstract protected function definition(): array;

    /** Règles de validation, par nom de champ. */
    abstract protected function regles(?Model $element): array;

    abstract protected function libelle(Model $element): string;

    protected function messages(): array
    {
        return [];
    }

    protected function requete(): Builder
    {
        return $this->modele()::query();
    }

    /** Données supplémentaires pour la vue (listes déroulantes, etc.). */
    protected function donneesVue(): array
    {
        return [];
    }

    /** Message de refus si l'élément ne peut pas être supprimé (sinon null). */
    protected function refusSuppression(Model $element): ?string
    {
        return null;
    }

    /** Contrôles croisés après validation (chevauchements, etc.) : lève une ValidationException. */
    protected function verifier(array $donnees, ?Model $element): void {}

    /** Enregistrement ; peut être redéfini (ex. une seule année courante). */
    protected function enregistrer(array $donnees, ?Model $element): Model
    {
        $element ??= new ($this->modele());
        $element->forceFill($donnees)->save();

        return $element;
    }

    public function index(Request $request): View
    {
        Gate::authorize($this->cle().'.voir');
        return view($this->vue(), ['lignes' => $this->requete()->get()] + $this->donneesVue());
    }

    /** Une ligne en JSON, pour préremplir la fenêtre de modification. */
    public function show(int $id): JsonResponse
    {
        Gate::authorize($this->cle().'.voir');
        $element = $this->requete()->findOrFail($id);

        $valeurs = ['id' => $element->getKey()];
        foreach ($this->definition()['champs'] as $champ) {
            $valeurs[$champ['name']] = $element->{$champ['name']};
        }

        return response()->json($valeurs);
    }

    /** Création (id_ vide) ou modification (id_ renseigné). */
    public function store(Request $request): RedirectResponse
    {
        $element = $request->filled('id_') ? $this->requete()->findOrFail((int) $request->input('id_')) : null;
        Gate::authorize($this->cle().($element ? '.modifier' : '.creer'));

        $champs = collect($this->definition()['champs']);
        $donnees = $request->validate($this->regles($element), $this->messages());

        // Une case à cocher absente de la requête signifie « décochée ».
        foreach ($champs->where('type', 'checkbox') as $champ) {
            $donnees[$champ['name']] = $request->boolean($champ['name']);
        }
        foreach ($donnees as $nom => $valeur) {
            if (is_string($valeur)) {
                $donnees[$nom] = trim($valeur) === '' ? null : trim($valeur);
            }
        }

        $this->verifier($donnees, $element);

        $avant = $element ? $this->valeursAudit($element) : [];
        $element = DB::transaction(fn () => $this->enregistrer($donnees, $element));
        $apres = $this->valeursAudit($element);

        Journal::audit($this->nomAudit().($avant ? '_modifie' : '_cree'), $element, $this->libelle($element), $avant, $apres);

        return redirect()->route('organisation.'.$this->cle())
            ->with('success', ucfirst($this->definition()['singulier']).($avant ? ' modifié(e).' : ' créé(e).'));
    }

    public function destroy(int $id): JsonResponse
    {
        Gate::authorize($this->cle().'.supprimer');
        $element = $this->requete()->findOrFail($id);

        if ($refus = $this->refusSuppression($element)) {
            return response()->json(['status' => false, 'message' => $refus]);
        }

        Journal::audit($this->nomAudit().'_supprime', $element, $this->libelle($element), $this->valeursAudit($element));
        $element->delete();

        return response()->json(['status' => true, 'message' => ucfirst($this->definition()['singulier']).' supprimé(e).']);
    }

    /** Valeurs des champs du formulaire, pour le journal (avant / après). */
    private function valeursAudit(Model $element): array
    {
        $valeurs = [];
        foreach ($this->definition()['champs'] as $champ) {
            $valeur = $element->{$champ['name']};
            $valeurs[$champ['name']] = $valeur instanceof \DateTimeInterface ? $valeur->format('Y-m-d') : $valeur;
        }

        return $valeurs;
    }
}
