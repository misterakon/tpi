<?php

namespace App\Http\Controllers\Decisions;

use App\Http\Controllers\Controller;
use App\Models\BlocTexte;
use App\Models\ModeleDecision;
use App\Services\Journal;
use App\Support\RechercheTexte;
use App\Support\Redaction\Gabarit;
use App\Support\TableauServeur;
use App\Support\TexteRiche;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/*
 | Blocs de texte réutilisables des modèles de décision : visas, formule de dépens, clôture… Un bloc s'insère dans un modèle
 | par {{> code }} et peut lui-même contenir des variables. Droits : blocs.voir / creer / modifier / supprimer.
 */
class BlocTexteController extends Controller
{
    private const CHAMPS = ['code', 'nom', 'categorie', 'contenu', 'actif'];

    public function index(): View
    {
        Gate::authorize('blocs.voir');

        return view('decisions.blocs.index', ['total' => BlocTexte::count()]);
    }

    /** Les lignes de la page, une page à la fois ; les modèles qui utilisent chaque bloc ne sont cherchés que pour les blocs affichés. */
    public function donnees(Request $request): JsonResponse
    {
        Gate::authorize('blocs.voir');
        $versions = null;
        $usagesDe = function (BlocTexte $b) use (&$versions) {
            $versions ??= ModeleDecision::with('versions')->get()->mapWithKeys(fn (ModeleDecision $m) => [$m->id => $m->versionCourante()])->filter();
            $motif = '/\{\{\s*(?:>|&gt;)\s*'.preg_quote($b->code, '/').'\s*\}\}/u';

            // Nom du modèle et lien vers sa feuille (la liste n'est consultable que par qui a le droit de voir les modèles).
            return $versions->filter(fn ($v) => preg_match($motif, $v->entete.$v->qualite.$v->factum))
                ->map(fn ($v) => ['nom' => $v->modele->nom, 'url' => Gate::allows('modeles.voir') ? route('decisions.modeles.edition', $v->modele_decision_id) : null])->values()->all();
        };

        return TableauServeur::repondre(
            $request, BlocTexte::orderBy('categorie')->orderBy('nom'),
            [0 => ['nom'], 1 => ['code'], 2 => ['categorie', 'nom'], 4 => ['actif']],
            fn ($q, $mots) => RechercheTexte::appliquer($q, $mots, ['nom', 'code', 'contenu']),
            fn (BlocTexte $l) => view('decisions.blocs._ligne', ['l' => $l, 'usages' => [$l->id => $usagesDe($l)]])->render(),
        );
    }

    public function show(int $id): JsonResponse
    {
        Gate::authorize('blocs.voir');
        $b = BlocTexte::findOrFail($id);

        return response()->json(['id' => $b->id] + $b->only(self::CHAMPS));
    }

    /** Création (id_ vide) ou modification (id_ renseigné). */
    public function store(Request $request): RedirectResponse
    {
        $b = $request->filled('id_') ? BlocTexte::findOrFail((int) $request->input('id_')) : null;
        Gate::authorize($b ? 'blocs.modifier' : 'blocs.creer');

        $request->merge(['contenu' => TexteRiche::nettoyer($request->input('contenu'))]);

        $donnees = $request->validate([
            'code' => ['required', 'string', 'max:60', 'regex:/^[a-z0-9_]+$/', Rule::unique('blocs_texte', 'code')->ignore($b?->id)],
            'nom' => ['required', 'string', 'max:160'],
            'categorie' => ['required', Rule::in(array_keys(BlocTexte::CATEGORIES))],
            'contenu' => ['required', 'string', 'max:20000'],
            'actif' => ['nullable', 'boolean'],
        ], [
            'code.required' => 'Le code est obligatoire.', 'code.regex' => 'Le code ne contient que des lettres minuscules sans accent, des chiffres et le tiret bas (ex. formule_cloture).',
            'code.unique' => 'Ce code est déjà utilisé par un autre bloc.', 'nom.required' => 'Le nom est obligatoire.', 'categorie.required' => 'Choisissez la catégorie.',
            'contenu.required' => 'Le texte du bloc est obligatoire.', 'contenu.max' => 'Le texte du bloc est trop long.',
        ]);

        // Un code déjà inséré dans un modèle ne peut plus changer : les modèles ne retrouveraient plus leur bloc.
        if ($b && $b->code !== $donnees['code'] && $this->modelesQuiLUtilisent($b->code) !== []) {
            throw ValidationException::withMessages(['code' => 'Ce bloc est utilisé par '.implode(', ', $this->modelesQuiLUtilisent($b->code)).' : son code ne peut pas changer.']);
        }
        if ($erreur = $this->filtresInconnus($donnees['contenu'])) {
            throw ValidationException::withMessages(['contenu' => $erreur]);
        }

        $donnees['actif'] = $request->boolean('actif');
        $donnees['nom'] = trim($donnees['nom']);

        $avant = $b ? $b->only(self::CHAMPS) : [];
        $b ??= new BlocTexte;
        $b->forceFill($donnees)->save();

        Journal::audit($avant ? 'bloc_texte_modifie' : 'bloc_texte_cree', $b, $b->nom, $avant, $b->only(self::CHAMPS));

        return redirect()->route('decisions.blocs')->with('success', $avant ? 'Bloc modifié.' : 'Bloc ajouté.');
    }

    public function destroy(int $id): JsonResponse
    {
        Gate::authorize('blocs.supprimer');
        $b = BlocTexte::findOrFail($id);

        if ($modeles = $this->modelesQuiLUtilisent($b->code)) {
            return response()->json(['status' => false, 'message' => 'Ce bloc est utilisé par '.implode(', ', $modeles).' : retirez-le de ces modèles d\'abord.']);
        }

        Journal::audit('bloc_texte_supprime', $b, $b->nom, $b->only(self::CHAMPS));
        $b->delete();

        return response()->json(['status' => true, 'message' => 'Bloc supprimé.']);
    }

    /** Noms des modèles dont la version courante appelle ce bloc. @return list<string> */
    private function modelesQuiLUtilisent(string $code): array
    {
        $motif = '/\{\{\s*(?:>|&gt;)\s*'.preg_quote($code, '/').'\s*\}\}/u';

        return ModeleDecision::with('versions')->get()
            ->filter(fn (ModeleDecision $m) => ($v = $m->versionCourante()) && preg_match($motif, $v->entete.$v->qualite.$v->factum))
            ->pluck('nom')->values()->all();
    }

    /** Refuse un texte qui utilise une transformation inexistante (une faute de frappe passerait inaperçue sinon). */
    private function filtresInconnus(string $contenu): ?string
    {
        $inconnus = Gabarit::filtresInconnus($contenu);

        return $inconnus === [] ? null : 'Transformation inconnue : '.implode(', ', $inconnus).'.';
    }
}
