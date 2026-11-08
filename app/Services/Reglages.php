<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Throwable;

/*
 | Réglages de l'application modifiables depuis l'interface.
 |
 | Principe : la clé d'un réglage EST la clé de configuration qu'il remplace (ex. securite.inactivite_minutes).
 | Au démarrage, les valeurs enregistrées en base sont posées dans config() : tout le code existant continue
 | de lire config('...') sans rien changer. La valeur par défaut est celle des fichiers de configuration / .env.
 |
 | Garde-fous : seuls les réglages listés dans le catalogue sont modifiables ; chacun a des bornes ;
 | l'accès à la base et les clés de chiffrement n'y figurent jamais.
 |
 | Réglages « secrets » (type « secret », ex. mot de passe du serveur de courriel) :
 |  - enregistrés CHIFFRÉS en base (clé de l'application) : une copie de la base ne les révèle pas ;
 |  - jamais renvoyés à l'écran, ni dans un formulaire, ni dans le journal d'audit ;
 |  - champ laissé vide = valeur conservée ;
 |  - réservés aux profils ayant le droit « reglages.secrets » (voir la rubrique concernée).
 */
class Reglages
{
    private const CACHE = 'reglages.valeurs';

    /** Les deux façons d'obtenir un numéro d'affaire. */
    private const MODES_NUMERO = [
        'manuel' => 'Saisi à la main (numéro du greffe ou du parquet)',
        'automatique' => "Attribué par l'application",
    ];

    /** Texte inscrit au journal à la place d'une valeur secrète. */
    public const MASQUE_DEFINI = '[défini]';

    public const MASQUE_VIDE = '[vide]';

    public const MASQUE_MODIFIE = '[modifié]';

    public const MASQUE_EFFACE = '[effacé]';

    /** Valeurs par défaut (avant application des réglages enregistrés). @var array<string, mixed> */
    private array $originaux = [];

    /**
     * Catalogue : rubrique > réglages. Les modules à venir y ajoutent les leurs.
     * type : entier (min, max, unite) | texte (max, requis) | liste (options) | secret (max).
     * Une rubrique peut exiger un droit supplémentaire (clé « droit »).
     *
     * @return array<string, array<string, mixed>>
     */
    public static function catalogue(): array
    {
        return [
            'organisation' => [
                'libelle' => 'Organisation',
                'icone' => 'fa-solid fa-building-columns',
                'description' => "Nom de l'application et de la juridiction, tels qu'ils s'affichent dans l'en-tête et les courriels.",
                'champs' => [
                    'app.name' => ['libelle' => "Nom de l'application", 'type' => 'texte', 'max' => 80, 'requis' => true, 'aide' => 'Titre des onglets du navigateur et des messages.'],
                    'tpi.juridiction' => ['libelle' => 'Nom complet de la juridiction', 'type' => 'texte', 'max' => 120, 'requis' => true, 'aide' => 'Affiché dans les courriels envoyés par l\'application.'],
                    'tpi.sigle' => ['libelle' => 'Sigle', 'type' => 'texte', 'max' => 12, 'requis' => true, 'aide' => "Affiché en orange dans l'en-tête (ex. TPI)."],
                    'tpi.ville' => ['libelle' => 'Ville', 'type' => 'texte', 'max' => 40, 'requis' => true, 'aide' => "Affichée en vert dans l'en-tête (ex. BINGERVILLE)."],
                ],
            ],
            'securite' => [
                'libelle' => 'Sécurité',
                'icone' => 'fa-solid fa-shield-halved',
                'description' => 'Délais et limites de la connexion. Des valeurs plus courtes ou plus basses sont plus sûres ; des valeurs plus longues sont plus confortables.',
                'champs' => [
                    'securite.inactivite_minutes' => ['libelle' => 'Verrouillage après inactivité', 'type' => 'entier', 'min' => 1, 'max' => 240, 'unite' => 'minutes', 'aide' => 'Sans aucune action pendant ce délai, la session est verrouillée (mot de passe seul pour la rouvrir).'],
                    'securite.avertissement_secondes' => ['libelle' => 'Avertissement avant verrouillage', 'type' => 'entier', 'min' => 10, 'max' => 300, 'unite' => 'secondes', 'aide' => 'Une fenêtre avec compte à rebours s\'affiche ce temps avant le verrouillage, pour rester connecté d\'un clic.'],
                    'securite.ecran_verrouille_max_minutes' => ['libelle' => 'Durée maximale du verrouillage', 'type' => 'entier', 'min' => 5, 'max' => 1440, 'unite' => 'minutes', 'aide' => 'Au-delà, la session est fermée : connexion complète exigée (mot de passe et code par e-mail).'],
                    'securite.deverrouillage_essais_max' => ['libelle' => 'Essais de déverrouillage', 'type' => 'entier', 'min' => 3, 'max' => 10, 'unite' => 'essais', 'aide' => 'Mots de passe erronés autorisés sur l\'écran de verrouillage avant fermeture de la session.'],
                    'securite.double_auth.validite_minutes' => ['libelle' => 'Validité du code par e-mail', 'type' => 'entier', 'min' => 2, 'max' => 30, 'unite' => 'minutes', 'aide' => 'Durée pendant laquelle le code à 6 chiffres reçu par e-mail peut être saisi.'],
                    'securite.double_auth.tentatives_max' => ['libelle' => 'Essais du code par e-mail', 'type' => 'entier', 'min' => 3, 'max' => 10, 'unite' => 'essais', 'aide' => 'Codes erronés autorisés avant d\'invalider le code envoyé.'],
                    'securite.double_auth.renvoi_secondes' => ['libelle' => 'Délai avant un nouvel envoi du code', 'type' => 'entier', 'min' => 30, 'max' => 600, 'unite' => 'secondes', 'aide' => 'Temps à attendre entre deux envois du code.'],
                    'securite.echecs_max' => ['libelle' => 'Échecs de connexion avant verrouillage du compte', 'type' => 'entier', 'min' => 3, 'max' => 10, 'unite' => 'échecs', 'aide' => 'Mots de passe erronés successifs avant le verrouillage temporaire du compte.'],
                    'securite.verrouillage_minutes' => ['libelle' => 'Durée du verrouillage du compte', 'type' => 'entier', 'min' => 1, 'max' => 1440, 'unite' => 'minutes', 'aide' => 'Temps pendant lequel le compte reste bloqué après trop d\'échecs.'],
                    'securite.mot_de_passe_min' => ['libelle' => 'Longueur minimale du mot de passe', 'type' => 'entier', 'min' => 8, 'max' => 64, 'unite' => 'caractères', 'aide' => 'S\'applique aux nouveaux mots de passe (jamais en dessous de 8).'],
                ],
            ],
            'courriels' => [
                'libelle' => 'Courriels',
                'icone' => 'fa-solid fa-envelope',
                'description' => "Expéditeur et serveur de messagerie (SMTP) utilisés pour envoyer les courriels : codes de connexion, création de compte… Le mot de passe est enregistré chiffré et n'est jamais réaffiché.",
                'droit' => 'reglages.secrets',
                'champs' => [
                    'mail.from.name' => ['libelle' => "Nom de l'expéditeur", 'type' => 'texte', 'max' => 80, 'requis' => true, 'aide' => 'Nom affiché dans la boîte de réception des destinataires.'],
                    'mail.from.address' => ['libelle' => "Adresse de l'expéditeur", 'type' => 'texte', 'max' => 255, 'requis' => true, 'format' => 'email', 'aide' => "Adresse qui apparaît comme expéditeur. Avec la plupart des serveurs, elle doit être celle du compte utilisé pour l'envoi."],
                    'mail.default' => ['libelle' => "Mode d'envoi", 'type' => 'liste', 'options' => ['log' => "Test : les courriels sont écrits dans un fichier, rien n'est envoyé", 'smtp' => 'Envoi réel par le serveur SMTP'], 'aide' => 'En mode Test, personne ne reçoit les courriels (codes de connexion compris) : ils sont seulement écrits dans le journal du serveur.'],
                    'mail.mailers.smtp.host' => ['libelle' => 'Serveur SMTP', 'type' => 'texte', 'max' => 255, 'requis' => true, 'format' => 'hote', 'aide' => 'Adresse du serveur de messagerie (ex. smtp.gmail.com, mail.votre-domaine.ci).'],
                    'mail.mailers.smtp.port' => ['libelle' => 'Port', 'type' => 'entier', 'min' => 1, 'max' => 65535, 'unite' => 'port', 'aide' => 'En général 587 (chiffrement automatique) ou 465 (SSL/TLS direct).'],
                    'mail.mailers.smtp.scheme' => ['libelle' => 'Chiffrement', 'type' => 'liste', 'options' => ['smtp' => 'Automatique (STARTTLS, port 587)', 'smtps' => 'SSL/TLS direct (port 465)'], 'aide' => 'À choisir selon le port indiqué par votre fournisseur.'],
                    'mail.mailers.smtp.username' => ['libelle' => 'Adresse e-mail du compte (identifiant)', 'type' => 'texte', 'max' => 255, 'requis' => false, 'aide' => "Identifiant de connexion au serveur, le plus souvent l'adresse e-mail complète."],
                    'mail.mailers.smtp.password' => ['libelle' => 'Mot de passe du compte', 'type' => 'secret', 'max' => 255, 'aide' => "Enregistré chiffré. Avec Gmail ou Microsoft 365, utilisez un « mot de passe d'application », pas le mot de passe de la boîte."],
                ],
            ],
            'affaires' => [
                'libelle' => 'Affaires',
                'icone' => 'fa-solid fa-folder-open',
                'description' => "Comment les numéros d'affaire sont obtenus, série par série. À Bingerville, le greffe a son propre logiciel d'enrôlement : on reporte ses numéros à la main. Un TPI qui enrôle dans l'application choisit l'attribution automatique.",
                'champs' => [
                    'tpi.enrolement.numero.rg' => ['libelle' => 'Numéro RG (civil)', 'type' => 'liste', 'options' => self::MODES_NUMERO, 'aide' => "Mode « saisi à la main » : le numéro est celui du greffe, sans contrôle de la date d'audience. Mode automatique : numérotation sans doublon ni trou, date limite d'enrôlement contrôlée."],
                    'tpi.enrolement.numero.rp' => ['libelle' => 'Numéro RP (pénal)', 'type' => 'liste', 'options' => self::MODES_NUMERO, 'aide' => 'Le numéro RP est normalement celui du parquet : à saisir à la main, sauf si le TPI préfère un numéro généré.'],
                    'tpi.enrolement.numero.ri' => ['libelle' => 'Numéro RI (instruction, adultes)', 'type' => 'liste', 'options' => self::MODES_NUMERO, 'aide' => "Registre des cabinets d'instruction pour les adultes."],
                    'tpi.enrolement.numero.re' => ['libelle' => 'Numéro RE (instruction, enfants)', 'type' => 'liste', 'options' => self::MODES_NUMERO, 'aide' => 'Registre des cabinets des juges des enfants.'],
                    'tpi.enrolement.numero.re' => ['libelle' => 'Numéro RE (instruction, enfants)', 'type' => 'liste', 'options' => self::MODES_NUMERO, 'aide' => 'Registre des cabinets des juges des enfants.'],
                    'tpi.penal.detention_jours' => ['libelle' => 'Durée du mandat de dépôt avant échéance', 'type' => 'entier', 'min' => 1, 'max' => 1500, 'unite' => 'jours', 'aide' => "Sert à calculer l'échéance d'un détenu quand elle n'est pas saisie. Valeur à valider par le tribunal (durée de la détention préventive selon la nature de l'infraction)."],
                    'tpi.penal.alerte_jours' => ['libelle' => "Alerte avant l'échéance d'un détenu", 'type' => 'entier', 'min' => 1, 'max' => 365, 'unite' => 'jours', 'aide' => "Un détenu dont l'échéance tombe dans ce délai passe en orange dans la liste des détenus."],
                    'tpi.requetes.delai_repartition_jours' => ['libelle' => "Délai de répartition d'une requête", 'type' => 'entier', 'min' => 1, 'max' => 30, 'unite' => 'jours', 'aide' => "Une requête non répartie après ce délai est signalée « en retard » au secrétariat de la Présidente. Valeur à valider par le tribunal."],
                    'tpi.reprise.taille_max_mo' => ['libelle' => "Taille maximale d'un classeur de reprise (Excel)", 'type' => 'entier', 'min' => 1, 'max' => 50, 'unite' => 'Mo', 'aide' => "Un classeur de plusieurs milliers de lignes pèse quelques Mo. Le serveur PHP peut en accepter moins (upload_max_filesize dans php.ini)."],
                    'tpi.dossier.taille_max_mo' => ['libelle' => "Taille maximale d'une pièce du dossier numérique", 'type' => 'entier', 'min' => 1, 'max' => 100, 'unite' => 'Mo', 'aide' => "PDF ou images. Le serveur peut en accepter moins : si besoin, relever upload_max_filesize et post_max_size dans php.ini."],
                ],
            ],
            'actes' => [
                'libelle' => 'Impression des actes',
                'icone' => 'fa-solid fa-print',
                'description' => "Police et tailles des décisions imprimées ou exportées (corps du texte et timbre). Si la police n'est pas installée sur le poste, une police de remplacement est utilisée.",
                'champs' => [
                    'tpi.actes.police_corps' => ['libelle' => 'Police du corps', 'type' => 'texte', 'max' => 60, 'requis' => true, 'format' => 'police', 'aide' => 'Nom de la police du texte de la décision (ex. Centaur).'],
                    'tpi.actes.taille_corps' => ['libelle' => 'Taille du corps', 'type' => 'entier', 'min' => 8, 'max' => 40, 'unite' => 'points', 'aide' => 'Taille du texte de la décision.'],
                    'tpi.actes.police_timbre' => ['libelle' => 'Police du timbre', 'type' => 'texte', 'max' => 60, 'requis' => true, 'format' => 'police', 'aide' => "Nom de la police de l'en-tête de l'acte (timbre)."],
                    'tpi.actes.taille_timbre' => ['libelle' => 'Taille du timbre', 'type' => 'entier', 'min' => 8, 'max' => 40, 'unite' => 'points', 'aide' => "Taille du texte de l'en-tête de l'acte."],
                    'tpi.actes.interligne_qualite_pct' => ['libelle' => 'Interligne de la qualité', 'type' => 'entier', 'min' => 100, 'max' => 200, 'unite' => '%', 'aide' => "100 : interligne simple, comme le modèle Word de la qualité."],
                    'tpi.actes.interligne_factum_pct' => ['libelle' => 'Interligne du factum', 'type' => 'entier', 'min' => 100, 'max' => 200, 'unite' => '%', 'aide' => 'Les documents Word du factum alternent 100, 107 et 115 : 110 en est la moyenne.'],
                    'tpi.actes.marge_haut_mm' => ['libelle' => 'Marge du haut', 'type' => 'entier', 'min' => 5, 'max' => 80, 'unite' => 'mm', 'aide' => 'Page A4.'],
                    'tpi.actes.marge_bas_mm' => ['libelle' => 'Marge du bas', 'type' => 'entier', 'min' => 5, 'max' => 80, 'unite' => 'mm', 'aide' => 'Page A4.'],
                    'tpi.actes.marge_gauche_mm' => ['libelle' => 'Marge de gauche', 'type' => 'entier', 'min' => 5, 'max' => 80, 'unite' => 'mm', 'aide' => 'Page A4.'],
                    'tpi.actes.marge_droite_mm' => ['libelle' => 'Marge de droite', 'type' => 'entier', 'min' => 5, 'max' => 80, 'unite' => 'mm', 'aide' => 'Page A4.'],
                    'tpi.actes.timbre_largeur_mm' => ['libelle' => 'Largeur du timbre', 'type' => 'entier', 'min' => 30, 'max' => 120, 'unite' => 'mm', 'aide' => "Colonne de gauche de la première page : cour d'appel, tribunal, numéros, parties. 57 mm dans le modèle Word de la qualité."],
                    'tpi.actes.timbre_hauteur_mm' => ['libelle' => 'Hauteur du timbre', 'type' => 'entier', 'min' => 30, 'max' => 250, 'unite' => 'mm', 'aide' => 'Le texte du timbre y est centré verticalement.'],
                    'tpi.actes.timbre_gauche_mm' => ['libelle' => 'Distance du timbre au bord gauche', 'type' => 'entier', 'min' => 1, 'max' => 60, 'unite' => 'mm', 'aide' => 'Mesurée depuis le bord de la feuille.'],
                    'tpi.actes.timbre_haut_mm' => ['libelle' => 'Distance du timbre au bord haut', 'type' => 'entier', 'min' => 1, 'max' => 120, 'unite' => 'mm', 'aide' => 'Mesurée depuis le haut de la feuille.'],
                    'tpi.actes.retrait_qualite_mm' => ['libelle' => 'Retrait du texte de la qualité', 'type' => 'entier', 'min' => 1, 'max' => 120, 'unite' => 'mm', 'aide' => 'Le texte de la qualité commence à droite du timbre ; le factum occupe toute la largeur.'],
                ],
            ],
            'decisions' => [
                'libelle' => 'Décisions',
                'icone' => 'fa-solid fa-file-signature',
                'description' => "Numérotation des décisions : un numéro par année, sans trou ni doublon, attribué automatiquement au moment choisi.",
                'champs' => [
                    'tpi.decisions.numero_moment' => ['libelle' => 'Moment où le numéro est attribué', 'type' => 'liste', 'options' => \App\Services\NumeroDecision::MOMENTS, 'aide' => "À la validation (choix du tribunal) : le numéro D-N/année est généré automatiquement quand le juge valide la décision après son factum. Par le greffier de la chambre : le greffier donne le numéro par un bouton, quand le juge a terminé sa rédaction ; la validation exige alors ce numéro. À la signature : le numéro est donné quand la décision est signée (le texte validé garde un numéro en pointillés jusque-là, puis le numéro est inscrit). À la validation : le numéro figure dès le texte validé. Valeur à confirmer avec la greffière en chef : une fois des numéros attribués, ne la changez qu'en connaissance de cause."],
                ],
            ],
            'pilotage' => [
                'libelle' => 'Pilotage et IA',
                'icone' => 'fa-solid fa-wand-magic-sparkles',
                'description' => "Analyse et recommandations par intelligence artificielle sur la page Analyse et pilotage. Facultatif : sans IA, une analyse automatique reste disponible. La clé d'API ne se saisit pas ici : elle se place dans le fichier .env du serveur (PILOTAGE_IA_CLE). Seuls des totaux (nombres d'affaires, délais, retards par chambre) sont transmis, jamais un nom de partie, un numéro d'affaire ni un document.",
                'droit' => 'reglages.secrets',
                'champs' => [
                    'pilotage.ia_active' => ['libelle' => "Analyse par l'IA", 'type' => 'liste', 'options' => ['non' => "Non : analyse automatique seulement", 'oui' => "Oui : proposer l'analyse par l'IA"], 'aide' => "Même activée, l'IA ne part que sur demande (bouton « Analyser avec l'IA ») et dans la limite quotidienne ci-dessous."],
                    'pilotage.ia_modele' => ['libelle' => 'Modèle', 'type' => 'texte', 'max' => 60, 'requis' => true, 'aide' => "Identifiant du modèle à utiliser (ex. claude-sonnet-5-5)."],
                    'pilotage.ia_generations_par_jour' => ['libelle' => "Analyses par jour (au plus)", 'type' => 'entier', 'min' => 1, 'max' => 200, 'unite' => 'analyses', 'aide' => "Plafond pour maîtriser le coût : au-delà, l'analyse automatique reste disponible."],
                ],
            ],
            'interface' => [
                'libelle' => 'Interface',
                'icone' => 'fa-solid fa-table-list',
                'description' => "Confort d'utilisation.",
                'champs' => [
                    'tpi.lignes_par_page' => ['libelle' => 'Lignes par page dans les tableaux', 'type' => 'liste', 'options' => [10 => '10', 25 => '25', 50 => '50', 100 => '100'], 'aide' => 'Nombre de lignes affichées avant la pagination.'],
                ],
            ],
        ];
    }

    /** Tous les réglages, à plat : clé => définition (avec sa rubrique). */
    public static function champs(): array
    {
        $tous = [];
        foreach (self::catalogue() as $rubrique => $def) {
            foreach ($def['champs'] as $cle => $champ) {
                $tous[$cle] = $champ + ['rubrique' => $rubrique];
            }
        }

        return $tous;
    }

    /** Droit supplémentaire exigé par une rubrique (null : aucun). */
    public static function droitRubrique(string $rubrique): ?string
    {
        return self::catalogue()[$rubrique]['droit'] ?? null;
    }

    public static function estSecret(array $champ): bool
    {
        return $champ['type'] === 'secret';
    }

    /** Nom du champ de formulaire : les points d'une clé sont remplacés (ils gênent la validation). */
    public static function nomChamp(string $cle): string
    {
        return str_replace('.', '__', $cle);
    }

    /** Nom de la case « effacer » d'un réglage secret. */
    public static function nomEffacer(string $cle): string
    {
        return self::nomChamp($cle).'__effacer';
    }

    /** Pose dans config() les réglages enregistrés. Appelé au démarrage de l'application. */
    public function appliquer(): void
    {
        $enregistres = $this->lire();

        foreach (self::champs() as $cle => $champ) {
            $this->originaux[$cle] ??= config($cle);

            if (! array_key_exists($cle, $enregistres) || $enregistres[$cle] === null) {
                continue;
            }
            try {
                config([$cle => $this->lireValeur($champ, (string) $enregistres[$cle])]);
            } catch (Throwable) {
                // valeur secrète illisible (clé de l'application changée) : on garde la valeur par défaut
            }
        }
    }

    public function defaut(string $cle): mixed
    {
        return $this->originaux[$cle] ?? config($cle);
    }

    /** Ce réglage a-t-il été modifié (différent du défaut) ? */
    public function estModifie(string $cle): bool
    {
        return array_key_exists($cle, $this->lire());
    }

    /**
     * Enregistre les valeurs d'une rubrique. Retourne les réglages réellement changés : cle => [avant, apres].
     * Une valeur identique au défaut supprime la ligne (le défaut suit alors le fichier de configuration).
     * Pour un réglage secret : valeur non vide = nouveau secret ; '' = effacer. (Un champ laissé vide pour
     * « conserver » ne doit simplement pas être transmis.) Les secrets ne sont jamais retournés en clair.
     *
     * @param  array<string, mixed>  $valeurs  cle => valeur déjà validée
     * @return array<string, array{0: mixed, 1: mixed}>
     */
    public function enregistrer(array $valeurs, ?User $par): array
    {
        $changes = [];
        $champs = self::champs();

        foreach ($valeurs as $cle => $valeur) {
            if (! isset($champs[$cle])) {
                continue;   // jamais un réglage hors catalogue
            }
            $champ = $champs[$cle];
            $secret = self::estSecret($champ);
            $valeur = $this->convertir($champ, $valeur);
            $avant = config($cle);
            if ($valeur === $avant) {
                continue;
            }

            if ($valeur === $this->defaut($cle)) {
                DB::table('reglages')->where('cle', $cle)->delete();
            } else {
                DB::table('reglages')->upsert(
                    [['cle' => $cle, 'valeur' => $this->ecrireValeur($champ, $valeur), 'modifie_par' => $par?->id, 'created_at' => now(), 'updated_at' => now()]],
                    ['cle'], ['valeur', 'modifie_par', 'updated_at'],
                );
            }
            config([$cle => $valeur]);
            $changes[$cle] = $secret
                ? [filled($avant) ? self::MASQUE_DEFINI : self::MASQUE_VIDE, filled($valeur) ? self::MASQUE_MODIFIE : self::MASQUE_EFFACE]
                : [$avant, $valeur];
        }

        Cache::forget(self::CACHE);

        return $changes;
    }

    /** Rétablit les valeurs par défaut d'une rubrique. Retourne les réglages changés (secrets masqués). */
    public function reinitialiser(string $rubrique): array
    {
        $changes = [];
        foreach (self::catalogue()[$rubrique]['champs'] ?? [] as $cle => $champ) {
            $avant = config($cle);
            $defaut = $this->defaut($cle);
            DB::table('reglages')->where('cle', $cle)->delete();
            config([$cle => $defaut]);
            if ($avant !== $defaut) {
                $changes[$cle] = self::estSecret($champ)
                    ? [filled($avant) ? self::MASQUE_DEFINI : self::MASQUE_VIDE, filled($defaut) ? self::MASQUE_DEFINI : self::MASQUE_VIDE]
                    : [$avant, $defaut];
            }
        }
        Cache::forget(self::CACHE);

        return $changes;
    }

    /** Dernière modification d'un réglage : auteur et date. */
    public function derniereModification(string $cle): ?object
    {
        return DB::table('reglages')->leftJoin('users', 'users.id', '=', 'reglages.modifie_par')
            ->where('reglages.cle', $cle)->select('reglages.updated_at', 'users.name as auteur')->first();
    }

    /** @return array<string, string|null> valeurs enregistrées (les secrets y sont chiffrés) */
    private function lire(): array
    {
        try {
            return Cache::rememberForever(self::CACHE, fn () => DB::table('reglages')->pluck('valeur', 'cle')->all());
        } catch (Throwable) {
            return [];   // table absente (installation, migration) ou base indisponible : valeurs par défaut
        }
    }

    /** Valeur saisie / enregistrée -> type attendu par la configuration. */
    private function convertir(array $champ, mixed $valeur): mixed
    {
        return match ($champ['type']) {
            'entier' => (int) $valeur,
            'liste' => array_is_list(array_keys($champ['options'])) && is_int(array_key_first($champ['options'])) ? (int) $valeur : (string) $valeur,
            'secret' => ((string) $valeur) === '' ? null : (string) $valeur,
            default => (($champ['requis'] ?? true) === false && ((string) $valeur) === '') ? null : (string) $valeur,
        };
    }

    /** Valeur à écrire en base (les secrets sont chiffrés ; une valeur vide reste une chaîne vide). */
    private function ecrireValeur(array $champ, mixed $valeur): string
    {
        $texte = (string) ($valeur ?? '');

        return self::estSecret($champ) && $texte !== '' ? Crypt::encryptString($texte) : $texte;
    }

    /** Valeur lue en base -> valeur de configuration (déchiffre les secrets). */
    private function lireValeur(array $champ, string $stockee): mixed
    {
        if (self::estSecret($champ)) {
            return $stockee === '' ? null : Crypt::decryptString($stockee);
        }

        return $this->convertir($champ, $stockee);
    }
}
