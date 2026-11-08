<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/** Jour férié, vacances judiciaires ou fermeture exceptionnelle : aucune audience ces jours-là. */
class Fermeture extends Model
{
    public const TYPES = ['ferie' => 'Jour férié', 'vacances' => 'Vacances judiciaires', 'fermeture' => 'Fermeture exceptionnelle'];

    /** Fêtes nationales fixes en Côte d'Ivoire : [mois, jour, libellé]. Les fêtes mobiles (Pâques, Ascension, Pentecôte, fêtes musulmanes) se saisissent chaque année. */
    public const FERIES_FIXES = [
        [1, 1, "Jour de l'An"], [5, 1, 'Fête du Travail'], [8, 7, "Fête de l'Indépendance"], [8, 15, 'Assomption'],
        [11, 1, 'Toussaint'], [11, 15, 'Journée nationale de la Paix'], [12, 25, 'Noël'],
    ];

    protected $table = 'fermetures';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['debut' => 'date', 'fin' => 'date'];
    }

    public function annee(): BelongsTo
    {
        return $this->belongsTo(AnneeJudiciaire::class, 'annee_judiciaire_id');
    }

    /** Ce jour tombe-t-il dans une fermeture du tribunal ? */
    public static function couvre(Carbon|string $jour): ?self
    {
        $jour = Carbon::parse($jour)->toDateString();

        return self::whereDate('debut', '<=', $jour)->whereDate('fin', '>=', $jour)->first();
    }

    public function jours(): int
    {
        return $this->debut->diffInDays($this->fin) + 1;
    }
}
