<?php

namespace App\Support\Organisation\Import;

use App\Models\Agent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/*
 | Compare le classeur contrôlé (Controle) avec l'organisation enregistrée et dresse la liste des CHANGEMENTS : créer, modifier, retirer.
 | Ne rien écrire : le calcul peut être refait à tout moment (il l'est juste avant l'application, pour ne jamais appliquer sur du périmé).
 |
 | Règles :
 |   - une cellule vide veut dire « non renseigné » : elle ne modifie jamais une valeur existante ;
 |   - un élément du fichier sans N° est rapproché de l'élément de même nom de la base avant d'être créé (pas de doublon) ;
 |   - en mode « complet », ce qui n'est plus dans le fichier est RETIRÉ : affectations et créneaux clôturés (date de fin), chambres et formations
 |     désactivées, responsables et membres de services ôtés. Sections, salles, agents, articles et dates limites ne sont jamais retirés ;
 |   - une valeur à écrire qui désigne un élément créé par le même fichier s'écrit ['_ref' => clé] : l'application la remplace par le numéro obtenu.
 |
 | Un changement : sig (signature stable), bloc, action (creer|modifier|retirer), table, id (existant), cle, libelle, champs (valeurs à écrire),
 | details (avant → après, pour l'écran), mode (pour un retrait : 'maj' met à jour une ligne, 'supprime' l'ôte).
 */
class Comparateur
{
    /** Ordre d'application (les parents avant les enfants) et libellés d'écran. */
    public const BLOCS = [
        'annee' => 'Année judiciaire', 'sections' => 'Sections', 'salles' => 'Salles', 'agents' => 'Agents', 'chambres' => 'Chambres', 'formations' => 'Formations',
        'composition' => 'Composition', 'audiences' => 'Audiences', 'services' => 'Services', 'responsables' => 'Responsables', 'membres' => 'Membres extérieurs',
        'dates_limites' => 'Dates limites', 'articles' => 'Articles',
    ];

    private array $ch = [];

    private array $cle2id = [];     // clé du fichier => id existant (élément « new: » retrouvé en base par son nom)

    private array $d;

    private string $mode;

    private ?int $anneeId;

    private string $effet;

    /** @return list<array<string, mixed>> */
    public static function calculer(array $donnees, string $mode): array
    {
        return (new self)->faire($donnees, $mode);
    }

    private function faire(array $d, string $mode): array
    {
        $this->d = $d;
        $this->mode = $mode;
        $this->anneeId = $d['annee']['id'] ?? null;
        $o = $d['ordonnance'] ?? [];
        $this->effet = $o['effet'] ?? $o['signature'] ?? today()->toDateString();

        $this->annee();
        $this->sections();
        $this->salles();
        $this->agents();
        $this->chambres();
        $this->formations();
        $this->services();
        $this->composition();
        $this->audiences();
        $this->responsables();
        $this->membres();
        $this->datesLimites();
        $this->articles();

        $ordre = array_flip(array_keys(self::BLOCS));
        usort($this->ch, fn ($a, $b) => [$ordre[$a['bloc']], $a['action'] === 'creer' ? 0 : 1] <=> [$ordre[$b['bloc']], $b['action'] === 'creer' ? 0 : 1]);

        return $this->ch;
    }

    // ------------------------------------------------------------------ outils

    private function norm(mixed $v): mixed
    {
        if ($v === null || $v === '') {
            return null;
        }
        if (is_bool($v)) {
            return (int) $v;
        }
        if (is_numeric($v) && ! is_string($v)) {
            return (int) $v;
        }
        $t = trim((string) $v);
        if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $t)) {
            return substr($t, 0, 5);
        }

        return $t === '' ? null : (ctype_digit($t) && strlen($t) < 10 && $t === (string) (int) $t ? (int) $t : $t);
    }

    /** Écarts entre une ligne existante et les valeurs du fichier (valeurs vides ignorées). @return array{0: array, 1: array} [champs à écrire, détails] */
    private function ecarts(?object $avant, array $apres, array $libelles): array
    {
        $champs = [];
        $details = [];
        foreach ($libelles as $col => $lib) {
            if (! array_key_exists($col, $apres)) {
                continue;
            }
            $v = $apres[$col];
            $ref = is_array($v);
            if (! $ref && $this->norm($v) === null) {
                continue;
            }
            $a = $avant ? ($avant->$col ?? null) : null;
            if (! $ref && $this->norm($a) === $this->norm($v)) {
                continue;
            }
            $champs[$col] = $ref ? $v : (is_bool($v) ? (int) $v : $v);
            $details[] = [$lib, $a === null || $a === '' ? null : $this->aff($a), $ref ? '(élément créé par ce fichier)' : $this->aff(is_bool($v) ? ($v ? 'oui' : 'non') : $v)];
        }

        return [$champs, $details];
    }

    /** Valeur d'écran : dates en jj/mm/aaaa, heures en hh:mm. */
    private function aff(mixed $v): string
    {
        $t = (string) $v;
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $t, $m)) {
            return "$m[3]/$m[2]/$m[1]";
        }
        if (preg_match('/^(\d{2}:\d{2}):\d{2}$/', $t, $m)) {
            return $m[1];
        }

        return $t;
    }

    private function fk(?string $cle): int|array|null
    {
        if ($cle === null) {
            return null;
        }
        if (str_starts_with($cle, 'id:')) {
            return (int) substr($cle, 3);
        }

        return $this->cle2id[$cle] ?? ['_ref' => $cle];
    }

    private function ajouter(string $bloc, string $action, string $table, ?int $id, ?string $cle, string $libelle, array $champs, array $details, string $mode = 'maj', string $nat = ''): void
    {
        $this->ch[] = [
            'sig' => substr(sha1($bloc.'|'.$action.'|'.$table.'|'.($id ?? $cle ?? '').'|'.$nat), 0, 16), 'bloc' => $bloc, 'action' => $action, 'table' => $table, 'id' => $id, 'cle' => $cle,
            'libelle' => $libelle, 'champs' => $champs, 'details' => $details, 'mode' => $mode,
        ];
    }

    private function creation(array $champs, array $libelles): array
    {
        $details = [];
        foreach ($libelles as $col => $lib) {
            if (isset($champs[$col]) && $champs[$col] !== null && $champs[$col] !== '' && ! is_array($champs[$col])) {
                $details[] = [$lib, null, (string) $champs[$col]];
            }
        }

        return $details;
    }

    /** Libellé lisible d'une référence, pour l'écran. */
    private function nomDe(string $table, string $col, mixed $ref): string
    {
        if (is_array($ref)) {
            return '(nouveau)';
        }

        return (string) (DB::table($table)->where('id', $ref)->value($col) ?? '?');
    }

    // ------------------------------------------------------------------ blocs

    private function annee(): void
    {
        $a = $this->d['annee'] ?? [];
        $o = $this->d['ordonnance'] ?? [];
        if (! empty($a['nouvelle'])) {
            $this->ajouter('annee', 'creer', 'annees_judiciaires', null, 'annee', 'Année judiciaire '.$a['libelle'], [
                'libelle' => $a['libelle'], 'debut' => $o['debut'] ?? null, 'fin' => $o['fin'] ?? null, 'ordonnance' => isset($o['numero']) ? 'Ordonnance n°'.$o['numero'] : null, 'courante' => 0,
            ], [['Libellé', null, $a['libelle']], ['Début', null, $o['debut'] ?? '?'], ['Fin', null, $o['fin'] ?? '?'], ['Ordonnance', null, $o['numero'] ?? '—'], ['Année courante', null, 'non (à choisir dans Années judiciaires)']]);

            return;
        }
        if (! $this->anneeId) {
            return;
        }
        $avant = DB::table('annees_judiciaires')->find($this->anneeId);
        [$champs, $details] = $this->ecarts($avant, ['debut' => $o['debut'] ?? null, 'fin' => $o['fin'] ?? null], ['debut' => 'Début', 'fin' => 'Fin']);
        if ($champs) {
            $this->ajouter('annee', 'modifier', 'annees_judiciaires', $this->anneeId, null, 'Année judiciaire '.$avant->libelle, $champs, $details);
        }
    }

    private function slugUnique(string $table, string $col, string $base): string
    {
        $racine = Str::slug($base, '_') ?: 'x';
        $code = $racine;
        for ($i = 2; DB::table($table)->where($col, $code)->exists() || $this->codePris($code); $i++) {
            $code = $racine.'_'.$i;
        }
        $this->codes[$code] = true;

        return $code;
    }

    private array $codes = [];

    private function codePris(string $c): bool
    {
        return isset($this->codes[$c]);
    }

    private function sections(): void
    {
        $base = DB::table('sections')->get()->keyBy('id');
        $parNom = $base->mapWithKeys(fn ($s) => [Classeur::cle($s->libelle) => $s->id]);
        foreach ($this->d['sections'] ?? [] as $s) {
            $id = str_starts_with($s['cle'], 'id:') ? (int) substr($s['cle'], 3) : ($parNom[Classeur::cle($s['libelle'])] ?? null);
            if ($id) {
                $this->cle2id[$s['cle']] = $id;
                [$champs, $det] = $this->ecarts($base[$id], ['libelle' => $s['libelle'], 'ordre' => $s['ordre']], ['libelle' => 'Libellé', 'ordre' => 'Ordre']);
                if ($champs) {
                    $this->ajouter('sections', 'modifier', 'sections', $id, null, $base[$id]->libelle, $champs, $det);
                }
            } else {
                $champs = ['code' => $this->slugUnique('sections', 'code', $s['libelle']), 'libelle' => $s['libelle'], 'ordre' => $s['ordre'] ?? 0];
                $this->ajouter('sections', 'creer', 'sections', null, $s['cle'], $s['libelle'], $champs, $this->creation($champs, ['libelle' => 'Libellé', 'ordre' => 'Ordre']));
            }
        }
    }

    private function salles(): void
    {
        $base = DB::table('salles')->get()->keyBy('id');
        $parNom = $base->mapWithKeys(fn ($s) => [Classeur::cle($s->nom) => $s->id]);
        foreach ($this->d['salles'] ?? [] as $s) {
            $id = str_starts_with($s['cle'], 'id:') ? (int) substr($s['cle'], 3) : ($parNom[Classeur::cle($s['nom'])] ?? null);
            $lib = ['nom' => 'Nom', 'type' => 'Type', 'emplacement' => 'Emplacement'];
            if ($id) {
                $this->cle2id[$s['cle']] = $id;
                [$champs, $det] = $this->ecarts($base[$id], $s, $lib);
                if ($champs) {
                    $this->ajouter('salles', 'modifier', 'salles', $id, null, $base[$id]->nom, $champs, $det);
                }
            } else {
                $champs = ['nom' => $s['nom'], 'type' => $s['type'], 'emplacement' => $s['emplacement'], 'actif' => 1];
                $this->ajouter('salles', 'creer', 'salles', null, $s['cle'], $s['nom'], $champs, $this->creation($champs, $lib));
            }
        }
    }

    private function agents(): void
    {
        $base = DB::table('agents')->whereNull('deleted_at')->get()->keyBy('id');
        $parNom = $base->mapWithKeys(fn ($a) => [Noms::cle($a->nom.' '.$a->prenoms) => $a->id]);
        $lib = ['nom' => 'Nom', 'prenoms' => 'Prénoms', 'sexe' => 'Sexe', 'fonction' => 'Fonction', 'categorie' => 'Catégorie', 'matricule' => 'Matricule', 'telephone' => 'Téléphone', 'email' => 'E-mail'];
        foreach ($this->d['agents'] ?? [] as $a) {
            $id = str_starts_with($a['cle'], 'id:') ? (int) substr($a['cle'], 3) : ($parNom[Noms::cle($a['nom'].' '.$a['prenoms'])] ?? null);
            $nom = trim($a['nom'].' '.$a['prenoms']);
            if ($id && isset($base[$id])) {
                $this->cle2id[$a['cle']] = $id;
                [$champs, $det] = $this->ecarts($base[$id], $a, $lib);
                if ($champs) {
                    $this->ajouter('agents', 'modifier', 'agents', $id, null, trim($base[$id]->nom.' '.$base[$id]->prenoms), $champs, $det);
                }
            } else {
                $this->ajouter('agents', 'creer', 'agents', null, $a['cle'], $nom, $this->champsAgent($a), $this->creation($this->champsAgent($a), $lib));
            }
        }
        foreach ($this->d['agents_a_creer'] ?? [] as $cleNom => $a) {
            $champs = $this->champsAgent($a);
            $this->ajouter('agents', 'creer', 'agents', null, 'nom:'.$cleNom, trim($a['nom'].' '.$a['prenoms']), $champs, $this->creation($champs, $lib));
        }
    }

    private function champsAgent(array $a): array
    {
        $fonction = $a['fonction'] ?? 'autre';
        $categorie = $a['categorie'] ?? null;
        if ($categorie === null) {
            foreach (Agent::CATEGORIES as $cle => $c) {
                if (in_array($fonction, $c['fonctions'], true)) {
                    $categorie = $cle;
                }
            }
        }

        return [
            'nom' => $a['nom'], 'prenoms' => $a['prenoms'] ?? '', 'sexe' => $a['sexe'] ?? null, 'fonction' => $fonction, 'categorie' => $categorie,
            'matricule' => $a['matricule'] ?? null, 'telephone' => $a['telephone'] ?? null, 'email' => $a['email'] ?? null, 'actif' => 1,
        ];
    }

    private function chambres(): void
    {
        $base = $this->anneeId ? DB::table('chambres')->where('annee_judiciaire_id', $this->anneeId)->get()->keyBy('id') : collect();
        $parNom = $base->mapWithKeys(fn ($c) => [Classeur::cle($c->libelle) => $c->id]);
        $lib = ['section_id' => 'Section', 'groupe' => 'Groupe', 'libelle' => 'Libellé', 'intitule' => 'Intitulé', 'attributions' => 'Attributions', 'remarque' => 'Remarque', 'ordre' => 'Ordre'];
        $vus = [];
        foreach ($this->d['chambres'] ?? [] as $c) {
            $id = str_starts_with($c['cle'], 'id:') ? (int) substr($c['cle'], 3) : ($parNom[Classeur::cle($c['libelle'])] ?? null);
            $valeurs = ['section_id' => $this->fk($c['section']), 'groupe' => $c['groupe'], 'libelle' => $c['libelle'], 'intitule' => $c['intitule'], 'attributions' => $c['attributions'], 'remarque' => $c['remarque'], 'ordre' => $c['ordre']];
            if ($id && isset($base[$id])) {
                $this->cle2id[$c['cle']] = $id;
                $vus[$id] = true;
                [$champs, $det] = $this->ecarts($base[$id], $valeurs, $lib);
                $det = array_map(fn ($x) => $x[0] === 'Section' ? ['Section', $this->nomDe('sections', 'libelle', $base[$id]->section_id), is_array($valeurs['section_id']) ? $x[2] : $this->nomDe('sections', 'libelle', $valeurs['section_id'])] : $x, $det);
                if (! $base[$id]->actif) {
                    $champs['actif'] = 1;
                    $det[] = ['Actif', 'non', 'oui'];
                }
                if ($champs) {
                    $this->ajouter('chambres', 'modifier', 'chambres', $id, null, $base[$id]->libelle, $champs, $det);
                }
            } else {
                $champs = $valeurs + ['annee_judiciaire_id' => $this->anneeId ?? ['_ref' => 'annee'], 'actif' => 1];
                $champs['ordre'] ??= 0;
                $det = array_map(fn ($x) => $x[0] === 'Section' ? ['Section', null, $this->nomDe('sections', 'libelle', $champs['section_id'])] : $x, $this->creation($champs, $lib));
                $this->ajouter('chambres', 'creer', 'chambres', null, $c['cle'], $c['libelle'], $champs, $det);
            }
        }
        if ($this->mode === 'complet' && ! empty($this->d['chambres'])) {
            foreach ($base as $id => $c) {
                if (! isset($vus[$id]) && $c->actif) {
                    $this->ajouter('chambres', 'retirer', 'chambres', $id, null, $c->libelle, ['actif' => 0], [['Actif', 'oui', 'non (retirée de l\'ordonnance)']]);
                }
            }
        }
    }

    private function formations(): void
    {
        $chambresAnnee = $this->anneeId ? DB::table('chambres')->where('annee_judiciaire_id', $this->anneeId)->pluck('libelle', 'id') : collect();
        $base = $this->anneeId ? DB::table('formations')->whereIn('chambre_id', $chambresAnnee->keys())->get()->keyBy('id') : collect();
        $parNom = $base->mapWithKeys(fn ($f) => [Classeur::cle(($chambresAnnee[$f->chambre_id] ?? '').'|'.($f->nom ?: 'unique')) => $f->id]);
        $lib = ['chambre_id' => 'Chambre', 'nom' => 'Nom', 'intitule' => 'Intitulé', 'attributions' => 'Attributions propres', 'ordre' => 'Ordre'];
        $vus = [];
        foreach ($this->d['formations'] ?? [] as $f) {
            $chambre = $this->fk($f['chambre']);
            $nomChambre = is_int($chambre) ? ($chambresAnnee[$chambre] ?? '') : str_replace(' (nouveau)', '', $this->libelleNouveau('chambres', $f['chambre']));
            $id = str_starts_with($f['cle'], 'id:') ? (int) substr($f['cle'], 3) : ($parNom[Classeur::cle($nomChambre.'|'.($f['nom'] ?: 'unique'))] ?? null);
            $valeurs = ['chambre_id' => $chambre, 'nom' => $f['nom'], 'intitule' => $f['intitule'], 'attributions' => $f['attributions'], 'ordre' => $f['ordre']];
            $nom = ($nomChambre ?: 'Formation').($f['nom'] ? ' — '.$f['nom'] : '');
            if ($id && isset($base[$id])) {
                $this->cle2id[$f['cle']] = $id;
                $vus[$id] = true;
                [$champs, $det] = $this->ecarts($base[$id], $valeurs, $lib);
                $det = array_map(fn ($x) => $x[0] === 'Chambre' ? ['Chambre', $chambresAnnee[$base[$id]->chambre_id] ?? '?', is_array($chambre) ? $x[2] : ($chambresAnnee[$chambre] ?? $this->nomDe('chambres', 'libelle', $chambre))] : $x, $det);
                if (! $base[$id]->actif) {
                    $champs['actif'] = 1;
                    $det[] = ['Actif', 'non', 'oui'];
                }
                if ($champs) {
                    $this->ajouter('formations', 'modifier', 'formations', $id, null, $nom, $champs, $det);
                }
            } else {
                $champs = $valeurs + ['actif' => 1];
                $champs['ordre'] ??= 0;
                $det = array_map(fn ($x) => $x[0] === 'Chambre' ? ['Chambre', null, $nomChambre] : $x, $this->creation($champs, $lib));
                $this->ajouter('formations', 'creer', 'formations', null, $f['cle'], $nom, $champs, $det);
            }
        }
        if ($this->mode === 'complet' && ! empty($this->d['formations'])) {
            foreach ($base as $id => $f) {
                if (! isset($vus[$id]) && $f->actif) {
                    $this->ajouter('formations', 'retirer', 'formations', $id, null, ($chambresAnnee[$f->chambre_id] ?? '').($f->nom ? ' — '.$f->nom : ''), ['actif' => 0], [['Actif', 'oui', 'non (retirée de l\'ordonnance)']]);
                }
            }
        }
    }

    private function services(): void
    {
        $base = $this->anneeId ? DB::table('services')->where('annee_judiciaire_id', $this->anneeId)->get()->keyBy('id') : collect();
        $parNom = $base->mapWithKeys(fn ($s) => [Classeur::cle($s->libelle) => $s->id]);
        $lib = ['categorie' => 'Catégorie', 'libelle' => 'Libellé', 'ordre' => 'Ordre', 'remarque' => 'Remarque ou modalité'];
        foreach ($this->d['services'] ?? [] as $s) {
            $id = str_starts_with($s['cle'], 'id:') ? (int) substr($s['cle'], 3) : ($parNom[Classeur::cle($s['libelle'])] ?? null);
            if ($id && isset($base[$id])) {
                $this->cle2id[$s['cle']] = $id;
                [$champs, $det] = $this->ecarts($base[$id], $s, $lib);
                if ($champs) {
                    $this->ajouter('services', 'modifier', 'services', $id, null, $base[$id]->libelle, $champs, $det);
                }
            } else {
                $champs = ['categorie' => $s['categorie'], 'libelle' => $s['libelle'], 'ordre' => $s['ordre'] ?? 0, 'remarque' => $s['remarque'], 'annee_judiciaire_id' => $this->anneeId ?? ['_ref' => 'annee']];
                $this->ajouter('services', 'creer', 'services', null, $s['cle'], $s['libelle'], $champs, $this->creation($champs, $lib));
            }
        }
    }

    private function composition(): void
    {
        if (! array_key_exists('composition', $this->d) || ! in_array('composition', $this->d['feuilles'] ?? [], true)) {
            return;
        }
        $formationsAnnee = $this->anneeId ? DB::table('formations')->whereIn('chambre_id', DB::table('chambres')->where('annee_judiciaire_id', $this->anneeId)->pluck('id'))->pluck('id') : collect();
        $base = DB::table('affectations')->whereIn('formation_id', $formationsAnnee)->orderBy('id')->get();
        $baseParId = $base->keyBy('id');
        $parCle = [];
        foreach ($base as $a) {
            $parCle[$a->formation_id.'|'.$a->agent_id.'|'.$a->role.'|'.(int) $a->suppleant][] = $a;
        }
        $agents = DB::table('agents')->pluck(DB::raw("concat(nom, ' ', prenoms)"), 'id');
        $formations = $this->nomsFormations();
        $lib = ['chef_section' => 'Chef de section', 'debut' => 'Du', 'fin' => 'Au', 'remarque' => 'Remarque'];
        $apparies = [];

        foreach ($this->d['composition'] as $c) {
            $fo = $this->fk($c['formation']);
            $ag = $this->fk($c['agent']);
            $qui = (is_int($ag) ? ($agents[$ag] ?? '?') : $this->libelleNouveau('agents', $c['agent'])).' — '.$c['role'].($c['suppleant'] ? ' (suppléant)' : '').' — '.(is_int($fo) ? ($formations[$fo] ?? '?') : $this->libelleNouveau('formations', $c['formation']));
            $trouve = null;
            $parNumero = false;
            if (($c['n'] ?? null) && isset($baseParId[$c['n']])) {
                $trouve = $baseParId[$c['n']];
                $parNumero = true;
                // Une autre personne, un autre rôle ou une autre formation sur la même ligne : c'est un remplacement. L'ancienne affectation est clôturée
                // (l'historique des rôles et décisions est conservé) et une nouvelle est créée.
                if ($fo !== (int) $trouve->formation_id || $ag !== (int) $trouve->agent_id || $c['role'] !== $trouve->role || (int) $c['suppleant'] !== (int) $trouve->suppleant) {
                    $apparies[$trouve->id] = true;
                    if ($trouve->fin === null || $trouve->fin > $this->effet) {
                        $fin = $trouve->debut && $trouve->debut > $this->effet ? $trouve->debut : $this->effet;
                        $this->ajouter('composition', 'retirer', 'affectations', $trouve->id, null, ($agents[$trouve->agent_id] ?? '?').' — '.$trouve->role.' — '.($formations[$trouve->formation_id] ?? '?'), ['fin' => $fin], [['Fin d\'affectation', $trouve->fin, $this->aff($fin)]]);
                    }
                    $trouve = null;
                    $parNumero = false;
                }
            }
            if (! $trouve && is_int($fo) && is_int($ag)) {
                $cands = $parCle[$fo.'|'.$ag.'|'.$c['role'].'|'.(int) $c['suppleant']] ?? [];
                $trouve = $c['du'] ? collect($cands)->first(fn ($x) => $x->debut === $c['du']) : collect($cands)->sortByDesc(fn ($x) => $x->fin === null ? '9999' : $x->fin)->first();
            }
            if ($trouve) {
                $apparies[$trouve->id] = true;
                $apres = ['chef_section' => $c['chef'] ? 1 : null, 'debut' => $c['du'], 'fin' => $c['au'], 'remarque' => $c['remarque']];
                if (! $c['chef'] && $trouve->chef_section) {
                    $apres['chef_section'] = 0;   // « non » écrit clairement dans le fichier : on retire la marque
                }
                [$champs, $det] = $this->ecarts($trouve, $apres, $lib);
                $det = array_map(fn ($x) => $x[0] === 'Chef de section' ? ['Chef de section', $trouve->chef_section ? 'oui' : 'non', $x[2] === '1' ? 'oui' : 'non'] : $x, $det);
                if ($champs) {
                    $this->ajouter('composition', 'modifier', 'affectations', $trouve->id, null, $qui, $champs, $det);
                }
            } else {
                $champs = [
                    'formation_id' => $fo, 'agent_id' => $ag, 'role' => $c['role'], 'suppleant' => (int) $c['suppleant'], 'chef_section' => (int) $c['chef'],
                    'debut' => $c['du'] ?? $this->effet, 'fin' => $c['au'], 'remarque' => $c['remarque'],
                ];
                $this->ajouter('composition', 'creer', 'affectations', null, 'c'.$c['l'], $qui, $champs, [['Affecté', null, 'à partir du '.$this->aff($champs['debut'])]], 'maj', (string) $c['l']);
            }
        }
        if ($this->mode === 'complet') {
            foreach ($base as $a) {
                if (! isset($apparies[$a->id]) && ($a->fin === null || $a->fin > $this->effet)) {
                    $fin = $a->debut && $a->debut > $this->effet ? $a->debut : $this->effet;
                    $this->ajouter('composition', 'retirer', 'affectations', $a->id, null,
                        ($agents[$a->agent_id] ?? '?').' — '.$a->role.($a->suppleant ? ' (suppléant)' : '').' — '.($formations[$a->formation_id] ?? '?'),
                        ['fin' => $fin], [['Fin d\'affectation', $a->fin, date('d/m/Y', strtotime($fin))]]);
                }
            }
        }
    }

    private function audiences(): void
    {
        if (! in_array('audiences', $this->d['feuilles'] ?? [], true)) {
            return;
        }
        $formationsAnnee = $this->anneeId ? DB::table('formations')->whereIn('chambre_id', DB::table('chambres')->where('annee_judiciaire_id', $this->anneeId)->pluck('id'))->pluck('id') : collect();
        $base = DB::table('creneaux')->whereIn('formation_id', $formationsAnnee)->orderBy('id')->get();
        $baseParId = $base->keyBy('id');
        $parCle = [];
        foreach ($base as $x) {
            $parCle[$x->formation_id.'|'.Classeur::cle($x->type).'|'.($x->jour ?? '').'|'.substr((string) $x->heure, 0, 5)][] = $x;
        }
        $formations = $this->nomsFormations();
        $jours = [1 => 'lundi', 2 => 'mardi', 3 => 'mercredi', 4 => 'jeudi', 5 => 'vendredi', 6 => 'samedi', 7 => 'dimanche'];
        $lib = ['frequence' => 'Fréquence', 'salle_id' => 'Salle', 'precision' => 'Précision', 'debut' => 'Du', 'fin' => 'Au'];
        $apparies = [];

        foreach ($this->d['audiences'] as $a) {
            $fo = $this->fk($a['formation']);
            $nomFo = is_int($fo) ? ($formations[$fo] ?? '?') : $this->libelleNouveau('formations', $a['formation']);
            $qui = $a['type'].' — '.($jours[$a['jour']] ?? 'à la demande').($a['heure'] ? ' '.$a['heure'] : '').' — '.$nomFo;
            $trouve = ($a['n'] ?? null) && isset($baseParId[$a['n']]) ? $baseParId[$a['n']] : null;
            if (! $trouve && is_int($fo)) {
                $cands = $parCle[$fo.'|'.Classeur::cle($a['type']).'|'.($a['jour'] ?? '').'|'.($a['heure'] ?? '')] ?? [];
                $trouve = $a['du'] ? collect($cands)->first(fn ($x) => $x->debut === $a['du']) : collect($cands)->sortByDesc(fn ($x) => $x->fin === null ? '9999' : $x->fin)->first();
            }
            $salle = $this->fk($a['salle']);
            if ($trouve) {
                $apparies[$trouve->id] = true;
                $apres = ['frequence' => $a['frequence'], 'salle_id' => $salle, 'precision' => $a['precision'], 'debut' => $a['du'], 'fin' => $a['au']];
                $libs = $lib;
                if ($a['n'] ?? null) {   // ligne identifiée par son N° : tout peut se corriger (type, jour, heure, formation)
                    $apres += ['type' => $a['type'], 'jour' => $a['jour'], 'heure' => $a['heure'], 'formation_id' => $fo];
                    $libs += ['type' => 'Type', 'jour' => 'Jour (1 = lundi)', 'heure' => 'Heure', 'formation_id' => 'Formation'];
                }
                [$champs, $det] = $this->ecarts($trouve, $apres, $libs);
                $det = array_map(fn ($x) => $x[0] === 'Salle' ? ['Salle', $trouve->salle_id ? $this->nomDe('salles', 'nom', $trouve->salle_id) : null, is_array($salle) ? $x[2] : $this->nomDe('salles', 'nom', $salle)] : $x, $det);
                if ($champs) {
                    $this->ajouter('audiences', 'modifier', 'creneaux', $trouve->id, null, $qui, $champs, $det);
                }
            } else {
                $champs = ['formation_id' => $fo, 'type' => $a['type'], 'jour' => $a['jour'], 'heure' => $a['heure'], 'frequence' => $a['frequence'], 'salle_id' => $salle, 'precision' => $a['precision'], 'debut' => $a['du'], 'fin' => $a['au']];
                $this->ajouter('audiences', 'creer', 'creneaux', null, 'a'.$a['l'], $qui, $champs, [['Audience', null, $qui]], 'maj', (string) $a['l']);
            }
        }
        if ($this->mode === 'complet') {
            foreach ($base as $x) {
                if (! isset($apparies[$x->id]) && ($x->fin === null || $x->fin > $this->effet)) {
                    $fin = $x->debut && $x->debut > $this->effet ? $x->debut : $this->effet;
                    $this->ajouter('audiences', 'retirer', 'creneaux', $x->id, null, $x->type.' — '.($jours[$x->jour] ?? 'à la demande').' — '.($formations[$x->formation_id] ?? '?'), ['fin' => $fin], [['Fin du créneau', $x->fin, date('d/m/Y', strtotime($fin))]]);
                }
            }
        }
    }

    private function responsables(): void
    {
        if (! in_array('responsables', $this->d['feuilles'] ?? [], true)) {
            return;
        }
        $servicesAnnee = $this->anneeId ? DB::table('services')->where('annee_judiciaire_id', $this->anneeId)->pluck('libelle', 'id') : collect();
        $base = DB::table('service_agents')->whereIn('service_id', $servicesAnnee->keys())->get();
        $parCle = $base->keyBy(fn ($x) => $x->service_id.'|'.$x->agent_id);
        $agents = DB::table('agents')->pluck(DB::raw("concat(nom, ' ', prenoms)"), 'id');
        $apparies = [];
        foreach ($this->d['responsables'] as $r) {
            $se = $this->fk($r['service']);
            $ag = $this->fk($r['agent']);
            $qui = (is_int($ag) ? ($agents[$ag] ?? '?') : $this->libelleNouveau('agents', $r['agent'])).' — '.(is_int($se) ? ($servicesAnnee[$se] ?? '?') : $this->libelleNouveau('services', $r['service']));
            $trouve = is_int($se) && is_int($ag) ? ($parCle[$se.'|'.$ag] ?? null) : null;
            if ($trouve) {
                $apparies[$trouve->id] = true;
                [$champs, $det] = $this->ecarts($trouve, ['fonction' => $r['fonction'], 'ordre' => $r['ordre']], ['fonction' => 'Fonction exercée', 'ordre' => 'Ordre']);
                if ($champs) {
                    $this->ajouter('responsables', 'modifier', 'service_agents', $trouve->id, null, $qui, $champs, $det);
                }
            } else {
                $champs = ['service_id' => $se, 'agent_id' => $ag, 'fonction' => $r['fonction'], 'ordre' => $r['ordre'] ?? 0];
                $this->ajouter('responsables', 'creer', 'service_agents', null, 'r'.$r['l'], $qui, $champs, [['Responsable', null, $r['fonction'] ?? 'oui']], 'maj', (string) $r['l']);
            }
        }
        if ($this->mode === 'complet') {
            foreach ($base as $x) {
                if (! isset($apparies[$x->id])) {
                    $this->ajouter('responsables', 'retirer', 'service_agents', $x->id, null, ($agents[$x->agent_id] ?? '?').' — '.($servicesAnnee[$x->service_id] ?? '?'), [], [['Responsable', $x->fonction ?? 'oui', 'retiré']], 'supprime');
                }
            }
        }
    }

    private function membres(): void
    {
        if (! in_array('membres_exterieurs', $this->d['feuilles'] ?? [], true)) {
            return;
        }
        $servicesAnnee = $this->anneeId ? DB::table('services')->where('annee_judiciaire_id', $this->anneeId)->pluck('libelle', 'id') : collect();
        $base = DB::table('membres_exterieurs')->whereIn('service_id', $servicesAnnee->keys())->get();
        $parCle = $base->keyBy(fn ($x) => $x->service_id.'|'.Classeur::cle($x->nom));
        $apparies = [];
        foreach ($this->d['membres'] as $m) {
            $se = $this->fk($m['service']);
            $qui = $m['nom'].' — '.(is_int($se) ? ($servicesAnnee[$se] ?? '?') : $this->libelleNouveau('services', $m['service']));
            $trouve = is_int($se) ? ($parCle[$se.'|'.Classeur::cle($m['nom'])] ?? null) : null;
            if ($trouve) {
                $apparies[$trouve->id] = true;
                [$champs, $det] = $this->ecarts($trouve, ['qualite' => $m['qualite'], 'institution' => $m['institution'], 'ordre' => $m['ordre']], ['qualite' => 'Qualité', 'institution' => 'Institution', 'ordre' => 'Ordre']);
                if ($champs) {
                    $this->ajouter('membres', 'modifier', 'membres_exterieurs', $trouve->id, null, $qui, $champs, $det);
                }
            } else {
                $champs = ['service_id' => $se, 'nom' => $m['nom'], 'qualite' => $m['qualite'], 'institution' => $m['institution'], 'ordre' => $m['ordre'] ?? 0];
                $this->ajouter('membres', 'creer', 'membres_exterieurs', null, 'm'.$m['l'], $qui, $champs, [['Membre', null, $m['qualite'] ?? $m['nom']]], 'maj', (string) $m['l']);
            }
        }
        if ($this->mode === 'complet') {
            foreach ($base as $x) {
                if (! isset($apparies[$x->id])) {
                    $this->ajouter('membres', 'retirer', 'membres_exterieurs', $x->id, null, $x->nom.' — '.($servicesAnnee[$x->service_id] ?? '?'), [], [['Membre', $x->qualite, 'retiré']], 'supprime');
                }
            }
        }
    }

    private function datesLimites(): void
    {
        $base = DB::table('delais_enrolement')->get()->keyBy('jour_audience');
        $jours = [1 => 'lundi', 2 => 'mardi', 3 => 'mercredi', 4 => 'jeudi', 5 => 'vendredi', 6 => 'samedi', 7 => 'dimanche'];
        foreach ($this->d['dates_limites'] ?? [] as $x) {
            $qui = 'Audience du '.$jours[$x['jour_audience']];
            if (isset($base[$x['jour_audience']])) {
                $avant = $base[$x['jour_audience']];
                [$champs, $det] = $this->ecarts($avant, ['jour_limite' => $x['jour_limite'], 'heure' => $x['heure'], 'remarque' => $x['remarque']], ['jour_limite' => 'Jour limite', 'heure' => 'Heure', 'remarque' => 'Remarque']);
                $det = array_map(fn ($d) => $d[0] === 'Jour limite' ? ['Jour limite', $jours[$avant->jour_limite] ?? $d[1], $jours[(int) $d[2]] ?? $d[2]] : $d, $det);
                if ($champs) {
                    $this->ajouter('dates_limites', 'modifier', 'delais_enrolement', $avant->id, null, $qui, $champs, $det);
                }
            } else {
                $champs = ['jour_audience' => $x['jour_audience'], 'jour_limite' => $x['jour_limite'], 'heure' => $x['heure'], 'remarque' => $x['remarque']];
                $this->ajouter('dates_limites', 'creer', 'delais_enrolement', null, 'd'.$x['l'], $qui, $champs, [['Date limite', null, $jours[$x['jour_limite']].' à '.$x['heure']]]);
            }
        }
    }

    private function articles(): void
    {
        $base = $this->anneeId ? DB::table('articles_ordonnance')->where('annee_judiciaire_id', $this->anneeId)->get() : collect();
        $parNum = $base->keyBy(fn ($a) => Classeur::cle($a->numero));
        foreach ($this->d['articles'] ?? [] as $i => $a) {
            $trouve = $parNum[Classeur::cle($a['numero'])] ?? null;
            if ($trouve) {
                [$champs, $det] = $this->ecarts($trouve, ['intitule' => $a['intitule'], 'texte' => $a['texte'], 'rattache_a' => $a['rattache_a']], ['intitule' => 'Intitulé', 'texte' => 'Texte', 'rattache_a' => 'Rattaché à']);
                if ($champs) {
                    $this->ajouter('articles', 'modifier', 'articles_ordonnance', $trouve->id, null, $trouve->numero, $champs, array_map(fn ($d) => $d[0] === 'Texte' ? ['Texte', Str::limit((string) $d[1], 90), Str::limit((string) $d[2], 90)] : $d, $det));
                }
            } else {
                $champs = ['annee_judiciaire_id' => $this->anneeId ?? ['_ref' => 'annee'], 'ordre' => $i + 1, 'numero' => $a['numero'], 'intitule' => $a['intitule'], 'texte' => $a['texte'], 'rattache_a' => $a['rattache_a']];
                $this->ajouter('articles', 'creer', 'articles_ordonnance', null, 'ar'.$a['l'], $a['numero'], $champs, [['Article', null, Str::limit((string) $a['texte'], 90)]]);
            }
        }
    }

    // ------------------------------------------------------------------ libellés

    /** @return array<int, string> */
    private function nomsFormations(): array
    {
        $r = [];
        foreach (DB::table('formations')->join('chambres', 'chambres.id', '=', 'formations.chambre_id')->get(['formations.id', 'chambres.libelle as chambre', 'formations.nom']) as $f) {
            $r[$f->id] = $f->chambre.($f->nom ? ' — '.$f->nom : '');
        }

        return $r;
    }

    /** Nom d'un élément créé par le fichier (pour l'écran). */
    private function libelleNouveau(string $bloc, string $cle): string
    {
        foreach ($this->ch as $c) {
            if ($c['bloc'] === $bloc && $c['cle'] === $cle) {
                return $c['libelle'].' (nouveau)';
            }
        }

        return '(nouveau)';
    }
}
