<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Ce que les statistiques relèvent en plus de la date : accordée ou rejetée, adoption, sous-catégorie, sortie saisie à la main. */
class ResultatAffaire extends Model
{
    public const SORTIES = ['decision_au_fond' => 'Décision au fond', 'radiation' => 'Radiation', 'pv_conciliation' => 'PV de conciliation totale', 'desistement' => 'Désistement'];

    public const RESULTATS = ['accord' => 'Accordée', 'rejet' => 'Rejetée'];

    public const CARACTERES = ['contradictoire' => 'Contradictoire', 'defaut' => 'Défaut', 'iteratif_defaut' => 'Itératif défaut'];

    public const ADOPTANTS = ['homme' => 'Homme', 'femme' => 'Femme', 'couple' => 'Couple', 'institution' => 'Institution', 'autre' => 'Autre'];

    protected $table = 'resultats_affaire';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['date_sortie' => 'date'];
    }
}
