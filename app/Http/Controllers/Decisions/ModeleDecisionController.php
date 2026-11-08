<?php

namespace App\Http\Controllers\Decisions;

use App\Http\Controllers\Controller;
use App\Models\BlocTexte;
use App\Models\Chambre;
use App\Models\Matiere;
use App\Models\ModeleDecision;
use App\Models\ModeleDecisionVersion;
use App\Models\ObjetLitige;
use App\Services\Journal;
use App\Support\Redaction\Gabarit;
use App\Support\Redaction\VariablesDecision;
use App\Support\TexteRiche;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/*
 | Modèles de décision : un modèle par type de décision, en deux parties (qualité et factum), versionné. Chaque enregistrement
 | qui change les textes crée une nouvelle version ; une version existante n'est jamais modifiée. Un modèle se rattache à des objets
 | du litige et à des chambres pour que l'application propose le bon modèle. Droits : modeles.voir / creer / modifier / supprimer.
 */
class ModeleDecisionController extends Controller
{
    public function index(): View
    {
        Gate::authorize('modeles.voir');

        return view('decisions.modeles.index', [
            'lignes' => ModeleDecision::with(['matiere', 'versions'])->withCount('objets')->orderBy('nom')->get(),
        ]);
    }

    /** Page d'édition d'un modèle (id vide : nouveau modèle). Lecture seule sans le droit de modification. */
    public function edition(?int $id = null): View
    {
        $modele = $id ? ModeleDecision::with(['versions.auteur', 'variables', 'objets', 'matiere'])->findOrFail($id) : new ModeleDecision(['etat' => 'brouillon']);
        $lecture = $id ? ! Gate::allows('modeles.modifier') : false;
        if (! $id) {
            Gate::authorize('modeles.creer');
        }

        $version = $id ? $modele->versionCourante() : null;
        $propres = $id ? $modele->variables->map(fn ($v) => ['code' => $v->code, 'libelle' => $v->libelle, 'type' => $v->type, 'exemple' => $v->exemple])->all() : [];

        return view('decisions.modeles.edition', [
            'modele' => $modele,
            'version' => $version,
            'lecture' => $lecture,
            'matieres' => Matiere::orderBy('libelle')->pluck('libelle', 'id')->all(),
            'objets' => ObjetLitige::orderBy('libelle')->get()->mapWithKeys(fn ($o) => [$o->id => $o->libelle.' — '.$o->chambre_libelle])->all(),
            'chambres' => Chambre::query()->distinct()->orderBy('libelle')->pluck('libelle', 'libelle')->all(),
            'objetsChoisis' => $id ? $modele->objets->pluck('id')->all() : [],
            'chambresChoisies' => $id ? $modele->chambres() : [],
            'blocs' => BlocTexte::where('actif', true)->orderBy('categorie')->orderBy('nom')->get(['code', 'nom', 'categorie']),
            'catalogue' => VariablesDecision::toutes($propres),
            'propres' => $propres,
            'filtres' => Gabarit::FILTRES,
            'elements' => Gabarit::ELEMENTS,
            'miseEnPage' => self::miseEnPage(),
        ]);
    }

    /** Création (id_ vide) ou modification (id_ renseigné). */
    public function store(Request $request): RedirectResponse
    {
        $modele = $request->filled('id_') ? ModeleDecision::findOrFail((int) $request->input('id_')) : null;
        Gate::authorize($modele ? 'modeles.modifier' : 'modeles.creer');

        $request->merge(['entete' => TexteRiche::nettoyer($request->input('entete')), 'qualite' => TexteRiche::nettoyer($request->input('qualite')), 'factum' => TexteRiche::nettoyer($request->input('factum'))]);

        $donnees = $request->validate([
            'nom' => ['required', 'string', 'max:160', Rule::unique('modeles_decision', 'nom')->ignore($modele?->id)],
            'matiere_id' => ['nullable', 'integer', 'exists:matieres,id'],
            'description' => ['nullable', 'string', 'max:255'],
            'etat' => ['required', Rule::in(array_keys(ModeleDecision::ETATS))],
            'objets' => ['nullable', 'array', 'max:200'],
            'objets.*' => ['integer', 'exists:objets_litige,id'],
            'chambres' => ['nullable', 'array', 'max:50'],
            'chambres.*' => ['string', 'max:120', 'exists:chambres,libelle'],
            'entete' => ['nullable', 'string', 'max:20000'],
            'qualite' => ['nullable', 'string', 'max:100000'],
            'factum' => ['nullable', 'string', 'max:100000'],
            'note' => ['nullable', 'string', 'max:255'],
            'variables' => ['nullable', 'array', 'max:80'],
            'variables.*.code' => ['required', 'string', 'max:60', 'regex:/^[a-z][a-z0-9_]*(\.[a-z0-9_]+)*$/'],
            'variables.*.libelle' => ['required', 'string', 'max:160'],
            'variables.*.type' => ['required', Rule::in(array_keys(VariablesDecision::TYPES))],
            'variables.*.exemple' => ['nullable', 'string', 'max:2000'],
        ], [
            'nom.required' => 'Le nom du modèle est obligatoire.', 'nom.unique' => 'Un modèle porte déjà ce nom.', 'etat.required' => "Choisissez l'état.",
            'variables.*.code.required' => 'Chaque variable a un code.', 'variables.*.code.regex' => 'Code de variable invalide : minuscules sans accent, chiffres, tiret bas, et un point pour une donnée composée (ex. defunt.nom).',
            'variables.*.libelle.required' => 'Chaque variable a un libellé.', 'chambres.*.exists' => 'Chambre inconnue.',
        ]);

        $codes = array_column($donnees['variables'] ?? [], 'code');
        if (count($codes) !== count(array_unique($codes))) {
            throw ValidationException::withMessages(['variables' => 'Deux variables portent le même code.']);
        }
        foreach (['entete', 'qualite', 'factum'] as $partie) {
            if ($inconnus = Gabarit::filtresInconnus((string) ($donnees[$partie] ?? ''))) {
                throw ValidationException::withMessages([$partie => 'Transformation inconnue : '.implode(', ', $inconnus).'.']);
            }
        }

        $creation = $modele === null;
        $modele = DB::transaction(function () use ($modele, $donnees, $request) {
            $modele ??= new ModeleDecision;
            $modele->forceFill([
                'nom' => trim($donnees['nom']), 'matiere_id' => $donnees['matiere_id'] ?? null,
                'description' => filled($donnees['description'] ?? null) ? trim($donnees['description']) : null, 'etat' => $donnees['etat'],
            ])->save();

            $modele->objets()->sync($donnees['objets'] ?? []);
            DB::table('modeles_decision_chambres')->where('modele_decision_id', $modele->id)->delete();
            foreach (array_unique($donnees['chambres'] ?? []) as $chambre) {
                DB::table('modeles_decision_chambres')->insert(['modele_decision_id' => $modele->id, 'chambre_libelle' => $chambre]);
            }

            $modele->variables()->delete();
            foreach ($donnees['variables'] ?? [] as $v) {
                $modele->variables()->create(['code' => $v['code'], 'libelle' => trim($v['libelle']), 'type' => $v['type'], 'exemple' => filled($v['exemple'] ?? null) ? $v['exemple'] : null]);
            }

            // Nouvelle version seulement si les textes ont changé.
            $courante = $modele->versions()->first();
            $entete = $donnees['entete'] ?? null;
            $qualite = $donnees['qualite'] ?? null;
            $factum = $donnees['factum'] ?? null;
            if (! $courante || $this->forme($courante->entete) !== $this->forme($entete) || $this->forme($courante->qualite) !== $this->forme($qualite) || $this->forme($courante->factum) !== $this->forme($factum)) {
                $numero = ($courante?->numero ?? 0) + 1;
                ModeleDecisionVersion::create([
                    'modele_decision_id' => $modele->id, 'numero' => $numero, 'entete' => $entete, 'qualite' => $qualite, 'factum' => $factum,
                    'note' => filled($donnees['note'] ?? null) ? trim($donnees['note']) : ($numero === 1 ? 'Première version' : null), 'cree_par' => $request->user()?->id,
                ]);
                Journal::audit('modele_decision_version', $modele, $modele->nom.' : version '.$numero, [], ['version' => $numero]);
            }

            return $modele;
        });

        Journal::audit($creation ? 'modele_decision_cree' : 'modele_decision_modifie', $modele, $modele->nom, [], ['nom' => $modele->nom, 'etat' => $modele->etat]);

        return redirect()->route('decisions.modeles.edition', $modele->id)->with('success', $creation ? 'Modèle créé.' : 'Modèle enregistré.');
    }

    /** Aperçu d'un texte en cours d'écriture, rempli avec des données fictives (rien n'est enregistré). */
    public function apercu(Request $request): JsonResponse
    {
        abort_unless(Gate::allows('modeles.voir'), 403);

        $donnees = $request->validate([
            'entete' => ['nullable', 'string', 'max:20000'], 'qualite' => ['nullable', 'string', 'max:100000'], 'factum' => ['nullable', 'string', 'max:100000'],
            'variables' => ['nullable', 'array', 'max:80'], 'variables.*.code' => ['required', 'string', 'max:60'], 'variables.*.libelle' => ['nullable', 'string', 'max:160'],
            'variables.*.type' => ['required', Rule::in(array_keys(VariablesDecision::TYPES))], 'variables.*.exemple' => ['nullable', 'string', 'max:2000'],
        ]);

        $propres = array_values(array_map(fn ($v) => ['code' => $v['code'], 'libelle' => $v['libelle'] ?? $v['code'], 'type' => $v['type'], 'exemple' => $v['exemple'] ?? null], $donnees['variables'] ?? []));
        $exemples = VariablesDecision::exemples($propres);
        $blocs = BlocTexte::where('actif', true)->pluck('contenu', 'code')->all();
        $connues = array_merge(array_column(array_merge(...array_values(VariablesDecision::toutes($propres))), 'code'));

        $sortie = ['status' => true, 'manquantes' => [], 'inconnues' => []];
        foreach (['entete', 'qualite', 'factum'] as $partie) {
            $texte = TexteRiche::nettoyer($donnees[$partie] ?? null) ?? '';
            $r = Gabarit::rendre($texte, $exemples, $blocs, true);
            $sortie[$partie] = $r['texte'];
            $sortie['manquantes'] = array_merge($sortie['manquantes'], $r['manquantes']);
            $sortie['inconnues'] = array_merge($sortie['inconnues'], array_filter(Gabarit::variables($texte, $blocs), fn ($v) => ! in_array($v, $connues, true) && ! $this->estSousDonnee($v, $connues)));
        }
        $sortie['manquantes'] = array_values(array_unique($sortie['manquantes']));
        $sortie['inconnues'] = array_values(array_unique($sortie['inconnues']));

        return response()->json($sortie);
    }

    /** Police, tailles et mise en page d'un acte, tels que réglés dans « Impression des actes ». @return array<string, mixed> */
    public static function miseEnPage(): array
    {
        $c = config('tpi.actes');

        return [
            'police' => $c['police_corps'], 'taille' => (int) $c['taille_corps'], 'policeTimbre' => $c['police_timbre'], 'tailleTimbre' => (int) $c['taille_timbre'],
            'interligneQualite' => (int) $c['interligne_qualite_pct'], 'interligneFactum' => (int) $c['interligne_factum_pct'], 'margeHaut' => (int) $c['marge_haut_mm'], 'margeBas' => (int) $c['marge_bas_mm'],
            'margeGauche' => (int) $c['marge_gauche_mm'], 'margeDroite' => (int) $c['marge_droite_mm'], 'timbreLargeur' => (int) $c['timbre_largeur_mm'],
            'timbreHauteur' => (int) $c['timbre_hauteur_mm'], 'timbreGauche' => (int) $c['timbre_gauche_mm'], 'timbreHaut' => (int) $c['timbre_haut_mm'],
            'retrait' => (int) $c['retrait_qualite_mm'],
        ];
    }

    /** Crée une copie de travail d'un modèle (pour en faire une variante : autre type de décision, autre cas) : textes, variables, objets et chambres repris. */
    public function dupliquer(int $id, Request $request): RedirectResponse
    {
        Gate::authorize('modeles.creer');
        $source = ModeleDecision::with(['variables', 'objets'])->findOrFail($id);
        $version = $source->versionCourante();

        $nom = 'Copie de '.$source->nom;
        for ($i = 2; ModeleDecision::where('nom', mb_substr($nom, 0, 160))->exists(); $i++) {
            $nom = 'Copie de '.$source->nom.' ('.$i.')';
        }

        $copie = DB::transaction(function () use ($source, $version, $nom, $request) {
            $copie = ModeleDecision::create(['nom' => mb_substr($nom, 0, 160), 'matiere_id' => $source->matiere_id, 'description' => $source->description, 'etat' => 'brouillon']);
            foreach ($source->variables as $v) {
                $copie->variables()->create($v->only(['code', 'libelle', 'type', 'exemple']));
            }
            $copie->objets()->sync($source->objets->pluck('id')->all());
            foreach ($source->chambres() as $chambre) {
                DB::table('modeles_decision_chambres')->insert(['modele_decision_id' => $copie->id, 'chambre_libelle' => $chambre]);
            }
            ModeleDecisionVersion::create([
                'modele_decision_id' => $copie->id, 'numero' => 1, 'entete' => $version?->entete, 'qualite' => $version?->qualite, 'factum' => $version?->factum,
                'note' => 'Copie de « '.$source->nom.' »'.($version ? ' (version '.$version->numero.')' : ''), 'cree_par' => $request->user()?->id,
            ]);

            return $copie;
        });

        Journal::audit('modele_decision_cree', $copie, $copie->nom, [], ['nom' => $copie->nom, 'copie_de' => $source->nom]);

        return redirect()->route('decisions.modeles.edition', $copie->id)->with('success', 'Copie créée : renommez-la et adaptez-la.');
    }

    /** Remet un ancien texte en service : une nouvelle version est créée à partir de l'ancienne (l'historique reste intact). */
    public function restaurer(int $id, int $numero, Request $request): RedirectResponse
    {
        Gate::authorize('modeles.modifier');
        $modele = ModeleDecision::findOrFail($id);
        $ancienne = $modele->versions()->where('numero', $numero)->firstOrFail();
        $courante = $modele->versions()->first();

        ModeleDecisionVersion::create([
            'modele_decision_id' => $modele->id, 'numero' => $courante->numero + 1, 'entete' => $ancienne->entete, 'qualite' => $ancienne->qualite, 'factum' => $ancienne->factum,
            'note' => 'Retour à la version '.$numero, 'cree_par' => $request->user()?->id,
        ]);
        Journal::audit('modele_decision_version', $modele, $modele->nom.' : retour à la version '.$numero, [], ['version' => $courante->numero + 1]);

        return redirect()->route('decisions.modeles.edition', $modele->id)->with('success', 'Version '.$numero.' remise en service (nouvelle version '.($courante->numero + 1).').');
    }

    public function destroy(int $id): JsonResponse
    {
        Gate::authorize('modeles.supprimer');
        $modele = ModeleDecision::findOrFail($id);

        if ($modele->etat === 'actif') {
            return response()->json(['status' => false, 'message' => 'Un modèle actif ne se supprime pas : archivez-le d\'abord.']);
        }

        Journal::audit('modele_decision_supprime', $modele, $modele->nom, ['nom' => $modele->nom, 'etat' => $modele->etat]);
        $modele->delete();

        return response()->json(['status' => true, 'message' => 'Modèle supprimé.']);
    }

    /** Un texte réduit à son contenu : un simple enregistrement qui reformate le HTML (entités, espaces, lignes vides finales) ne crée pas de version. */
    private function forme(?string $texte): string
    {
        return (string) preg_replace('/\s+/u', ' ', html_entity_decode(trim((string) TexteRiche::nettoyer($texte))));
    }

    /** « assesseurs.0.nom » est une donnée d'un ensemble connu (« assesseurs ») : pas une variable inconnue. */
    private function estSousDonnee(string $variable, array $connues): bool
    {
        foreach ($connues as $c) {
            if (str_starts_with($variable, $c.'.') || str_starts_with($c, $variable.'.')) {
                return true;
            }
        }

        return false;
    }
}
