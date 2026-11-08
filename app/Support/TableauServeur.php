<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/*
 | Réponse d'une liste paginée côté serveur, au format de DataTables (draw, recordsTotal, recordsFiltered, data) : la page ne charge qu'une page de lignes à la fois,
 | quel que soit le nombre d'affaires. Le tri, la recherche et les filtres se font dans la base. Chaque liste fournit :
 |   - la requête de base (avec ses droits et son périmètre : la même que celle de la page) ;
 |   - les colonnes triables (indice de colonne => colonnes SQL) ;
 |   - la recherche (une fonction qui reçoit la requête et les mots) ;
 |   - la mise en page d'une ligne (une fonction qui renvoie le HTML de la ligne, ses <td> étant découpés en cellules).
 */
class TableauServeur
{
    public const MAX_LIGNES = 100;

    /**
     * @param  array<int, list<string>>  $tri  indice de colonne => colonnes SQL à trier
     * @param  callable(Builder, list<string>): void  $recherche
     * @param  callable(mixed): string  $ligne  le HTML d'une ligne (<tr>…</tr>)
     */
    public static function repondre(Request $r, Builder $base, array $tri, callable $recherche, callable $ligne): JsonResponse
    {
        $total = (clone $base)->toBase()->getCountForPagination();

        $mots = RechercheTexte::mots((string) $r->input('search.value', ''));
        if ($mots !== []) {
            $recherche($base, $mots);
        }
        $filtre = (clone $base)->toBase()->getCountForPagination();

        $colonne = (int) $r->input('order.0.column', -1);
        if (isset($tri[$colonne])) {
            $sens = $r->input('order.0.dir') === 'asc' ? 'asc' : 'desc';
            $base->reorder();
            foreach ($tri[$colonne] as $c) {
                $base->orderBy($c, $sens);
            }
        }

        $debut = max(0, (int) $r->input('start', 0));
        $longueur = min(max(1, (int) $r->input('length', 10)), self::MAX_LIGNES);
        $lignes = $base->skip($debut)->take($longueur)->get()->map(fn ($m) => self::cellules($ligne($m)))->all();

        return response()->json(['draw' => (int) $r->input('draw', 0), 'recordsTotal' => $total, 'recordsFiltered' => $filtre, 'data' => $lignes]);
    }

    /** Les cellules (contenu HTML de chaque <td> de premier niveau) d'une ligne <tr> : un tableau imbriqué dans une cellule reste dans sa cellule. @return list<string> */
    public static function cellules(string $ligneHtml): array
    {
        preg_match_all('#<(/?)td(?![a-z])[^>]*>#i', $ligneHtml, $m, PREG_OFFSET_CAPTURE);
        $cellules = [];
        $profondeur = 0;
        $debut = 0;
        foreach ($m[0] as $k => [$balise, $pos]) {
            if ($m[1][$k][0] === '') {
                if ($profondeur++ === 0) {
                    $debut = $pos + strlen($balise);
                }
            } elseif (--$profondeur === 0) {
                $cellules[] = trim(substr($ligneHtml, $debut, $pos - $debut));
            }
        }

        return $cellules;
    }
}
