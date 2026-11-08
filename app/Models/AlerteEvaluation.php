<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Un jour évalué par le moteur d'alertes : sert au rattrapage quand le serveur est resté éteint, et au suivi de son bon fonctionnement. */
class AlerteEvaluation extends Model
{
    protected $table = 'alertes_evaluations';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['jour' => 'date'];
    }
}
