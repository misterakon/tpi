<?php

namespace App\Support\Organisation\Import;

use App\Models\Agent;
use App\Models\AgentAlias;
use Illuminate\Support\Str;

/*
 | Rapprochement des noms de personnes écrits dans le classeur avec les agents connus. Les écritures varient d'une ordonnance à l'autre
 | (« KOUADIO Marie-Laurette », « Mme KOUADJO Marie Laurette », « N'GUESSAN Appa Brigitte épouse LEPRY »…) : on compare des ensembles de mots
 | sans accent, sans titre, sans « épouse … », dans n'importe quel ordre. Une correspondance exacte et unique est acceptée ; tout le reste est
 | proposé à l'utilisateur, jamais deviné. Ses choix sont mémorisés (agents_alias).
 */
class Noms
{
    private const TITRES = ['m', 'mr', 'mme', 'mmes', 'mlle', 'mlles', 'me', 'mes', 'maitre', 'maitres', 'dr', 'madame', 'monsieur', 'mademoiselle', 'pr', 'president', 'presidente'];

    /** Mots significatifs d'un nom : minuscules, sans accent ni titre ni « épouse … ». @return list<string> */
    public static function mots(string $nom): array
    {
        $t = Str::lower(Str::ascii($nom));
        $t = preg_replace("/[’'`]/u", '', $t);
        $t = preg_replace('/[^a-z0-9]+/', ' ', $t);
        $mots = [];
        foreach (preg_split('/\s+/', trim($t), -1, PREG_SPLIT_NO_EMPTY) as $mot) {
            if ($mot === 'epouse' || $mot === 'ep' || $mot === 'nee') {
                break;                              // ce qui suit est le nom d'épouse : ignoré
            }
            if (! in_array($mot, self::TITRES, true)) {
                $mots[] = $mot;
            }
        }

        return $mots;
    }

    /** Clé de comparaison : les mots triés. */
    public static function cle(string $nom): string
    {
        $m = self::mots($nom);
        sort($m);

        return implode(' ', $m);
    }

    /** Ressemblance entre deux écritures, de 0 à 1 : mots communs, avec une tolérance sur les fautes de frappe. */
    public static function score(string $a, string $b): float
    {
        $ma = self::mots($a);
        $mb = self::mots($b);
        if (! $ma || ! $mb) {
            return 0.0;
        }
        $communs = 0.0;
        foreach ($ma as $x) {
            $meilleur = 0.0;
            foreach ($mb as $y) {
                if ($x === $y) {
                    $meilleur = 1.0;
                    break;
                }
                similar_text($x, $y, $pct);
                if ($pct >= 80 && min(strlen($x), strlen($y)) >= 4) {
                    $meilleur = max($meilleur, $pct / 100 * 0.9);
                }
            }
            $communs += $meilleur;
        }

        return $communs / max(count($ma), count($mb));
    }

    /**
     * Cherche un nom parmi les agents. Retourne :
     *   ['agent' => id, 'par' => 'alias'|'nom']        correspondance sûre ;
     *   ['agent' => null, 'propositions' => [...]]      à confirmer : agents proches (id, nom, score), du plus proche au moins proche.
     *
     * @param  array<int, Agent>  $agents  les agents connus, par id
     * @param  array<string, int>  $alias  clé de nom => id d'agent
     */
    public static function chercher(string $texte, array $agents, array $alias): array
    {
        $cle = self::cle($texte);
        if ($cle !== '' && isset($alias[$cle]) && isset($agents[$alias[$cle]])) {
            return ['agent' => $alias[$cle], 'par' => 'alias'];
        }

        $exacts = [];
        $scores = [];
        foreach ($agents as $id => $a) {
            $nomComplet = trim($a->nom.' '.$a->prenoms);
            if ($cle !== '' && self::cle($nomComplet) === $cle) {
                $exacts[] = $id;
            }
            $scores[$id] = self::score($texte, $nomComplet);
        }
        if (count($exacts) === 1) {
            return ['agent' => $exacts[0], 'par' => 'nom'];
        }

        arsort($scores);
        $propositions = [];
        foreach ($scores as $id => $s) {
            if ($s >= 0.5 && count($propositions) < 5) {
                $propositions[] = ['id' => $id, 'nom' => trim($agents[$id]->nom.' '.$agents[$id]->prenoms), 'score' => round($s, 2), 'homonyme' => in_array($id, $exacts, true)];
            }
        }

        return ['agent' => null, 'propositions' => $propositions];
    }

    /** Les agents connus et leurs alias, prêts pour chercher(). @return array{0: array<int, Agent>, 1: array<string, int>} */
    public static function charger(): array
    {
        $agents = Agent::orderBy('id')->get()->keyBy('id')->all();
        $alias = AgentAlias::pluck('agent_id', 'alias')->all();

        return [$agents, $alias];
    }
}
