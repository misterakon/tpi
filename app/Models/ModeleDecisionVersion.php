<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Une version des textes d'un modèle (numérotée à partir de 1) ; elle n'est jamais modifiée après coup. */
class ModeleDecisionVersion extends Model
{
    protected $table = 'modeles_decision_versions';

    protected $guarded = [];

    public function modele(): BelongsTo
    {
        return $this->belongsTo(ModeleDecision::class, 'modele_decision_id');
    }

    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cree_par');
    }
}
