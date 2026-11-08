<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/** Règle d'enrôlement : pour une audience tenue tel jour, dernier jour et dernière heure pour enrôler l'affaire. */
class DelaiEnrolement extends Model
{
    protected $table = 'delais_enrolement';

    protected $guarded = [];

    /** Date et heure limites d'enrôlement pour une audience à la date donnée (le jour « précédent » le plus proche), ou null s'il n'y a pas de règle. */
    public static function limitePour(Carbon|string $audience): ?Carbon
    {
        $audience = Carbon::parse($audience)->startOfDay();
        $regle = self::where('jour_audience', $audience->isoWeekday())->first();
        if (! $regle) {
            return null;
        }

        $limite = $audience->copy();
        do {
            $limite->subDay();
        } while ($limite->isoWeekday() !== (int) $regle->jour_limite);

        return $limite->setTimeFromTimeString($regle->heure);
    }
}
