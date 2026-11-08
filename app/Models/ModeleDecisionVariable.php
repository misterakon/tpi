<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Variable propre à un modèle de décision (en plus du catalogue commun), avec une valeur fictive pour l'aperçu. */
class ModeleDecisionVariable extends Model
{
    protected $table = 'modeles_decision_variables';

    protected $guarded = [];
}
