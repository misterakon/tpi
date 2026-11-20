<?php

namespace App\Support;

/*
 | Catalogue des droits, organisé comme le menu :  menu  >  sous-menu (page)  >  niveaux.
 |
 | Pour chaque sous-menu, l'administrateur choisit UN niveau :
 |     Aucun accès  <  Lecture  <  Saisie  <  Gestion
 | Chaque niveau regroupe des droits précis (clés « page.action », ex. comptes.creer) : ce sont eux
 | qui sont enregistrés dans le profil et vérifiés côté serveur. Quelques actions sensibles
 | (valider, exporter, imprimer…) sont des « droits spéciaux » à accorder séparément.
 |
 | Le profil Admin a tous les droits d'office, y compris ceux des modules à venir.
 */
class Droits
{
    public const NIVEAUX = ['aucun' => 'Aucun accès', 'lecture' => 'Lecture', 'saisie' => 'Saisie', 'gestion' => 'Gestion'];

    /**
     * @return array<string, array{libelle: string, icone: string, pages: array<string, array{libelle: string, a_venir: bool, niveaux: array<string, list<string>>, speciales: array<string, string>}>}>
     */
    public static function catalogue(): array
    {
        return [
            'dossiers' => [
                'libelle' => 'Dossiers',
                'icone' => 'fa-solid fa-folder-open',
                'pages' => [
                    'enrolement' => self::groupe('Affaires', self::page('Enregistrement', self::standard('enrolement', 'annuler'), ['enrolement.deroger' => "Déroger à la date limite d'enregistrement"])),
                    'affaires' => self::groupe('Affaires', self::page('Liste des affaires', self::standard('affaires', 'annuler'), ['affaires.reattribuer' => 'Réattribuer à une autre chambre', 'affaires.toutes_chambres' => 'Voir les affaires de toutes les chambres', 'affaires.famille.mineurs' => 'Voir les affaires de mineurs (juge des enfants, tribunaux pour mineurs et pour enfants) : accès protégé, à donner expressément', 'affaires.famille.instruction' => "Voir les dossiers d'instruction (cabinets d'instruction) : accès protégé, à donner expressément", 'affaires.exporter' => 'Exporter la liste (Excel, PDF)', 'affaires.corriger_numero' => 'Corriger un numéro saisi à la main'])),
                    'dossiers' => self::groupe('Affaires', self::page('Dossier numérique', self::standard('dossiers'), ['dossiers.telecharger' => 'Télécharger'])),
                    'mise_en_etat' => self::groupe('Affaires', self::page('Mise en état', self::standard('mise_en_etat', 'annuler'))),
                    'requetes' => self::groupe('Affaires', self::page('Requêtes et hérédité', self::standard('requetes', 'annuler'), ['requetes.signer' => 'Signer une ordonnance sur requête ou une injonction de payer (signature réservée)', 'requetes.repartir' => 'Répartir les requêtes entre les formations (secrétariat de la Présidente)'])),
                    'detenus' => self::groupe('Affaires', self::page('Détenus prioritaires', ['lecture' => ['detenus.voir'], 'saisie' => ['detenus.voir'], 'gestion' => ['detenus.voir']])),
                    'avocats' => self::groupe('Affaires', self::page('Avocats', self::standard('avocats'))),
                    'reprise' => self::groupe('Affaires', self::page("Reprise de l'existant", self::standard('reprise', 'annuler'), ['reprise.importer' => "Lancer l'importation définitive dans le registre"])),
                    'scelles' => self::groupe('Affaires', self::page('Scellés (dans la fiche)', self::standard('scelles'))),
                    'instruction' => self::groupe('Instruction', self::page("Dossiers d'instruction", ['lecture' => ['instruction.voir'], 'saisie' => ['instruction.voir', 'instruction.modifier']], [], false)),
                    'assistance' => self::groupe('Annexes', self::page('Assistance judiciaire', self::standard('assistance'))),
                    'jap' => self::groupe('Annexes', self::page("Activités du juge de l’application des peines", ['lecture' => ['jap.voir'], 'saisie' => ['jap.voir', 'jap.modifier']])),
                ],
            ],
            'audiences' => [
                'libelle' => 'Audiences',
                'icone' => 'fa-solid fa-gavel',
                'pages' => [
                    'roles' => self::page("Rôles d'audience", self::standard('roles'), ['roles.imprimer' => 'Exporter en Word et en PDF', 'roles.publier' => 'Publier un rôle et le rouvrir', 'roles.penal' => 'Établir les rôles pénaux (parquet)'], false),
                    'feuille' => self::page("Feuille d'audience", ['lecture' => ['feuille.voir'], 'saisie' => ['feuille.voir', 'feuille.modifier']], [], false),
                    'renvois' => self::page('Renvois et délibérés', ['lecture' => ['renvois.voir'], 'saisie' => ['renvois.voir', 'renvois.modifier']], [], false),
                    'seances' => self::page('Séances hors audience publique', self::standard('seances', 'annuler'), [], false),
                    'suites' => self::page("Suites d'audience", ['lecture' => ['suites.voir']], ['suites.exporter' => 'Exporter en Excel et en PDF'], false),
                ],
            ],
            'decisions' => [
                'libelle' => 'Décisions',
                'icone' => 'fa-solid fa-file-signature',
                'pages' => [
                    'redaction' => self::page('Rédaction', self::standard('redaction', 'annuler'), ['redaction.valider' => 'Valider (et renvoyer en rédaction)', 'redaction.numeroter' => 'Attribuer le numéro à une décision rédigée (greffier de la chambre)', 'redaction.signer' => 'Signer une décision validée (le numéro est attribué à ce moment ou à la validation, selon les réglages)', 'redaction.transmettre' => 'Transmettre au greffe les décisions signées, une par une ou en lot, avec copie Word au greffier en chef (le juge)', 'redaction.word' => 'Télécharger la décision en Word']),
                    'registre_decisions' => self::page('Registre des décisions', ['lecture' => ['registre_decisions.voir']], ['registre_decisions.exporter' => 'Exporter en Excel et en PDF']),
                    'repertoire_penal' => self::page('Répertoire pénal', self::standard('repertoire_penal')),
                    'recours' => self::page('Recours et certificats', self::standard('recours', 'annuler'), ['recours.certifier' => 'Délivrer un certificat de non-appel ou de non-opposition', 'recours.delais' => 'Modifier et confirmer les délais de recours']),
                ],
            ],
            'pilotage' => [
                'libelle' => 'Pilotage',
                'icone' => 'fa-solid fa-chart-line',
                'pages' => [
                    'suivi_alertes' => self::groupe('Alertes', self::page('Suivi des alertes', ['lecture' => ['alertes.voir']], ['alertes.justifier' => "Justifier un retard (suspend les relances d'une alerte)"], false)),
                    'regles_alerte' => self::groupe('Alertes', self::page("Règles d'alerte", self::standard('regles_alerte', 'annuler'), [], false)),
                    'delais_etape' => self::groupe('Alertes', self::page('Délais par étape', ['lecture' => ['delais_etape.voir'], 'gestion' => ['delais_etape.voir', 'delais_etape.modifier']], [], false)),
                    // Les délais de procédure (délai global et étapes du circuit) se gèrent dans la même page que les délais par étape.
                    'delais_procedure' => self::groupe('Alertes', self::page('Délais de procédure', self::standard('delais_procedure'))),
                    'tableau' => self::groupe('Analyse et pilotage', self::page('Analyse et pilotage', ['lecture' => ['tableau.voir']], ['tableau.ia' => "Générer l'analyse par l'IA (sur demande, dans la limite quotidienne)"], false)),
                    'stats_civil' => self::groupe('Statistiques', self::page('Statistiques civiles', ['lecture' => ['stats_civil.voir']], [
                        'stats_civil.detail' => 'Voir les affaires derrière un chiffre (noms et numéros) ; sans ce droit, chiffres agrégés seulement',
                        'stats_civil.qualifier' => 'Préciser une affaire : accordée ou rejetée, adoption, sortie',
                        'stats_civil.exporter' => 'Exporter en Excel et en PDF au modèle des classeurs',
                    ])),
                    'stats_penal' => self::groupe('Statistiques', self::page('Statistiques pénales', ['lecture' => ['stats_penal.voir']], [
                        'stats_penal.detail' => 'Inclure la liste des décisions (une ligne par décision) dans l’export Excel ; sans ce droit, chiffres agrégés seulement',
                        'stats_penal.exporter' => 'Exporter en Excel et en PDF au modèle des classeurs',
                    ])),
                    'stats_instruction' => self::groupe('Statistiques', self::page('Statistiques de l’instruction', ['lecture' => ['stats_instruction.voir']])),
                    'stats_registres' => self::groupe('Statistiques', self::page('Registres du tribunal', ['lecture' => ['stats_registres.voir']], ['stats_registres.exporter' => 'Télécharger les registres au modèle du tribunal (Excel avec les noms des parties)'])),
                ],
            ],
            'administration' => [
                'libelle' => 'Administration',
                'icone' => 'fa-solid fa-building-columns',
                'pages' => [
                    'comptes' => self::groupe('Utilisateurs', self::page('Liste des utilisateurs', self::standard('comptes'), ['comptes.mot_de_passe' => 'Lien de mot de passe'])),
                    'profils' => self::groupe('Utilisateurs', self::page('Profils et droits', self::standard('profils'))),
                    'ordonnances' => self::groupe('Organisation du tribunal', self::page('Ordonnances', self::standard('ordonnances'), ['ordonnances.importer' => "Charger l'ordonnance par fichier Excel (contrôler, comparer, appliquer, annuler)"])),
                    'annees' => self::groupe('Organisation du tribunal', self::page('Année judiciaire', self::standard('annees'))),
                    'salles' => self::groupe('Organisation du tribunal', self::page('Salles et lieux', self::standard('salles'))),
                    'personnel' => self::groupe('Organisation du tribunal', self::page('Personnel du tribunal', self::standard('personnel'))),
                    'sections' => self::groupe('Organisation du tribunal', self::page('Sections', self::standard('sections'))),
                    'chambres' => self::groupe('Organisation du tribunal', self::page('Chambres', self::standard('chambres'))),
                    'formations' => self::groupe('Organisation du tribunal', self::page('Formations (dans une chambre)', self::standard('formations'))),
                    'affectations' => self::groupe('Organisation du tribunal', self::page('Composition des chambres', self::standard('affectations'))),
                    'creneaux' => self::groupe('Organisation du tribunal', self::page("Jours et heures d'audience", self::standard('creneaux'))),
                    'services' => self::groupe('Organisation du tribunal', self::page('Services et responsabilités', self::standard('services'))),
                    'fermetures' => self::groupe('Organisation du tribunal', self::page('Jours fériés et vacances', self::standard('fermetures'))),
                    'delais' => self::groupe('Organisation du tribunal', self::page("Dates limites d'enregistrement", self::standard('delais'))),
                    'reconduction' => self::groupe('Organisation du tribunal', self::page('Reconduire une année', ['lecture' => ['reconduction.voir'], 'gestion' => ['reconduction.voir', 'reconduction.executer']])),
                    'types_audience' => self::groupe('Référentiels', self::page('Types d\'audience', self::standard('types_audience'))),
                    'modes_saisine' => self::groupe('Référentiels', self::page('Modes de saisine', self::standard('modes_saisine'))),
                    'matieres' => self::groupe('Référentiels', self::page('Matières', self::standard('matieres'))),
                    'infractions' => self::groupe('Référentiels', self::page('Infractions', self::standard('infractions'))),
                    'natures_affaire' => self::groupe('Référentiels', self::page('Natures d\'affaire', self::standard('natures_affaire'))),
                    'objets_litige' => self::groupe('Référentiels', self::page('Objets du litige', self::standard('objets_litige'))),
                    'modeles' => self::groupe('Modèles et textes', self::page('Modèles de décision', self::standard('modeles'))),
                    'blocs' => self::groupe('Modèles et textes', self::page('Blocs de texte', self::standard('blocs'))),
                    'reglages' => self::groupe('Paramètres et journaux', self::page('Réglages', ['lecture' => ['reglages.voir'], 'gestion' => ['reglages.voir', 'reglages.modifier']], ['reglages.secrets' => 'Courriels : serveur et mot de passe'])),
                    'connexion' => self::groupe('Paramètres et journaux', self::page('Journal de connexion', ['lecture' => ['journaux.connexion']], ['journaux.connexion.exporter' => 'Exporter'])),
                    'audit' => self::groupe('Paramètres et journaux', self::page("Journal d'audit", ['lecture' => ['journaux.audit']], ['journaux.audit.exporter' => 'Exporter'])),
                ],
            ],
        ];
    }

    /** @return array<string, array{menu: string, libelle: string, a_venir: bool, niveaux: array<string, list<string>>, speciales: array<string, string>}> */
    public static function pages(): array
    {
        $pages = [];
        foreach (self::catalogue() as $cleMenu => $menu) {
            foreach ($menu['pages'] as $clePage => $page) {
                $pages[$clePage] = ['menu' => $cleMenu] + $page;
            }
        }

        return $pages;
    }

    /** Tous les droits précis. @return list<string> */
    public static function tous(): array
    {
        $cles = [];
        foreach (self::pages() as $page) {
            foreach ($page['niveaux'] as $droits) {
                array_push($cles, ...$droits);
            }
            array_push($cles, ...array_keys($page['speciales']));
        }

        return array_values(array_unique($cles));
    }

    public static function existe(string $droit): bool
    {
        return in_array($droit, self::tous(), true);
    }

    /** Niveau le plus élevé proposé pour une page. */
    public static function niveauMax(array $page): string
    {
        return array_key_last($page['niveaux']);
    }

    /**
     * Choix de l'administrateur  ->  liste des droits précis à enregistrer.
     * Un droit spécial n'est accordé que si la page est accessible (niveau au moins « lecture »).
     *
     * @param  array<string, string>  $niveaux  page => niveau
     * @param  list<string>  $speciales
     * @return list<string>
     */
    public static function depuisChoix(array $niveaux, array $speciales = []): array
    {
        $droits = [];
        foreach (self::pages() as $clePage => $page) {
            $niveau = $niveaux[$clePage] ?? 'aucun';
            if ($niveau === 'aucun' || ! isset($page['niveaux'][$niveau])) {
                continue;
            }
            array_push($droits, ...$page['niveaux'][$niveau]);
            foreach (array_keys($page['speciales']) as $speciale) {
                if (in_array($speciale, $speciales, true)) {
                    $droits[] = $speciale;
                }
            }
        }

        return array_values(array_unique($droits));
    }

    /**
     * Droits précis enregistrés  ->  choix à afficher dans la fenêtre.
     *
     * @param  list<string>  $droits
     * @return array{niveaux: array<string, string>, speciales: list<string>}
     */
    public static function versChoix(array $droits): array
    {
        $niveaux = [];
        $speciales = [];
        foreach (self::pages() as $clePage => $page) {
            $niveau = 'aucun';
            foreach ($page['niveaux'] as $nom => $requis) {
                if (array_diff($requis, $droits) === []) {
                    $niveau = $nom;
                }
            }
            $niveaux[$clePage] = $niveau;
            foreach (array_keys($page['speciales']) as $speciale) {
                if (in_array($speciale, $droits, true)) {
                    $speciales[] = $speciale;
                }
            }
        }

        return ['niveaux' => $niveaux, 'speciales' => $speciales];
    }

    /** Nombre de sous-menus auxquels les droits donnent accès (niveau au moins « lecture »). */
    public static function nombrePagesAccessibles(array $droits): int
    {
        return count(array_filter(self::versChoix($droits)['niveaux'], fn ($n) => $n !== 'aucun'));
    }

    /** Lecture : voir. Saisie : voir, créer, modifier. Gestion : tout, y compris supprimer (ou annuler pour un acte). */
    private static function standard(string $page, string $retrait = 'supprimer'): array
    {
        return [
            'lecture' => ["$page.voir"],
            'saisie' => ["$page.voir", "$page.creer", "$page.modifier"],
            'gestion' => ["$page.voir", "$page.creer", "$page.modifier", "$page.$retrait"],
        ];
    }

    /** Place une page dans un sous-menu du menu (affichage de l'écran des profils : même découpage que le menu de l'application). */
    private static function groupe(string $groupe, array $page): array
    {
        return ['groupe' => $groupe] + $page;
    }

    private static function page(string $libelle, array $niveaux, array $speciales = [], bool $aVenir = false): array
    {
        return ['libelle' => $libelle, 'a_venir' => $aVenir, 'niveaux' => $niveaux, 'speciales' => $speciales];
    }
}
