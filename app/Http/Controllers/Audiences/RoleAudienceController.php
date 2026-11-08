<?php

namespace App\Http\Controllers\Audiences;

use App\Http\Controllers\Controller;
use App\Models\Affaire;
use App\Models\AnneeJudiciaire;
use App\Models\Chambre;
use App\Models\Creneau;
use App\Models\Fermeture;
use App\Models\RoleAudience;
use App\Models\RoleLigne;
use App\Models\RoleVersion;
use App\Models\Salle;
use App\Services\Journal;
use App\Support\Audiences\DatesAudience;
use App\Support\PerimetreChambres;
use App\Support\RechercheTexte;
use App\Support\TableauServeur;
use App\Support\Roles\GenerateurRole;
use App\Support\Roles\RoleDocument;
use App\Support\Roles\RoleExport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/*
 | Rôles d'audience (lot J, ROL-01 à 10) : le rôle d'une chambre pour une date se compose d'après les affaires et les rôles précédents,
 | se retouche, se publie (version figée) et s'exporte en Word et en PDF. Droits : roles.voir / creer / modifier / supprimer / imprimer,
 | et le droit spécial roles.publier. Le planning par salle détecte les conflits (même salle, même jour, moins de trois heures d'écart).
 */
class RoleAudienceController extends Controller
{
    private const ENTETE = ['heure', 'salle_id', 'avec_avocats', 'fait_a', 'date_signature', 'signataire'];

    // ---------------------------------------------------------------------------------------------------- liste

    public function index(Request $request): View
    {
        Gate::authorize('roles.voir');
        $user = $request->user();
        $statut = in_array($request->query('statut'), ['brouillon', 'publie'], true) ? $request->query('statut') : null;
        $chambre = ctype_digit((string) $request->query('chambre')) ? (int) $request->query('chambre') : null;

        $aujourdhui = today();
        $base = RoleAudience::visiblesPour($user);
        $compte = [
            'avenir' => (clone $base)->whereDate('date_audience', '>=', $aujourdhui)->count(),
            'brouillons' => (clone $base)->where('statut', 'brouillon')->count(),
            'publies' => (clone $base)->where('statut', 'publie')->count(),
            'conflits' => (clone $base)->whereDate('date_audience', '>=', $aujourdhui)->whereNotNull('salle_id')->get()->filter(fn ($r) => $r->conflits()->isNotEmpty())->count(),
        ];

        return view('audiences.roles.index', [
            'compte' => $compte,
            'chambres' => $this->chambres($user),
            'salles' => $this->salles(),
            'peutPenal' => $request->user()->can('roles.penal'),
            'statut' => $statut,
            'chambreFiltre' => $chambre,
            'aPreparer' => Gate::allows('roles.creer') ? $this->aPreparer($user, $aujourdhui, 14)->take(8) : collect(),
            'creneaux' => $this->creneauxParChambre($user),
        ]);
    }

    /** Les lignes de la page, une page à la fois (filtres d'état et de chambre, tri et recherche dans la base). */
    public function donnees(Request $request): JsonResponse
    {
        Gate::authorize('roles.voir');
        $statut = in_array($request->query('statut'), ['brouillon', 'publie'], true) ? $request->query('statut') : null;
        $chambre = ctype_digit((string) $request->query('chambre')) ? (int) $request->query('chambre') : null;
        $base = RoleAudience::visiblesPour($request->user())->with('chambre', 'salle')->withCount('lignes')
            ->when($statut, fn ($q) => $q->where('statut', $statut))->when($chambre, fn ($q) => $q->where('chambre_id', $chambre))
            ->orderByDesc('date_audience')->orderBy('chambre_id');

        return TableauServeur::repondre(
            $request, $base,
            [0 => ['date_audience', 'chambre_id'], 4 => ['statut']],
            fn ($q, $mots) => RechercheTexte::appliquer($q, $mots, ['date_audience', 'intitule'], 'chambre', ['libelle', 'intitule']),
            fn (RoleAudience $r) => view('audiences.roles._ligne', ['r' => $r])->render(),
        );
    }

    /** Création du rôle : la chambre et la date suffisent, l'heure et la salle viennent du calendrier de la chambre. Le rôle est composé aussitôt. */
    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('roles.creer');
        $donnees = $request->validate([
            'chambre_id' => ['required', 'integer', Rule::exists('chambres', 'id')],
            'date_audience' => ['required', 'date', 'after_or_equal:2020-01-01', 'before_or_equal:'.today()->addYear()->toDateString()],
            'heure' => ['nullable', 'date_format:H:i'],
            'salle_id' => ['nullable', 'integer', Rule::exists('salles', 'id')],
            'type' => ['nullable', Rule::in(array_keys(RoleAudience::TYPES))],
            'intitule' => ['nullable', 'string', 'max:160'],
            'additif' => ['nullable', 'boolean'],
        ], ['chambre_id.required' => 'Choisissez la chambre.', 'date_audience.required' => "Indiquez la date de l'audience.", 'date_audience.before_or_equal' => "La date de l'audience est trop éloignée."]);
        abort_unless(PerimetreChambres::voit($request->user(), (int) $donnees['chambre_id']), 403);
        $type = $donnees['type'] ?? 'civil';
        // Le rôle pénal est établi et signé par le parquet : droit spécial.
        abort_if($type === 'penal' && ! $request->user()->can('roles.penal'), 403, 'Seul le parquet établit les rôles pénaux.');

        $chambre = Chambre::findOrFail($donnees['chambre_id']);
        $date = Carbon::parse($donnees['date_audience']);
        if (RoleAudience::where('chambre_id', $chambre->id)->whereDate('date_audience', $date->toDateString())->exists()) {
            throw ValidationException::withMessages(['date_audience' => 'Un rôle existe déjà pour cette chambre à cette date.']);
        }
        $creneau = $this->creneauPour($chambre, $date);
        $role = new RoleAudience([
            'chambre_id' => $chambre->id, 'date_audience' => $date->toDateString(),
            'heure' => ($donnees['heure'] ?? null) ?: $creneau?->heure, 'salle_id' => ($donnees['salle_id'] ?? null) ?: $creneau?->salle_id,
            'fait_a' => $type === 'penal' ? 'Parquet de '.ucfirst(mb_strtolower((string) config('tpi.ville', 'Bingerville'))) : ucfirst(mb_strtolower((string) config('tpi.ville', 'Bingerville'))),
            'signataire' => $type === 'penal' ? 'LE PROCUREUR DE LA REPUBLIQUE' : 'Le Président', 'date_signature' => today()->toDateString(),
            'type' => $type, 'intitule' => trim((string) ($donnees['intitule'] ?? '')) ?: null, 'additif' => (bool) ($donnees['additif'] ?? false),
            'cree_par' => $request->user()->id,
        ]);
        $this->refuserConflit($role);
        $role->save();
        $ajoutees = GenerateurRole::completer($role);
        Journal::audit('role_cree', $role, $role->chambre->libelle.' – '.$date->format('d/m/Y'), [], ['affaires_proposees' => $ajoutees]);

        return redirect()->route('audiences.roles.show', $role->id)->with('success', 'Rôle créé : '.$ajoutees.' affaire'.($ajoutees > 1 ? 's' : '').' reprise'.($ajoutees > 1 ? 's' : '').' automatiquement.');
    }

    // ---------------------------------------------------------------------------------------------------- éditeur

    public function show(int $id, Request $request): View
    {
        Gate::authorize('roles.voir');
        $role = $this->role($id, $request)->load('chambre.section', 'salle', 'lignes.affaire', 'versions.auteur');
        $propositions = $role->estPublie() ? ['deliberes' => collect(), 'anciennes' => collect(), 'nouvelles' => collect()] : GenerateurRole::proposer($role);

        return view('audiences.roles.show', [
            'role' => $role,
            'doc' => RoleDocument::depuis($role),
            'propositions' => array_map(fn ($c) => $c->count(), $propositions),
            'conflits' => $role->conflits(),
            'avertissements' => $role->agentsDejaPris(),
            'fermeture' => Fermeture::couvre($role->date_audience),
            'jourHabituel' => in_array($role->date_audience->isoWeekday(), \App\Support\CalendrierEnrolement::joursDAudience($role->chambre), true),
            'salles' => $this->salles(),
            'avecPropositions' => array_sum(array_map(fn ($c) => $c->count(), $propositions)),
        ]);
    }

    public function entete(int $id, Request $request): RedirectResponse
    {
        Gate::authorize('roles.modifier');
        $role = $this->brouillon($id, $request);
        $donnees = $request->validate([
            'date_audience' => ['required', 'date', 'after_or_equal:2020-01-01'],
            'heure' => ['nullable', 'date_format:H:i'],
            'salle_id' => ['nullable', 'integer', Rule::exists('salles', 'id')],
            'fait_a' => ['required', 'string', 'max:80'],
            'date_signature' => ['nullable', 'date'],
            'signataire' => ['required', 'string', 'max:80'],
            'intitule' => ['nullable', 'string', 'max:160'],
            'president' => ['nullable', 'string', 'max:200'], 'assesseurs' => ['nullable', 'string', 'max:300'],
            'procureur' => ['nullable', 'string', 'max:200'], 'greffier' => ['nullable', 'string', 'max:200'],
        ], ['date_audience.required' => "Indiquez la date de l'audience.", 'fait_a.required' => 'Indiquez le lieu.', 'signataire.required' => 'Indiquez qui signe le rôle.']);
        $avant = $role->only(['date_audience', 'heure', 'salle_id', 'avec_avocats', 'fait_a', 'date_signature', 'signataire', 'intitule', 'additif', 'president', 'assesseurs', 'procureur', 'greffier']);

        $date = Carbon::parse($donnees['date_audience']);
        if (RoleAudience::where('chambre_id', $role->chambre_id)->where('id', '!=', $role->id)->whereDate('date_audience', $date->toDateString())->exists()) {
            throw ValidationException::withMessages(['date_audience' => 'Un rôle existe déjà pour cette chambre à cette date.']);
        }
        $role->fill([
            'date_audience' => $date->toDateString(), 'heure' => $donnees['heure'] ?? null, 'salle_id' => $donnees['salle_id'] ?? null,
            'avec_avocats' => $request->boolean('avec_avocats'), 'fait_a' => trim($donnees['fait_a']), 'date_signature' => $donnees['date_signature'] ?? null, 'signataire' => trim($donnees['signataire']),
            'intitule' => trim((string) ($donnees['intitule'] ?? '')) ?: null, 'additif' => $request->boolean('additif'),
            'president' => trim((string) ($donnees['president'] ?? '')) ?: null, 'assesseurs' => trim((string) ($donnees['assesseurs'] ?? '')) ?: null,
            'procureur' => trim((string) ($donnees['procureur'] ?? '')) ?: null, 'greffier' => trim((string) ($donnees['greffier'] ?? '')) ?: null,
        ]);
        $this->refuserConflit($role);
        $role->save();
        Journal::audit('role_modifie', $role, $role->chambre->libelle.' – '.$role->date_audience->format('d/m/Y'), $avant, $role->only(array_keys($avant)));

        return redirect()->route('audiences.roles.show', $role->id)->with('success', "L'en-tête du rôle est enregistré.");
    }

    /**
     * Aide à la saisie d'une date de renvoi (jamais bloquante) : la date tombe-t-elle un jour fermé ou un jour où la chambre ne siège pas d'habitude ?
     * Propose la prochaine audience habituelle de la chambre, hors jours fermés.
     */
    public function renvoi(int $id, Request $request): JsonResponse
    {
        $role = $this->role($id, $request)->load('chambre');
        $date = $request->query('date');
        abort_unless(is_string($date) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) && strtotime($date), 422);
        $jour = Carbon::parse($date);

        return response()->json(DatesAudience::analyser($role->chambre, $jour, $role->date_audience));
    }

    /** Ajoute les affaires qui doivent figurer au rôle et n'y sont pas (nouvelles affaires, renvois arrivés à échéance, délibérés). */
    public function completer(int $id, Request $request): RedirectResponse
    {
        Gate::authorize('roles.modifier');
        $role = $this->brouillon($id, $request);
        $ajoutees = GenerateurRole::completer($role);
        Journal::audit('role_complete', $role, $role->chambre->libelle.' – '.$role->date_audience->format('d/m/Y'), [], ['ajoutees' => $ajoutees]);

        return redirect()->route('audiences.roles.show', $role->id)->with($ajoutees ? 'success' : 'info', $ajoutees ? $ajoutees.' affaire'.($ajoutees > 1 ? 's' : '').' ajoutée'.($ajoutees > 1 ? 's' : '').' au rôle.' : 'Aucune affaire à ajouter : le rôle est complet.');
    }

    // ---------------------------------------------------------------------------------------------------- lignes

    public function ligneAjouter(int $id, Request $request): RedirectResponse
    {
        Gate::authorize('roles.modifier');
        $role = $this->brouillon($id, $request);
        $donnees = $request->validate([
            'rubrique' => ['required', Rule::in(array_keys(RoleLigne::RUBRIQUES))],
            'numero' => ['nullable', 'string', 'max:40'],
        ]);
        $ordre = (int) $role->lignes()->where('rubrique', $donnees['rubrique'])->max('ordre') + 1;

        if (trim((string) ($donnees['numero'] ?? '')) === '') {
            $role->lignes()->create(['rubrique' => $donnees['rubrique'], 'ordre' => $ordre, 'numero' => '', 'origine' => 'libre']);
            Journal::audit('role_ligne_ajoutee', $role, 'ligne libre', [], ['rubrique' => $donnees['rubrique']]);

            return $this->retour($role, 'Une ligne vide est ajoutée : complétez-la avec le crayon.');
        }
        if (! preg_match('/^\s*(?:(RG|RP|RI|RE)\s*)?(\d{1,6})\s*\/\s*(\d{2}|\d{4})\s*$/i', $donnees['numero'], $m)) {
            throw ValidationException::withMessages(['numero' => 'Écrivez le numéro comme dans le registre : 1391/25 (ou RP 1334/26).']);
        }
        $annee = (int) (strlen($m[3]) === 2 ? 2000 + (int) $m[3] : $m[3]);
        $affaire = Affaire::visiblesPour($request->user())->where('statut', 'enrolee')->where('serie', strtoupper($m[1] ?: 'RG'))->where('numero', (int) $m[2])->where('annee', $annee)->first();
        if (! $affaire) {
            throw ValidationException::withMessages(['numero' => "Aucune affaire enregistrée n'a ce numéro (ou elle est hors de votre périmètre)."]);
        }
        $cible = $affaire->jointe_a_id ? Affaire::find($affaire->jointe_a_id) : $affaire;
        if ($role->lignes()->where('affaire_id', $cible->id)->exists()) {
            throw ValidationException::withMessages(['numero' => 'Cette affaire figure déjà sur ce rôle.']);
        }
        $role->lignes()->create(GenerateurRole::attributs($cible, $donnees['rubrique'], $ordre, 'ajout', $role->type));
        Journal::audit('role_ligne_ajoutee', $role, $cible->numeroAffichage(), [], ['rubrique' => $donnees['rubrique']]);

        return $this->retour($role, 'Affaire '.$cible->numeroAffichage().' ajoutée au rôle.');
    }

    public function ligneModifier(int $id, int $ligne, Request $request): RedirectResponse
    {
        Gate::authorize('roles.modifier');
        $role = $this->role($id, $request);
        abort_if($role->estPenal() && ! $request->user()->can('roles.penal'), 403, 'Seul le parquet modifie un rôle pénal.');
        $l = $role->lignes()->findOrFail($ligne);

        // Un rôle publié ne change plus de texte : seule la suite donnée à l'audience s'y note (elle alimentera le rôle suivant).
        $suite = $request->validate([
            'issue' => ['nullable', Rule::in(array_keys(RoleLigne::ISSUES))],
            'renvoi_au' => ['nullable', 'date', 'required_if:issue,renvoi', 'after:'.$role->date_audience->toDateString()],
        ], ['renvoi_au.required_if' => 'Indiquez la date de renvoi.', 'renvoi_au.after' => "La date de renvoi doit suivre celle de l'audience."]);
        $avant = $l->only(['issue', 'renvoi_au']);
        $attributs = ['issue' => $suite['issue'] ?? null, 'renvoi_au' => ($suite['issue'] ?? null) === 'renvoi' ? ($suite['renvoi_au'] ?? null) : null];

        if (! $role->estPublie()) {
            $texte = $request->validate([
                'rubrique' => ['required', Rule::in(array_keys(RoleLigne::RUBRIQUES))],
                'numero' => ['nullable', 'string', 'max:120'], 'demandeurs' => ['nullable', 'string', 'max:2000'], 'defendeurs' => ['nullable', 'string', 'max:2000'],
                'avocats_demandeurs' => ['nullable', 'string', 'max:1000'], 'avocats_defendeurs' => ['nullable', 'string', 'max:1000'],
                'objet' => ['nullable', 'string', 'max:1500'], 'observations' => ['nullable', 'string', 'max:1000'],
            ]);
            $attributs += array_map(fn ($v) => $v === null ? null : trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $v)), $texte);
            if ($texte['rubrique'] !== $l->rubrique) {
                $attributs['ordre'] = (int) $role->lignes()->where('rubrique', $texte['rubrique'])->max('ordre') + 1;
            }
            $attributs['numero'] = $attributs['numero'] ?? '';
        }
        $l->update($attributs);
        Journal::audit('role_ligne_modifiee', $role, $l->numero ?: 'ligne libre', $avant, $l->only(['issue', 'renvoi_au', 'rubrique']));

        return $this->retour($role, 'Ligne enregistrée.', $l->id);
    }

    public function ligneDeplacer(int $id, int $ligne, Request $request): RedirectResponse
    {
        Gate::authorize('roles.modifier');
        $role = $this->brouillon($id, $request);
        $l = $role->lignes()->findOrFail($ligne);
        $sens = $request->input('sens') === 'haut' ? 'haut' : 'bas';
        $voisines = $role->lignes()->where('rubrique', $l->rubrique)->get()->values();
        $i = $voisines->search(fn ($x) => $x->id === $l->id);
        $j = $sens === 'haut' ? $i - 1 : $i + 1;
        if ($j >= 0 && $j < $voisines->count()) {
            $ordres = $voisines->map(fn ($x) => $x->id)->all();
            [$ordres[$i], $ordres[$j]] = [$ordres[$j], $ordres[$i]];
            DB::transaction(function () use ($ordres) {
                foreach ($ordres as $rang => $idLigne) {
                    RoleLigne::whereKey($idLigne)->update(['ordre' => $rang + 1]);
                }
            });
        }

        return $this->retour($role, null, $l->id);
    }

    public function ligneRetirer(int $id, int $ligne, Request $request): RedirectResponse
    {
        Gate::authorize('roles.modifier');
        $role = $this->brouillon($id, $request);
        $l = $role->lignes()->findOrFail($ligne);
        Journal::audit('role_ligne_retiree', $role, $l->numero ?: 'ligne libre', ['rubrique' => $l->rubrique], []);
        $l->delete();

        return $this->retour($role, 'Ligne retirée du rôle.');
    }

    // ---------------------------------------------------------------------------------------------------- publication

    /** Publie le rôle : sa version est figée (ce que le greffe diffuse) ; on peut ensuite le rouvrir pour une nouvelle version. */
    public function publier(int $id, Request $request): RedirectResponse
    {
        Gate::authorize('roles.publier');
        $role = $this->brouillon($id, $request);
        if (! $role->lignes()->exists()) {
            throw ValidationException::withMessages(['publier' => 'Le rôle est vide : ajoutez au moins une affaire avant de le publier.']);
        }
        $this->refuserConflit($role);
        DB::transaction(function () use ($role, $request) {
            $role->forceFill(['statut' => 'publie', 'publie_par' => $request->user()->id, 'publie_le' => now()])->save();
            RoleVersion::updateOrCreate(['role_id' => $role->id, 'version' => $role->version], [
                'contenu' => json_encode(RoleDocument::depuis($role->fresh()), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'motif' => $role->version > 1 ? $request->input('motif') : null, 'par' => $request->user()->id,
            ]);
        });
        Journal::audit('role_publie', $role, $role->chambre->libelle.' – '.$role->date_audience->format('d/m/Y'), [], ['version' => $role->version]);

        return redirect()->route('audiences.roles.show', $role->id)->with('success', 'Rôle publié (version '.$role->version.').');
    }

    /** Rouvre un rôle publié pour le corriger : la publication suivante portera le numéro de version suivant. */
    public function rouvrir(int $id, Request $request): RedirectResponse
    {
        Gate::authorize('roles.modifier');
        Gate::authorize('roles.publier');
        $role = $this->role($id, $request);
        abort_unless($role->estPublie(), 403, "Ce rôle n'est pas publié.");
        $donnees = $request->validate(['motif' => ['required', 'string', 'min:5', 'max:300']], ['motif.required' => 'Indiquez pourquoi le rôle est rouvert.', 'motif.min' => 'Le motif est trop court.']);
        $role->forceFill(['statut' => 'brouillon', 'version' => $role->version + 1])->save();
        Journal::audit('role_rouvert', $role, $role->chambre->libelle.' – '.$role->date_audience->format('d/m/Y'), ['version' => $role->version - 1], ['version' => $role->version, 'motif' => $donnees['motif']]);

        return redirect()->route('audiences.roles.show', $role->id)->with('success', 'Rôle rouvert : version '.$role->version.' en préparation.');
    }

    /** Supprime un rôle qui n'a jamais été publié. */
    public function supprimer(int $id, Request $request): RedirectResponse
    {
        Gate::authorize('roles.supprimer');
        $role = $this->brouillon($id, $request);
        abort_if($role->versions()->exists(), 403, 'Un rôle déjà publié ne se supprime pas.');
        $nom = $role->chambre->libelle.' – '.$role->date_audience->format('d/m/Y');
        $role->delete();
        Journal::audit('role_supprime', null, $nom);

        return redirect()->route('audiences.roles')->with('success', 'Rôle supprimé.');
    }

    // ---------------------------------------------------------------------------------------------------- exports

    /** Export Word ou PDF : le rôle en cours, ou une version publiée. */
    public function exporter(int $id, Request $request): Response
    {
        Gate::authorize('roles.imprimer');
        $format = (string) $request->route('format');
        $version = $request->route('version') !== null ? (int) $request->route('version') : null;
        abort_unless(in_array($format, ['pdf', 'docx'], true), 404);
        $role = $this->role($id, $request)->load('chambre', 'salle', 'lignes');

        if ($version !== null) {
            $doc = $role->versions()->where('version', $version)->firstOrFail()->contenu();
        } elseif ($role->estPublie()) {
            $doc = $role->versions()->where('version', $role->version)->first()?->contenu() ?: RoleDocument::depuis($role);
        } else {
            $doc = RoleDocument::depuis($role);
        }
        $doc['brouillon'] = $version === null && ! $role->estPublie();
        Journal::audit('role_exporte', $role, $role->chambre->libelle.' – '.$role->date_audience->format('d/m/Y'), [], ['format' => $format, 'version' => $version ?? $role->version]);

        $nom = RoleDocument::nomFichier($role, $version).'.'.$format;
        if ($format === 'pdf') {
            // Aperçu dans la fenêtre du rôle : le PDF s'affiche au lieu d'être téléchargé.
            $disposition = $request->boolean('apercu') ? 'inline' : 'attachment';

            return response(RoleExport::pdf($doc), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => $disposition.'; filename="'.$nom.'"', 'X-Content-Type-Options' => 'nosniff']);
        }

        return response(RoleExport::docx($doc), 200, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'Content-Disposition' => 'attachment; filename="'.$nom.'"', 'X-Content-Type-Options' => 'nosniff']);
    }

    // ---------------------------------------------------------------------------------------------------- planning

    /** Planning de la semaine par salle : les rôles, les conflits, les jours fermés et les audiences habituelles sans rôle. */
    public function planning(Request $request): View
    {
        Gate::authorize('roles.voir');
        $user = $request->user();
        $depart = $request->query('du');
        $lundi = ($depart && preg_match('/^\d{4}-\d{2}-\d{2}$/', $depart) && strtotime($depart) ? Carbon::parse($depart) : today())->copy()->startOfWeek();
        $jours = collect(range(0, 5))->map(fn ($i) => $lundi->copy()->addDays($i));

        $roles = RoleAudience::visiblesPour($user)->with('chambre', 'salle')->withCount('lignes')
            ->whereBetween('date_audience', [$lundi->toDateString(), $lundi->copy()->addDays(5)->toDateString()])->orderBy('heure')->get();
        $enConflit = $roles->filter(fn ($r) => $r->conflits()->isNotEmpty())->pluck('id')->all();
        // Avertissements (non bloquants) : magistrat ou greffier déjà pris par un autre rôle à la même heure. id du rôle => texte.
        $avertis = $roles->mapWithKeys(fn ($r) => [$r->id => $r->agentsDejaPris()])->filter(fn ($l) => $l->isNotEmpty())
            ->map(fn ($l) => $l->map(fn ($x) => $x['agent']->nomComplet().' ('.$x['role'].') siège aussi à la '.$x['autre']->chambre->libelle.($x['autre']->heureCourte() ? ' à '.$x['autre']->heureCourte() : ''))->unique()->implode(' ; '))->all();
        $prevues = Gate::allows('roles.creer') ? $this->aPreparer($user, $lundi, 6) : collect();

        $salles = $this->salles();
        $lignes = $salles->map(fn ($s) => ['salle' => $s, 'id' => $s->id]);
        if ($roles->whereNull('salle_id')->isNotEmpty() || $prevues->whereNull('salle_id')->isNotEmpty()) {
            $lignes->push(['salle' => null, 'id' => null]);
        }

        $donnees = [
            'lundi' => $lundi, 'jours' => $jours, 'roles' => $roles, 'prevues' => $prevues, 'lignes' => $lignes, 'enConflit' => $enConflit, 'avertis' => $avertis,
            'fermetures' => $jours->mapWithKeys(fn ($j) => [$j->toDateString() => Fermeture::couvre($j)]),
            'precedent' => $lundi->copy()->subWeek()->toDateString(), 'suivant' => $lundi->copy()->addWeek()->toDateString(),
        ];

        // Changement de semaine sans recharger la page : seule la zone du planning est renvoyée.
        if ($request->ajax()) {
            return view('audiences.roles.planning_semaine', $donnees);
        }

        return view('audiences.roles.planning', $donnees + ['chambres' => $this->chambres($user), 'salles' => $salles, 'creneaux' => $this->creneauxParChambre($user), 'peutPenal' => $user->can('roles.penal')]);
    }

    // ---------------------------------------------------------------------------------------------------- aides

    private function role(int $id, Request $request): RoleAudience
    {
        return RoleAudience::visiblesPour($request->user())->findOrFail($id);
    }

    /** Le rôle, à condition qu'il soit encore en préparation. */
    private function brouillon(int $id, Request $request): RoleAudience
    {
        $role = $this->role($id, $request);
        abort_if($role->estPublie(), 403, 'Ce rôle est publié : rouvrez-le pour le modifier.');
        abort_if($role->estPenal() && ! $request->user()->can('roles.penal'), 403, 'Seul le parquet modifie un rôle pénal.');

        return $role;
    }

    private function retour(RoleAudience $role, ?string $message, ?int $ligne = null): RedirectResponse
    {
        $r = redirect()->to(route('audiences.roles.show', $role->id).($ligne ? '#ligne-'.$ligne : ''));

        return $message ? $r->with('success', $message) : $r;
    }

    /** Refuse un rôle qui prendrait une salle déjà occupée à la même heure. */
    private function refuserConflit(RoleAudience $role): void
    {
        $autre = $role->conflits()->first();
        if ($autre) {
            $champ = request()->routeIs('audiences.roles.store') ? 'date_audience' : 'salle_id';
            throw ValidationException::withMessages([$champ => 'La salle « '.$role->salle?->nom.' » est déjà prise ce jour-là'.($autre->heureCourte() ? ' à '.$autre->heureCourte() : '').' par la '.$autre->chambre->libelle.'. Changez la salle ou l\'heure (trois heures d\'écart au moins).']);
        }
    }

    /** Les chambres de l'année en cours que l'utilisateur peut voir. */
    private function chambres($user)
    {
        $perimetre = PerimetreChambres::de($user);

        return Chambre::with('section')->whereIn('annee_judiciaire_id', AnneeJudiciaire::where('courante', true)->select('id'))->where('actif', true)
            ->when($perimetre !== null, fn ($q) => $q->whereIn('id', $perimetre ?: [0]))->orderBy('ordre')->get(['id', 'libelle', 'intitule', 'section_id']);
    }

    private function salles()
    {
        return Salle::where('actif', true)->orderByRaw("CASE type WHEN 'audience' THEN 0 ELSE 1 END")->orderBy('nom')->get(['id', 'nom', 'type']);
    }

    /** Le créneau d'audience habituel de la chambre pour ce jour de la semaine (heure et salle). */
    private function creneauPour(Chambre $chambre, Carbon $date): ?Creneau
    {
        return Creneau::whereIn('formation_id', $chambre->formations()->select('id'))->whereRaw("LOWER(type) LIKE 'audience%'")
            ->where('jour', $date->isoWeekday())->where('frequence', 'hebdomadaire')->orderBy('heure')->first();
    }

    /** Créneaux habituels par chambre (jour, heure, salle) : pré-remplissent le formulaire de création. */
    private function creneauxParChambre($user): array
    {
        $resultat = [];
        foreach ($this->chambres($user) as $c) {
            foreach (Creneau::whereIn('formation_id', $c->formations()->select('id'))->whereRaw("LOWER(type) LIKE 'audience%'")->whereNotNull('jour')->where('frequence', 'hebdomadaire')->get(['jour', 'heure', 'salle_id']) as $k) {
                $resultat[$c->id][(int) $k->jour] = ['heure' => $k->heure ? substr((string) $k->heure, 0, 5) : null, 'salle' => $k->salle_id];
            }
        }

        return $resultat;
    }

    /**
     * Les audiences habituelles des chambres (d'après leurs créneaux) pour lesquelles aucun rôle n'existe encore, dans les jours qui viennent.
     *
     * @return \Illuminate\Support\Collection<int, array{chambre: Chambre, date: Carbon, heure: ?string, salle_id: ?int}>
     */
    private function aPreparer($user, Carbon $depuis, int $jours)
    {
        $prevues = collect();
        $existants = RoleAudience::visiblesPour($user)->whereBetween('date_audience', [$depuis->toDateString(), $depuis->copy()->addDays($jours)->toDateString()])
            ->get(['chambre_id', 'date_audience'])->map(fn ($r) => $r->chambre_id.'|'.$r->date_audience->toDateString())->all();
        $parChambre = $this->creneauxParChambre($user);
        foreach ($this->chambres($user) as $c) {
            foreach ($parChambre[$c->id] ?? [] as $jour => $k) {
                for ($i = 0; $i <= $jours; $i++) {
                    $d = $depuis->copy()->addDays($i);
                    if ($d->isoWeekday() !== $jour || Fermeture::couvre($d) || in_array($c->id.'|'.$d->toDateString(), $existants, true)) {
                        continue;
                    }
                    $prevues->push(['chambre' => $c, 'date' => $d, 'heure' => $k['heure'], 'salle_id' => $k['salle']]);
                }
            }
        }

        return $prevues->sortBy(fn ($p) => $p['date']->toDateString().'|'.($p['heure'] ?? '99').'|'.$p['chambre']->id)->values();
    }
}
