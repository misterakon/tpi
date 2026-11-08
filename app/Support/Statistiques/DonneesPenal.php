<?php

namespace App\Support\Statistiques;

use App\Models\DecisionPenale;
use App\Models\User;
use Illuminate\Support\Collection;

/*
 | Statistiques pénales d'après le répertoire pénal, avec les rubriques des classeurs du TPI : condamnés par sexe, nationalité et profession,
 | infractions retenues, peines, victimes (hommes, femmes, garçons et filles mineurs), traite des personnes, durées (première audience à décision ;
 | décision à transmission de la minute). Les dates manquantes sont comptées à part, jamais devinées.
 */
class DonneesPenal
{
    public const NATURES = ['crime' => 'Crime', 'delit' => 'Délit', 'contravention' => 'Contravention'];

    /** @return Collection<int, array<string, mixed>> */
    public static function decisions(User $user, int $depuis, int $jusqua, ?int $chambre = null): Collection
    {
        return DecisionPenale::visiblesPour($user)->with(['affaire.nature:id,libelle', 'affaire.chambre:id,libelle', 'partie'])
            ->whereBetween('date_decision', [$depuis.'-01-01', $jusqua.'-12-31'])
            ->when($chambre, fn ($q) => $q->whereHas('affaire', fn ($a) => $a->where('chambre_id', $chambre)))
            ->orderBy('date_decision')->get()->map(function (DecisionPenale $d) {
                $a = $d->affaire;
                $nature = $d->nature_infraction ?: (str_starts_with(mb_strtolower((string) $a->nature?->libelle), 'simple police') ? 'contravention' : 'delit');
                $condamnes = $d->issue === 'condamne' ? max(1, $d->partie_id ? 1 : $a->parties()->whereIn('role', ['prevenu', 'inculpe'])->count()) : 0;
                $p = $d->partie;

                return [
                    'id' => $d->id, 'affaire_id' => $a->id, 'numero' => $a->numeroAffichage(), 'chambre' => $a->chambre?->libelle, 'date' => $d->date_decision, 'issue' => $d->issue,
                    'infraction' => trim((string) ($d->infraction_retenue ?: $a->objet)), 'nature' => $nature, 'traite' => (bool) $d->traite,
                    'condamnes' => $condamnes, 'hommes' => $p ? ($condamnes && $p->nature === 'homme' ? 1 : 0) : null, 'femmes' => $p ? ($condamnes && $p->nature === 'femme' ? 1 : 0) : null,
                    'nationalite' => $condamnes ? trim((string) $p?->nationalite) : '', 'profession' => $condamnes ? trim((string) $p?->profession) : '',
                    'emprisonnement' => (int) $d->emprisonnement_mois, 'sursis' => (int) $d->sursis_mois, 'amende' => (int) $d->amende, 'autres' => (string) $d->autres_peines,
                    'victimes' => ['h' => (int) $d->victimes_hommes, 'f' => (int) $d->victimes_femmes, 'g' => (int) $d->victimes_garcons, 'm' => (int) $d->victimes_filles],
                    'audience' => $a->date_premiere_audience?->copy()->startOfDay(), 'minute' => $d->date_transmission_minute?->copy()->startOfDay(), 'decision_le' => $d->date_decision->copy()->startOfDay(),
                    'contradiction' => $d->contradiction, 'peine' => $d->peineEnClair(), 'prevenus' => $a->parties()->whereIn('role', ['prevenu', 'inculpe'])->count(), 'numero_decision' => $d->numero_decision,
                    'reparation' => null,
                ];
            });
    }

    /** @return array<string, mixed> */
    public static function recapitulatif(Collection $l, int $depuis, int $jusqua): array
    {
        $annees = range($depuis, $jusqua);
        $parAn = [];
        foreach ($annees as $y) {
            $x = $l->filter(fn ($d) => $d['date']->year === $y);
            $parAn[$y] = [
                'decisions' => $x->count(), 'condamnes' => $x->sum('condamnes'), 'hommes' => (int) $x->sum('hommes'), 'femmes' => (int) $x->sum('femmes'),
                'relaxes' => $x->whereIn('issue', ['relaxe', 'acquitte'])->count(), 'autres' => $x->whereNotIn('issue', ['condamne', 'relaxe', 'acquitte'])->count(),
                'victimes' => $x->sum(fn ($d) => array_sum($d['victimes'])), 'traite' => $x->where('traite', true)->count(),
            ];
        }
        $mois = [];
        for ($m = 1; $m <= 12; $m++) {
            $x = $l->filter(fn ($d) => $d['date']->year === $jusqua && $d['date']->month === $m);
            $mois[$m] = ['decisions' => $x->count(), 'condamnes' => $x->sum('condamnes')];
        }

        $compter = fn (Collection $c, string $cle, int $limite = 12) => $c->filter(fn ($d) => $d[$cle] !== '')->groupBy(fn ($d) => mb_strtolower($d[$cle]))
            ->map(fn ($g) => ['libelle' => $g->first()[$cle], 'n' => $g->sum(fn ($d) => max(1, $d['condamnes']))])->sortByDesc('n')->take($limite)->values()->all();
        $condamnes = $l->where('condamnes', '>', 0);
        $durees = fn (Collection $c, string $de, string $a) => $c->filter(fn ($d) => $d[$de] && $d[$a] && $d[$a]->gte($d[$de]))->map(fn ($d) => (int) $d[$de]->diffInDays($d[$a]))->sort()->values();
        $stat = function (Collection $v, int $manquantes) {
            $n = $v->count();

            return ['mesurees' => $n, 'manquantes' => $manquantes, 'moyenne' => $n ? (int) round($v->avg()) : null, 'mediane' => $n ? ($n % 2 ? $v[intdiv($n, 2)] : (int) round(($v[$n / 2 - 1] + $v[$n / 2]) / 2)) : null];
        };
        $dAudience = $durees($l, 'audience', 'decision_le');
        $dMinute = $durees($l, 'decision_le', 'minute');
        $vigilance = (int) config('tpi.penal.minute_vigilance_jours', 30);
        $peines = $condamnes->where('emprisonnement', '>', 0);
        $delai = (int) config('tpi.delais_jugement.correctionnel_jours', 91);

        return [
            'annees' => $annees, 'par_an' => $parAn, 'mois' => $mois, 'annee_mois' => $jusqua,
            'infractions' => $compter($l, 'infraction', 15), 'nationalites' => $compter($condamnes, 'nationalite'), 'professions' => $compter($condamnes, 'profession'),
            'natures' => collect(self::NATURES)->map(fn ($lib, $k) => ['libelle' => $lib, 'n' => $l->where('nature', $k)->count()])->values()->all(),
            'issues' => collect(DecisionPenale::ISSUES)->map(fn ($lib, $k) => ['libelle' => $lib, 'n' => $l->where('issue', $k)->count()])->filter(fn ($x) => $x['n'] > 0)->values()->all(),
            'victimes' => ['hommes' => $l->sum(fn ($d) => $d['victimes']['h']), 'femmes' => $l->sum(fn ($d) => $d['victimes']['f']), 'garcons' => $l->sum(fn ($d) => $d['victimes']['g']), 'filles' => $l->sum(fn ($d) => $d['victimes']['m'])],
            'peines' => [
                'avec_emprisonnement' => $peines->count(), 'moyenne_mois' => $peines->count() ? round($peines->avg('emprisonnement'), 1) : null, 'avec_sursis' => $condamnes->where('sursis', '>', 0)->count(),
                'amendes' => $condamnes->where('amende', '>', 0)->count(), 'total_amendes' => (int) $condamnes->sum('amende'),
            ],
            'duree_audience' => $stat($dAudience, $l->count() - $dAudience->count()) + ['dans_le_delai' => $dAudience->count() ? (int) round($dAudience->filter(fn ($d) => $d <= $delai)->count() / $dAudience->count() * 100) : null, 'delai_jours' => $delai],
            'duree_minute' => $stat($dMinute, $l->count() - $dMinute->count()) + ['non_transmises' => $l->whereNull('minute')->count(), 'en_retard' => $l->whereNull('minute')->filter(fn ($d) => $d['decision_le']->diffInDays(today()) > $vigilance)->count(), 'vigilance' => $vigilance],
            'total' => $l->count(),
        ];
    }

    /**
     * Les blocs d'export : un onglet « RECAP » et, pour qui a le droit du détail, l'onglet « AUDIENCE PENALE » (une ligne par décision, colonnes du classeur).
     *
     * @return array<string, list<array{titre: string, entete: list<string>, lignes: list<list<int|string>>}>>
     */
    public static function onglets(array $r, ?Collection $detail): array
    {
        $A = $r['annees'];
        $an = fn (string $titre, string $cle) => ['titre' => $titre, 'entete' => array_merge([''], array_map('strval', $A)), 'lignes' => [array_merge([$titre], array_map(fn ($y) => $r['par_an'][$y][$cle], $A))]];
        $recap = [[
            'titre' => 'Décisions et condamnés par année', 'entete' => array_merge([''], array_map('strval', $A)),
            'lignes' => [
                array_merge(['Décisions rendues'], array_map(fn ($y) => $r['par_an'][$y]['decisions'], $A)), array_merge(['Total condamnés'], array_map(fn ($y) => $r['par_an'][$y]['condamnes'], $A)),
                array_merge(['Dont condamnés masculins'], array_map(fn ($y) => $r['par_an'][$y]['hommes'], $A)), array_merge(['Dont condamnés féminins'], array_map(fn ($y) => $r['par_an'][$y]['femmes'], $A)),
                array_merge(['Relaxés ou acquittés'], array_map(fn ($y) => $r['par_an'][$y]['relaxes'], $A)), array_merge(['Total victimes'], array_map(fn ($y) => $r['par_an'][$y]['victimes'], $A)),
                array_merge(['Cas de traite des personnes'], array_map(fn ($y) => $r['par_an'][$y]['traite'], $A)),
            ],
        ]];
        $liste = fn (string $titre, array $items) => ['titre' => $titre, 'entete' => ['', 'Nombre'], 'lignes' => array_map(fn ($x) => [$x['libelle'], $x['n']], $items) ?: [['Aucune donnée', 0]]];
        $recap[] = $liste('Infractions retenues', $r['infractions']);
        $recap[] = $liste('Nature des infractions', $r['natures']);
        $recap[] = $liste('Nationalité des condamnés', $r['nationalites']);
        $recap[] = $liste('Profession des condamnés', $r['professions']);
        $recap[] = $liste('Issue des décisions', $r['issues']);
        $recap[] = ['titre' => 'Victimes', 'entete' => ['', 'Nombre'], 'lignes' => [['Hommes', $r['victimes']['hommes']], ['Femmes', $r['victimes']['femmes']], ['Garçons mineurs', $r['victimes']['garcons']], ['Filles mineures', $r['victimes']['filles']]]];
        $d = $r['duree_audience'];
        $m = $r['duree_minute'];
        $recap[] = ['titre' => 'Durées (jours)', 'entete' => ['', 'Moyenne', 'Médiane', 'Mesurées', 'Dates manquantes'], 'lignes' => [
            ['Première audience à décision', $d['moyenne'] ?? '–', $d['mediane'] ?? '–', $d['mesurees'], $d['manquantes']], ['Décision à transmission de la minute', $m['moyenne'] ?? '–', $m['mediane'] ?? '–', $m['mesurees'], $m['manquantes']],
        ]];
        unset($an);
        $out = ['RECAP PENAL' => $recap];

        if ($detail !== null) {
            $lignes = $detail->map(fn ($x) => [
                $x['numero'], (string) $x['prevenus'], $x['audience']?->format('d/m/Y') ?? '', (string) ($x['numero_decision'] ?? ''), $x['date']->format('d/m/Y'), DecisionPenale::CONTRADICTIONS[$x['contradiction']] ?? '',
                DecisionPenale::ISSUES[$x['issue']] ?? '', $x['peine'], $x['infraction'], self::NATURES[$x['nature']] ?? '', $x['traite'] ? 'OUI' : 'NON', (string) $x['condamnes'], (string) ($x['hommes'] ?? ''), (string) ($x['femmes'] ?? ''),
                $x['nationalite'], $x['profession'], (string) array_sum($x['victimes']), (string) $x['victimes']['h'], (string) $x['victimes']['f'], (string) $x['victimes']['g'], (string) $x['victimes']['m'],
                $x['minute']?->format('d/m/Y') ?? '', ($x['audience'] && $x['decision_le']->gte($x['audience'])) ? (string) $x['audience']->diffInDays($x['decision_le']) : '', ($x['minute'] && $x['minute']->gte($x['decision_le'])) ? (string) $x['decision_le']->diffInDays($x['minute']) : '',
            ])->values()->all();
            $out['AUDIENCE PENALE'] = [['titre' => 'Suivi de chaque décision rendue par les juridictions répressives', 'entete' => [
                'N° RP', 'Nombre prévenus', 'Date de la 1re audience', 'N° de la décision', 'Date de la décision', 'Caractère de la décision', 'Contenu de la décision', 'Peines prononcées', 'Infraction retenue par le tribunal',
                'Nature infraction', 'Traite des personnes', 'Total condamnés', 'Dont condamnés masculins', 'Dont condamnés féminins', 'Nationalité des condamnés', 'Profession des condamnés', 'Total victimes', 'Victimes hommes',
                'Victimes femmes', 'Victimes garçons mineurs', 'Victimes filles mineures', 'Date transmission minute au Greffier en Chef', 'Durée 1re audience à décision (j)', 'Durée décision à minute (j)',
            ], 'lignes' => $lignes ?: [array_fill(0, 24, '')], 'total' => false]];
        }

        return $out;
    }
}
