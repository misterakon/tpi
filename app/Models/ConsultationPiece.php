<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Une consultation d'une pièce du dossier numérique : qui l'a ouverte ou téléchargée, et quand. */
class ConsultationPiece extends Model
{
    public $timestamps = false;

    protected $table = 'dossier_consultations';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function utilisateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
