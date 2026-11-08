<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Un scellé (pièce à conviction) d'une affaire pénale ou d'instruction. */
class Scelle extends Model
{
    public const NATURES = ['arme' => 'Arme', 'stupefiant' => 'Stupéfiants', 'argent' => 'Argent', 'vehicule' => 'Véhicule', 'document' => 'Document', 'telephone' => 'Téléphone ou appareil', 'autre' => 'Autre objet'];

    public const ETATS = ['conserve' => 'Conservé au greffe', 'restitue' => 'Restitué', 'detruit' => 'Détruit', 'confisque' => 'Confisqué', 'remis_domaine' => 'Remis au service des domaines'];

    protected $table = 'scelles';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['date_saisie' => 'date', 'date_etat' => 'date'];
    }

    public function affaire(): BelongsTo
    {
        return $this->belongsTo(Affaire::class);
    }

    public function estConserve(): bool
    {
        return $this->etat === 'conserve';
    }
}
