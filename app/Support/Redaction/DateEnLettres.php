<?php

namespace App\Support\Redaction;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Dates en toutes lettres pour les décisions : « vendredi quatre juillet deux mil vingt-cinq ».
 * Le premier du mois s'écrit « premier » ; l'année s'écrit avec « mil ».
 */
class DateEnLettres
{
    public const JOURS = [1 => 'lundi', 2 => 'mardi', 3 => 'mercredi', 4 => 'jeudi', 5 => 'vendredi', 6 => 'samedi', 7 => 'dimanche'];

    public const MOIS = [1 => 'janvier', 2 => 'février', 3 => 'mars', 4 => 'avril', 5 => 'mai', 6 => 'juin', 7 => 'juillet', 8 => 'août', 9 => 'septembre', 10 => 'octobre', 11 => 'novembre', 12 => 'décembre'];

    /** « vendredi quatre juillet deux mil vingt-cinq » (avec le jour de la semaine). */
    public static function complete(CarbonInterface|string $date): string
    {
        $d = self::date($date);

        return self::JOURS[$d->isoWeekday()].' '.self::sansJour($d);
    }

    /** « quatre juillet deux mil vingt-cinq ». */
    public static function sansJour(CarbonInterface|string $date): string
    {
        $d = self::date($date);
        $jour = $d->day === 1 ? 'premier' : NombreEnLettres::convertir($d->day);

        return $jour.' '.self::MOIS[$d->month].' '.NombreEnLettres::annee($d->year);
    }

    /** « vendredi 04 juillet 2025 » : le jour de la semaine, puis la date en chiffres avec le mois en lettres. */
    public static function jourEtChiffres(CarbonInterface|string $date): string
    {
        $d = self::date($date);

        return self::JOURS[$d->isoWeekday()].' '.$d->format('d').' '.self::MOIS[$d->month].' '.$d->year;
    }

    /** « 16 janvier 2025 » (« 1er janvier 2025 » pour le premier du mois) : la date écrite dans le corps d'un jugement. */
    public static function courte(CarbonInterface|string $date): string
    {
        $d = self::date($date);

        return ($d->day === 1 ? '1er' : $d->day).' '.self::MOIS[$d->month].' '.$d->year;
    }

    /** « 04/07/2025 ». */
    public static function numerique(CarbonInterface|string $date): string
    {
        return self::date($date)->format('d/m/Y');
    }

    /** « vendredi quatre juillet deux mil vingt-cinq (04/07/2025) ». */
    public static function lettresEtChiffres(CarbonInterface|string $date): string
    {
        return self::complete($date).' ('.self::numerique($date).')';
    }

    private static function date(CarbonInterface|string $date): CarbonInterface
    {
        return $date instanceof CarbonInterface ? $date : Carbon::parse($date);
    }
}
