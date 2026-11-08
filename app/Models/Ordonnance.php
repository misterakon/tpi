<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Ordonnance signée archivée (PDF) : de l'année, ou modificative en cours d'année. */
class Ordonnance extends Model
{
    public const TYPES = ['annuelle' => "Ordonnance de l'année", 'modificative' => 'Ordonnance modificative'];

    public const DOSSIER = 'ordonnances';

    protected $table = 'ordonnances';

    protected $guarded = [];

    protected $hidden = ['chemin', 'empreinte'];

    protected function casts(): array
    {
        return ['date_signature' => 'date', 'date_effet' => 'date', 'taille' => 'integer'];
    }

    public function annee(): BelongsTo
    {
        return $this->belongsTo(AnneeJudiciaire::class, 'annee_judiciaire_id');
    }

    public function deposePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'depose_par');
    }

    public function libelle(): string
    {
        return 'Ordonnance n° '.$this->numero;
    }

    public function tailleLisible(): string
    {
        return $this->taille >= 1048576 ? number_format($this->taille / 1048576, 1, ',', ' ').' Mo' : max(1, (int) round($this->taille / 1024)).' Ko';
    }
}
