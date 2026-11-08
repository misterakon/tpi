<?php

namespace App\Http\Controllers\Organisation;

use App\Http\Controllers\Controller;
use App\Models\AnneeJudiciaire;
use App\Models\Fermeture;
use App\Services\Journal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/*
 | Jours fériés, vacances judiciaires et fermetures du tribunal, par année judiciaire.
 | Droits : fermetures.voir / creer / modifier / supprimer.
 */
class FermetureController extends Controller
{
    private const CHAMPS = ['annee_judiciaire_id', 'type', 'libelle', 'debut', 'fin', 'remarque'];

    public function index(Request $request): View
    {
        Gate::authorize('fermetures.voir');

        $annees = AnneeJudiciaire::orderByDesc('debut')->get();
        $annee = $annees->firstWhere('id', (int) $request->query('annee')) ?? $annees->firstWhere('courante', true) ?? $annees->first();

        return view('organisation.fermetures.index', [
            'annees' => $annees,
            'annee' => $annee,
            'lignes' => $annee ? Fermeture::where('annee_judiciaire_id', $annee->id)->orderBy('debut')->get() : collect(),
        ]);
    }

    public function show(int $id): JsonResponse
    {
        Gate::authorize('fermetures.voir');
        $f = Fermeture::findOrFail($id);

        return response()->json([
            'id' => $f->id, 'annee_judiciaire_id' => $f->annee_judiciaire_id, 'type' => $f->type, 'libelle' => $f->libelle,
            'debut' => $f->debut->format('Y-m-d'), 'fin' => $f->fin->format('Y-m-d'), 'remarque' => $f->remarque,
        ]);
    }

    /** Création (id_ vide) ou modification (id_ renseigné). */
    public function store(Request $request): RedirectResponse
    {
        $f = $request->filled('id_') ? Fermeture::findOrFail((int) $request->input('id_')) : null;
        Gate::authorize($f ? 'fermetures.modifier' : 'fermetures.creer');

        $donnees = $request->validate([
            'annee_judiciaire_id' => ['required', 'integer', 'exists:annees_judiciaires,id'],
            'type' => ['required', Rule::in(array_keys(Fermeture::TYPES))],
            'libelle' => ['required', 'string', 'max:160'],
            'debut' => ['required', 'date'],
            'fin' => ['nullable', 'date', 'after_or_equal:debut'],
            'remarque' => ['nullable', 'string', 'max:255'],
        ], [
            'annee_judiciaire_id.required' => "Choisissez l'année judiciaire.", 'type.required' => 'Choisissez le type.', 'type.in' => 'Type invalide.',
            'libelle.required' => 'Le libellé est obligatoire.', 'debut.required' => 'La date de début est obligatoire.', 'debut.date' => 'La date de début est invalide.',
            'fin.date' => 'La date de fin est invalide.', 'fin.after_or_equal' => 'La date de fin ne peut pas précéder la date de début.',
        ]);

        $donnees['libelle'] = trim($donnees['libelle']);
        $donnees['fin'] = $donnees['fin'] ?? $donnees['debut'];
        $donnees['remarque'] = filled($donnees['remarque'] ?? null) ? trim($donnees['remarque']) : null;

        $existe = Fermeture::where('annee_judiciaire_id', $donnees['annee_judiciaire_id'])->where('libelle', $donnees['libelle'])->whereDate('debut', $donnees['debut'])
            ->when($f, fn ($q) => $q->whereKeyNot($f->id))->exists();
        if ($existe) {
            throw ValidationException::withMessages(['libelle' => 'Cette période est déjà enregistrée.']);
        }

        $avant = $f ? $this->valeurs($f) : [];
        $f ??= new Fermeture;
        $f->forceFill($donnees)->save();

        Journal::audit($avant ? 'fermeture_modifie' : 'fermeture_cree', $f, $f->libelle, $avant, $this->valeurs($f->refresh()));

        return redirect()->route('organisation.fermetures', ['annee' => $f->annee_judiciaire_id])->with('success', $avant ? 'Période modifiée.' : 'Période ajoutée.');
    }

    /** Ajoute les fêtes nationales fixes comprises dans l'année judiciaire (celles déjà présentes sont ignorées). */
    public function feriesFixes(Request $request): RedirectResponse
    {
        Gate::authorize('fermetures.creer');
        $annee = AnneeJudiciaire::findOrFail((int) $request->input('annee_judiciaire_id'));

        $ajoutes = 0;
        for ($an = $annee->debut->year; $an <= $annee->fin->year; $an++) {
            foreach (Fermeture::FERIES_FIXES as [$mois, $jour, $libelle]) {
                $date = Carbon::create($an, $mois, $jour)->startOfDay();
                if ($date->lt($annee->debut) || $date->gt($annee->fin)) {
                    continue;
                }
                $existe = Fermeture::where('annee_judiciaire_id', $annee->id)->where('libelle', $libelle)->whereDate('debut', $date->toDateString())->exists();
                if (! $existe) {
                    Fermeture::create(['annee_judiciaire_id' => $annee->id, 'libelle' => $libelle, 'type' => 'ferie', 'debut' => $date->toDateString(), 'fin' => $date->toDateString()]);
                    $ajoutes++;
                }
            }
        }

        Journal::audit('fermeture_cree', $annee, 'Jours fériés fixes '.$annee->libelle, [], ['ajoutes' => $ajoutes]);

        return redirect()->route('organisation.fermetures', ['annee' => $annee->id])
            ->with('success', $ajoutes ? $ajoutes.' jour(s) férié(s) ajouté(s). Ajoutez à la main les fêtes mobiles (Pâques, Ascension, Pentecôte, fêtes musulmanes).' : 'Les jours fériés fixes sont déjà tous enregistrés.');
    }

    public function destroy(int $id): JsonResponse
    {
        Gate::authorize('fermetures.supprimer');
        $f = Fermeture::findOrFail($id);

        Journal::audit('fermeture_supprime', $f, $f->libelle, $this->valeurs($f));
        $f->delete();

        return response()->json(['status' => true, 'message' => 'Période supprimée.']);
    }

    /** @return array<string, mixed> */
    private function valeurs(Fermeture $f): array
    {
        $v = $f->only(self::CHAMPS);
        $v['debut'] = $f->debut->format('Y-m-d');
        $v['fin'] = $f->fin->format('Y-m-d');

        return $v;
    }
}
