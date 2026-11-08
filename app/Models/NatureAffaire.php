<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Nature d'affaire (lot E : référentiels judiciaires). */
class NatureAffaire extends Model
{
    public const REGISTRES = ['RG' => 'Civil (RG)', 'RP' => 'Pénal (RP)', 'RI' => 'Instruction, adultes (RI)', 'RE' => 'Instruction, enfants (RE)'];

    protected $table = 'natures_affaire';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['actif' => 'boolean', 'exclue_enrolement' => 'boolean'];
    }
}
