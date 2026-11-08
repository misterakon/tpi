<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Bloc de texte réutilisable dans les modèles de décision (visas, formule de dépens, clôture…). Il s'insère par {{> code }}. */
class BlocTexte extends Model
{
    public const CATEGORIES = [
        'visas' => 'Visas', 'motifs' => 'Motifs', 'dispositif' => 'Dispositif', 'depens' => 'Dépens', 'cloture' => 'Clôture', 'formule' => 'Formule', 'autre' => 'Autre',
    ];

    protected $table = 'blocs_texte';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['actif' => 'boolean'];
    }
}
