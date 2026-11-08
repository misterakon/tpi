<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Suivi d'instruction (lot R) : voir la migration 2026_12_08_100000_instruction. */
class SuiviVictime extends Model
{
    protected $table = 'suivis_victimes';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'date_requisitoire' => 'date', 'date_faits' => 'date', 'date_cloture' => 'date', 'date_transmission_parquet' => 'date', 'traite' => 'boolean', 'date_ogp' => 'date', 'date_titre_detention' => 'date',
            'date_renouvellement' => 'date', 'date_liberte_provisoire' => 'date', 'date_mainlevee_cj' => 'date', 'audition_1' => 'date', 'audition_2' => 'date', 'audition_3' => 'date', 'date_notification_cloture' => 'date',
        ];
    }

    public function partie(): BelongsTo
    {
        return $this->belongsTo(Partie::class);
    }
}
