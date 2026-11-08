<?php

namespace App\Services;

use App\Models\Decision;
use Illuminate\Support\Facades\DB;

/*
 | Numéro de décision : 1, 2, 3… dans l'année, sans trou ni doublon, jamais réutilisé. Le compteur est verrouillé le temps de l'attribution :
 | deux greffiers qui signent en même temps reçoivent deux numéros différents. L'année est celle de la date de la décision.
 | Le moment de l'attribution est un réglage (Réglages > Décisions). Pratique retenue : le numéro est généré automatiquement quand le juge valide la décision
 | après son factum. Le greffier de la chambre peut aussi le donner à la main une fois la décision « rédigée » (option du réglage).
 */
class NumeroDecision
{
    public const MOMENTS = [
        'redaction' => 'Par le greffier de la chambre, quand le juge a terminé sa rédaction',
        'validation' => 'À la validation de la décision',
        'signature' => 'À la signature de la décision',
    ];

    /** Le numéro s'écrit « D-N/AAAA » : D comme décision, N le numéro dans l'année, AAAA l'année de la décision (D-145/2026). */
    public const PREFIXE = 'D-';

    public static function libelle(int $numero, int $annee): string
    {
        return self::PREFIXE.$numero.'/'.$annee;
    }

    public static function moment(): string
    {
        $m = (string) config('tpi.decisions.numero_moment', 'validation');

        return array_key_exists($m, self::MOMENTS) ? $m : 'validation';
    }

    /** Attribue le numéro si la décision n'en a pas encore (sinon ne change rien). */
    public static function attribuer(Decision $decision): Decision
    {
        if ($decision->numero !== null) {
            return $decision;
        }

        return DB::transaction(function () use ($decision) {
            $annee = (int) ($decision->date_decision ?? today())->format('Y');
            DB::table('numeros_decision')->insertOrIgnore(['annee' => $annee, 'dernier' => 0, 'created_at' => now(), 'updated_at' => now()]);
            $compteur = DB::table('numeros_decision')->where('annee', $annee)->lockForUpdate()->first();
            $seq = (int) $compteur->dernier + 1;
            DB::table('numeros_decision')->where('id', $compteur->id)->update(['dernier' => $seq, 'updated_at' => now()]);

            $decision->forceFill(['numero_seq' => $seq, 'numero_annee' => $annee, 'numero' => (string) $seq, 'numero_le' => now()])->save();

            return $decision;
        });
    }
}
