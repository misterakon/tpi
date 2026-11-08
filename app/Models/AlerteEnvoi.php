<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Ce qui est parti : une alerte, une règle, un canal, une personne. Une seule fois (contrainte d'unicité). */
class AlerteEnvoi extends Model
{
    public $timestamps = false;

    protected $table = 'alertes_envois';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['envoye_le' => 'datetime'];
    }
}
