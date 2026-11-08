<?php

namespace App\Http\Controllers\Organisation;

use App\Http\Controllers\Controller;
use App\Models\AnneeJudiciaire;
use App\Models\Chambre;
use App\Models\Formation;
use App\Models\Section;
use App\Services\Journal;
use App\Support\TexteRiche;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/*
 | Chambres (et juridictions, cabinets d'instruction) d'une section pour une année judiciaire, avec leurs attributions.
 | La composition et les formations se règlent depuis la page « Composition » de chaque chambre.
 | Droits : chambres.voir / creer / modifier / supprimer.
 */
class ChambreController extends Controller
{
    private const CHAMPS = ['annee_judiciaire_id', 'section_id', 'groupe', 'libelle', 'intitule', 'attributions', 'remarque', 'ordre', 'actif'];

    public function index(Request $request): View
    {
        Gate::authorize('chambres.voir');

        $annees = AnneeJudiciaire::orderByDesc('debut')->get();
        $annee = $annees->firstWhere('id', (int) $request->query('annee')) ?? AnneeJudiciaire::courante() ?? $annees->first();

        $lignes = Chambre::query()
            ->with(['section', 'formations.creneaux', 'formations.affectations' => fn ($q) => $q->with('agent')])
            ->when($annee, fn ($q) => $q->where('annee_judiciaire_id', $annee->id), fn ($q) => $q->whereRaw('1 = 0'))
            ->orderBy('ordre')->get();

        return view('organisation.chambres.index', [
            'annees' => $annees,
            'annee' => $annee,
            'sections' => Section::orderBy('ordre')->orderBy('libelle')->get(),
            'lignes' => $lignes,
        ]);
    }

    public function show(int $id): JsonResponse
    {
        Gate::authorize('chambres.voir');

        return response()->json(Chambre::findOrFail($id)->only(['id', ...self::CHAMPS]));
    }

    /** Création (id_ vide) ou modification (id_ renseigné). */
    public function store(Request $request): RedirectResponse
    {
        $chambre = $request->filled('id_') ? Chambre::findOrFail((int) $request->input('id_')) : null;
        Gate::authorize($chambre ? 'chambres.modifier' : 'chambres.creer');

        $request->merge(['attributions' => TexteRiche::nettoyer($request->input('attributions')), 'remarque' => TexteRiche::nettoyer($request->input('remarque'))]);

        $donnees = $request->validate([
            'annee_judiciaire_id' => ['required', 'integer', 'exists:annees_judiciaires,id'],
            'section_id' => ['required', 'integer', 'exists:sections,id'],
            'groupe' => ['nullable', 'string', 'max:120'],
            'libelle' => ['required', 'string', 'max:120'],
            'intitule' => ['nullable', 'string', 'max:160'],
            'attributions' => ['nullable', 'string', 'max:30000'],
            'remarque' => ['nullable', 'string', 'max:10000'],
            'ordre' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'actif' => ['nullable', 'boolean'],
        ], [
            'annee_judiciaire_id.required' => "Choisissez l'année judiciaire.", 'section_id.required' => 'Choisissez la section.',
            'libelle.required' => 'Le nom de la chambre est obligatoire.', 'attributions.max' => 'Les attributions sont trop longues.',
            'remarque.max' => 'La remarque est trop longue.',
        ]);

        foreach (['groupe', 'intitule', 'attributions', 'remarque'] as $champ) {
            $donnees[$champ] = filled($donnees[$champ] ?? null) ? trim($donnees[$champ]) : null;
        }
        $donnees['libelle'] = trim($donnees['libelle']);
        $donnees['actif'] = $request->boolean('actif');
        $donnees['ordre'] = (int) ($donnees['ordre'] ?? 0);
        if (! $chambre && $donnees['ordre'] === 0) {
            $donnees['ordre'] = (int) Chambre::where('annee_judiciaire_id', $donnees['annee_judiciaire_id'])->max('ordre') + 1;   // à la suite des chambres existantes
        }

        $existe = Chambre::where('annee_judiciaire_id', $donnees['annee_judiciaire_id'])->where('libelle', $donnees['libelle'])
            ->when($chambre, fn ($q) => $q->whereKeyNot($chambre->id))->exists();
        if ($existe) {
            throw ValidationException::withMessages(['libelle' => 'Cette chambre existe déjà pour cette année.']);
        }

        $avant = $chambre ? $chambre->only(self::CHAMPS) : [];
        $chambre = DB::transaction(function () use ($chambre, $donnees) {
            $creation = $chambre === null;
            $chambre ??= new Chambre;
            $chambre->forceFill($donnees)->save();
            if ($creation) {
                // Une chambre a toujours au moins une formation : à défaut de formations nommées, celle-ci porte la composition.
                Formation::create(['chambre_id' => $chambre->id, 'ordre' => 1, 'actif' => true]);
            }

            return $chambre;
        });

        Journal::audit($avant ? 'chambre_modifie' : 'chambre_cree', $chambre, $chambre->libelleComplet(), $avant, $chambre->only(self::CHAMPS));

        return redirect()->route('organisation.chambres', ['annee' => $chambre->annee_judiciaire_id])->with('success', $avant ? 'Chambre modifiée.' : 'Chambre créée.');
    }

    public function destroy(int $id): JsonResponse
    {
        Gate::authorize('chambres.supprimer');
        $chambre = Chambre::with('formations')->findOrFail($id);

        $occupee = $chambre->formations->contains(fn (Formation $f) => $f->affectations()->exists() || $f->creneaux()->exists());
        if ($occupee) {
            return response()->json(['status' => false, 'message' => "Cette chambre a une composition ou un calendrier : retirez-les d'abord, ou désactivez la chambre."]);
        }

        Journal::audit('chambre_supprime', $chambre, $chambre->libelleComplet(), $chambre->only(self::CHAMPS));
        $chambre->delete();

        return response()->json(['status' => true, 'message' => 'Chambre supprimée.']);
    }
}
