<?php

namespace App\Support\Redaction;

use App\Models\Affectation;
use App\Models\Affaire;
use App\Models\Chambre;
use App\Models\Decision;
use App\Models\ModeleDecision;
use App\Models\Requete;
use App\Models\RoleAudience;
use App\Models\RoleLigne;

/**
 * Ce que l'application sait déjà d'une décision : la juridiction, la chambre, la date d'audience et la composition du tribunal
 * (président, assesseurs, greffier de la chambre à cette date). Ces valeurs remplissent le formulaire d'avance ; l'utilisateur
 * ne les corrige que si elles ne conviennent pas. Seules les variables que le modèle utilise sont ensuite conservées.
 */
class Preremplissage
{
    private const ORDINAUX = [1 => 'première', 2 => 'deuxième', 3 => 'troisième', 4 => 'quatrième', 5 => 'cinquième', 6 => 'sixième', 7 => 'septième', 8 => 'huitième', 9 => 'neuvième', 10 => 'dixième'];

    /** Fonction (liste des agents) => [écriture au masculin, au féminin] telle qu'elle s'écrit dans une décision. */
    private const FONCTIONS_TEXTE = [
        'presidente' => ['Président du Tribunal', 'Présidente du Tribunal'], 'vice_president' => ['Vice-Président du Tribunal', 'Vice-Présidente du Tribunal'],
        'juge' => ['Juge', 'Juge'], 'juge_instruction' => ["Juge d'instruction", "Juge d'instruction"], 'juge_enfants' => ['Juge des enfants', 'Juge des enfants'],
        'greffier_en_chef' => ['Greffier en chef', 'Greffière en chef'], 'greffier_en_chef_adjoint' => ['Greffier en chef adjoint', 'Greffière en chef adjointe'],
        'greffier' => ['Greffier', 'Greffière'], 'secretaire' => ['Secrétaire', 'Secrétaire'],
        'assesseur_spjej' => ['Assesseur SPJEJ', 'Assesseur SPJEJ'], 'assesseur_employeur' => ['Assesseur employeur', 'Assesseur employeur'], 'assesseur_travailleur' => ['Assesseur travailleur', 'Assesseur travailleur'],
        'service_social' => ['Service social (SPJEJ)', 'Service social (SPJEJ)'],
    ];

    /** « vice_president » + « f » donne « Vice-Présidente du Tribunal » ; un texte déjà écrit est gardé tel quel. */
    public static function fonctionEnTexte(string $valeur, ?string $genre): string
    {
        $formes = self::FONCTIONS_TEXTE[$valeur] ?? null;

        return $formes ? $formes[$genre === 'f' ? 1 : 0] : $valeur;
    }

    /** Variables que l'application connaît toujours : elles ne sont jamais demandées dans le formulaire. */
    public const AUTOMATIQUES = ['juridiction', 'cour_appel', 'type_decision', 'numero_rg', 'numero_decision'];

    /** Valeurs des variables automatiques : réglages de la juridiction, nom du modèle, numéro tiré de la référence (« RG 017/2026 » donne « 017/2026 »). */
    public static function automatiques(Decision $decision): array
    {
        return array_filter([
            'juridiction' => config('tpi.juridiction'),
            'cour_appel' => config('tpi.cour_appel'),
            'type_decision' => $decision->modele?->nom,
            // Le numéro est attribué par l'application (voir NumeroDecision) ; en attendant, des points de suspension tiennent la place dans le texte.
            'numero_decision' => $decision->numeroAffichage() ?: '……',
            'numero_rg' => $decision->affaire ? preg_replace('/^[A-Z]+-/', '', $decision->affaire->numeroAffichage()) : trim((string) preg_replace('/^\s*R\.?G\.?\s*(?:n°|no|n)?\s*/iu', '', (string) $decision->reference)),
        ], fn ($v) => $v !== null && $v !== '');
    }

    /** @return array<string, mixed> code => valeur, à plat */
    public static function pour(ModeleDecision $modele, ?Chambre $chambre, ?string $dateAudience, ?Affaire $affaire = null, ?RoleAudience $role = null): array
    {
        $v = $affaire ? self::affaire($affaire) + self::requete($affaire) : [];
        if ($dateAudience) {
            $v['date_audience'] = $dateAudience;
        }
        // L'audience où l'affaire a été jugée en sait plus que l'ordonnance : composition réellement tenue, type d'audience, nature de la décision.
        if ($role && $affaire && $dateAudience === $role->date_audience->toDateString()) {
            $v += self::audience($role, $role->lignes()->where('affaire_id', $affaire->id)->orderByDesc('id')->first());
        }
        if ($chambre) {
            $v += self::chambre($chambre) + self::composition($chambre, $dateAudience);
        }

        return $v;
    }

    /** Matières proposées dans le formulaire, selon les mots du référentiel des matières. */
    private const MATIERES = [
        'commerc' => 'commerciale', 'social' => 'sociale', 'travail' => 'sociale', 'état des personnes' => "civile d'état des personnes", 'etat des personnes' => "civile d'état des personnes",
        'administrati' => 'administrative et fiscale', 'fiscal' => 'administrative et fiscale', 'correctionn' => 'correctionnelle', 'délit' => 'correctionnelle', 'crimin' => 'criminelle',
        'enfance' => "de l'enfance délinquante", 'civil' => 'civile',
    ];

    /** La matière de l'affaire, écrite comme dans la liste du formulaire (ou null si elle n'y figure pas). */
    private static function matiere(Affaire $affaire): ?string
    {
        $libelle = mb_strtolower((string) $affaire->matiere?->libelle);
        foreach (self::MATIERES as $mot => $choix) {
            if ($libelle !== '' && str_contains($libelle, $mot)) {
                return $choix;
            }
        }

        return null;
    }

    /** Ce que la requête d'hérédité de l'affaire a déjà saisi : date de la requête, défunt, date et lieu du décès. */
    private static function requete(Affaire $affaire): array
    {
        $v = [];
        if ($matiere = self::matiere($affaire)) {
            $v['matiere'] = $matiere;
        }
        $r = Requete::where('affaire_id', $affaire->id)->orderBy('id')->first();
        if (! $r) {
            return $v;
        }
        $v['date_requete'] = $r->date_depot->toDateString();
        if ($r->type === 'heredite') {
            $v['matiere'] = "civile d'état des personnes";
            foreach (['defunt.nom' => $r->defunt_nom, 'date_deces' => $r->defunt_date_deces?->toDateString(), 'lieu_deces' => $r->defunt_lieu_deces] as $code => $valeur) {
                if ($valeur) {
                    $v[$code] = $valeur;
                }
            }
        }

        return $v;
    }

    /**
     * Ce que l'audience apprend : type d'audience, date de la décision (le jour de l'audience quand l'affaire y a été jugée),
     * nature de la décision d'après la présence des parties notée sur la feuille, et composition réellement tenue.
     */
    private static function audience(RoleAudience $role, ?RoleLigne $ligne): array
    {
        $intitule = mb_strtolower((string) $role->intitule);
        $v = ['type_audience' => str_contains($intitule, 'extraordinaire') ? 'extraordinaire' : (str_contains($intitule, 'foraine') ? 'foraine' : 'ordinaire')];
        if ($ligne?->issue === 'juge') {
            $v['date_decision'] = $role->date_audience->toDateString();
        }
        $nature = match ($ligne?->presence) {
            'toutes' => 'contradictoire', 'demandeur' => 'par défaut', default => null,
        };
        if ($nature) {
            $v['nature_decision'] = $nature;
        }

        return $v + self::compositionTenue($role);
    }

    /**
     * La composition notée sur la feuille d'audience : les absents ne figurent pas, un remplaçant prend la place de l'absent.
     * Rien si la feuille n'a pas de composition enregistrée (on prend alors celle de l'ordonnance).
     */
    private static function compositionTenue(RoleAudience $role): array
    {
        if (! $role->composition_tenue) {
            return [];
        }
        $sexes = \App\Models\Agent::whereIn('id', collect($role->composition_tenue)->pluck('agent_id')->filter())->pluck('sexe', 'id');
        $fonctions = \App\Models\Agent::whereIn('id', collect($role->composition_tenue)->pluck('agent_id')->filter())->pluck('fonction', 'id');

        $presents = [];
        foreach ($role->composition_tenue as $m) {
            $nom = ($m['present'] ?? true) ? ($m['nom'] ?? '') : trim((string) ($m['remplacant'] ?? ''));
            if ($nom === '') {
                continue;
            }
            $remplace = ! ($m['present'] ?? true);
            $presents[] = ['nom' => $nom, 'role' => (string) ($m['role'] ?? ''), 'genre' => $remplace ? null : (isset($sexes[$m['agent_id'] ?? 0]) ? strtolower($sexes[$m['agent_id']]) : null), 'fonction' => $remplace ? null : ($fonctions[$m['agent_id'] ?? 0] ?? null)];
        }

        $v = [];
        $president = collect($presents)->firstWhere('role', 'Président');
        $greffier = collect($presents)->firstWhere('role', 'Greffier');
        foreach (['president' => $president, 'greffier' => $greffier] as $code => $p) {
            if (! $p) {
                continue;
            }
            $v[$code.'.nom'] = $p['nom'];
            if ($p['genre']) {
                $v[$code.'.genre'] = $p['genre'];
            }
        }
        if ($president && $president['fonction']) {
            $v['president.fonction'] = $president['fonction'];
        }
        $assesseurs = collect($presents)->reject(fn ($p) => in_array($p['role'], ['Président', 'Greffier'], true))
            ->map(fn ($p) => array_filter(['nom' => $p['nom'], 'genre' => $p['genre']]))->values()->all();
        if ($assesseurs) {
            $v['assesseurs'] = $assesseurs;
        }

        return $v;
    }

    /** Ce que l'enrôlement a déjà saisi : date du RG et premier demandeur. */
    private static function affaire(Affaire $affaire): array
    {
        $v = ['date_rg' => $affaire->date_rg->toDateString()];
        $d = $affaire->parties->firstWhere('role', 'demandeur');
        if (! $d) {
            return $v;
        }

        if ($d->estPersonnePhysique()) {
            $v['demandeur.nom'] = trim(mb_strtoupper((string) $d->nom).' '.$d->prenoms);
            $v['demandeur.genre'] = $d->nature === 'femme' ? 'f' : 'm';
            if ($d->naissance_mode === 'date' && $d->naissance_date) {
                $v['demandeur.naissance'] = ['mode' => 'date', 'date' => $d->naissance_date->toDateString()];
            } elseif ($d->naissance_mode === 'annee' && $d->naissance_annee) {
                $v['demandeur.naissance'] = ['mode' => 'annee', 'annee' => (string) $d->naissance_annee];
            }
            foreach (['lieu_naissance' => 'demandeur.lieu_naissance', 'nationalite' => 'demandeur.nationalite'] as $champ => $code) {
                if ($d->$champ) {
                    $v[$code] = $d->$champ;
                }
            }
        } else {
            $v['demandeur.nom'] = (string) $d->denomination;
        }
        if ($d->domicile) {
            $v['demandeur.domicile'] = $d->domicile;
        }

        return $v;
    }

    /** « 3e chambre civile » s'écrit « 3ème chambre civile » (court) et « troisième chambre civile » (en lettres). */
    private static function chambre(Chambre $chambre): array
    {
        $libelle = trim($chambre->libelle);
        if (preg_match('/^(\d+)\s*(?:e|ère|re|er|ème)\s+(.+)$/iu', $libelle, $m)) {
            $rang = (int) $m[1];

            return ['chambre' => $rang.($rang === 1 ? 'ère ' : 'ème ').$m[2], 'chambre_lettres' => (self::ORDINAUX[$rang] ?? $rang.'ème').' '.$m[2]];
        }

        return ['chambre' => $libelle, 'chambre_lettres' => mb_strtolower($libelle)];
    }

    /** Président, assesseurs et greffier de la chambre à cette date (aujourd'hui sans date), d'après l'ordonnance. */
    public static function composition(Chambre $chambre, ?string $date): array
    {
        $jour = $date ?: now()->toDateString();
        $affectations = Affectation::with('agent')
            ->whereIn('formation_id', $chambre->formations()->where('actif', true)->pluck('id'))
            ->where(fn ($q) => $q->whereNull('debut')->orWhereDate('debut', '<=', $jour))
            ->where(fn ($q) => $q->whereNull('fin')->orWhereDate('fin', '>=', $jour))
            ->where('suppleant', false)
            ->get()
            ->filter(fn (Affectation $a) => $a->agent && ! $a->agent->trashed());

        $v = [];
        $aPersonne = function (string $code, ?Affectation $a) use (&$v) {
            if (! $a) {
                return;
            }
            $v[$code.'.nom'] = $a->agent->nomComplet();
            if ($a->agent->sexe) {
                $v[$code.'.genre'] = strtolower($a->agent->sexe);
            }
        };

        $president = $affectations->firstWhere('role', 'president');
        $aPersonne('president', $president);
        if ($president) {
            $v['president.fonction'] = $president->agent->fonction;   // la fonction de la liste des agents ; son écriture est accordée au rendu
        }
        $assesseurs = [];
        foreach ($affectations->filter(fn ($a) => ! in_array($a->role, ['president', 'greffier'], true))->values() as $a) {
            $assesseurs[] = ['nom' => $a->agent->nomComplet(), 'genre' => $a->agent->sexe ? strtolower($a->agent->sexe) : ''];
        }
        if ($assesseurs) {
            $v['assesseurs'] = $assesseurs;
        }
        $aPersonne('greffier', $affectations->firstWhere('role', 'greffier'));

        return $v;
    }
}
