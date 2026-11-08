<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Mode de saisine (lot E : référentiels judiciaires). */
class ModeSaisine extends Model
{
    public const REGISTRES = ['civil' => 'Civil (RG)', 'penal' => 'Pénal (RP)', 'instruction' => 'Instruction (RI, RE)'];

    protected $table = 'modes_saisine';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['actif' => 'boolean', 'a_valider' => 'boolean'];
    }
}
