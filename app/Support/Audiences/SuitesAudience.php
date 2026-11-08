<?php

namespace App\Support\Audiences;

use App\Models\Chambre;
use App\Models\RoleLigne;
use App\Models\User;
use App\Support\PerimetreChambres;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Database\Eloquent\Builder;
use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;

/*
 | Les suites notées aux audiences (jugée, radiée, jointe, désistement, renvoi, délibéré), à consulter et à exporter.
 | Source : les lignes des rôles publiés dont la suite est notée. Chacun ne voit que les chambres de son périmètre.
 | L'export Excel n'écrit que du texte (une cellule « =… » reste du texte) et le PDF est produit sans accès réseau.
 */
class SuitesAudience
{
    /** Valeur du filtre « suite » qui montre les affaires en retard au lieu des suites notées. */
    public const RETARD = 'retard';

    public const COLONNES = ['Audience du', 'Chambre', 'Affaire', 'Enrôlée le', '1re audience', 'Parties', 'Objet', 'Suite', 'Détail'];

    /** Les filtres reconnus, nettoyés. @return array{suite: ?string, chambre: ?int, du: ?string, au: ?string, q: string} */
    public static function filtres(array $entree): array
    {
        $date = fn ($v) => is_string($v) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) && strtotime($v) !== false ? $v : null;
        $suite = $entree['suite'] ?? null;

        return [
            'suite' => is_string($suite) && (array_key_exists($suite, RoleLigne::ISSUES) || $suite === self::RETARD) ? $suite : null,
            'chambre' => ! empty($entree['chambre']) && ctype_digit((string) $entree['chambre']) ? (int) $entree['chambre'] : null,
            'du' => $date($entree['du'] ?? null),
            'au' => $date($entree['au'] ?? null),
            'q' => mb_substr(trim((string) ($entree['q'] ?? '')), 0, 80),
        ];
    }

    /** La requête de base, dans le périmètre de l'utilisateur, sans le filtre « suite » (les compteurs en ont besoin). */
    public static function base(User $user, array $f): Builder
    {
        $perimetre = PerimetreChambres::de($user);

        return RoleLigne::query()
            ->join('roles_audience', 'roles_audience.id', '=', 'role_lignes.role_id')
            ->where('roles_audience.statut', 'publie')->whereNotNull('role_lignes.issue')
            ->when($perimetre !== null, fn ($q) => $q->whereIn('roles_audience.chambre_id', $perimetre ?: [0]))
            ->when($f['chambre'], fn ($q, $v) => $q->where('roles_audience.chambre_id', $v))
            ->when($f['du'], fn ($q, $v) => $q->whereDate('roles_audience.date_audience', '>=', $v))
            ->when($f['au'], fn ($q, $v) => $q->whereDate('roles_audience.date_audience', '<=', $v))
            ->when($f['q'] !== '', function ($q) use ($f) {
                $mot = '%'.addcslashes($f['q'], '%_\\').'%';
                $jour = self::termeDate($f['q']);
                $q->where(function ($w) use ($mot, $jour) {
                    $w->where('role_lignes.numero', 'like', $mot)->orWhere('role_lignes.demandeurs', 'like', $mot)->orWhere('role_lignes.defendeurs', 'like', $mot)->orWhere('role_lignes.objet', 'like', $mot);
                    if ($jour && isset($jour['date'])) {
                        $w->orWhereDate('roles_audience.date_audience', $jour['date']);
                    } elseif ($jour) {
                        $w->orWhere(fn ($x) => $x->whereMonth('roles_audience.date_audience', $jour['mois'])->whereDay('roles_audience.date_audience', $jour['jour']));
                    }
                });
            });
    }

    /** Nombre de suites par type, avec les autres filtres. @return array<string, int> */
    public static function compteurs(User $user, array $f): array
    {
        $n = self::base($user, $f)->selectRaw('role_lignes.issue as suite, count(*) as n')->groupBy('role_lignes.issue')->pluck('n', 'suite')->all();
        $par = [];
        foreach (array_keys(RoleLigne::ISSUES) as $cle) {
            $par[$cle] = (int) ($n[$cle] ?? 0);
        }
        $par[self::RETARD] = self::retards($user, $f)->count();

        return $par;
    }

    /** « 05/11/2026 », « 5/11/2026 », « 2026-11-05 » (un jour précis) ou « 05/11 » (ce jour de chaque année) : la date qu'on veut retrouver. @return array<string, mixed>|null */
    public static function termeDate(string $terme): ?array
    {
        $t = trim($terme);
        if (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})$#', $t, $m) && checkdate((int) $m[2], (int) $m[1], (int) $m[3])) {
            return ['date' => sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1])];
        }
        if (preg_match('#^(\d{4})-(\d{2})-(\d{2})$#', $t, $m) && checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            return ['date' => $t];
        }
        if (preg_match('#^(\d{1,2})/(\d{1,2})$#', $t, $m) && checkdate((int) $m[2], (int) $m[1], 2024)) {
            return ['jour' => (int) $m[1], 'mois' => (int) $m[2]];
        }

        return null;
    }

    /**
     * Les affaires en retard (renvoi dépassé, délibéré échu, suite non notée, jamais portée au rôle), les plus en retard d'abord, mises en forme
     * comme les suites pour partager le même tableau. Mêmes filtres chambre, période et recherche ; le périmètre de l'utilisateur s'applique.
     *
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    public static function retards(User $user, array $f): \Illuminate\Support\Collection
    {
        $chambres = self::chambres();
        $jour = self::termeDate($f['q']);
        $mot = mb_strtolower($f['q']);

        return SuiviAffaires::etats(PerimetreChambres::de($user))->filter(fn ($e) => $e['retard'])
            ->when($f['chambre'], fn ($c, $v) => $c->where('chambre_id', $v))
            ->map(fn ($e) => self::ecranRetard($e, $chambres))
            ->filter(fn ($r) => (! $f['du'] || ($r['date'] && $r['date'] >= $f['du'])) && (! $f['au'] || ($r['date'] && $r['date'] <= $f['au'])))
            ->filter(function ($r) use ($f, $jour, $mot) {
                if ($f['q'] === '') {
                    return true;
                }
                if (str_contains(mb_strtolower($r['numero'].' '.$r['parties'].' '.$r['objet']), $mot)) {
                    return true;
                }

                return $jour && $r['date'] && (isset($jour['date']) ? $r['date'] === $jour['date'] : (int) substr($r['date'], 5, 2) === $jour['mois'] && (int) substr($r['date'], 8, 2) === $jour['jour']);
            })
            ->sortByDesc('jours_retard')->values();
    }

    /** Une affaire en retard, dans la même forme qu'une suite pour l'écran et l'export. @param  array<string, mixed>  $e  @param  array<int, string>  $chambres */
    private static function ecranRetard(array $e, array $chambres): array
    {
        $date = ($e['derniere_audience'] ?? null) ?: ($e['premiere'] ?? null);
        $echeance = $e['echeance'] ? $e['echeance']->format('d/m/Y') : '';
        $jours = (int) $e['jours_retard'];
        $retard = $jours > 0 ? 'en retard de '.$jours.' jour'.($jours > 1 ? 's' : '') : 'à traiter aujourd\'hui';
        [$libelle, $detail] = match ($e['etat']) {
            'renvoyee' => ['Renvoi dépassé', 'Renvoyée au '.$echeance.' · '.$retard],
            'deliberee' => ['Délibéré échu', 'Délibéré au '.$echeance.' · '.$retard],
            'sans_suite' => ['Suite non notée', ucfirst($retard)],
            default => ['Jamais portée', 'Audience passée sans passage sur un rôle · '.$retard],
        };

        return [
            'ligne' => null, 'role_id' => (int) ($e['role_id'] ?? 0), 'affaire_id' => $e['affaire_id'], 'date' => $date?->toDateString(), 'date_libelle' => $date ? $date->format('d/m/Y') : '',
            'enrolee_le' => $e['enrolee_le'] ?? null, 'premiere_le' => $e['premiere'] ? $e['premiere']->format('d/m/Y') : null,
            'chambre' => $chambres[$e['chambre_id']] ?? (string) $e['chambre'], 'numero' => $e['numero'], 'parties' => $e['parties'], 'objet' => (string) $e['objet'],
            'suite' => self::RETARD, 'suite_libelle' => $libelle, 'detail' => $detail, 'motif' => (string) $e['motif'], 'jours_retard' => $jours,
        ];
    }

    /** Toutes les lignes du filtre (suites notées ou affaires en retard), pour l'export. @return list<array<string, mixed>> */
    public static function toutes(User $user, array $f, int $limite = 20000): array
    {
        if ($f['suite'] === self::RETARD) {
            return self::retards($user, $f)->take($limite)->all();
        }
        $chambres = self::chambres();

        return self::liste($user, $f)->limit($limite)->get()->map(fn ($l) => self::ecran($l, $chambres))->all();
    }

    public static function liste(User $user, array $f): Builder
    {
        return self::base($user, $f)->leftJoin('affaires', 'affaires.id', '=', 'role_lignes.affaire_id')
            ->select('role_lignes.*', 'roles_audience.date_audience as date_role', 'roles_audience.chambre_id as chambre_role', 'roles_audience.id as id_role', 'affaires.date_rg as enrolee_le', 'affaires.date_premiere_audience as premiere_le')
            ->when($f['suite'], fn ($q, $v) => $q->where('role_lignes.issue', $v))
            ->orderByDesc('roles_audience.date_audience')->orderByDesc('role_lignes.id');
    }

    /** Une ligne de rôle mise en forme pour l'écran. @param  array<int, string>  $chambres  id => libellé */
    public static function ecran(RoleLigne $l, array $chambres): array
    {
        $ligne = fn (?string $t) => array_values(array_filter(array_map('trim', preg_split('/\R/u', (string) $t))))[0] ?? '';
        $demande = $ligne($l->demandeurs);
        $contre = $ligne($l->defendeurs);
        $date = \Illuminate\Support\Carbon::parse($l->date_role);

        return [
            'ligne' => $l->id, 'role_id' => (int) $l->id_role, 'affaire_id' => $l->affaire_id, 'date' => $date->toDateString(), 'date_libelle' => $date->format('d/m/Y'),
            'enrolee_le' => self::jour($l->enrolee_le ?? null), 'premiere_le' => self::jour($l->premiere_le ?? null),
            'chambre' => $chambres[$l->chambre_role] ?? '', 'numero' => $ligne($l->numero), 'parties' => trim($demande.($contre !== '' ? ' c/ '.$contre : '')), 'objet' => $ligne($l->objet),
            'suite' => $l->issue, 'suite_libelle' => RoleLigne::ISSUES[$l->issue] ?? $l->issue, 'detail' => self::detail($l), 'motif' => (string) $l->motif,
        ];
    }

    /** Une date de la base (texte « 2026-05-20 » ou « 2026-05-20 00:00:00 ») écrite « 20/05/2026 » ; null si elle n'existe pas. */
    private static function jour(?string $valeur): ?string
    {
        return $valeur ? \Illuminate\Support\Carbon::parse($valeur)->format('d/m/Y') : null;
    }

    public static function detail(RoleLigne $l): string
    {
        $d = match ($l->issue) {
            'renvoi' => $l->renvoi_au ? 'Renvoyée au '.$l->renvoi_au->format('d/m/Y') : '',
            'delibere' => $l->date_delibere ? 'Délibéré au '.$l->date_delibere->format('d/m/Y') : 'Délibéré sans date',
            default => '',
        };

        return trim($d.($l->motif ? ($d !== '' ? ' · ' : '').$l->motif : ''));
    }

    /** @return array<int, string> id => libellé des chambres */
    public static function chambres(): array
    {
        return Chambre::pluck('libelle', 'id')->all();
    }

    /** @param  iterable<array<string, mixed>>  $lignes  lignes déjà mises en forme pour l'écran @return list<list<string>> */
    public static function lignesExport(iterable $lignes): array
    {
        $sortie = [];
        foreach ($lignes as $e) {
            $sortie[] = [$e['date_libelle'], $e['chambre'], $e['numero'], (string) $e['enrolee_le'], (string) $e['premiere_le'], $e['parties'], $e['objet'], $e['suite_libelle'], $e['detail']];
        }

        return $sortie;
    }

    /** Les feuilles du classeur Excel : une feuille par suite, en plus de « Toutes les suites » et « En retard ». */
    public const FEUILLES = ['renvoi' => 'Renvoyées', 'delibere' => 'En délibéré', 'juge' => 'Jugées', 'radie' => 'Radiées', 'joint' => 'Jointes', 'desiste' => 'Désistements'];

    /** Le titre en grand du PDF selon la suite affichée. */
    public const TITRES = [
        'renvoi' => 'AFFAIRES RENVOYÉES', 'delibere' => 'AFFAIRES EN DÉLIBÉRÉ', 'juge' => 'AFFAIRES JUGÉES', 'radie' => 'AFFAIRES RADIÉES',
        'joint' => 'AFFAIRES JOINTES À UNE AUTRE', 'desiste' => 'DÉSISTEMENTS', 'retard' => 'AFFAIRES EN RETARD',
    ];

    /**
     * Tout en un coup : une feuille « Toutes les suites », puis une feuille par suite et une feuille « En retard ». Le filtre « suite » est ignoré
     * (c'est justement ce qu'on répartit en feuilles) ; chambre, période et recherche s'appliquent.
     *
     * @return array<string, list<list<string>>> nom de la feuille => lignes
     */
    public static function classeur(User $user, array $f): array
    {
        $tout = self::toutes($user, array_merge($f, ['suite' => null]));
        $par = collect($tout)->groupBy('suite');
        $feuilles = ['Toutes les suites' => self::lignesExport($tout)];
        foreach (self::FEUILLES as $cle => $nom) {
            $feuilles[$nom] = self::lignesExport($par->get($cle, []));
        }
        $feuilles['En retard'] = self::lignesExport(self::retards($user, array_merge($f, ['suite' => self::RETARD]))->all());

        return $feuilles;
    }

    /**
     * Écrit le classeur (une feuille par entrée) et renvoie son chemin (fichier temporaire, à supprimer après envoi).
     *
     * @param  array<string, list<list<string>>>  $feuilles
     */
    public static function excel(array $feuilles): string
    {
        $chemin = tempnam(sys_get_temp_dir(), 'sui');
        $w = new Writer;
        foreach ([14, 28, 14, 14, 14, 44, 38, 16, 40] as $i => $largeur) {
            $w->getOptions()->setColumnWidth($largeur, $i + 1);
        }
        $w->openToFile($chemin);
        $entete = (new Style)->withFontBold(true)->withFontColor('FFFFFF')->withBackgroundColor('00794C');
        $premiere = true;
        foreach ($feuilles as $nom => $lignes) {
            if ($premiere) {
                $premiere = false;
            } else {
                $w->addNewSheetAndMakeItCurrent();
            }
            $w->getCurrentSheet()->setName(mb_substr((string) $nom, 0, 31));
            $w->addRow(Row::fromValuesWithStyles(self::COLONNES, array_fill(0, count(self::COLONNES), $entete)));
            foreach ($lignes as $ligne) {
                $w->addRow(new Row(array_map(fn ($v) => new StringCell((string) $v, null), $ligne)));   // cellules de texte explicites : jamais de formule
            }
        }
        $w->close();

        return $chemin;
    }

    /**
     * Le PDF de la suite affichée : papier à en-tête des rôles, filigrane, grand titre avec la suite. Quand une seule suite est affichée,
     * la colonne « Suite » (la même partout) est retirée ; « Toutes » et « En retard » la gardent.
     *
     * @param  list<list<string>>  $lignes
     */
    public static function pdf(array $lignes, array $criteres, ?string $suite = null): string
    {
        $colonnes = self::COLONNES;
        if ($suite && $suite !== self::RETARD) {
            $colonnes = array_values(array_diff_key($colonnes, [7 => true]));
            $lignes = array_map(fn ($l) => array_values(array_diff_key($l, [7 => true])), $lignes);
        }

        $options = new Options;
        $options->set('isRemoteEnabled', false);
        $options->set('isPhpEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');
        $pdf = new Dompdf($options);
        $pdf->loadHtml(view('audiences.suites.export_pdf', [
            'lignes' => $lignes, 'colonnes' => $colonnes, 'criteres' => $criteres, 'date' => now(), 'titre' => $suite ? (self::TITRES[$suite] ?? "SUITES D'AUDIENCE") : "TOUTES LES SUITES D'AUDIENCE",
            'logo' => \App\Support\Roles\RoleExport::filigraneDonnees(), 'armoiries' => \App\Support\Roles\RoleExport::armoiriesDonnees(),
            'gauche' => \App\Support\Roles\RoleDocument::enteteGauche(), 'droite' => \App\Support\Roles\RoleDocument::enteteDroite(),
        ])->render());
        $pdf->setPaper('A4', 'landscape');
        $pdf->render();

        return $pdf->output();
    }
}
