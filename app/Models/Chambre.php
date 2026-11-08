<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/*
 | Chambre (ou juridiction, ou cabinet d'instruction) d'une section pour une année judiciaire : elle a ses attributions.
 | Ses formations sont facultatives ; sans formation nommée, une formation unique (sans nom) porte la composition.
 */
class Chambre extends Model
{
    protected $table = 'chambres';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['actif' => 'boolean'];
    }

    public function annee(): BelongsTo
    {
        return $this->belongsTo(AnneeJudiciaire::class, 'annee_judiciaire_id');
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function formations(): HasMany
    {
        return $this->hasMany(Formation::class)->orderBy('ordre');
    }

    /** « 2e chambre civile – Immobilière et des paiements » */
    public function libelleComplet(): string
    {
        return $this->intitule ? $this->libelle.' – '.$this->intitule : $this->libelle;
    }
}
