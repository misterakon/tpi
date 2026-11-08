<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/*
 | Attribution des numéros de registre (RG, RP, RI, RE) au format RG-0001/26, avec redémarrage chaque année.
 | Le compteur est verrouillé le temps de l'attribution : deux greffiers qui enrôlent en même temps reçoivent deux numéros différents,
 | sans trou. À appeler dans la transaction qui crée l'affaire : si la création échoue, le numéro n'est pas consommé.
 */
class NumerotationRegistre
{
    /** @return array{annee: int, numero: int, libelle: string} */
    public static function attribuer(string $serie, int $annee): array
    {
        return DB::transaction(function () use ($serie, $annee) {
            DB::table('compteurs_registre')->insertOrIgnore(['serie' => $serie, 'annee' => $annee, 'dernier' => 0, 'created_at' => now(), 'updated_at' => now()]);

            $compteur = DB::table('compteurs_registre')->where('serie', $serie)->where('annee', $annee)->lockForUpdate()->first();
            $numero = (int) $compteur->dernier + 1;
            DB::table('compteurs_registre')->where('id', $compteur->id)->update(['dernier' => $numero, 'updated_at' => now()]);

            return ['annee' => $annee, 'numero' => $numero, 'libelle' => self::formater($serie, $numero, $annee)];
        });
    }

    /** Verrouille le compteur d'une série et d'une année (à appeler dans une transaction) : deux saisies simultanées d'un même numéro s'attendent. */
    public static function verrouiller(string $serie, int $annee): void
    {
        DB::table('compteurs_registre')->insertOrIgnore(['serie' => $serie, 'annee' => $annee, 'dernier' => 0, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('compteurs_registre')->where('serie', $serie)->where('annee', $annee)->lockForUpdate()->first();
    }

    /** « RG-0001/26 » : quatre chiffres, année sur deux chiffres. */
    public static function formater(string $serie, int $numero, int $annee): string
    {
        return sprintf('%s-%04d/%02d', $serie, $numero, $annee % 100);
    }
}
