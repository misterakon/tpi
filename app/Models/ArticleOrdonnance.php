<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Un article de l'ordonnance, avec son texte complet. Le numéro est une étiquette : l'application ne s'y fie jamais (il change d'une ordonnance à l'autre). */
class ArticleOrdonnance extends Model
{
    /** Feuille du classeur Excel que l'article pilote. */
    public const RATTACHEMENTS = ['sections' => 'Sections', 'composition' => 'Chambres, composition et audiences', 'services' => 'Services et responsables', 'dates_limites' => 'Dates limites', 'audiences' => 'Audiences'];

    protected $table = 'articles_ordonnance';

    protected $guarded = [];

    public function annee(): BelongsTo
    {
        return $this->belongsTo(AnneeJudiciaire::class, 'annee_judiciaire_id');
    }
}
