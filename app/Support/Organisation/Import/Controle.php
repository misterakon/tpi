<?php

namespace App\Support\Organisation\Import;

use App\Models\Affectation;
use App\Models\Agent;
use App\Models\AnneeJudiciaire;
use App\Models\ArticleOrdonnance;
use App\Models\Chambre;
use App\Models\Creneau;
use App\Models\Formation;
use App\Models\Ordonnance;
use App\Models\Salle;
use App\Models\Section;
use App\Models\Service;
use Illuminate\Support\Str;

/*
 | Contrôle du classeur de l'ordonnance, SANS rien écrire en base. Trois résultats :
 |   - « donnees » : le classeur normalisé (valeurs nettoyées, références résolues) ;
 |   - « controle » : erreurs (bloquent l'application), avertissements (à lire), comptes par bloc, noms à confirmer ;
 |   - les références (agent, chambre, formation, service…) sont des CLÉS : « id:12 » (existe en base) ou « new:L7 » (créé par ce fichier, ligne 7).
 |
 | Une référence s'écrit par son N° (l'identifiant de la base) ou par son nom ; un nom d'agent douteux n'est jamais deviné (voir Noms).
 | Fonction pure vis-à-vis de la base : on peut la relancer autant de fois que l'on corrige le fichier ou les rapprochements.
 */
class Controle
{
    private const SEUIL_AUTRE_BASE = 0.3;

    private array $err = [];

    private array $avert = [];

    private array $comptes = [];

    private array $noms = [];          // clé de nom => ['texte', 'cle', 'emplacements' => [[feuille, ligne]], 'propositions']

    private array $d = [];             // données normalisées

    private ?AnneeJudiciaire $annee = null;

    /** Le fichier vient d'une autre base : ses N° sont ignorés, tout est rapproché par les noms. */
    private bool $autreBase = false;

    /** @var array<int, Agent> */
    private array $agentsBase = [];

    /** @var array<string, int> */
    private array $alias = [];

    /** Index de résolution par type : ['id' => [n => clé], 'nom' => [nom normalisé => clé], 'fichier_n' => [n => clé]] */
    private array $index = [];

    /** @param array{feuilles: array, inconnues: array, colonnes: array} $classeur
     *  @param array<string, array> $rapprochements choix de l'utilisateur, par clé de nom */
    public static function executer(array $classeur, string $mode, array $rapprochements = [], ?callable $progres = null): array
    {
        $c = new self;
        $c->progres = $progres;

        return $c->analyser($classeur, $mode, $rapprochements);
    }

    private $progres = null;

    private function jalon(int $fait, int $total, string $message): void
    {
        if ($this->progres) {
            ($this->progres)('controle', $fait, $total, $message);
        }
    }

    private function analyser(array $classeur, string $mode, array $rapprochements): array
    {
        $f = $classeur['feuilles'];
        [$this->agentsBase, $this->alias] = Noms::charger();

        foreach ($classeur['inconnues'] as $nom) {
            $this->avert('Classeur', null, "La feuille « $nom » n'est pas reconnue : elle est ignorée.");
        }
        if (! array_intersect(array_keys($f), array_diff(Classeur::FEUILLES, ['ordonnance', 'remarques']))) {
            $this->erreur('Classeur', null, 'Aucune feuille de données reconnue (Chambres, Composition, Services…). Utilisez le fichier téléchargé depuis Organisation > Ordonnances.');
        }
        foreach ($f as $cle => $lignes) {
            $this->comptes[$cle] = count($lignes);
        }

        $this->ordonnance($f['ordonnance'] ?? null);
        $this->colonnesRequises($classeur);
        $this->autreBase($f['agents'] ?? []);
        $this->construireIndex($f);

        $this->jalon(0, 12, 'Contrôle des sections…');
        $this->sections($f['sections'] ?? []);
        $this->jalon(1, 12, 'Contrôle des salles…');
        $this->salles($f['salles'] ?? []);
        $this->jalon(2, 12, 'Contrôle des agents…');
        $this->agents($f['agents'] ?? []);
        $this->jalon(3, 12, 'Contrôle des chambres…');
        $this->chambres($f['chambres'] ?? []);
        $this->jalon(4, 12, 'Contrôle des formations…');
        $this->formations($f['formations'] ?? []);
        $this->jalon(5, 12, 'Contrôle de la composition et rapprochement des noms…');
        $this->composition($f['composition'] ?? [], $rapprochements);
        $this->jalon(8, 12, 'Contrôle des audiences…');
        $this->audiences($f['audiences'] ?? []);
        $this->jalon(9, 12, 'Contrôle des services…');
        $this->services($f['services'] ?? []);
        $this->responsables($f['responsables'] ?? [], $rapprochements);
        $this->jalon(10, 12, 'Contrôle des membres, dates limites et articles…');
        $this->membres($f['membres_exterieurs'] ?? []);
        $this->datesLimites($f['dates_limites'] ?? []);
        $this->articles($f['articles'] ?? [], $f);
        $this->jalon(12, 12, 'Contrôle terminé');
        $this->d['remarques'] = array_values(array_filter(array_map(fn ($l) => $this->txt($l['remarque'] ?? null), $f['remarques'] ?? [])));

        $this->coherence($mode);

        $this->d['feuilles'] = array_keys($f);
        $noms = array_values($this->noms);
        usort($noms, fn ($a, $b) => strcmp($a['texte'], $b['texte']));

        return [
            'donnees' => $this->d,
            'controle' => [
                'erreurs' => $this->err, 'avertissements' => $this->avert, 'comptes' => $this->comptes, 'noms' => $noms, 'autre_base' => $this->autreBase,
                'peut_continuer' => $this->err === [] && $noms === [],
            ],
        ];
    }

    // ------------------------------------------------------------------ outils

    private function erreur(string $feuille, ?int $ligne, string $message): void
    {
        $this->err[] = ['feuille' => $feuille, 'ligne' => $ligne, 'message' => $message];
    }

    private function avert(string $feuille, ?int $ligne, string $message): void
    {
        $this->avert[] = ['feuille' => $feuille, 'ligne' => $ligne, 'message' => $message];
    }

    private function txt(mixed $v): ?string
    {
        $t = trim((string) ($v ?? ''));

        return $t === '' ? null : $t;
    }

    private function entier(mixed $v): ?int
    {
        $t = $this->txt($v);

        return $t !== null && preg_match('/^\d{1,9}$/', $t) ? (int) $t : null;
    }

    private function booleen(mixed $v, bool $defaut = false): bool
    {
        $t = Str::lower(Str::ascii((string) ($v ?? '')));

        return match (true) {
            in_array($t, ['oui', 'o', 'yes', '1', 'x', 'vrai', 'true'], true) => true,
            in_array($t, ['non', 'n', 'no', '0', 'faux', 'false', ''], true) => false,
            default => $defaut,
        };
    }

    /** @return string|false|null  AAAA-MM-JJ, null si vide, false si illisible */
    private function date(mixed $v): string|false|null
    {
        $t = $this->txt($v);
        if ($t === null) {
            return null;
        }
        if (preg_match('#^(\d{1,2})[/.-](\d{1,2})[/.-](\d{4})$#', $t, $m) && checkdate((int) $m[2], (int) $m[1], (int) $m[3])) {
            return sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
        }
        if (preg_match('#^(\d{4})-(\d{2})-(\d{2})#', $t, $m) && checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            return "$m[1]-$m[2]-$m[3]";
        }

        return false;
    }

    /** @return string|false|null  HH:MM */
    private function heure(mixed $v): string|false|null
    {
        $t = $this->txt($v);
        if ($t === null) {
            return null;
        }
        if (preg_match('/(?:^|\s)(\d{1,2})\s*[:hH]\s*(\d{2})(?::\d{2})?$/', $t, $m) && (int) $m[1] < 24 && (int) $m[2] < 60) {
            return sprintf('%02d:%02d', $m[1], $m[2]);
        }

        return false;
    }

    private function jour(mixed $v): int|false|null
    {
        $t = Str::lower(Str::ascii((string) ($v ?? '')));
        if (trim($t) === '') {
            return null;
        }
        $jours = ['lundi' => 1, 'mardi' => 2, 'mercredi' => 3, 'jeudi' => 4, 'vendredi' => 5, 'samedi' => 6, 'dimanche' => 7];
        $t = trim($t);

        return $jours[$t] ?? (preg_match('/^[1-7]$/', $t) ? (int) $t : false);
    }

    /** Valeur d'une liste fermée, écrite par sa clé ou par son libellé. @param array<string, string> $liste */
    private function enumeration(mixed $v, array $liste): string|false|null
    {
        $t = $this->txt($v);
        if ($t === null) {
            return null;
        }
        $c = Classeur::cle($t);
        foreach ($liste as $cle => $libelle) {
            if ($c === Classeur::cle((string) $cle) || $c === Classeur::cle((string) $libelle)) {
                return (string) $cle;
            }
        }

        return false;
    }

    private function longueur(string $feuille, array $l, array $champs): void
    {
        foreach ($champs as $k) {
            if (isset($l[$k]) && mb_strlen((string) $l[$k]) >= 2000) {
                $this->avert($feuille, $l['_l'], 'Le texte de « '.$k.' » est peut-être coupé (2 000 caractères au plus par cellule).');
            }
        }
    }

    private function exiger(string $feuille, array $l, array $champs): bool
    {
        $ok = true;
        foreach ($champs as $k => $libelle) {
            if ($this->txt($l[$k] ?? null) === null) {
                $this->erreur($feuille, $l['_l'], "« $libelle » est vide.");
                $ok = false;
            }
        }

        return $ok;
    }

    // ------------------------------------------------------------------ ordonnance et colonnes

    private function ordonnance(?array $lignes): void
    {
        $kv = [];
        foreach ($lignes ?? [] as $l) {
            if ($this->txt($l['rubrique'] ?? null) !== null) {
                $kv[Classeur::cle((string) $l['rubrique'])] = $this->txt($l['valeur'] ?? null);
            }
        }
        $get = fn (string ...$cles) => collect($cles)->map(fn ($c) => $kv[$c] ?? null)->filter()->first();

        $libelle = $get('annee_judiciaire');
        if ($libelle === null) {
            $this->annee = AnneeJudiciaire::where('courante', true)->first();
            $this->avert('Ordonnance', null, "L'année judiciaire n'est pas indiquée : l'année en cours (".($this->annee?->libelle ?? '?').') est utilisée.');
        } else {
            $this->annee = AnneeJudiciaire::where('libelle', $libelle)->first();
        }

        $dates = [];
        foreach (['signature' => $get('date_de_signature'), 'effet' => $get('date_d_effet'), 'debut' => $get('debut_de_l_annee_judiciaire'), 'fin' => $get('fin_de_l_annee_judiciaire')] as $k => $v) {
            $r = $this->date($v);
            if ($r === false) {
                $this->erreur('Ordonnance', null, "La date « $k » est illisible (jj/mm/aaaa).");
            }
            $dates[$k] = $r ?: null;
        }
        $type = $this->enumeration($get('type'), Ordonnance::TYPES) ?: 'annuelle';

        $this->d['annee'] = ['libelle' => $libelle ?? $this->annee?->libelle, 'id' => $this->annee?->id, 'nouvelle' => $libelle !== null && ! $this->annee];
        $this->d['ordonnance'] = [
            'numero' => $get('n_de_l_ordonnance'), 'type' => $type, 'objet' => $get('objet'), 'observations' => $get('observations'),
        ] + $dates;

        if ($libelle !== null && ! $this->annee) {
            $this->avert('Ordonnance', null, "L'année judiciaire « $libelle » n'existe pas encore : elle sera créée, avec toutes ses chambres, formations et services.");
            if (! ($dates['debut'] ?? null) || ! ($dates['fin'] ?? null)) {
                $this->erreur('Ordonnance', null, "Pour créer l'année « $libelle », indiquez son début et sa fin.");
            }
        }
    }

    private function colonnesRequises(array $classeur): void
    {
        $requises = [
            'sections' => ['libelle'], 'salles' => ['nom'], 'agents' => ['nom'], 'chambres' => ['libelle', 'section'], 'formations' => ['chambre'],
            'composition' => ['formation', 'role', 'agent'], 'audiences' => ['formation', 'jour'], 'services' => ['categorie', 'libelle'],
            'responsables' => ['service', 'agent'], 'membres_exterieurs' => ['service', 'nom'], 'dates_limites' => ['jour_d_audience', 'jour_limite', 'heure'], 'articles' => ['numero', 'texte_complet'],
        ];
        foreach ($requises as $feuille => $cols) {
            if (! isset($classeur['colonnes'][$feuille])) {
                continue;
            }
            foreach (array_diff($cols, $classeur['colonnes'][$feuille]) as $manque) {
                $this->erreur(ucfirst(str_replace('_', ' ', $feuille)), 1, "La colonne « $manque » est introuvable : ne modifiez pas les en-têtes du fichier.");
            }
        }
    }

    // ------------------------------------------------------------------ index de résolution

    /** Prépare, pour chaque type, la table « N° ou nom → clé » avec les éléments de la base puis ceux du fichier. */
    private function construireIndex(array $f): void
    {
        $annee = $this->annee?->id;
        $bases = [
            'section' => Section::all(),
            'salle' => Salle::all(),
            'agent' => collect($this->agentsBase),
            'chambre' => $annee ? Chambre::where('annee_judiciaire_id', $annee)->get() : collect(),
            'formation' => $annee ? Formation::with('chambre')->whereIn('chambre_id', Chambre::where('annee_judiciaire_id', $annee)->select('id'))->get() : collect(),
            'service' => $annee ? Service::where('annee_judiciaire_id', $annee)->get() : collect(),
        ];
        $nomDe = [
            'section' => fn ($m) => $m->libelle, 'salle' => fn ($m) => $m->nom, 'agent' => fn ($m) => Noms::cle($m->nom.' '.$m->prenoms),
            'chambre' => fn ($m) => $m->libelle, 'formation' => fn ($m) => ($m->chambre?->libelle ?? '').'|'.($m->nom ?: 'unique'), 'service' => fn ($m) => $m->libelle,
        ];
        foreach ($bases as $type => $modeles) {
            foreach ($modeles as $m) {
                $this->index[$type]['id'][$m->id] = 'id:'.$m->id;
                $this->index[$type]['nom'][Classeur::cle($nomDe[$type]($m))] ??= 'id:'.$m->id;
            }
        }

        // Éléments du fichier : un N° connu de la base garde sa clé « id: » ; sinon « new:Lx » (ligne x).
        $feuilles = ['section' => 'sections', 'salle' => 'salles', 'agent' => 'agents', 'chambre' => 'chambres', 'formation' => 'formations', 'service' => 'services'];
        foreach ($feuilles as $type => $feuille) {
            foreach ($f[$feuille] ?? [] as $l) {
                $n = $this->entier($l['n'] ?? null);
                if (! $this->autreBase && $n !== null && isset($this->index[$type]['id'][$n])) {
                    $cle = 'id:'.$n;
                    $this->index[$type]['fichier_n'][$n] = $cle;
                } else {
                    $cle = 'new:'.$type.$l['_l'];
                    if ($n !== null) {
                        $this->index[$type]['fichier_n'][$n] = $cle;     // N° d'une autre année : les lignes qui s'y réfèrent suivent ce nouvel élément
                    }
                }
                $this->index[$type]['ligne'][$l['_l']] = $cle;
            }
        }
    }

    /** Un N° de la base qui ne correspond pas à la personne écrite : le fichier vient probablement d'une autre base. */
    private function autreBase(array $agents): void
    {
        $verifies = 0;
        $incoherents = 0;
        foreach ($agents as $l) {
            $n = $this->entier($l['n'] ?? null);
            if ($n === null || ! isset($this->agentsBase[$n])) {
                continue;
            }
            $verifies++;
            $a = $this->agentsBase[$n];
            if (Noms::cle(($l['nom'] ?? '').' '.($l['prenoms'] ?? '')) !== Noms::cle($a->nom.' '.$a->prenoms) && Noms::score(($l['nom'] ?? '').' '.($l['prenoms'] ?? ''), $a->nom.' '.$a->prenoms) < 0.6) {
                $incoherents++;
            }
        }
        if ($verifies >= 3 && $incoherents / $verifies >= self::SEUIL_AUTRE_BASE) {
            $this->autreBase = true;
            $this->avert('Agents', null, "Ce fichier semble venir d'une autre base de données ($incoherents des $verifies N° ne correspondent pas aux personnes de cette base). Ses N° sont ignorés : chambres, formations, services, salles et personnes sont rapprochés par leur NOM. Lisez bien l'écran de comparaison avant d'appliquer.");
        }
    }

    /**
     * Résout une référence (N° ou nom) vers une clé. Retourne la clé, ou null (erreur déjà signalée).
     *
     * @param  string  $type  section, salle, agent, chambre, formation, service
     * @param  ?string  $nomCle  clé « nom » pour un nom ; pour une formation « chambre|nom »
     */
    private function ref(string $type, string $feuille, int $ligne, mixed $valeur, bool $obligatoire = true): ?string
    {
        $t = $this->txt($valeur);
        if ($t === null) {
            if ($obligatoire) {
                $this->erreur($feuille, $ligne, 'La référence « '.$type.' » est vide.');
            }

            return null;
        }
        $n = $this->entier($t);
        if ($n !== null) {
            $cle = $this->index[$type]['fichier_n'][$n] ?? ($this->autreBase ? null : ($this->index[$type]['id'][$n] ?? null));
            if ($cle === null) {
                $this->erreur($feuille, $ligne, "Le N° $n n'existe pas ($type). Pour un élément nouveau, écrivez son nom.");
            }

            return $cle;
        }
        $cle = $this->index[$type]['nom'][Classeur::cle($t)] ?? null;
        if ($cle === null && $type === 'agent') {
            return null;   // les agents sont traités par résolutionAgent()
        }
        if ($cle === null) {
            // nom d'un élément défini dans le fichier sans N°
            $cle = $this->index[$type]['nom_fichier'][Classeur::cle($t)] ?? null;
        }
        if ($cle === null) {
            $this->erreur($feuille, $ligne, "« $t » est introuvable ($type). Vérifiez l'écriture, ou ajoutez-le dans la feuille correspondante.");
        }

        return $cle;
    }

    /** Résolution d'un agent par N° ou nom, avec rapprochement des noms douteux. */
    private function agent(string $feuille, int $ligne, mixed $valeur, array $rapprochements): ?string
    {
        $t = $this->txt($valeur);
        if ($t === null) {
            $this->erreur($feuille, $ligne, "La colonne « Agent » est vide.");

            return null;
        }
        $n = $this->entier($t);
        if ($n !== null) {
            $cle = $this->index['agent']['fichier_n'][$n] ?? ($this->autreBase ? null : ($this->index['agent']['id'][$n] ?? null));
            if ($cle === null) {
                $this->erreur($feuille, $ligne, "Le N° $n n'est pas un agent connu. Pour une personne nouvelle, écrivez son nom.");
            }

            return $cle;
        }

        $cleNom = Noms::cle($t);
        // Agent défini dans la feuille Agents du fichier (sans N°) ?
        if (isset($this->index['agent']['nom_fichier'][$cleNom])) {
            return $this->index['agent']['nom_fichier'][$cleNom];
        }
        if (isset($rapprochements[$cleNom])) {
            $r = $rapprochements[$cleNom];
            if (($r['action'] ?? '') === 'agent' && isset($this->agentsBase[(int) ($r['id'] ?? 0)])) {
                return 'id:'.(int) $r['id'];
            }
            if (($r['action'] ?? '') === 'creer') {
                $this->d['agents_a_creer'][$cleNom] ??= self::decouper($t);

                return 'nom:'.$cleNom;
            }
            if (($r['action'] ?? '') === 'ignorer') {
                $this->avert($feuille, $ligne, "« $t » ignoré à votre demande : cette ligne ne sera pas chargée.");

                return null;
            }
        }

        $res = Noms::chercher($t, $this->agentsBase, $this->alias);
        if ($res['agent'] !== null) {
            return 'id:'.$res['agent'];
        }
        $this->noms[$cleNom] ??= ['texte' => $t, 'cle' => $cleNom, 'emplacements' => [], 'propositions' => $res['propositions']];
        $this->noms[$cleNom]['emplacements'][] = [$feuille, $ligne];

        return null;
    }

    /** « N'GUESSAN Appa Brigitte » => nom en majuscules, prénoms le reste. @return array{nom: string, prenoms: string} */
    public static function decouper(string $texte): array
    {
        $texte = trim(preg_replace('/^(M\.|Mme|Mlle|Me|Maître)\s+/iu', '', trim($texte)));
        $mots = preg_split('/\s+/u', $texte, -1, PREG_SPLIT_NO_EMPTY);
        $nom = [];
        while ($mots && mb_strtoupper($mots[0]) === $mots[0] && preg_match('/\p{L}/u', $mots[0])) {
            $nom[] = array_shift($mots);
        }
        if (! $nom) {
            $nom[] = array_shift($mots) ?? $texte;
        }

        return ['nom' => implode(' ', $nom), 'prenoms' => implode(' ', $mots)];
    }

    // ------------------------------------------------------------------ feuilles

    private function sections(array $lignes): void
    {
        $this->d['sections'] = [];
        $vus = [];
        foreach ($lignes as $l) {
            if (! $this->exiger('Sections', $l, ['libelle' => 'Libellé'])) {
                continue;
            }
            $this->doublon('Sections', $l, $vus);
            $this->index['section']['nom_fichier'][Classeur::cle($l['libelle'])] = $this->index['section']['ligne'][$l['_l']];
            $this->d['sections'][] = ['l' => $l['_l'], 'cle' => $this->index['section']['ligne'][$l['_l']], 'libelle' => $this->txt($l['libelle']), 'ordre' => $this->entier($l['ordre'] ?? null)];
        }
    }

    private function salles(array $lignes): void
    {
        $this->d['salles'] = [];
        foreach ($lignes as $l) {
            if (! $this->exiger('Salles', $l, ['nom' => 'Nom'])) {
                continue;
            }
            $type = $this->enumeration($l['type'] ?? null, Salle::TYPES);
            if ($type === false) {
                $this->erreur('Salles', $l['_l'], 'Type de salle inconnu : « '.$l['type'].' » (audience, cabinet ou reunion).');
            }
            $this->index['salle']['nom_fichier'][Classeur::cle($l['nom'])] = $this->index['salle']['ligne'][$l['_l']];
            $this->d['salles'][] = ['l' => $l['_l'], 'cle' => $this->index['salle']['ligne'][$l['_l']], 'nom' => $this->txt($l['nom']), 'type' => $type ?: 'audience', 'emplacement' => $this->txt($l['emplacement'] ?? null)];
        }
    }

    private function agents(array $lignes): void
    {
        $this->d['agents'] = [];
        foreach ($lignes as $l) {
            if (! $this->exiger('Agents', $l, ['nom' => 'Nom'])) {
                continue;
            }
            $sexe = $this->txt($l['sexe'] ?? null);
            $sexe = $sexe !== null ? Str::upper(mb_substr($sexe, 0, 1)) : null;
            if ($sexe !== null && ! in_array($sexe, ['M', 'F'], true)) {
                $this->erreur('Agents', $l['_l'], 'Sexe : M ou F.');
                $sexe = null;
            }
            $fonction = $this->enumeration($l['fonction'] ?? null, Agent::FONCTIONS);
            if ($fonction === false) {
                $this->erreur('Agents', $l['_l'], 'Fonction inconnue : « '.$l['fonction'].' ».');
                $fonction = null;
            }
            $categorie = $this->enumeration($l['categorie'] ?? null, array_map(fn ($c) => $c['libelle'], Agent::CATEGORIES));
            if ($categorie === false) {
                $this->erreur('Agents', $l['_l'], 'Catégorie inconnue : « '.$l['categorie'].' ».');
                $categorie = null;
            }
            $email = $this->txt($l['e_mail'] ?? null);
            if ($email !== null && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->erreur('Agents', $l['_l'], "Adresse e-mail invalide : « $email ».");
                $email = null;
            }
            $cle = $this->index['agent']['ligne'][$l['_l']];
            $this->index['agent']['nom_fichier'][Noms::cle(($l['nom'] ?? '').' '.($l['prenoms'] ?? ''))] ??= $cle;
            $this->d['agents'][] = [
                'l' => $l['_l'], 'cle' => $cle, 'nom' => $this->txt($l['nom']), 'prenoms' => $this->txt($l['prenoms'] ?? null), 'sexe' => $sexe, 'fonction' => $fonction, 'categorie' => $categorie,
                'matricule' => $this->txt($l['matricule'] ?? null), 'telephone' => $this->txt($l['telephone'] ?? null), 'email' => $email,
            ];
        }
    }

    private function chambres(array $lignes): void
    {
        $this->d['chambres'] = [];
        $vus = [];
        foreach ($lignes as $l) {
            if (! $this->exiger('Chambres', $l, ['libelle' => 'Libellé', 'section' => 'Section'])) {
                continue;
            }
            $this->longueur('Chambres', $l, ['attributions']);
            $this->doublon('Chambres', $l, $vus);
            $cle = $this->index['chambre']['ligne'][$l['_l']];
            $this->index['chambre']['nom_fichier'][Classeur::cle($l['libelle'])] = $cle;
            $section = $this->ref('section', 'Chambres', $l['_l'], $l['section']);
            $this->d['chambres'][] = [
                'l' => $l['_l'], 'cle' => $cle, 'section' => $section, 'groupe' => $this->txt($l['groupe'] ?? null), 'libelle' => $this->txt($l['libelle']), 'intitule' => $this->txt($l['intitule'] ?? null),
                'attributions' => $this->txt($l['attributions'] ?? null), 'remarque' => $this->txt($l['remarque'] ?? null), 'ordre' => $this->entier($l['ordre'] ?? null),
            ];
        }
    }

    private function formations(array $lignes): void
    {
        $this->d['formations'] = [];
        foreach ($lignes as $l) {
            if (! $this->exiger('Formations', $l, ['chambre' => 'Chambre'])) {
                continue;
            }
            $this->longueur('Formations', $l, ['attributions_propres']);
            $chambre = $this->ref('chambre', 'Formations', $l['_l'], $l['chambre']);
            $cle = $this->index['formation']['ligne'][$l['_l']];
            $nom = $this->txt($l['nom'] ?? null);
            // « Chambre|nom » : permet d'écrire une formation par son nom dans Composition et Audiences.
            $libChambre = $this->libelleChambre($chambre);
            if ($libChambre !== null) {
                $this->index['formation']['nom_fichier'][Classeur::cle($libChambre.'|'.($nom ?: 'unique'))] = $cle;
                if ($nom === null) {
                    $this->index['formation']['nom_fichier'][Classeur::cle($libChambre)] ??= $cle;
                }
            }
            $this->d['formations'][] = [
                'l' => $l['_l'], 'cle' => $cle, 'chambre' => $chambre, 'nom' => $nom, 'intitule' => $this->txt($l['intitule'] ?? null),
                'attributions' => $this->txt($l['attributions_propres'] ?? null), 'ordre' => $this->entier($l['ordre'] ?? null),
            ];
        }
    }

    private function libelleChambre(?string $cle): ?string
    {
        if ($cle === null) {
            return null;
        }
        foreach ($this->d['chambres'] ?? [] as $c) {
            if ($c['cle'] === $cle) {
                return $c['libelle'];
            }
        }
        if (str_starts_with($cle, 'id:')) {
            return Chambre::find((int) substr($cle, 3))?->libelle;
        }

        return null;
    }

    private function composition(array $lignes, array $rapprochements): void
    {
        $this->d['composition'] = [];
        $vus = [];
        $existants = $lignes ? \App\Models\Affectation::pluck('id')->flip()->all() : [];
        foreach ($lignes as $l) {
            if (! $this->exiger('Composition', $l, ['formation' => 'Formation', 'role' => 'Rôle', 'agent' => 'Agent'])) {
                continue;
            }
            $n = $this->autreBase ? null : $this->entier($l['n'] ?? null);
            if ($n !== null && ! isset($existants[$n])) {
                $this->erreur('Composition', $l['_l'], "Le N° $n n'est pas une affectation connue : laissez-le vide pour une nouvelle ligne.");
            }
            $formation = $this->ref('formation', 'Composition', $l['_l'], $l['formation']);
            $role = $this->enumeration($l['role'], Affectation::ROLES);
            if ($role === false) {
                $this->erreur('Composition', $l['_l'], 'Rôle inconnu : « '.$l['role'].' ». Valeurs : '.implode(', ', array_keys(Affectation::ROLES)).'.');
            }
            $agent = $this->agent('Composition', $l['_l'], $l['agent'], $rapprochements);
            $du = $this->date($l['du'] ?? null);
            $au = $this->date($l['au'] ?? null);
            foreach (['du' => $du, 'au' => $au] as $k => $v) {
                if ($v === false) {
                    $this->erreur('Composition', $l['_l'], "La date « $k » est illisible (jj/mm/aaaa).");
                }
            }
            if ($du && $au && $du > $au) {
                $this->erreur('Composition', $l['_l'], 'La date « du » est après la date « au ».');
            }
            $suppleant = $this->booleen($l['suppleant'] ?? null);
            $cle = implode('|', [$formation, $role, $agent, $suppleant ? 's' : 't', $du ?: '']);
            if ($formation && $role && $agent && isset($vus[$cle])) {
                $this->avert('Composition', $l['_l'], 'Même personne, même rôle et même formation que la ligne '.$vus[$cle].' : doublon.');
            }
            $vus[$cle] = $l['_l'];
            if ($formation && $role && $agent) {
                $this->d['composition'][] = [
                    'l' => $l['_l'], 'n' => $n, 'formation' => $formation, 'role' => $role, 'suppleant' => $suppleant, 'agent' => $agent, 'chef' => $this->booleen($l['chef_de_section'] ?? null),
                    'du' => $du ?: null, 'au' => $au ?: null, 'remarque' => $this->txt($l['remarque'] ?? null),
                ];
            }
        }
    }

    private function audiences(array $lignes): void
    {
        $this->d['audiences'] = [];
        $existants = $lignes ? Creneau::pluck('id')->flip()->all() : [];
        foreach ($lignes as $l) {
            if (! $this->exiger('Audiences', $l, ['formation' => 'Formation'])) {
                continue;
            }
            $n = $this->autreBase ? null : $this->entier($l['n'] ?? null);
            if ($n !== null && ! isset($existants[$n])) {
                $this->erreur('Audiences', $l['_l'], "Le N° $n n'est pas un créneau connu : laissez-le vide pour une nouvelle ligne.");
            }
            $formation = $this->ref('formation', 'Audiences', $l['_l'], $l['formation']);
            $jour = $this->jour($l['jour'] ?? null);
            if ($jour === false) {
                $this->erreur('Audiences', $l['_l'], 'Jour illisible : « '.$l['jour'].' » (lundi à dimanche).');
            }
            $heure = $this->heure($l['heure'] ?? null);
            if ($heure === false) {
                $this->erreur('Audiences', $l['_l'], 'Heure illisible : « '.$l['heure'].' » (hh:mm).');
            }
            $frequence = $this->enumeration($l['frequence'] ?? null, Creneau::FREQUENCES) ?: 'hebdomadaire';
            if ($this->txt($l['frequence'] ?? null) !== null && $this->enumeration($l['frequence'], Creneau::FREQUENCES) === false) {
                $this->erreur('Audiences', $l['_l'], 'Fréquence inconnue : « '.$l['frequence'].' ».');
            }
            $salle = $this->ref('salle', 'Audiences', $l['_l'], $l['salle'] ?? null, false);
            $du = $this->date($l['du'] ?? null);
            $au = $this->date($l['au'] ?? null);
            if ($du === false || $au === false) {
                $this->erreur('Audiences', $l['_l'], 'Date « du » ou « au » illisible (jj/mm/aaaa).');
            }
            if ($jour === null && in_array($frequence, ['hebdomadaire', 'trimestrielle'], true)) {
                $this->erreur('Audiences', $l['_l'], 'Le jour est vide : il est obligatoire pour une audience ' . ($frequence === 'hebdomadaire' ? 'hebdomadaire' : 'trimestrielle') . ' (laissez-le vide seulement pour « a_la_demande »).');
            }
            if ($formation && $jour !== false) {
                $this->d['audiences'][] = [
                    'l' => $l['_l'], 'n' => $n, 'formation' => $formation, 'type' => $this->txt($l['type'] ?? null) ?? 'Audience', 'jour' => $jour ?: null, 'heure' => $heure ?: null, 'frequence' => $frequence,
                    'salle' => $salle, 'precision' => $this->txt($l['precision'] ?? null), 'du' => $du ?: null, 'au' => $au ?: null,
                ];
            }
        }
    }

    private function services(array $lignes): void
    {
        $this->d['services'] = [];
        $vus = [];
        foreach ($lignes as $l) {
            if (! $this->exiger('Services', $l, ['categorie' => 'Catégorie', 'libelle' => 'Libellé'])) {
                continue;
            }
            $cat = $this->enumeration($l['categorie'], Service::CATEGORIES);
            if ($cat === false) {
                $this->erreur('Services', $l['_l'], 'Catégorie inconnue : « '.$l['categorie'].' ». Valeurs : '.implode(', ', array_keys(Service::CATEGORIES)).'.');
            }
            $this->longueur('Services', $l, ['remarque_ou_modalite']);
            $this->doublon('Services', $l, $vus);
            $cle = $this->index['service']['ligne'][$l['_l']];
            $this->index['service']['nom_fichier'][Classeur::cle($l['libelle'])] = $cle;
            $this->d['services'][] = ['l' => $l['_l'], 'cle' => $cle, 'categorie' => $cat ?: 'service', 'libelle' => $this->txt($l['libelle']), 'ordre' => $this->entier($l['ordre'] ?? null), 'remarque' => $this->txt($l['remarque_ou_modalite'] ?? null)];
        }
    }

    private function responsables(array $lignes, array $rapprochements): void
    {
        $this->d['responsables'] = [];
        foreach ($lignes as $l) {
            if (! $this->exiger('Responsables', $l, ['service' => 'Service', 'agent' => 'Agent'])) {
                continue;
            }
            $service = $this->ref('service', 'Responsables', $l['_l'], $l['service']);
            $agent = $this->agent('Responsables', $l['_l'], $l['agent'], $rapprochements);
            if ($service && $agent) {
                $this->d['responsables'][] = ['l' => $l['_l'], 'service' => $service, 'agent' => $agent, 'fonction' => $this->txt($l['fonction_exercee'] ?? null), 'ordre' => $this->entier($l['ordre'] ?? null)];
            }
        }
    }

    private function membres(array $lignes): void
    {
        $this->d['membres'] = [];
        foreach ($lignes as $l) {
            if (! $this->exiger('Membres extérieurs', $l, ['service' => 'Service', 'nom' => 'Nom'])) {
                continue;
            }
            $service = $this->ref('service', 'Membres extérieurs', $l['_l'], $l['service']);
            if ($service) {
                $this->d['membres'][] = ['l' => $l['_l'], 'service' => $service, 'nom' => $this->txt($l['nom']), 'qualite' => $this->txt($l['qualite'] ?? null), 'institution' => $this->txt($l['institution'] ?? null), 'ordre' => $this->entier($l['ordre'] ?? null)];
            }
        }
    }

    private function datesLimites(array $lignes): void
    {
        $this->d['dates_limites'] = [];
        $vus = [];
        foreach ($lignes as $l) {
            if (! $this->exiger('Dates limites', $l, ['jour_d_audience' => "Jour d'audience", 'jour_limite' => 'Jour limite', 'heure' => 'Heure'])) {
                continue;
            }
            $a = $this->jour($l['jour_d_audience']);
            $b = $this->jour($l['jour_limite']);
            $h = $this->heure($l['heure']);
            foreach ([['jour_d_audience', $a], ['jour_limite', $b]] as [$k, $v]) {
                if ($v === false) {
                    $this->erreur('Dates limites', $l['_l'], "Jour illisible dans « $k » (lundi à dimanche).");
                }
            }
            if ($h === false) {
                $this->erreur('Dates limites', $l['_l'], 'Heure illisible (hh:mm).');
            }
            if ($a && isset($vus[$a])) {
                $this->erreur('Dates limites', $l['_l'], "Le jour d'audience figure déjà à la ligne ".$vus[$a].'.');
            }
            $vus[$a] = $l['_l'];
            if ($a && $b && $h) {
                $this->d['dates_limites'][] = ['l' => $l['_l'], 'jour_audience' => $a, 'jour_limite' => $b, 'heure' => $h, 'chambres' => $this->txt($l['chambres_concernees'] ?? null), 'exclusions' => $this->txt($l['exclusions'] ?? null), 'remarque' => $this->txt($l['remarque'] ?? null)];
            }
        }
    }

    private function articles(array $lignes, array $f): void
    {
        $this->d['articles'] = [];
        foreach ($lignes as $l) {
            if (! $this->exiger('Articles', $l, ['numero' => 'Numéro', 'texte_complet' => 'Texte complet'])) {
                continue;
            }
            $this->longueur('Articles', $l, ['texte_complet']);
            $rat = $this->txt($l['rattache_a'] ?? null);
            if ($rat !== null) {
                $rat = Classeur::cle($rat);
                if (! array_key_exists($rat, ArticleOrdonnance::RATTACHEMENTS)) {
                    $this->avert('Articles', $l['_l'], "Rattachement inconnu : « $rat » (sections, composition, services, dates_limites, audiences ou vide).");
                    $rat = null;
                } elseif (empty($f[$rat === 'composition' ? 'composition' : $rat]) && in_array($rat, ['composition', 'services', 'dates_limites', 'audiences', 'sections'], true)) {
                    $this->avert('Articles', $l['_l'], 'Cet article est rattaché à la feuille « '.str_replace('_', ' ', $rat).' », qui est vide ou absente du fichier.');
                }
            }
            $this->d['articles'][] = ['l' => $l['_l'], 'numero' => $this->txt($l['numero']), 'intitule' => $this->txt($l['intitule'] ?? null), 'texte' => $this->txt($l['texte_complet']), 'rattache_a' => $rat];
        }
    }

    private function doublon(string $feuille, array $l, array &$vus): void
    {
        $n = $this->entier($l['n'] ?? null);
        if ($n !== null) {
            if (isset($vus["n$n"])) {
                $this->erreur($feuille, $l['_l'], "Le N° $n figure déjà à la ligne ".$vus["n$n"].'.');
            }
            $vus["n$n"] = $l['_l'];
        }
    }

    /** Vérifications entre feuilles (une formation sans président, par exemple). */
    private function coherence(string $mode): void
    {
        if (! empty($this->d['composition'])) {
            $presidents = [];
            foreach ($this->d['composition'] as $c) {
                if (in_array($c['role'], ['president', 'juge', 'juge_delegue'], true) && ! $c['suppleant']) {
                    $presidents[$c['formation']] = true;
                }
            }
            foreach ($this->d['formations'] ?? [] as $fo) {
                if (! isset($presidents[$fo['cle']]) && ($mode === 'complet' || str_starts_with($fo['cle'], 'new:'))) {
                    $this->avert('Formations', $fo['l'], 'Aucun président ni juge n\'est indiqué dans Composition pour cette formation.');
                }
            }
        }
        if ($mode === 'complet') {
            foreach (['chambres', 'formations', 'composition'] as $obligatoire) {
                if (empty($this->d[$obligatoire])) {
                    $this->erreur(ucfirst($obligatoire), null, "Mode « ordonnance complète » : la feuille « $obligatoire » est vide ou absente.");
                }
            }
        }
    }
}
