<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Infraction proposée à l'enrôlement d'une affaire pénale (libellé et articles du code pénal). */
class Infraction extends Model
{
    protected $table = 'infractions';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['actif' => 'boolean'];
    }
}
