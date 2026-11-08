<?php

namespace App\Http\Controllers\Decisions;

use App\Http\Controllers\Controller;
use App\Models\Affaire;
use App\Models\AnneeJudiciaire;
use App\Models\Chambre;
use App\Models\Decision;
use App\Models\Delivrance;
use App\Models\ModeleDecision;
use App\Models\RoleAudience;
use App\Services\Journal;
use App\Services\NumeroDecision;
use App\Support\PerimetreChambres;
use App\Support\RechercheTexte;
use App\Support\TableauServeur;
use App\Support\Redaction\ChampsDecision;
use App\Support\Redaction\ChoixAffaire;
use App\Support\Redaction\Preremplissage;
use App\Support\Redaction\RenduDecision;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/*
 | Rédaction d'une décision à partir d'un modèle : on choisit le modèle, on remplit les champs que son texte demande (formulaire déduit
 | des variables), on contrôle l'aperçu sur une page A4, puis on valide. La validation fige le texte : il ne change plus, même si le
 | modèle est modifié ensuite (une correction passe par une nouvelle décision). Droits : redaction.voir / creer / modifier / annuler,
 | et le droit spécial redaction.valider.
 */
class RedactionController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('redaction.voir');

        // Ouverture depuis la feuille d'audience : l'affaire jugée arrive déjà choisie dans la fenêtre « Rédiger une décision ».
        $affaire = $request->filled('affaire') && ctype_digit((string) $request->query('affaire')) && Gate::allows('redaction.creer')
            ? Affaire::visiblesPour($request->user())->find((int) $request->query('affaire')) : null;

        return view('decisions.redaction.index', [
            'preselection' => $affaire ? ChoixAffaire::pour($affaire) : null,
            'total' => Decision::visiblesPour($request->user())->count(), 'brouillons' => Decision::visiblesPour($request->user())->where('statut', 'brouillon')->count(),
            'modeles' => ModeleDecision::whereIn('etat', ['actif', 'brouillon'])->whereHas('versions')->orderBy('nom')->get(['id', 'nom', 'etat']),
            'chambres' => Chambre::whereIn('annee_judiciaire_id', AnneeJudiciaire::where('courante', true)->select('id'))->where('actif', true)
                ->when(PerimetreChambres::de($request->user()) !== null, fn ($q) => $q->whereIn('id', PerimetreChambres::de($request->user()) ?: [0]))->orderBy('ordre')->get(['id', 'libelle', 'intitule']),
        ]);
    }

    /** Les lignes de la page, une page à la fois (tri et recherche dans la base). */
    public function donnees(Request $request): JsonResponse
    {
        Gate::authorize('redaction.voir');
        $base = Decision::visiblesPour($request->user())->with(['modele', 'auteur'])->orderByDesc('updated_at')->orderByDesc('id');

        return TableauServeur::repondre(
            $request, $base,
            [0 => ['reference'], 2 => ['statut'], 3 => ['updated_at']],
            fn ($q, $mots) => RechercheTexte::appliquer($q, $mots, ['reference'], 'modele', ['nom']),
            fn (Decision $l) => view('decisions.redaction._ligne', ['l' => $l])->render(),
        );
    }

    /** Une décision, seulement si elle est dans le périmètre de l'utilisateur (sinon elle est « introuvable » : on ne révèle pas qu'elle existe). */
    private function decision(int $id, array $avec = []): Decision
    {
        $decision = Decision::with($avec)->findOrFail($id);
        abort_unless($decision->estVisiblePour(auth()->user()), 404);

        return $decision;
    }

    /** Recherche d'une affaire du registre (numéro ou partie) ; sans recherche, les affaires jugées dont la décision reste à rédiger. */
    public function affaires(Request $request): JsonResponse
    {
        Gate::authorize('redaction.creer');

        return response()->json(['resultats' => ChoixAffaire::rechercher($request->user(), (string) $request->query('q', ''))]);
    }

    /** Ouvre une décision pour une affaire du registre : le modèle, l'affaire, et la chambre et la date d'audience qui en découlent. */
    public function creer(Request $request): RedirectResponse
    {
        Gate::authorize('redaction.creer');

        $donnees = $request->validate([
            'modele_decision_id' => ['required', 'integer', 'exists:modeles_decision,id'],
            'affaire_id' => ['nullable', 'integer', 'required_without:reference'],
            'reference' => ['nullable', 'string', 'max:120', 'required_without:affaire_id'],
            'chambre_id' => ['required', 'integer', 'exists:chambres,id'],
            'date_audience' => ['nullable', 'date'],
        ], ['modele_decision_id.required' => 'Choisissez le modèle de décision.', 'chambre_id.required' => 'Choisissez la chambre : sa composition est reprise de l\'ordonnance.',
            'affaire_id.required_without' => "Choisissez l'affaire concernée.", 'reference.required_without' => "Choisissez l'affaire concernée."]);

        if (! PerimetreChambres::voit($request->user(), (int) $donnees['chambre_id'])) {
            throw ValidationException::withMessages(['chambre_id' => "Cette chambre n'est pas dans votre périmètre."]);
        }

        // Une affaire du registre : seulement celles que l'utilisateur a le droit de voir. Sa référence est celle du registre.
        $affaire = null;
        if (! empty($donnees['affaire_id'])) {
            $affaire = Affaire::visiblesPour($request->user())->with('parties')->find((int) $donnees['affaire_id']);
            if (! $affaire) {
                throw ValidationException::withMessages(['affaire_id' => "Cette affaire n'existe pas ou vous n'y avez pas accès."]);
            }
            $donnees['reference'] = $affaire->numeroAffichage();
        }

        $modele = ModeleDecision::findOrFail($donnees['modele_decision_id']);
        $version = $modele->versionCourante();
        if (! $version || $modele->etat === 'archive') {
            throw ValidationException::withMessages(['modele_decision_id' => "Ce modèle n'est pas utilisable."]);
        }

        // Ce que l'application sait déjà (juridiction, chambre, composition, date d'audience) est rempli d'avance : on ne le saisit pas.
        $champs = ChampsDecision::pour($modele->load('variables'), $version);
        // L'audience où l'affaire a été jugée apporte la composition tenue, le type d'audience, la nature et la date de la décision.
        $dernier = $affaire ? (ChoixAffaire::derniers([$affaire->id])[$affaire->id] ?? null) : null;
        $role = $dernier ? RoleAudience::find($dernier['role_id']) : null;
        $connues = Preremplissage::pour($modele, Chambre::find($donnees['chambre_id'] ?? null), $donnees['date_audience'] ?? $role?->date_audience->toDateString(), $affaire, $role);

        // Un assesseur dont le genre n'est pas connu (agent sans sexe, remplaçant écrit à la main) n'empêche pas d'ouvrir la décision :
        // il reste dans la liste et son genre est à indiquer dans le formulaire.
        $sansGenre = [];
        if (! empty($connues['assesseurs'])) {
            $connues['assesseurs'] = array_values(array_filter($connues['assesseurs'], function ($p) use (&$sansGenre) {
                if (! empty($p['genre'])) {
                    return true;
                }
                $sansGenre[] = ['nom' => $p['nom'], 'genre' => ''];

                return false;
            }));
        }
        $valeurs = ChampsDecision::nettoyer($champs, $connues);
        if ($sansGenre && collect($champs)->contains('code', 'assesseurs')) {
            $valeurs['assesseurs'] = array_merge($valeurs['assesseurs'] ?? [], $sansGenre);
        }

        $decision = Decision::create([
            'modele_decision_id' => $modele->id, 'chambre_id' => $donnees['chambre_id'], 'affaire_id' => $affaire?->id, 'version_numero' => $version->numero, 'reference' => trim($donnees['reference']),
            'statut' => 'brouillon', 'valeurs' => $valeurs, 'cree_par' => $request->user()?->id,
        ]);
        Journal::audit('decision_creee', $decision, $decision->libelle(), [], ['modele' => $modele->nom, 'version' => $version->numero, 'reference' => $decision->reference]);

        return redirect()->route('decisions.redaction.edition', $decision->id);
    }

    public function edition(int $id): View
    {
        Gate::authorize('redaction.voir');
        $decision = $this->decision($id, ['modele.variables']);
        $version = $decision->version();
        abort_unless($version, 404, 'La version du modèle de cette décision est introuvable.');

        return view('decisions.redaction.edition', [
            'decision' => $decision,
            'version' => $version,
            'champs' => ChampsDecision::pour($decision->modele, $version),
            // Figée dès la validation ; rédigée, elle n'est plus modifiable que par celui qui valide.
            'lecture' => $decision->estValidee() || ! Gate::allows('redaction.modifier') || ($decision->estRedigee() && ! Gate::allows('redaction.valider')),
            'miseEnPage' => ModeleDecisionController::miseEnPage(),
            'peutValider' => Gate::allows('redaction.valider'),
            'peutNumeroter' => Gate::allows('redaction.numeroter'),
            'peutSigner' => Gate::allows('redaction.signer'), 'peutDelivrer' => Gate::allows('redaction.delivrer'),
            'delivrances' => $decision->delivrances()->with('auteur:id,name')->get(),
            'momentNumero' => NumeroDecision::moment(),
        ]);
    }

    /** Enregistre la référence et les valeurs saisies (la décision reste un brouillon). */
    public function enregistrer(int $id, Request $request): RedirectResponse
    {
        Gate::authorize('redaction.modifier');
        $decision = $this->decision($id, ['modele.variables']);
        abort_if($decision->estValidee(), 403, 'Une décision validée ne se modifie plus.');
        abort_if($decision->estRedigee() && ! Gate::allows('redaction.valider'), 403, 'Cette décision est rédigée : seul celui qui la valide peut encore la modifier.');

        // Rattachée à une affaire du registre, la référence est celle du registre et ne se retape pas.
        $reference = $decision->affaire_id ? $decision->reference : $request->validate(['reference' => ['required', 'string', 'max:120']], ['reference.required' => "La référence de l'affaire est obligatoire."])['reference'];
        $champs = ChampsDecision::pour($decision->modele, $decision->version());
        $valeurs = ChampsDecision::nettoyer($champs, (array) $request->input('valeurs', []));

        $avant = ['reference' => $decision->reference, 'valeurs' => count($decision->valeurs ?? [])];
        $decision->forceFill(['reference' => trim($reference), 'valeurs' => $valeurs, 'version_numero' => $decision->numeroVersion()])->save();
        Journal::audit('decision_modifiee', $decision, $decision->libelle(), $avant, ['reference' => $decision->reference, 'valeurs' => count($valeurs)]);

        return redirect()->route('decisions.redaction.edition', $decision->id)->with('success', 'Décision enregistrée.');
    }

    /** Aperçu du texte avec les valeurs en cours de saisie (rien n'est enregistré) ; une décision validée montre son texte figé. */
    public function apercu(int $id, Request $request): JsonResponse
    {
        Gate::authorize('redaction.voir');
        $decision = $this->decision($id, ['modele.variables']);

        if ($decision->estValidee() && $decision->rendu) {
            return response()->json(['status' => true, 'fige' => true, 'manquantes' => []] + $decision->rendu);
        }

        // Sans formulaire envoyé (lecture seule), on montre les valeurs enregistrées.
        if (! $request->has('valeurs')) {
            return response()->json(['status' => true, 'fige' => false] + $this->rendu($decision, $decision->valeurs ?? []));
        }

        $champs = ChampsDecision::pour($decision->modele, $decision->version());
        try {
            $valeurs = ChampsDecision::nettoyer($champs, (array) $request->input('valeurs', []));
        } catch (ValidationException $e) {
            return response()->json(['status' => false, 'message' => implode(' ', collect($e->errors())->flatten()->all())], 422);
        }

        return response()->json(['status' => true, 'fige' => false] + $this->rendu($decision, $valeurs));
    }

    /** Le texte avec les champs vides nommés par des pastilles, et la liste de ce qui reste à compléter. */
    private function rendu(Decision $decision, array $valeurs): array
    {
        $champs = ChampsDecision::pour($decision->modele, $decision->version());
        $rendu = RenduDecision::rendre($decision->version(), $decision->valeursCompletes($valeurs), ChampsDecision::libelles($champs), $decision->retouches ?? []);

        return $rendu + ['a_completer' => ChampsDecision::aCompleter($champs, $rendu['manquantes'])];
    }

    /** Reprend la composition du tribunal de l'ordonnance (président, assesseurs, greffier) à la date d'audience saisie. */
    public function recomposer(int $id, Request $request): RedirectResponse
    {
        Gate::authorize('redaction.modifier');
        $decision = $this->decision($id, ['modele.variables', 'chambre']);
        abort_if($decision->estValidee(), 403, 'Une décision validée ne se modifie plus.');

        if (! $decision->chambre && $request->filled('chambre_id')) {
            $request->validate(['chambre_id' => ['integer', 'exists:chambres,id']]);
            $decision->forceFill(['chambre_id' => (int) $request->input('chambre_id')])->save();
            $decision->load('chambre');
        }
        if (! $decision->chambre) {
            return redirect()->route('decisions.redaction.edition', $decision->id)->with('error', "Cette décision n'a pas de chambre : la composition ne peut pas être reprise.");
        }

        $composition = Preremplissage::composition($decision->chambre, $decision->valeurs['date_audience'] ?? null);
        if ($composition === []) {
            return redirect()->route('decisions.redaction.edition', $decision->id)->with('error', "L'ordonnance ne donne aucune composition pour cette chambre à cette date.");
        }

        // Les anciennes valeurs de composition sont remplacées, le reste est conservé.
        $valeurs = array_filter($decision->valeurs ?? [], fn ($k) => ! preg_match('/^(president|greffier|assesseurs)(\.|$)/', $k), ARRAY_FILTER_USE_KEY);
        $champs = ChampsDecision::pour($decision->modele, $decision->version());
        $decision->forceFill(['valeurs' => $valeurs + ChampsDecision::nettoyer($champs, $composition)])->save();
        Journal::audit('decision_modifiee', $decision, $decision->libelle(), [], ['composition' => 'reprise de l\'ordonnance']);

        return redirect()->route('decisions.redaction.edition', $decision->id)->with('success', "Composition reprise de l'ordonnance.");
    }

    /** Retouche à la main des parties du texte (gras, italique, lignes…) ; une partie « rétablie » suit de nouveau le formulaire. */
    public function retoucher(int $id, Request $request): JsonResponse
    {
        Gate::authorize('redaction.modifier');
        $decision = $this->decision($id);
        abort_if($decision->estValidee(), 403, 'Une décision validée ne se modifie plus.');

        $parties = array_keys(RenduDecision::PARTIES);
        $donnees = $request->validate([
            'retouches' => ['nullable', 'array'],
            'retouches.*' => ['nullable', 'string', 'max:60000'],
            'retablir' => ['nullable', 'array'],
            'retablir.*' => [\Illuminate\Validation\Rule::in($parties)],
        ]);

        $retouches = $decision->retouches ?? [];
        foreach ($donnees['retablir'] ?? [] as $partie) {
            unset($retouches[$partie]);
        }
        foreach ($donnees['retouches'] ?? [] as $partie => $html) {
            if (! in_array($partie, $parties, true)) {
                continue;
            }
            $propre = \App\Support\TexteRiche::nettoyer($html);
            if ($propre === null || $propre === '') {
                unset($retouches[$partie]);
            } else {
                $retouches[$partie] = $propre;
            }
        }

        $avant = ['retouches' => array_keys($decision->retouches ?? [])];
        $decision->forceFill(['retouches' => $retouches ?: null])->save();
        Journal::audit('decision_retouchee', $decision, $decision->libelle(), $avant, ['retouches' => array_keys($retouches)]);

        return response()->json(['status' => true, 'message' => 'Texte retouché enregistré.']);
    }

    /** La rédaction est terminée : la décision passe à « rédigée » et attend la validation. Tout doit être renseigné. */
    public function redigee(int $id, Request $request): RedirectResponse
    {
        Gate::authorize('redaction.modifier');
        $decision = $this->decision($id, ['modele']);
        if ($decision->statut !== 'brouillon') {
            return $this->retour($decision, 'error', 'Seul un brouillon peut être marqué comme rédigé.');
        }
        if ($manquantes = $this->manquantes($decision)) {
            return $this->retour($decision, 'error', 'Enregistrez puis complétez : il manque '.implode(', ', $manquantes).'.');
        }

        $decision->forceFill(['statut' => 'redigee', 'redigee_le' => now(), 'redigee_par' => $request->user()?->id])->save();
        Journal::audit('decision_redigee', $decision, $decision->libelle(), ['statut' => 'brouillon'], ['statut' => 'redigee']);

        return $this->retour($decision, 'success', 'Décision rédigée : elle attend maintenant la validation.');
    }

    /** Le greffier de la chambre donne le numéro quand le juge a terminé sa rédaction (la décision est « rédigée »). Le numéro est définitif : il suit la décision. */
    public function numeroter(int $id, Request $request): RedirectResponse
    {
        Gate::authorize('redaction.numeroter');
        $decision = $this->decision($id, ['modele']);
        if (NumeroDecision::moment() !== 'redaction') {
            return $this->retour($decision, 'error', "Le numéro n'est pas attribué à cette étape : voir Réglages > Décisions.");
        }
        if ($decision->numero !== null) {
            return $this->retour($decision, 'error', 'Cette décision a déjà son numéro : '.$decision->numeroAffichage().'.');
        }
        if (! $decision->estRedigee()) {
            return $this->retour($decision, 'error', 'Le numéro se donne quand le juge a terminé sa rédaction : la décision doit être marquée « rédigée ».');
        }

        DB::transaction(function () use ($decision) {
            $decision->forceFill(['date_decision' => $this->dateDeLaDecision($decision)])->save();   // l'année du numéro vient de cette date
            NumeroDecision::attribuer($decision);
        });
        Journal::audit('decision_numerotee', $decision, $decision->libelle(), ['numero' => null], ['numero' => $decision->numeroAffichage()]);

        return $this->retour($decision, 'success', 'Numéro attribué : '.$decision->numeroAffichage().'. La décision peut maintenant être validée.');
    }

    /** Celui qui valide peut renvoyer une décision rédigée en rédaction (elle redevient modifiable). */
    public function renvoyer(int $id, Request $request): RedirectResponse
    {
        Gate::authorize('redaction.valider');
        $decision = $this->decision($id, ['modele']);
        if (! $decision->estRedigee()) {
            return $this->retour($decision, 'error', 'Seule une décision rédigée peut être renvoyée en rédaction.');
        }

        $decision->forceFill(['statut' => 'brouillon', 'redigee_le' => null, 'redigee_par' => null])->save();
        Journal::audit('decision_renvoyee', $decision, $decision->libelle(), ['statut' => 'redigee'], ['statut' => 'brouillon']);

        return $this->retour($decision, 'success', 'Décision renvoyée en rédaction.');
    }

    /** Valide la décision (brouillon ou rédigée) : tout doit être renseigné ; le texte final est figé. Le numéro est attribué ici si le réglage le demande. */
    public function valider(int $id, Request $request): RedirectResponse
    {
        Gate::authorize('redaction.valider');
        $decision = $this->decision($id, ['modele']);
        abort_if($decision->estValidee(), 403, 'Cette décision est déjà validée.');

        if ($manquantes = $this->manquantes($decision)) {
            return $this->retour($decision, 'error', 'Enregistrez puis complétez : il manque '.implode(', ', $manquantes).'.');
        }

        if (NumeroDecision::moment() === 'redaction' && $decision->numero === null) {
            return $this->retour($decision, 'error', 'Le greffier de la chambre doit d\'abord donner le numéro de la décision.');
        }

        $avant = $decision->statut;
        DB::transaction(function () use ($decision, $request) {
            $decision->forceFill(['date_decision' => $this->dateDeLaDecision($decision)])->save();   // l'année du numéro vient de cette date
            if (NumeroDecision::moment() === 'validation') {
                NumeroDecision::attribuer($decision);
            }
            $rendu = RenduDecision::rendre($decision->version(), $decision->valeursCompletes(), [], $decision->retouches ?? []);
            $decision->forceFill([
                'statut' => 'validee', 'version_numero' => $decision->numeroVersion(), 'rendu' => ['entete' => $rendu['entete'], 'qualite' => $rendu['qualite'], 'factum' => $rendu['factum']],
                'validee_le' => now(), 'validee_par' => $request->user()?->id,
            ])->save();
        });
        Journal::audit('decision_validee', $decision, $decision->libelle(), ['statut' => $avant], ['statut' => 'validee', 'numero' => $decision->numeroAffichage()]);

        return $this->retour($decision, 'success', 'Décision validée : le texte est figé.'.($decision->numero ? ' Numéro '.$decision->numeroAffichage().'.' : ''));
    }

    /** Signe la décision validée à la date indiquée ; le numéro est attribué ici s\'il ne l\'a pas déjà été, et inscrit dans le texte figé. */
    public function signer(int $id, Request $request): RedirectResponse
    {
        Gate::authorize('redaction.signer');
        $decision = $this->decision($id, ['modele']);
        if ($decision->statut !== 'validee') {
            return $this->retour($decision, 'error', $decision->estSignee() ? 'Cette décision est déjà signée.' : 'Seule une décision validée peut être signée.');
        }
        $donnees = $request->validate(['date_signature' => ['required', 'date', 'before_or_equal:today']], ['date_signature.required' => 'Indiquez la date de signature.', 'date_signature.before_or_equal' => 'La date de signature ne peut pas être dans le futur.']);

        DB::transaction(function () use ($decision, $donnees, $request) {
            $numeroDejaDonne = $decision->numero !== null;
            NumeroDecision::attribuer($decision);
            $champs = ['statut' => 'signee', 'date_signature' => $donnees['date_signature'], 'signee_le' => now(), 'signee_par' => $request->user()?->id];
            if (! $numeroDejaDonne) {
                // Le numéro vient d\'être attribué : il est inscrit dans le texte figé (les points de suspension de la validation disparaissent).
                $rendu = RenduDecision::rendre($decision->version(), $decision->valeursCompletes(), [], $decision->retouches ?? []);
                $champs['rendu'] = ['entete' => $rendu['entete'], 'qualite' => $rendu['qualite'], 'factum' => $rendu['factum']];
            }
            $decision->forceFill($champs)->save();
        });
        Journal::audit('decision_signee', $decision, $decision->libelle(), ['statut' => 'validee'], ['statut' => 'signee', 'numero' => $decision->numeroAffichage(), 'date_signature' => $donnees['date_signature']]);

        return $this->retour($decision, 'success', 'Décision signée. Numéro '.$decision->numeroAffichage().'.');
    }

    /** Remet une expédition, une grosse ou une copie : à qui, quand. Une grosse n\'est délivrée qu\'une fois à la même personne. */
    public function delivrer(int $id, Request $request): RedirectResponse
    {
        Gate::authorize('redaction.delivrer');
        $decision = $this->decision($id, ['modele']);
        if (! $decision->estSignee()) {
            return $this->retour($decision, 'error', 'Une décision ne se délivre qu\'une fois signée.');
        }
        $donnees = $request->validate([
            'type' => ['required', Rule::in(array_keys(Decision::TYPES_DELIVRANCE))], 'delivre_a' => ['required', 'string', 'max:160'], 'qualite' => ['nullable', 'string', 'max:60'],
            'delivre_le' => ['required', 'date', 'before_or_equal:today'], 'observation' => ['nullable', 'string', 'max:300'],
        ], ['delivre_a.required' => 'Indiquez à qui la décision est remise.', 'delivre_le.required' => 'Indiquez la date de remise.', 'delivre_le.before_or_equal' => 'La date de remise ne peut pas être dans le futur.', 'type.required' => 'Choisissez le type de remise.']);

        $nom = trim(preg_replace('/\s+/u', ' ', $donnees['delivre_a']));
        if ($donnees['type'] === 'grosse' && $decision->delivrances()->where('type', 'grosse')->get()->contains(fn ($d) => mb_strtolower($d->delivre_a) === mb_strtolower($nom))) {
            return $this->retour($decision, 'error', "Une grosse a déjà été délivrée à $nom : elle ne se délivre qu'une fois à la même personne (une copie ou une expédition reste possible).");
        }

        Delivrance::create([
            'decision_id' => $decision->id, 'type' => $donnees['type'], 'delivre_a' => $nom, 'qualite' => trim((string) ($donnees['qualite'] ?? '')) ?: null, 'delivre_le' => $donnees['delivre_le'],
            'observation' => trim((string) ($donnees['observation'] ?? '')) ?: null, 'delivre_par' => $request->user()?->id,
        ]);
        if ($decision->statut !== 'delivree') {
            $decision->forceFill(['statut' => 'delivree', 'delivree_le' => now()])->save();
        }
        Journal::audit('decision_delivree', $decision, $decision->libelle(), [], ['type' => $donnees['type'], 'delivre_a' => $nom, 'delivre_le' => $donnees['delivre_le']]);

        return $this->retour($decision, 'success', Decision::TYPES_DELIVRANCE[$donnees['type']].' délivrée à '.$nom.'.');
    }

    /** Ce qui manque encore pour que le texte soit complet. @return list<string> */
    private function manquantes(Decision $decision): array
    {
        return RenduDecision::rendre($decision->version(), $decision->valeursCompletes(), [], $decision->retouches ?? [])['manquantes'];
    }

    /** La date de la décision (prononcé) saisie dans le formulaire, si le modèle la demande. */
    private function dateDeLaDecision(Decision $decision): ?string
    {
        $d = $decision->valeurs['date_decision'] ?? null;

        return is_string($d) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) ? $d : null;
    }

    private function retour(Decision $decision, string $type, string $message): RedirectResponse
    {
        return redirect()->route('decisions.redaction.edition', $decision->id)->with($type, $message);
    }

    /** Une décision validée ne se corrige pas : on en crée une nouvelle, en brouillon, avec les mêmes valeurs et la version actuelle du modèle. Une décision signée non plus. */
    public function corriger(int $id, Request $request): RedirectResponse
    {
        Gate::authorize('redaction.creer');
        $source = $this->decision($id, ['modele']);
        if ($source->estSignee()) {
            return $this->retour($source, 'error', 'Une décision signée ne se corrige plus : elle a un numéro et peut avoir été délivrée. Une rectification est une procédure à part.');
        }
        $version = $source->modele->versionCourante();

        $copie = Decision::create([
            'modele_decision_id' => $source->modele_decision_id, 'chambre_id' => $source->chambre_id, 'affaire_id' => $source->affaire_id, 'version_numero' => $version?->numero ?? $source->version_numero,
            'reference' => $source->reference, 'statut' => 'brouillon', 'valeurs' => $source->valeurs, 'retouches' => $source->retouches, 'cree_par' => $request->user()?->id,
        ]);
        Journal::audit('decision_creee', $copie, $copie->libelle(), [], ['corrige_depuis' => $source->id, 'version' => $copie->version_numero]);

        return redirect()->route('decisions.redaction.edition', $copie->id)->with('success', 'Nouvelle décision créée à partir de la décision validée : corrigez-la puis validez-la.');
    }

    public function destroy(int $id): JsonResponse
    {
        Gate::authorize('redaction.annuler');
        $decision = $this->decision($id);

        if ($decision->estValidee()) {
            return response()->json(['status' => false, 'message' => 'Une décision validée ne se supprime pas.']);
        }

        Journal::audit('decision_supprimee', $decision, $decision->libelle(), ['reference' => $decision->reference]);
        $decision->delete();

        return response()->json(['status' => true, 'message' => 'Brouillon supprimé.']);
    }

    /** Page d'impression : la décision sur des pages A4, prête pour « Imprimer » ou « Enregistrer au format PDF » du navigateur. */
    public function imprimer(int $id): View
    {
        Gate::authorize('redaction.voir');
        $decision = $this->decision($id, ['modele']);
        $rendu = $decision->estValidee() && $decision->rendu ? $decision->rendu : $this->rendu($decision, $decision->valeurs ?? []);

        Journal::audit('decision_imprimee', $decision, $decision->libelle(), [], ['statut' => $decision->statut]);

        return view('decisions.redaction.impression', [
            'decision' => $decision,
            'parties' => ['entete' => $rendu['entete'], 'qualite' => $rendu['qualite'], 'factum' => $rendu['factum']],
            'manquantes' => $rendu['manquantes'] ?? [],
            'miseEnPage' => ModeleDecisionController::miseEnPage(),
        ]);
    }
}
