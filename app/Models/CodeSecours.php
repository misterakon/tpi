<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Un code de secours : jamais conservé en clair, seulement son empreinte. Voir App\Services\CodesSecours. */
class CodeSecours extends Model
{
    protected $table = 'codes_secours';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['utilise_le' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
