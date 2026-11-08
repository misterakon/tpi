<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Affectation d'un agent à une formation (président, assesseur, greffier…), avec ses dates de validité. */
class Affectation extends Model
{
    public const ROLES = [
        'president' => 'Président',
        'juge' => 'Juge',
        'juge_delegue' => 'Juge délégué',
        'assesseur' => 'Assesseur',
        'assesseur_spjej' => 'Assesseur SPJEJ',
        'assesseur_employeur' => 'Assesseur employeur',
        'assesseur_travailleur' => 'Assesseur travailleur',
        'representant_spjej' => 'Représentant SPJEJ',
        'greffier' => 'Greffier',
    ];

    protected $table = 'affectations';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['suppleant' => 'boolean', 'chef_section' => 'boolean', 'debut' => 'date', 'fin' => 'date'];
    }

    public function formation(): BelongsTo
    {
        return $this->belongsTo(Formation::class);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class)->withTrashed();
    }
}
