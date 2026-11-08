<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Service, comité, délégation ou fonction particulière fixé par l'ordonnance d'une année judiciaire. */
class Service extends Model
{
    public const CATEGORIES = [
        'coordination' => 'Coordination, contrôle et supervision',
        'comite' => 'Comités et commissions',
        'delegation' => 'Délégations',
        'service' => 'Services annexes',
        'fonction' => 'Fonctions particulières',
        'bureau' => 'Bureaux locaux',
    ];

    protected $table = 'services';

    protected $guarded = [];

    public function annee(): BelongsTo
    {
        return $this->belongsTo(AnneeJudiciaire::class, 'annee_judiciaire_id');
    }

    public function agents(): HasMany
    {
        return $this->hasMany(ServiceAgent::class)->orderBy('ordre');
    }
}
