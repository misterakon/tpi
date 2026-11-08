<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Un délai de recours (appel, opposition). « valide » : confirmé par la personne habilitée ; sans cela, aucun certificat ne peut être délivré. */
class DelaiRecours extends Model
{
    public const NATURES = ['appel' => 'Appel', 'opposition' => 'Opposition'];

    public const UNITES = ['jours' => 'jours', 'mois' => 'mois'];

    protected $table = 'delais_recours';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['actif' => 'boolean', 'valide' => 'boolean', 'valide_le' => 'datetime'];
    }

    public function natureAffaire(): BelongsTo
    {
        return $this->belongsTo(NatureAffaire::class, 'nature_affaire_id');
    }

    public function validePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'valide_par');
    }

    public function libelleDelai(): string
    {
        return $this->valeur.' '.($this->unite === 'mois' ? 'mois' : ($this->valeur > 1 ? 'jours' : 'jour'));
    }
}
