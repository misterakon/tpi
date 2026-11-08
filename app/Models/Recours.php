<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Un recours formé contre une décision : date, nature, auteur, transmission à la Cour d'appel d'Abidjan. */
class Recours extends Model
{
    public const NATURES = ['appel' => 'Appel', 'opposition' => 'Opposition', 'pourvoi' => 'Pourvoi', 'autre' => 'Autre recours'];

    public const STATUTS = ['forme' => 'Formé, à transmettre', 'transmis' => 'Transmis à la Cour', 'abandonne' => 'Abandonné'];

    protected $table = 'recours';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['date_recours' => 'date', 'date_transmission' => 'date'];
    }

    public function affaire(): BelongsTo
    {
        return $this->belongsTo(Affaire::class);
    }

    public function decision(): BelongsTo
    {
        return $this->belongsTo(Decision::class);
    }
}
