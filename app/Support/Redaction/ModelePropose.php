<?php

namespace App\Support\Redaction;

use App\Support\RapprochementObjet;
use Illuminate\Support\Facades\DB;

/*
 | Propose le modèle de décision d'une affaire d'après son objet. Chaque modèle est rattaché à des objets du litige (page « Modèles de
 | décision ») ; l'objet de l'affaire, saisi librement, est rapproché de ces objets avec les mêmes règles que l'enregistrement (sans
 | majuscules, accents ni pluriels). Le modèle dont l'objet correspond le mieux vient en premier ; sans correspondance, le greffier choisit.
 */
class ModelePropose
{
    /** Les objets rattachés à un modèle utilisable, avec leurs mots. @return list<array{id: int, nom: string, mots: list<string>}> */
    public static function liens(): array
    {
        return DB::table('modeles_decision_objets as mo')
            ->join('objets_litige as o', 'o.id', '=', 'mo.objet_litige_id')
            ->join('modeles_decision as m', 'm.id', '=', 'mo.modele_decision_id')
            ->whereIn('m.etat', ['actif', 'brouillon'])->where('o.actif', true)
            ->select('m.id', 'm.nom', 'o.libelle')->get()
            ->map(fn ($r) => ['id' => (int) $r->id, 'nom' => (string) $r->nom, 'mots' => RapprochementObjet::mots((string) $r->libelle)])->all();
    }

    /**
     * Les modèles qui conviennent à cet objet, le plus précis d'abord.
     *
     * @param  ?list<array{id: int, nom: string, mots: list<string>}>  $liens  résultat de liens(), pour ne le chercher qu'une fois
     * @return list<array{id: int, nom: string}>
     */
    public static function pour(?string $objet, ?array $liens = null): array
    {
        $texte = RapprochementObjet::mots((string) $objet);
        if ($texte === []) {
            return [];
        }
        $meilleurs = [];
        foreach ($liens ?? self::liens() as $lien) {
            if ($lien['mots'] === []) {
                continue;
            }
            $part = count(array_intersect($lien['mots'], $texte)) / count($lien['mots']);
            // Tous les mots de l'objet connu, ou au moins les deux tiers quand il en compte plusieurs : même règle qu'à l'enregistrement.
            if (! ($part >= 1 || (count($lien['mots']) >= 3 && $part >= 0.66))) {
                continue;
            }
            $score = [$part, count($lien['mots'])];
            if (! isset($meilleurs[$lien['id']]) || $score > $meilleurs[$lien['id']]['score']) {
                $meilleurs[$lien['id']] = ['id' => $lien['id'], 'nom' => $lien['nom'], 'score' => $score];
            }
        }
        usort($meilleurs, fn ($a, $b) => [$b['score'], $a['nom']] <=> [$a['score'], $b['nom']]);

        return array_map(fn ($m) => ['id' => $m['id'], 'nom' => $m['nom']], $meilleurs);
    }
}
