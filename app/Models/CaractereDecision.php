<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Caractère de décision (lot E : référentiels judiciaires). */
class CaractereDecision extends Model
{
    protected $table = 'caracteres_decision';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['actif' => 'boolean', 'a_valider' => 'boolean'];
    }
}
