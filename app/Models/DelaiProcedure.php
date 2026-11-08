<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Délai d'une étape (lot E : référentiels judiciaires). */
class DelaiProcedure extends Model
{
    public const ETAPES = ['procedure_complete' => 'Procédure complète (de l\'ouverture de l\'audience à la décision)', 'enrolement' => 'Enrôlement', 'programmation' => 'Programmation', 'role' => 'Rôle d\'audience', 'audience' => 'Audience', 'renvoi_delibere' => 'Renvoi ou délibéré', 'saisie_decision' => 'Saisie après décision', 'redaction' => 'Rédaction de la décision', 'cloture' => 'Clôture', 'remise' => 'Remise au justiciable'];

    public const ACTEURS = ['presidente' => 'Présidente du Tribunal', 'juge' => 'Juge de la formation', 'coordonnateur' => 'Coordonnateur du greffe', 'greffier_chambre' => 'Greffier de chambre', 'agent_enrolement' => 'Agent d\'enrôlement', 'secretariat' => 'Secrétariat de la Présidence', 'greffier_cabinet' => 'Greffier de cabinet d\'instruction', 'juge_instruction' => 'Juge d\'instruction ou juge des enfants', 'parquet' => 'Parquet'];

    public const UNITES = ['jours_ouvrables' => 'jours ouvrables', 'jours' => 'jours calendaires', 'mois' => 'mois'];

    public const POINTS_DEPART = ['ouverture_audience' => 'L\'ouverture de l\'audience', 'enrolement' => 'L\'enrôlement de l\'affaire', 'reception' => 'La réception du dossier', 'mise_en_delibere' => 'La mise en délibéré', 'decision_rendue' => 'La décision rendue', 'etape_precedente' => 'La fin de l\'étape précédente'];

    protected $table = 'delais_procedure';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['actif' => 'boolean', 'a_valider' => 'boolean'];
    }

    public function nature(): BelongsTo
    {
        return $this->belongsTo(NatureAffaire::class, 'nature_affaire_id');
    }
}
