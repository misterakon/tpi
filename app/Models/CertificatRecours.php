<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Un certificat de non-appel ou de non-opposition délivré à l'échéance du délai de recours. Numéro : N/AAAA, un compteur par année. */
class CertificatRecours extends Model
{
    public const TYPES = ['non_appel' => 'Certificat de non-appel', 'non_opposition' => 'Certificat de non-opposition'];

    protected $table = 'certificats_recours';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['date_signification' => 'date', 'echeance' => 'date', 'date_delivrance' => 'date'];
    }

    public function decision(): BelongsTo
    {
        return $this->belongsTo(Decision::class);
    }

    public function affaire(): BelongsTo
    {
        return $this->belongsTo(Affaire::class);
    }

    public function numeroAffichage(): string
    {
        return $this->numero.'/'.$this->annee;
    }
}
