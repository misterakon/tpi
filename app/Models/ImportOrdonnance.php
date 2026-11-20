<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Un fichier Excel d'ordonnance chargé : brouillon (rien n'est modifié), appliqué, annulé ou abandonné. */
class ImportOrdonnance extends Model
{
    public const STATUTS = ['brouillon' => 'En préparation', 'applique' => 'Appliqué', 'annule' => 'Annulé', 'abandonne' => 'Abandonné'];

    public const MODES = ['maj' => 'Mise à jour (ajouter et modifier)', 'complet' => "Ordonnance complète (ce qui n'y figure plus est clôturé)"];

    protected $table = 'imports_ordonnance';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'donnees' => 'array', 'controle' => 'array', 'rapprochements' => 'array', 'changements' => 'array',
            'applique_le' => 'datetime', 'annule_le' => 'datetime',
        ];
    }

    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function annee(): BelongsTo
    {
        return $this->belongsTo(AnneeJudiciaire::class, 'annee_judiciaire_id');
    }

    public function enCours(): bool
    {
        return $this->statut === 'brouillon';
    }
}
