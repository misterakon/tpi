<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Issue d'audience (lot E : référentiels judiciaires). */
class IssueAudience extends Model
{
    protected $table = 'issues_audience';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['demande_date' => 'boolean', 'actif' => 'boolean'];
    }
}
