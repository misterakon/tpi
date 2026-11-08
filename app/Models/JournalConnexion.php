<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/** Ligne du journal de connexion : écriture seule, jamais modifiée ni supprimée. */
class JournalConnexion extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'journal_connexions';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['succes' => 'boolean', 'details' => 'array', 'created_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Le journal de connexion est non modifiable.'));
        static::deleting(fn () => throw new LogicException('Le journal de connexion est non modifiable.'));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }
}
