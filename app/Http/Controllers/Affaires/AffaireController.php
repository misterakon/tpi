<?php

namespace App\Http\Controllers\Affaires;

use App\Http\Controllers\Controller;
use App\Models\Affaire;
use App\Models\Avocat;
use App\Models\AffaireAttribution;
use App\Models\Chambre;
use App\Models\Decision;
use App\Models\PieceDossier;
use App\Models\ModeleDecision;
use App\Services\Journal;
use App\Support\EnrolementAffaire;
use App\Support\ExportAffaires;
use App\Support\ParametresAffaires;
use App\Support\RegistreAffaire;
use App\Support\Redaction\ChampsDecision;
use App\Support\Redaction\Preremplissage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/*
 | Liste, fiche et suivi des affaires. Une affaire ne se supprime jamais : elle s'annule (avec un motif) et son numéro n'est pas réutilisé.
 | La réattribution à une autre chambre est un droit spécial (la Présidente) et reste tracée. Les corrections sont tracées au journal.
 | Droits : affaires.voir / modifier / annuler, affaires.reattribuer.
 */
class AffaireController extends Controller
{
    /** Export Excel ou PDF de la liste, avec les filtres de l'écran (chambre, période, statut, registre). */
    public function exporter(Request $request)
    {
        Gate::authorize('affaires.exporter');
        $format = $request->query('format') === 'pdf' ? 'pdf' : 'xlsx';
        $filtres = ExportAffaires::filtres($request->query());
        $affaires = ExportAffaires::appliquer(Affaire::visiblesPour($request->user())->with(['parties', 'chambre', 'nature']), $filtres)->orderByDesc('annee')->orderByDesc('numero')->limit(20000)->get();
        $lignes = ExportAffaires::lignes($affaires);

        Journal::audit('liste_exportee', null, $format === 'pdf' ? 'PDF' : 'Excel', [], ['affaires' => count($lignes)] + array_filter($filtres));

        $nom = 'affaires-'.now()->format('Ymd-Hi');
        if ($format === 'pdf') {
            $criteres = $this->criteres($filtres);

            return response(ExportAffaires::pdf($lignes, $criteres, 'Liste des affaires'), 200, [
                'Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="'.$nom.'.pdf"', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff',
            ]);
        }

        return response()->download(ExportAffaires::excel($lignes), $nom.'.xlsx', ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff'])->deleteFileAfterSend(true);
    }

    /** Les filtres en clair, pour l'en-tête du PDF. @return list<string> */
    private function criteres(array $f): array
    {
        $c = [];
        if ($f['serie']) {
            $c[] = 'Registre '.$f['serie'];
        }
        if ($f['chambre']) {
            $c[] = (string) Chambre::whereKey($f['chambre'])->value('libelle');
        }
        if ($f['du'] || $f['au']) {
            $c[] = 'Du '.($f['du'] ? date('d/m/Y', strtotime($f['du'])) : '…').' au '.($f['au'] ? date('d/m/Y', strtotime($f['au'])) : '…');
        }
        if ($f['statut']) {
            $c[] = Affaire::STATUTS[$f['statut']];
        }

        return $c;
    }

    /** Une page de la liste, au format DataTables (tri, recherche et filtres dans la base) : la page ne charge jamais toutes les affaires. */
    public function donnees(Request $request): \Illuminate\Http\JsonResponse
    {
        Gate::authorize('affaires.voir');
        $filtres = ExportAffaires::filtres($request->query());
        $serie = (string) $request->input('filtre.1', '');
        if (in_array($serie, ['RG', 'RP', 'RI', 'RE'], true)) {
            $filtres['serie'] = $serie;
        }
        $base = ExportAffaires::appliquer(Affaire::visiblesPour($request->user())->with(['parties', 'chambre', 'nature'])->withCount('pieces'), $filtres)->orderByDesc('annee')->orderByDesc('numero')->orderByDesc('id');

        return \App\Support\TableauServeur::repondre(
            $request, $base,
            [0 => ['annee', 'numero'], 1 => ['serie'], 2 => ['date_rg', 'id'], 3 => ['objet'], 6 => ['statut']],
            fn ($q, $mots) => \App\Support\RechercheTexte::appliquer($q, $mots, ['numero_complet', 'numero_parquet', 'objet', 'articles', 'ancien_numero'], 'parties', ['nom', 'prenoms', 'denomination']),
            fn (Affaire $l) => view('affaires.liste._ligne', ['l' => $l])->render(),
        );
    }

    public function index(Request $request): View
    {
        Gate::authorize('affaires.voir');
        $filtres = ExportAffaires::filtres($request->query());

        return view('affaires.liste.index', EnrolementController::listes() + [
            'filtres' => $filtres,
            'total' => ExportAffaires::appliquer(Affaire::visiblesPour(auth()->user()), $filtres)->count(),
            'manuel' => ParametresAffaires::numeroManuel('RG'),
            'manuel_penal' => ParametresAffaires::numeroManuel('RP'),
            'manuel_instruction' => ParametresAffaires::numeroManuel('RI') || ParametresAffaires::numeroManuel('RE'),
            'vocab' => ParametresAffaires::vocabulaire(),
        ]);
    }

    public function fiche(int $id): View
    {
        Gate::authorize('affaires.voir');
        $affaire = Affaire::visiblesPour(auth()->user())->with(['parties', 'chambre', 'nature', 'matiere', 'modeSaisine', 'auteur', 'derogant', 'principale', 'jointes.parties', 'numerosCorriges', 'attributions.avant', 'attributions.apres', 'attributions.auteur'])->findOrFail($id);

        $peutCorriger = Gate::allows('affaires.modifier') && ! $affaire->estAnnulee();

        // Qui a ouvert quelle affaire, et quand : une ligne au journal d'audit (au plus une par quart d'heure, un rafraîchissement ne compte pas).
        if (\Illuminate\Support\Facades\Cache::add('consultation_affaire:'.auth()->id().':'.$affaire->id, true, now()->addMinutes(15))) {
            \App\Services\Journal::audit('affaire_consultee', $affaire, $affaire->numeroAffichage());
        }

        return view('affaires.liste.fiche', [
            'affaire' => $affaire,
            'correction' => $peutCorriger ? $this->donneesDeCorrection($affaire) : null,
            'dossier' => Gate::allows('dossiers.voir') ? $this->donneesDuDossier($affaire) : null,
            // Scellés : seulement pour le pénal et l'instruction, et pour qui a le droit de les voir.
            'scelles' => Gate::allows('scelles.voir') && in_array($affaire->serie, ['RP', 'RI', 'RE'], true) ? $affaire->scelles()->get() : null,
            'chambres' => EnrolementController::chambres(),
            // Affaires de la même chambre auxquelles on peut joindre celle-ci (ni annulées, ni déjà jointes).
            'joignables' => Gate::allows('affaires.modifier') && ! $affaire->estAnnulee() && ! $affaire->jointe_a_id
                ? Affaire::visiblesPour(auth()->user())->with('parties')->where('id', '!=', $affaire->id)->where('serie', $affaire->serie)->where('chambre_id', $affaire->chambre_id)->where('statut', 'enrolee')->whereNull('jointe_a_id')->orderByDesc('id')->limit(200)->get()
                : collect(),
            'modeles' => Gate::allows('redaction.creer') && $affaire->serie === 'RG' ? ModeleDecision::whereIn('etat', ['actif', 'brouillon'])->whereHas('versions')->orderBy('nom')->get(['id', 'nom', 'etat']) : collect(),
        ]);
    }

    public function mettreAJour(int $id, Request $request): RedirectResponse
    {
        Gate::authorize('affaires.modifier');
        $affaire = Affaire::visiblesPour(auth()->user())->with('parties')->findOrFail($id);
        abort_if($affaire->estAnnulee(), 403, 'Une affaire annulée ne se corrige pas.');

        $def = RegistreAffaire::depuisSerie($affaire->serie);
        $donnees = $request->validate(EnrolementAffaire::regles(false, ParametresAffaires::numeroManuel($affaire->serie), $def), EnrolementAffaire::messages($def));
        $parties = EnrolementAffaire::parties($donnees['parties'], $def);

        $doublons = EnrolementAffaire::doublons($parties, $affaire->id, $affaire->serie);
        if ($doublons !== [] && ! $request->boolean('confirmer_doublon')) {
            return back()->withInput()->with('doublons', $doublons);
        }

        // La date d'audience n'est recontrôlée que si on la change.
        $date = $donnees['date_premiere_audience'] ?? null;
        $motif = $affaire->derogation_motif;
        if ($date !== $affaire->date_premiere_audience?->toDateString()) {
            [$date, $motif] = EnrolementAffaire::dateAudience($donnees, $request, $affaire->chambre, $def);
        }

        $avant = [
            'objet' => $affaire->objet, 'nature' => $affaire->nature?->libelle, 'matiere' => $affaire->matiere?->libelle, 'mode' => $affaire->modeSaisine?->libelle,
            'audience' => trim($affaire->date_premiere_audience?->format('d/m/Y').' '.$affaire->heureAudience()), 'parties' => $affaire->parties->map(fn ($p) => $p->libelle())->implode(' ; '),
        ];

        DB::transaction(function () use ($affaire, $donnees, $parties, $date, $motif, $request) {
            $affaire->forceFill([
                'mode_saisine_id' => $donnees['mode_saisine_id'], 'objet' => trim($donnees['objet']), 'articles' => $donnees['articles'] ?? null, 'numero_parquet' => filled($donnees['numero_parquet'] ?? null) ? trim($donnees['numero_parquet']) : null, 'nature_affaire_id' => $donnees['nature_affaire_id'], 'matiere_id' => $donnees['matiere_id'],
                'date_premiere_audience' => $date, 'heure_premiere_audience' => $date ? ($donnees['heure_premiere_audience'] ?? null) : null, 'derogation_motif' => $motif, 'derogation_par' => $motif && $motif !== $affaire->getOriginal('derogation_motif') ? $request->user()?->id : $affaire->derogation_par,
            ])->save();
            $affaire->parties()->delete();
            foreach ($parties as $i => $p) {
                [$p['avocat_id'], $p['avocat']] = Avocat::rattacher($p['avocat'] ?? null, Gate::allows('avocats.creer'));
                $affaire->parties()->create($p + ['ordre' => $i]);
            }
        });

        $affaire->load('nature', 'matiere', 'modeSaisine', 'parties');
        $apres = [
            'objet' => $affaire->objet, 'nature' => $affaire->nature?->libelle, 'matiere' => $affaire->matiere?->libelle, 'mode' => $affaire->modeSaisine?->libelle,
            'audience' => trim($affaire->date_premiere_audience?->format('d/m/Y').' '.$affaire->heureAudience()), 'parties' => $affaire->parties->map(fn ($p) => $p->libelle())->implode(' ; '),
        ];
        Journal::audit('affaire_modifiee', $affaire, $affaire->numeroAffichage(), $avant, $apres);

        return redirect()->route('affaires.liste.fiche', $affaire->id)->with('success', 'Affaire corrigée : le numéro '.$affaire->numeroAffichage().' ne change pas.');
    }

    /** Les pièces du dossier numérique, telles que la visionneuse en a besoin (sans jamais exposer le chemin du fichier). */
    private function donneesDuDossier(Affaire $affaire): array
    {
        $pieces = $affaire->pieces()->get()->map(fn (PieceDossier $p) => [
            'id' => $p->id, 'type' => $p->type, 'type_libelle' => $p->typeLibelle(), 'titre' => $p->titre, 'nom' => $p->nom_original, 'pdf' => $p->estPdf(),
            'date' => $p->date_piece?->format('d/m/Y'), 'taille' => PieceDossier::tailleLisible($p->taille), 'depose_le' => $p->created_at?->format('d/m/Y à H:i'),
            'depose_par' => $p->auteur?->name, 'empreinte' => substr($p->empreinte, 0, 16),
            'url' => route('affaires.dossier.fichier', $p->id), 'url_telecharger' => route('affaires.dossier.telecharger', $p->id), 'url_consultations' => route('affaires.dossier.consultations', $p->id),
            'url_modifier' => route('affaires.dossier.modifier', $p->id), 'url_retirer' => route('affaires.dossier.retirer', $p->id),
            'date_iso' => $p->date_piece?->format('Y-m-d'),
        ])->all();

        return ['pieces' => $pieces, 'taille_max' => PieceDossier::tailleLisible(PieceDossier::tailleMaxKo() * 1024)];
    }

    /** Ce qu'il faut à la fenêtre de correction : listes, famille de l'affaire, valeurs et parties actuelles. */
    private function donneesDeCorrection(Affaire $affaire): array
    {
        $listes = EnrolementController::listes();
        // Instruction : la série ne change pas, on ne propose donc que les natures de la même série.
        $listes['natures_instruction'] = $listes['natures_instruction']->where('registre', $affaire->serie)->values();

        return $listes + [
            'def' => RegistreAffaire::depuisSerie($affaire->serie),
            'manuel' => ParametresAffaires::numeroManuel($affaire->serie),
            'defauts' => [
                'mode_saisine_id' => $affaire->mode_saisine_id, 'objet' => $affaire->objet, 'nature_affaire_id' => $affaire->nature_affaire_id, 'matiere_id' => $affaire->matiere_id,
                'chambre_id' => $affaire->chambre_id, 'date_premiere_audience' => $affaire->date_premiere_audience?->format('Y-m-d'), 'heure_premiere_audience' => $affaire->heure_premiere_audience ? substr((string) $affaire->heure_premiere_audience, 0, 5) : null, 'articles' => $affaire->articles,
            ],
            'parties' => $affaire->parties->map(fn ($p) => [
                'role' => $p->role, 'nature' => $p->nature, 'nom' => $p->nom, 'prenoms' => $p->prenoms, 'denomination' => $p->denomination,
                'naissance_date' => $p->naissance_date?->format('Y-m-d'), 'naissance_annee' => $p->naissance_annee, 'lieu_naissance' => $p->lieu_naissance,
                'nationalite' => $p->nationalite, 'profession' => $p->profession, 'domicile' => $p->domicile, 'avocat' => $p->avocat, 'autres' => $p->autres ?: '',
                'mandat_depot_date' => $p->mandat_depot_date?->format('Y-m-d'), 'lieu_detention' => $p->lieu_detention,
                'echeance_detention' => $p->echeance_detention?->format('Y-m-d'), 'libere_le' => $p->libere_le?->format('Y-m-d'),
            ])->all(),
        ];
    }

    /**
     * Corrige le numéro d'une affaire dont le numéro a été reporté à la main du registre du greffe (faute de frappe).
     * Droit spécial « affaires.corriger_numero ». Jamais pour un numéro attribué par l'application, ni pour une affaire annulée ou jointe ;
     * le nouveau numéro ne doit pas déjà exister. L'ancien numéro, le motif et l'auteur sont conservés.
     */
    public function corrigerNumero(int $id, Request $request): RedirectResponse
    {
        Gate::authorize('affaires.corriger_numero');
        $affaire = Affaire::visiblesPour(auth()->user())->findOrFail($id);
        abort_if($affaire->estAnnulee(), 403, 'Une affaire annulée ne se corrige pas.');
        abort_unless($affaire->numero_saisi, 403, "Ce numéro a été attribué par l'application : il ne se modifie jamais.");
        abort_if($affaire->jointe_a_id || $affaire->jointes()->exists(), 403, 'Cette affaire est jointe à une autre (ou a des jointes) : le numéro est commun, il ne peut pas être corrigé ici.');

        $donnees = $request->validate([
            'numero_rg' => ['required', 'regex:/^\d{1,6}\/\d{2}$/'],
            'motif' => ['required', 'string', 'min:5', 'max:300'],
        ], ['numero_rg.required' => 'Saisissez le bon numéro.', 'numero_rg.regex' => 'Le numéro doit s\'écrire ainsi : 1391/25.', 'motif.required' => 'Indiquez le motif de la correction.', 'motif.min' => 'Le motif est trop court.']);

        [$numero, $annee] = EnrolementController::decouper($donnees['numero_rg']);
        if ($numero === $affaire->numero && $annee === $affaire->annee) {
            throw ValidationException::withMessages(['numero_rg' => "C'est déjà le numéro de cette affaire."]);
        }

        $ancien = $affaire->numeroAffichage();
        DB::transaction(function () use ($affaire, $numero, $annee, $donnees, $request, $ancien) {
            \App\Services\NumerotationRegistre::verrouiller($affaire->serie, $annee);
            if (Affaire::where('serie', $affaire->serie)->where('annee', $annee)->where('numero', $numero)->exists()) {
                throw ValidationException::withMessages(['numero_rg' => 'Ce numéro existe déjà dans le registre '.$affaire->serie.' : une affaire ne peut pas le prendre sans jonction.']);
            }
            $affaire->corrigerLeNumero($numero, $annee);
            $affaire->numerosCorriges()->create(['ancien' => $ancien, 'nouveau' => $affaire->numeroAffichage(), 'motif' => trim($donnees['motif']), 'par' => $request->user()?->id]);
        });
        Journal::audit('numero_corrige', $affaire, $affaire->numeroAffichage(), ['numero' => $ancien], ['numero' => $affaire->numeroAffichage(), 'motif' => trim($donnees['motif'])]);

        return redirect()->route('affaires.liste.fiche', $affaire->id)->with('success', 'Numéro corrigé : '.$ancien.' devient '.$affaire->numeroAffichage().'. L\'ancien numéro reste dans l\'historique.');
    }

    public function annuler(int $id, Request $request): RedirectResponse
    {
        Gate::authorize('affaires.annuler');
        $affaire = Affaire::visiblesPour(auth()->user())->findOrFail($id);
        abort_if($affaire->estAnnulee(), 403, 'Cette affaire est déjà annulée.');

        $donnees = $request->validate(['motif' => ['required', 'string', 'min:5', 'max:300']], ['motif.required' => "Indiquez le motif de l'annulation.", 'motif.min' => "Le motif de l'annulation est trop court."]);

        DB::transaction(function () use ($affaire, $donnees, $request) {
            $affaire->forceFill(['statut' => 'annulee', 'motif_annulation' => trim($donnees['motif']), 'annulee_le' => now(), 'annulee_par' => $request->user()?->id])->save();
            // Une affaire annulée sort de sa jonction (principale ou jointe).
            Affaire::where('jointe_a_id', $affaire->id)->update(['jointe_a_id' => null]);
            $affaire->forceFill(['jointe_a_id' => null])->save();
        });
        Journal::audit('affaire_annulee', $affaire, $affaire->numeroAffichage(), ['statut' => 'enrolee'], ['statut' => 'annulee', 'motif' => $affaire->motif_annulation]);

        return redirect()->route('affaires.liste.fiche', $affaire->id)->with('success', 'Affaire annulée : le numéro '.$affaire->numeroAffichage().' reste attribué et ne sera pas réutilisé.');
    }

    /** Réattribue l'affaire à une autre chambre (la décision de la Présidente), avec un motif tracé. */
    public function reattribuer(int $id, Request $request): RedirectResponse
    {
        Gate::authorize('affaires.reattribuer');
        $affaire = Affaire::visiblesPour(auth()->user())->findOrFail($id);
        abort_if($affaire->estAnnulee(), 403, 'Une affaire annulée ne se réattribue pas.');

        $donnees = $request->validate([
            'chambre_id' => ['required', 'integer', 'exists:chambres,id'],
            'motif' => ['required', 'string', 'min:5', 'max:300'],
        ], ['chambre_id.required' => 'Choisissez la chambre.', 'motif.required' => 'Indiquez le motif de la réattribution.', 'motif.min' => 'Le motif est trop court.']);

        if ((int) $donnees['chambre_id'] === (int) $affaire->chambre_id) {
            return back()->with('error', "L'affaire est déjà dans cette chambre.");
        }

        DB::transaction(function () use ($affaire, $donnees, $request) {
            AffaireAttribution::create([
                'affaire_id' => $affaire->id, 'chambre_avant_id' => $affaire->chambre_id, 'chambre_apres_id' => $donnees['chambre_id'],
                'motif' => trim($donnees['motif']), 'par' => $request->user()?->id,
            ]);
            // Des causes jointes siègent ensemble : elles quittent la jonction si l'une change de chambre.
            Affaire::where('jointe_a_id', $affaire->id)->update(['jointe_a_id' => null]);
            $affaire->forceFill(['chambre_id' => $donnees['chambre_id'], 'jointe_a_id' => null])->save();
        });
        $affaire->load('chambre');
        Journal::audit('affaire_reattribuee', $affaire, $affaire->numeroAffichage(), [], ['chambre' => $affaire->chambre?->libelle, 'motif' => trim($donnees['motif'])]);

        return redirect()->route('affaires.liste.fiche', $affaire->id)->with('success', 'Affaire réattribuée à '.$affaire->chambre?->libelle.'.');
    }

    /** Jonction des causes : l'affaire choisie est jointe à celle-ci (principale) ; chacune garde son numéro. */
    public function joindre(int $id, Request $request): RedirectResponse
    {
        Gate::authorize('affaires.modifier');
        $principale = Affaire::visiblesPour(auth()->user())->findOrFail($id);
        $donnees = $request->validate(['affaire_id' => ['required', 'integer', 'exists:affaires,id']], ['affaire_id.required' => "Choisissez l'affaire à joindre."]);
        $jointe = Affaire::visiblesPour(auth()->user())->findOrFail($donnees['affaire_id']);

        $erreur = match (true) {
            $principale->id === $jointe->id => 'Une affaire ne se joint pas à elle-même.',
            $principale->estAnnulee() || $jointe->estAnnulee() => 'Une affaire annulée ne peut pas être jointe.',
            $principale->jointe_a_id !== null => "Cette affaire est elle-même jointe à une autre : joignez à l'affaire principale.",
            $jointe->jointe_a_id !== null => 'Cette affaire est déjà jointe à une autre.',
            Affaire::where('jointe_a_id', $jointe->id)->exists() => 'Cette affaire a déjà des affaires jointes : joignez plutôt celles-ci une à une.',
            $principale->serie !== $jointe->serie => 'Deux affaires de registres différents (civil, pénal, instruction) ne peuvent pas être jointes.',
            (int) $principale->chambre_id !== (int) $jointe->chambre_id => 'Les deux affaires doivent être dans la même chambre.',
            default => null,
        };
        if ($erreur) {
            throw ValidationException::withMessages(['affaire_id' => $erreur]);
        }

        $jointe->forceFill(['jointe_a_id' => $principale->id])->save();
        Journal::audit('affaire_jointe', $jointe, $jointe->numeroAffichage(), [], ['jointe_a' => $principale->numeroAffichage()]);

        return redirect()->route('affaires.liste.fiche', $principale->id)->with('success', $jointe->numeroAffichage().' est jointe à '.$principale->numeroAffichage().' : chacune garde son numéro.');
    }

    public function disjoindre(int $id): RedirectResponse
    {
        Gate::authorize('affaires.modifier');
        $jointe = Affaire::visiblesPour(auth()->user())->with('principale')->findOrFail($id);
        abort_unless($jointe->jointe_a_id, 403, "Cette affaire n'est pas jointe.");

        $principale = $jointe->principale;
        $jointe->forceFill(['jointe_a_id' => null])->save();
        Journal::audit('affaire_disjointe', $jointe, $jointe->numeroAffichage(), ['jointe_a' => $principale?->numeroAffichage()], []);

        return redirect()->route('affaires.liste.fiche', $jointe->id)->with('success', $jointe->numeroAffichage().' est disjointe.');
    }

    /** Ouvre une décision rédigée à partir de cette affaire : numéro, date du RG, chambre, date d'audience et demandeur sont repris. */
    public function rediger(int $id, Request $request): RedirectResponse
    {
        Gate::authorize('redaction.creer');
        $affaire = Affaire::visiblesPour(auth()->user())->with('parties')->findOrFail($id);
        abort_if($affaire->estAnnulee(), 403, 'Une affaire annulée ne donne pas lieu à décision.');

        $donnees = $request->validate(['modele_decision_id' => ['required', 'integer', 'exists:modeles_decision,id']], ['modele_decision_id.required' => 'Choisissez le modèle de décision.']);
        $modele = ModeleDecision::with('variables')->findOrFail($donnees['modele_decision_id']);
        $version = $modele->versionCourante();
        if (! $version || $modele->etat === 'archive') {
            throw ValidationException::withMessages(['modele_decision_id' => "Ce modèle n'est pas utilisable."]);
        }

        $champs = ChampsDecision::pour($modele, $version);
        $connues = Preremplissage::pour($modele, Chambre::find($affaire->chambre_id), $affaire->date_premiere_audience?->toDateString(), $affaire);

        $decision = Decision::create([
            'modele_decision_id' => $modele->id, 'chambre_id' => $affaire->chambre_id, 'affaire_id' => $affaire->id, 'version_numero' => $version->numero,
            'reference' => $affaire->numeroAffichage(), 'statut' => 'brouillon', 'valeurs' => ChampsDecision::nettoyer($champs, $connues), 'cree_par' => $request->user()?->id,
        ]);
        Journal::audit('decision_creee', $decision, $decision->libelle(), [], ['modele' => $modele->nom, 'version' => $version->numero, 'reference' => $decision->reference, 'affaire' => $affaire->numeroAffichage()]);

        return redirect()->route('decisions.redaction.edition', $decision->id)->with('success', 'Décision ouverte à partir de l\'affaire '.$affaire->numeroAffichage().'.');
    }
}
