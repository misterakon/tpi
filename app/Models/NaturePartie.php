<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Nature de partie (lot E : référentiels judiciaires). */
class NaturePartie extends Model
{
    protected $table = 'natures_partie';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['actif' => 'boolean'];
    }
}
