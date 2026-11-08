<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Année judiciaire : l'organisation du tribunal (ordonnance) est fixée pour une année donnée. */
class AnneeJudiciaire extends Model
{
    protected $table = 'annees_judiciaires';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['debut' => 'date', 'fin' => 'date', 'courante' => 'boolean'];
    }

    /** L'année en cours (celle marquée « courante »), s'il y en a une. */
    public static function courante(): ?self
    {
        return self::where('courante', true)->first();
    }
}
