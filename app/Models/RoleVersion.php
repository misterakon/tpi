<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Le rôle tel qu'il a été publié : ne change plus. */
class RoleVersion extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'role_versions';

    protected $guarded = [];

    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'par');
    }

    public function contenu(): array
    {
        return json_decode($this->getRawOriginal('contenu'), true) ?: [];
    }
}
