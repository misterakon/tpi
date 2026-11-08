<?php

namespace App\Support\Statistiques;

use App\Models\DecisionPenale;
use App\Models\MiseEnEtat;
use App\Models\Partie;
use App\Models\ResultatAffaire;
use App\Models\User;
use Illuminate\Support\Collection;

/*
 | Les registres du tribunal, un par classeur du dossier TPI : civil (présidentielle, référés, matrimoniale, état des personnes, civile diverse), tutelle,
 | hérédité, mise en état et pénal (flagrants délits, délits divers, délits économiques, circulation, simple police, tribunal pour enfants).
 | Chaque affaire va dans UN registre, d'après sa chambre, sa nature et son objet. Les colonnes suivent celles des classeurs, lettre par lettre ;
 | les colonnes calculées (durées, mois, années, « reçu avant 2020 ») sont celles des classeurs eux-mêmes (voir ModeleTribunal).
 */
class RegistresTribunal
{
    public const REGISTRES = [
        'civil_presidentielle' => ['Audience civile présidentielle', 'civil', 'Affaires de la juridiction présidentielle'],
        'refere_voies_execution' => ['Référé et voies d\'exécution', 'civil', 'Juge de l\'exécution et voies d\'exécution'],
        'refere_expulsion' => ['Référé-expulsion', 'civil', 'Expulsions en référé'],
        'refere_ordinaire' => ['Référé ordinaire', 'civil', 'Autres référés'],
        'matrimoniale' => ['Affaires matrimoniales', 'civil', 'Divorce, séparation de corps, adoption'],
        'etat_des_personnes' => ['État des personnes', 'civil', 'Naissance, décès, nom, prénom, identité, paternité'],
        'heredite' => ['Hérédité', 'tutelle', 'Qualité d\'héritiers'],
        'tutelle' => ['Tutelle', 'tutelle', 'Tutelle, garde juridique, autorité parentale'],
        'civile_diverse' => ['Audience civile diverse', 'civile_diverse', 'Civile ordinaire, commerciale, administrative et autres'],
        'mise_en_etat' => ['Mise en état', 'mise_en_etat', 'Affaires confiées à un juge chargé de la mise en état'],
        'penal_flagrants_delits' => ['Flagrants délits', 'penal', 'Tribunal correctionnel : flagrants délits'],
        'penal_delits_divers' => ['Délits divers', 'penal', 'Tribunal correctionnel : délits divers'],
        'penal_delits_economiques' => ['Délits économiques', 'penal', 'Tribunal correctionnel : délits économiques'],
        'penal_police_circulation' => ['Police de la circulation', 'penal', 'Délits de circulation'],
        'penal_simple_police' => ['Simple police', 'penal', 'Tribunal de simple police'],
        'penal_tpe' => ['Tribunal pour enfants', 'penal', 'Affaires jugées par le tribunal pour enfants'],
        'instruction_ji1' => ['Instruction : 1er cabinet', 'instruction', 'Dossiers du 1er cabinet d\'instruction, inculpés et victimes'],
        'instruction_ji2' => ['Instruction : 2e cabinet', 'instruction', 'Dossiers du 2e cabinet d\'instruction, inculpés et victimes'],
        'instruction_je' => ['Juge des enfants', 'instruction', 'Dossiers du cabinet du juge des enfants, mineurs et victimes'],
        'assistance_judiciaire' => ['Assistance judiciaire', 'annexe', 'Demandes examinées par le bureau local, décisions et recours'],
        'activites_jap' => ['Activités du juge de l\'application des peines', 'annexe', 'Compteurs mensuels : visites, placements, semi-liberté, permissions, libération conditionnelle, reclassement'],
        'personnel_magistrat' => ['Personnel magistrat', 'personnel', 'Magistrats du siège en fonction (effectif par sexe, matricule, fonction)'],
    ];

    /** Le cabinet (libellé de la chambre) de chaque registre d'instruction, et le nom de sa feuille. */
    public const CABINETS = ['instruction_ji1' => ['1er cabinet', 'JI'], 'instruction_ji2' => ['2e cabinet', 'JI'], 'instruction_je' => ['juge des enfants', 'JE']];

    private const DECISIONS = ['decision_au_fond' => 'Décision au fond', 'radiation' => 'Radiation', 'pv_conciliation' => 'PV de conciliation totale', 'desistement' => 'Désistement'];

    /** Le registre civil d'une affaire, dans l'ordre de priorité : présidentielle, voies d'exécution, référés, matrimoniale, état civil, hérédité, tutelle, sinon civile diverse. */
    public static function registreCivil(array $x): string
    {
        $nature = self::sansAccents($x['nature']);
        $objet = self::sansAccents($x['objet']);

        return match (true) {
            str_contains(self::sansAccents((string) $x['chambre_libelle']), 'presidentielle') => 'civil_presidentielle',
            str_starts_with($nature, 'juge de l') => 'refere_voies_execution',
            str_starts_with($nature, 'refere-expulsion') || str_starts_with($nature, 'refere expulsion') => 'refere_expulsion',
            str_starts_with($nature, 'refere') => 'refere_ordinaire',
            str_contains($objet, 'adoption') => 'etat_des_personnes',   // dans les classeurs spécialisés, l'adoption est un traitement de l'état des personnes
            $x['categorie'] === 'matrimoniale' => 'matrimoniale',
            $x['categorie'] === 'heredite' => 'heredite',
            (bool) preg_match('/tutelle|curatelle|garde juridique|\bdvap\b|vacance|autorite parentale|\bcal\b|\bcap\b/', $objet) => 'tutelle',
            $x['categorie'] === 'etat_civil' => 'etat_des_personnes',
            default => 'civile_diverse',
        };
    }

    /** Le nombre de lignes de chaque registre pour l'utilisateur. @return array<string, int> */
    public static function compter(User $user, ?int $chambre = null): array
    {
        $n = array_fill_keys(array_keys(self::REGISTRES), 0);
        foreach (DonneesCivil::affaires($user, $chambre) as $x) {
            $n[self::registreCivil($x)]++;
        }
        $n['personnel_magistrat'] = self::magistrats()->count();
        $n['assistance_judiciaire'] = \App\Models\AssistanceJudiciaire::count();
        $n['activites_jap'] = \App\Models\ActiviteJap::where('valeur', '>', 0)->count();
        foreach (array_keys(self::CABINETS) as $code) {
            $n[$code] = self::affairesInstruction($user, $code, $chambre)->count();
        }
        $n['mise_en_etat'] = MiseEnEtat::visiblesPour($user)->when($chambre, fn ($q) => $q->whereHas('affaire', fn ($a) => $a->where('chambre_id', $chambre)))->count();
        foreach (self::decisionsPenales($user, $chambre) as $d) {
            $n[self::registrePenal($d)]++;
        }

        return $n;
    }

    /** Les lignes d'un registre, indexées par lettre de colonne. @return list<array<string, mixed>> */
    public static function lignes(string $code, User $user, ?int $chambre = null): array
    {
        $famille = self::REGISTRES[$code][1] ?? null;

        return match ($famille) {
            'civil', 'civile_diverse', 'tutelle' => self::lignesCiviles($code, $famille, DonneesCivil::affaires($user, $chambre)->filter(fn ($x) => self::registreCivil($x) === $code)),
            'mise_en_etat' => self::lignesMiseEnEtat($user, $chambre),
            'personnel' => self::lignesPersonnel(),
            'instruction' => self::lignesInstruction($code, $user, $chambre)['affaires'],
            'penal' => self::lignesPenales(self::decisionsPenales($user, $chambre)->filter(fn ($d) => self::registrePenal($d) === $code)),
            default => [],
        };
    }

    /** @return list<array<string, mixed>> */
    private static function lignesCiviles(string $code, string $famille, Collection $affaires): array
    {
        $lignes = [];
        foreach ($affaires->sortBy(fn ($x) => $x['date_rg']) as $x) {
            [$dem, $def] = [self::cote($x['parties'], ['demandeur']), self::cote($x['parties'], ['defendeur'])];
            $nature = self::natureLibelle($x, $famille, $code);
            $sortie = $x['type_sortie'] ? self::DECISIONS[$x['type_sortie']] : null;
            $caractere = $x['caractere'] ? ResultatAffaire::CARACTERES[$x['caractere']] : null;
            $resultat = $x['resultat'] ? ($x['resultat'] === 'accord' ? 'Accord' : 'Rejet') : null;
            $communs = ['A' => $x['numero'], 'B' => $x['date_rg'], 'C' => $dem['noms'], 'D' => $dem['H'], 'E' => $dem['F'], 'F' => $dem['Couple'], 'G' => $dem['PM'], 'H' => $def['noms'], 'I' => $def['H'], 'J' => $def['F'], 'K' => $def['Couple'], 'L' => $def['PM'], 'M' => $x['objet'], 'N' => $nature];

            if ($famille === 'tutelle') {
                $lignes[] = $communs + ['O' => $x['audience'], 'P' => $sortie, 'Q' => $x['decision_numero'], 'R' => $x['sortie'], 'S' => $caractere, 'T' => $resultat, 'U' => $x['observations'], 'V' => $code === 'tutelle' ? self::traitementTutelle($x['objet']) : null];

                continue;
            }
            $ligne = $communs + [
                'O' => self::adoptant($x['adoptant_ivoirien']), 'P' => self::adoptant($x['adoptant_etranger']), 'Q' => $x['audience'], 'R' => $sortie, 'S' => $x['decision_numero'], 'T' => $x['sortie'], 'U' => $caractere, 'V' => $resultat,
                'W' => $x['filles'], 'X' => $x['garcons'], 'Y' => $x['observations'],
            ];
            if ($famille === 'civil' && in_array($code, ['matrimoniale', 'etat_des_personnes'], true)) {
                $personnes = $x['categorie'] === 'etat_civil' || str_contains(self::sansAccents($x['objet']), 'adoption');
                $ligne['Z'] = $personnes ? self::traitementEtatDesPersonnes($x['objet']) : null;
                $ligne['AA'] = $x['categorie'] === 'matrimoniale' && ! $personnes ? self::traitementMatrimonial($x['objet']) : null;
            } elseif ($famille === 'civil') {
                $ligne['Z'] = $x['categorie'] === 'etat_civil' ? CategoriesStats::sous('etat_civil')[$x['sous']] ?? null : null;
                $ligne['AA'] = $x['categorie'] === 'matrimoniale' ? CategoriesStats::sous('matrimoniale')[$x['sous']] ?? null : null;
            }
            $lignes[] = $ligne;
        }

        return $lignes;
    }

    /** Le fichier Excel d'un registre et son nombre de lignes. @return array{0: string, 1: int} */
    public static function fichier(string $code, User $user, ?int $chambre = null): array
    {
        $famille = self::REGISTRES[$code][1];
        if ($famille === 'instruction') {
            $t = self::lignesInstruction($code, $user, $chambre);

            return [ModeleTribunal::generer($code, $t['affaires'], 'Liste et suivi des affaires '.self::CABINETS[$code][1], ['SUIVI INCULPE' => $t['inculpes'], 'SUIVI VICTIME' => $t['victimes']], self::feuillesCommissions($code, $user)), count($t['affaires'])];
        }
        if ($code === 'activites_jap') {
            $cellules = self::cellulesJap();

            return [ModeleTribunal::genererCellules($code, $cellules), count($cellules)];
        }
        if ($code === 'assistance_judiciaire') {
            $lignes = self::lignesAssistance();

            return [ModeleTribunal::generer($code, $lignes), count($lignes)];
        }
        $lignes = self::lignes($code, $user, $chambre);
        // Le classeur du personnel a une feuille par année : on remplit celle de l'année en cours.
        $feuille = $code === 'personnel_magistrat' ? 'PERSONNEL MAGISTRAT '.min(max((int) now()->year, 2021), 2031) : null;

        return [ModeleTribunal::generer($code, $lignes, $feuille), count($lignes)];
    }

    /** Les feuilles « CR JI 2026 » : commissions rogatoires reçues (colonnes C à G) et émises (H à L), par mois, nationales (ligne 3 + 3 × mois) puis internationales (ligne suivante). @return array<string, array<string, int>> */
    private static function feuillesCommissions(string $code, User $user): array
    {
        $cabinets = \App\Models\Chambre::where('libelle', 'like', '%'.self::CABINETS[$code][0].'%')->pluck('id')->all();
        $visibles = \App\Support\PerimetreChambres::de($user);
        $cabinets = $visibles === null ? $cabinets : array_values(array_intersect($cabinets, $visibles));
        $feuilles = [];
        foreach (\App\Models\CommissionRogatoire::whereIn('chambre_id', $cabinets)->get() as $c) {
            $ligne = 3 + 3 * ($c->mois - 1) + ($c->portee === 'internationale' ? 1 : 0);
            [$a, $b, $e, $f, $g] = $c->sens === 'recu' ? ['C', 'D', 'E', 'F', 'G'] : ['H', 'I', 'J', 'K', 'L'];
            $feuille = 'CR '.self::CABINETS[$code][1].' '.$c->annee;
            $feuilles[$feuille][$a.$ligne] = $c->stock_initial;
            $feuilles[$feuille][$b.$ligne] = $c->nouvelles;
            $feuilles[$feuille][$e.$ligne] = $c->stock_initial + $c->nouvelles;
            $feuilles[$feuille][$f.$ligne] = $c->executees;
            $feuilles[$feuille][$g.$ligne] = $c->stockRestant();
        }

        return $feuilles;
    }

    /** Les dossiers d'un cabinet que l'utilisateur peut voir. */
    private static function affairesInstruction(User $user, string $code, ?int $chambre): \Illuminate\Database\Eloquent\Builder
    {
        $motif = self::CABINETS[$code][0];

        return \App\Models\Affaire::visiblesPour($user)->whereIn('serie', ['RI', 'RE'])->where('statut', 'enrolee')
            ->whereHas('chambre', fn ($q) => $q->where('libelle', 'like', '%'.$motif.'%'))->when($chambre, fn ($q) => $q->where('chambre_id', $chambre));
    }

    /** Les trois tableaux des classeurs « INFO JI » et « INFO JE » : dossiers, inculpés, victimes. @return array{affaires: list<array<string, mixed>>, inculpes: list<array<string, mixed>>, victimes: list<array<string, mixed>>} */
    private static function lignesInstruction(string $code, User $user, ?int $chambre): array
    {
        $affaires = self::affairesInstruction($user, $code, $chambre)->with(['parties', 'modeSaisine', 'dossierInstruction'])->orderBy('date_rg')->orderBy('id')->get();
        $suivisI = \App\Models\SuiviInculpe::whereIn('partie_id', $affaires->flatMap->parties->pluck('id'))->get()->keyBy('partie_id');
        $suivisV = \App\Models\SuiviVictime::whereIn('partie_id', $affaires->flatMap->parties->pluck('id'))->get()->keyBy('partie_id');
        $nature = ['crime' => 'Crime', 'delit' => 'Délit', 'contravention' => 'Contravention'];
        $statut = \App\Http\Controllers\Instruction\InstructionController::STATUTS;
        $sexe = fn (Partie $p) => $p->nature === 'femme' ? 'Féminin' : 'Masculin';
        $age = fn (Partie $p) => $p->age();
        $majeur = fn (Partie $p) => $p->age() === null ? null : ($p->age() < 18 ? 'Mineur' : 'Majeur');
        $out = ['affaires' => [], 'inculpes' => [], 'victimes' => []];

        foreach ($affaires as $a) {
            $d = $a->dossierInstruction;
            $mode = (string) $a->modeSaisine?->libelle;
            $modeListe = match (true) {
                str_contains(self::sansAccents($mode), 'requisitoire') => 'Réquisitoire introductif', str_contains(self::sansAccents($mode), 'plainte') => 'Plainte avec CPC', str_contains(self::sansAccents($mode), 'attribution') => "Dossier d'attribution", default => $mode,
            };
            $rp = $a->numero_parquet;
            $num = $a->numeroAffichage();
            $out['affaires'][] = [
                'A' => $rp, 'B' => $num.' du '.$a->date_rg?->format('d/m/Y'), 'C' => $a->date_rg, 'D' => $modeListe, 'E' => $d?->date_requisitoire, 'F' => $a->objet, 'G' => $d?->textes_vises ?: $a->articles,
                'H' => $d?->date_faits, 'I' => $d?->lieu_faits, 'J' => $nature[$d?->nature_infraction] ?? null, 'K' => $d?->infraction_specifique, 'L' => $d?->autre_specificite, 'M' => $d?->date_cloture, 'N' => $d?->nature_cloture,
                'O' => $d?->autres_ordonnances, 'P' => $d?->infraction_retenue, 'Q' => $d?->date_transmission_parquet, 'R' => $d?->observations,
            ];
            foreach ($a->parties->where('role', 'inculpe') as $p) {
                $s = $suivisI->get($p->id);
                $st = $s?->statut ?: ($p->estDetenu() ? 'detenu' : null);
                $out['inculpes'][] = [
                    'A' => $rp, 'B' => $num, 'C' => $a->date_rg, 'D' => trim($p->prenoms.' '.mb_strtoupper((string) $p->nom)), 'E' => $s?->traite ? 'OUI' : 'NON', 'F' => $a->objet, 'G' => $p->profession, 'H' => $sexe($p), 'I' => $age($p),
                    'J' => $majeur($p), 'K' => $p->nationalite, 'L' => $st ? ($statut[$st] ?? null) : null, 'M' => $s?->precisions_statut, 'N' => $s?->date_ogp, 'Q' => $s?->date_titre_detention ?? $p->mandat_depot_date,
                    'R' => $s?->duree_detention_mois, 'U' => $s?->date_renouvellement, 'V' => $s?->duree_renouvellement_mois, 'AG' => $s?->date_liberte_provisoire, 'AH' => $s?->date_mainlevee_cj,
                    'AI' => $s?->date_cloture ?? $d?->date_cloture, 'AJ' => $s?->nature_cloture ?? $d?->nature_cloture, 'AL' => $s?->observations,
                ];
            }
            foreach ($a->parties->where('role', 'victime') as $p) {
                $s = $suivisV->get($p->id);
                $out['victimes'][] = [
                    'A' => $rp, 'B' => $num, 'C' => $a->date_rg, 'D' => trim($p->prenoms.' '.mb_strtoupper((string) $p->nom)) ?: $p->libelle(), 'E' => $s?->traite ? 'OUI' : 'NON', 'F' => $s?->infraction_subie, 'G' => $p->profession,
                    'H' => $sexe($p), 'I' => $age($p), 'J' => $majeur($p), 'K' => $p->nationalite, 'L' => $s?->audition_1, 'M' => $s?->audition_2, 'N' => $s?->audition_3, 'O' => $s?->date_cloture ?? $d?->date_cloture,
                    'P' => $s?->date_notification_cloture, 'Q' => $s?->observations,
                ];
            }
        }

        return $out;
    }

    /** Les demandes d'assistance judiciaire, colonnes du classeur (tableau « Ass_Judiciaire »). @return list<array<string, mixed>> */
    private static function lignesAssistance(): array
    {
        return \App\Models\AssistanceJudiciaire::orderBy('date_rg')->orderBy('id')->get()->map(fn ($a) => [
            'A' => $a->numero, 'B' => $a->date_rg, 'C' => $a->demandeur, 'D' => $a->objet, 'E' => $a->nature, 'F' => $a->date_audience, 'G' => $a->decision ? \App\Models\AssistanceJudiciaire::DECISIONS[$a->decision] : null,
            'H' => $a->numero_decision, 'I' => $a->date_decision, 'J' => $a->date_recours, 'K' => $a->date_transmission_recours, 'L' => $a->observation,
        ])->all();
    }

    /** Les cellules des feuilles « ACTIVITES DU JAP AAAA » : mois en colonnes B à M, rubriques en lignes. @return array<string, array<string, int>> */
    private static function cellulesJap(): array
    {
        $colonnes = ['B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M'];
        $lignes = [];
        foreach (\App\Models\ActiviteJap::VISITES as $c => [, $ligne]) {
            $lignes['visites_'.$c] = $ligne;
        }
        foreach (\App\Models\ActiviteJap::RUBRIQUES as $code => [, , $rows]) {
            foreach ($rows as $champ => $ligne) {
                $lignes[$code.'_'.$champ] = $ligne;
            }
        }
        $feuilles = [];
        foreach (\App\Models\ActiviteJap::where('annee', '>=', 2021)->where('annee', '<=', 2030)->get() as $a) {
            // Le stock du début du mois n'est une saisie qu'en janvier : les autres mois sont des formules du classeur.
            if (isset($lignes[$a->cle]) && ! (str_ends_with($a->cle, '_stock') && $a->mois > 1)) {
                $feuilles['ACTIVITES DU JAP '.$a->annee][$colonnes[$a->mois - 1].$lignes[$a->cle]] = $a->valeur;
            }
        }

        return $feuilles;
    }

    /** Les magistrats du siège en fonction. @return Collection<int, \App\Models\Agent> */
    private static function magistrats(): Collection
    {
        return \App\Models\Agent::where('actif', true)->whereIn('fonction', ['presidente', 'vice_president', 'juge', 'juge_instruction', 'juge_enfants'])->orderBy('nom')->orderBy('prenoms')->get();
    }

    /** Le personnel magistrat, colonnes du classeur « RH Magistrat ». Les dates et le grade, que l'application ne tient pas, restent à compléter à la main. @return list<array<string, mixed>> */
    private static function lignesPersonnel(): array
    {
        return self::magistrats()->map(fn ($a) => [
            'A' => $a->matricule, 'B' => trim(mb_strtoupper((string) $a->nom).' '.$a->prenoms), 'D' => $a->fonctionLibelle(), 'F' => $a->sexe === 'F' ? 'F' : ($a->sexe === 'M' ? 'H' : null), 'J' => 'Siège',
        ])->values()->all();
    }

    /** @return list<array<string, mixed>> */
    private static function lignesMiseEnEtat(User $user, ?int $chambre): array
    {
        $lignes = [];
        $liste = MiseEnEtat::visiblesPour($user)->with(['affaire.parties', 'affaire.nature', 'affaire.chambre'])->when($chambre, fn ($q) => $q->whereHas('affaire', fn ($a) => $a->where('chambre_id', $chambre)))->orderBy('date_reception')->get();
        foreach ($liste as $m) {
            $a = $m->affaire;
            $parties = $a->parties;
            $x = ['nature' => (string) $a->nature?->libelle, 'objet' => (string) $a->objet];
            $lignes[] = [
                'A' => $a->numeroAffichage(), 'B' => $m->date_reception, 'C' => self::cote($parties, ['demandeur'])['noms'], 'D' => self::cote($parties, ['defendeur'])['noms'], 'E' => $a->objet,
                'F' => self::natureLibelle($x + ['categorie' => CategoriesStats::categorie($a)], 'mise_en_etat', 'mise_en_etat'), 'G' => $m->date_premiere_audience ?? $a->date_premiere_audience,
                'H' => $m->numero_ordonnance_cloture, 'I' => $m->date_cloture, 'J' => $m->statut === 'retiree' ? trim('Retirée de la mise en état. '.$m->motif_retrait) : $m->observations,
            ];
        }

        return $lignes;
    }

    /** @return Collection<int, DecisionPenale> */
    private static function decisionsPenales(User $user, ?int $chambre): Collection
    {
        return DecisionPenale::visiblesPour($user)->with(['affaire.nature', 'affaire.parties', 'partie'])
            ->when($chambre, fn ($q) => $q->whereHas('affaire', fn ($a) => $a->where('chambre_id', $chambre)))->orderBy('date_decision')->get();
    }

    private static function registrePenal(DecisionPenale $d): string
    {
        $nature = self::sansAccents((string) $d->affaire->nature?->libelle);

        return match (true) {
            str_starts_with($nature, 'flagrant') => 'penal_flagrants_delits',
            str_contains($nature, 'economique') => 'penal_delits_economiques',
            str_contains($nature, 'circulation') => 'penal_police_circulation',
            str_starts_with($nature, 'simple') => 'penal_simple_police',
            str_contains($nature, 'enfant') => 'penal_tpe',
            default => 'penal_delits_divers',
        };
    }

    /** @param Collection<int, DecisionPenale> $decisions @return list<array<string, mixed>> */
    private static function lignesPenales(Collection $decisions): array
    {
        $contradiction = ['contradictoire' => 'Contradictoire', 'defaut' => 'Défaut', 'reputee_contradictoire' => 'Contradictoire'];
        $nature = ['crime' => 'Crime', 'delit' => 'Délit', 'contravention' => 'Contravention'];
        $lignes = [];
        foreach ($decisions as $d) {
            $a = $d->affaire;
            $prevenus = $a->parties->whereIn('role', ['prevenu', 'inculpe']);
            $condamnes = $d->issue === 'condamne' ? ($d->partie ? collect([$d->partie]) : $prevenus) : collect();
            $h = $condamnes->where('nature', 'homme')->count();
            $f = $condamnes->where('nature', 'femme')->count();
            $v = [(int) $d->victimes_hommes, (int) $d->victimes_femmes, (int) $d->victimes_garcons, (int) $d->victimes_filles];
            $nationalites = self::repartition($condamnes, 'nationalite');
            $lignes[] = [
                'A' => preg_replace('/^[A-Z]+[\s-]*/', '', $a->numeroAffichage()), 'B' => null, 'C' => $prevenus->count() ?: null, 'D' => $a->date_premiere_audience, 'E' => $d->numero_decision, 'F' => $d->date_decision,
                'G' => $contradiction[$d->contradiction] ?? 'Contradictoire', 'H' => self::contenuDecision($d), 'I' => $d->peineEnClair() ?: null, 'J' => null, 'K' => $d->infraction_retenue ?: $a->objet,
                'L' => $d->traite ? 'OUI' : 'NON', 'M' => $nature[$d->nature_infraction] ?? (str_starts_with(self::sansAccents((string) $a->nature?->libelle), 'simple') ? 'Contravention' : 'Délit'),
                'N' => $d->traite ? 'Traite des personnes' : null, 'O' => null,
                'P' => $condamnes->count() ?: null, 'Q' => $condamnes->isEmpty() ? null : $h, 'R' => $condamnes->isEmpty() ? null : $f, 'S' => $nationalites, 'T' => self::repartition($condamnes, 'profession'),
                'U' => array_sum($v) ?: null, 'V' => $v[0] ?: null, 'W' => $v[1] ?: null, 'X' => $v[2] ?: null, 'Y' => $v[3] ?: null, 'Z' => null, 'AA' => null,
                'AB' => $d->date_decision, 'AC' => $d->date_transmission_minute, 'AD' => null, 'AE' => null,
            ];
        }

        return $lignes;
    }

    /** « Contenu de la décision » : les valeurs de la liste du classeur. */
    private static function contenuDecision(DecisionPenale $d): string
    {
        if (in_array($d->issue, ['relaxe', 'acquitte'], true)) {
            return 'Relaxe';
        }
        if ($d->issue !== 'condamne') {
            return 'Autre';
        }
        $prison = (int) $d->emprisonnement_mois;
        $sursis = (int) $d->sursis_mois;

        return match (true) {
            $prison > 0 && $sursis >= $prison => "Condamnation à l'emprisonnement avec sursis",
            $prison > 0 && $sursis > 0 => 'Emprisonnement mixte',
            $prison > 0 => "Condamnation à l'emprisonnement",
            (int) $d->amende > 0 => "Condamnation à l'amende",
            filled($d->autres_peines) => 'Peine alternative',
            default => 'Autre',
        };
    }

    /** « 01 burkinabè, 02 ivoirienne » : le nombre de condamnés par valeur, comme dans les classeurs. */
    private static function repartition(Collection $parties, string $champ): ?string
    {
        $g = $parties->map(fn ($p) => trim((string) $p->{$champ}))->filter()->countBy();

        return $g->isEmpty() ? null : $g->map(fn ($n, $v) => str_pad((string) $n, 2, '0', STR_PAD_LEFT).' '.$v)->implode(', ');
    }

    /** Noms et nombre de personnes par nature, pour les colonnes « H, F, Couple, Pers. Morale ». @return array{noms: ?string, H: ?int, F: ?int, Couple: ?int, PM: ?int} */
    private static function cote(Collection $parties, array $roles): array
    {
        $l = $parties->filter(fn (Partie $p) => in_array($p->role, $roles, true));
        $n = fn (array $natures) => ($c = $l->filter(fn ($p) => in_array($p->nature, $natures, true))->sum(fn ($p) => 1 + (int) $p->autres)) ? (int) $c : null;

        return ['noms' => $l->isEmpty() ? null : $l->map(fn ($p) => $p->libelle())->implode(' ; '), 'H' => $n(['homme']), 'F' => $n(['femme']), 'Couple' => $n(['couple']), 'PM' => $n(['morale', 'etat', 'mp'])];
    }

    /** La valeur de « Nature de l'affaire », dans la liste du classeur. */
    private static function natureLibelle(array $x, string $famille, string $code): string
    {
        if ($code === 'tutelle') {
            return 'Tutelle';
        }
        $c = $x['categorie'];
        // Les classeurs spécialisés (matrimoniale, état des personnes, civile diverse) écrivent « Etat des personnes » ; l'adoption en fait partie.
        if (in_array($code, ['matrimoniale', 'etat_des_personnes'], true) && str_contains(self::sansAccents((string) ($x['objet'] ?? '')), 'adoption')) {
            return 'Etat des personnes';
        }
        $liste = ['civile_ordinaire' => 'Civile ordinaire', 'etat_civil' => in_array($code, ['civile_diverse', 'matrimoniale', 'etat_des_personnes'], true) ? 'Etat des personnes' : 'Etat civil', 'matrimoniale' => 'Affaire matrimoniale', 'heredite' => 'Hérédité', 'refere' => 'Référé',
            'jex' => "Procédure devant le juge de l'exécution", 'commerciale' => 'Commerciale', 'administrative' => 'Administrative', 'saisie_remuneration' => 'Saisie rémunération', 'autre' => 'Autre'];

        return $liste[$c] ?? 'Autre';
    }

    /** « Traitement état des personnes » : la liste des classeurs matrimoniale et état des personnes. */
    private static function traitementEtatDesPersonnes(string $objet): string
    {
        $o = self::sansAccents($objet);

        return match (true) {
            (bool) preg_match('/supplet.*deces|\bjsd\b/', $o) => 'JSD', (bool) preg_match('/supplet.*naissance|\bjsn\b/', $o) => 'JSN', str_contains($o, 'transcription') => 'Transcription',
            (bool) preg_match('/retablissement.*acte|reconstitution/', $o) => 'Rétablissement acte état civil', (bool) preg_match('/retablissement.*identite|reprise.*identite/', $o) => "Rétablissement d'identité",
            str_contains($o, 'notoriete') => 'Notoriété', str_contains($o, 'pleniere') => 'Adoption plénière', str_contains($o, 'adoption') => 'Adoption simple', str_contains($o, 'rectification') => 'Rectification judiciaire',
            (bool) preg_match('/filiation|paternite/', $o) => 'Changement de filiation', (bool) preg_match('/\bnom\b|prenom/', $o) => 'Modification nom et/ou prénom', default => 'Autre',
        };
    }

    /** « Traitement affaire matrimoniale » : la liste des classeurs matrimoniale et état des personnes. */
    private static function traitementMatrimonial(string $objet): string
    {
        $o = self::sansAccents($objet);

        return match (true) {
            str_contains($o, 'separation de corps') => 'Séparation de corps', (bool) preg_match('/consentement mutuel|\bdcm\b/', $o) => 'DCM', str_contains($o, 'divorce') => 'Divorce pour faute',
            (bool) preg_match('/contribution.*charges|charges du menage/', $o) => 'Contribution aux charges du ménage', str_contains($o, 'regime matrimonial') => 'Changement de régime matrimonial', default => 'Autre',
        };
    }

    private static function traitementTutelle(string $objet): string
    {
        $o = self::sansAccents($objet);

        return match (true) {
            (bool) preg_match('/\bdvap\b|vacance/', $o) => 'DVAP', (bool) preg_match('/\bcal\b/', $o) => 'CAL', (bool) preg_match('/\bcap\b/', $o) => 'CAP', str_contains($o, 'autorisation parentale') => 'Autorisation parentale',
            str_contains($o, 'garde juridique') => 'GJ', str_contains($o, 'tutelle') => 'Tutelle', default => 'Autre',
        };
    }

    private static function adoptant(?string $v): ?string
    {
        return $v ? (ResultatAffaire::ADOPTANTS[$v] ?? null) : null;
    }

    private static function sansAccents(string $t): string
    {
        return strtr(mb_strtolower($t), ['é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e', 'à' => 'a', 'â' => 'a', 'î' => 'i', 'ï' => 'i', 'ô' => 'o', 'ù' => 'u', 'û' => 'u', 'ç' => 'c', '’' => "'"]);
    }
}
