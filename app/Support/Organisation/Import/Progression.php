<?php

namespace App\Support\Organisation\Import;

use Illuminate\Support\Facades\Cache;

/*
 | Avancement du contrôle d'un fichier, lu par l'écran pendant que le serveur travaille (voir public/assets/js/import-ordonnance.js).
 | Le serveur note ses étapes dans le cache ; l'écran les interroge toutes les fractions de seconde. Rien de confidentiel n'y figure
 | (étape, pourcentage, nom d'une feuille). Valable quelques minutes.
 */
class Progression
{
    /** Les étapes, dans l'ordre, avec la part de la barre qu'elles occupent (de 0 à 100). */
    public const ETAPES = [
        'verification' => ['Vérification du fichier', 0, 8],
        'lecture' => ['Lecture des feuilles', 8, 50],
        'controle' => ['Contrôle des données', 50, 85],
        'comparaison' => ["Comparaison avec l'organisation", 85, 100],
    ];

    private static function cle(int $id): string
    {
        return 'import_ordonnance.progression.'.$id;
    }

    public static function debut(int $id): void
    {
        Cache::put(self::cle($id), ['etape' => 'verification', 'pct' => 0, 'message' => 'Ouverture du fichier…', 'termine' => false, 'tick' => 0], 600);
    }

    /** @param int $fait  éléments traités (par exemple 4 feuilles lues) @param int $total  éléments à traiter */
    public static function noter(int $id, string $etape, int $fait, int $total, string $message): void
    {
        [, $de, $a] = self::ETAPES[$etape];
        $pct = $total > 0 ? $de + (int) round(($a - $de) * min(1, $fait / $total)) : $de;
        $ancien = Cache::get(self::cle($id), ['tick' => 0]);
        Cache::put(self::cle($id), ['etape' => $etape, 'pct' => $pct, 'message' => $message, 'termine' => false, 'tick' => ($ancien['tick'] ?? 0) + 1], 600);
    }

    public static function fin(int $id, string $message = 'Terminé'): void
    {
        Cache::put(self::cle($id), ['etape' => 'comparaison', 'pct' => 100, 'message' => $message, 'termine' => true, 'tick' => 9999], 600);
    }

    /** @return array{etape: string, pct: int, message: string, termine: bool, tick: int}|null */
    public static function lire(int $id): ?array
    {
        return Cache::get(self::cle($id));
    }

    /** Un rappel prêt à brancher sur la lecture ou le contrôle : fn(étape, fait, total, message). */
    public static function rappel(int $id): \Closure
    {
        return fn (string $etape, int $fait, int $total, string $message) => self::noter($id, $etape, $fait, $total, $message);
    }
}
