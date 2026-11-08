<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/** Ligne du journal d'audit : écriture seule, jamais modifiée ni supprimée. */
class JournalAudit extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'journal_audit';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['avant' => 'array', 'apres' => 'array', 'created_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException("Le journal d'audit est non modifiable."));
        static::deleting(fn () => throw new LogicException("Le journal d'audit est non modifiable."));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }
}
