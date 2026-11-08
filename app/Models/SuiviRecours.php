<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** La signification d'une décision (point de départ du délai de recours) et la voie de recours surveillée. Une ligne par décision. */
class SuiviRecours extends Model
{
    protected $table = 'suivis_recours';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['date_signification' => 'date'];
    }

    public function decision(): BelongsTo
    {
        return $this->belongsTo(Decision::class);
    }
}
