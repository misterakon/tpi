<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/*
 | Recherche « tous les mots » dans des colonnes (et, au besoin, celles d'une relation), insensible à la casse, avec les jokers SQL « % » et « _ » traités comme de simples
 | caractères. Sert à la recherche plein texte et aux listes paginées côté serveur.
 */
class RechercheTexte
{
    /** Les mots d'une saisie : au moins deux caractères chacun, six au plus. @return list<string> */
    public static function mots(string $saisie): array
    {
        return array_slice(array_values(array_filter(preg_split('/\s+/u', mb_substr($saisie, 0, 120)) ?: [], fn ($t) => mb_strlen($t) >= 2)), 0, 6);
    }

    /**
     * Restreint la requête aux lignes où chaque mot se retrouve dans l'une des colonnes, ou dans l'une des colonnes de la relation.
     *
     * @param  list<string>  $mots
     * @param  list<string>  $colonnes
     * @param  list<string>  $colonnesRelation
     */
    public static function appliquer(Builder $requete, array $mots, array $colonnes, ?string $relation = null, array $colonnesRelation = []): Builder
    {
        $grammaire = DB::getQueryGrammar();
        foreach ($mots as $mot) {
            $motif = '%'.str_replace(['|', '%', '_'], ['||', '|%', '|_'], $mot).'%';      // « | » protège les jokers SQL : « 100% » se cherche tel quel
            $requete->where(function ($w) use ($colonnes, $relation, $colonnesRelation, $motif, $grammaire) {
                foreach ($colonnes as $c) {
                    $w->orWhereRaw($grammaire->wrap($c)." like ? escape '|'", [$motif]);
                }
                if ($relation) {
                    $w->orWhereHas($relation, function ($r) use ($colonnesRelation, $motif, $grammaire) {
                        $r->where(function ($x) use ($colonnesRelation, $motif, $grammaire) {
                            foreach ($colonnesRelation as $c) {
                                $x->orWhereRaw($grammaire->wrap($c)." like ? escape '|'", [$motif]);
                            }
                        });
                    });
                }
            });
        }

        return $requete;
    }
}
