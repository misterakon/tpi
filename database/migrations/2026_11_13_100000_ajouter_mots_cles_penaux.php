<?php

use App\Models\Matiere;
use App\Models\NatureAffaire;
use App\Models\ObjetLitige;
use Illuminate\Database\Migrations\Migration;

/*
 | Mots-clés pénaux supplémentaires (vol, meurtre, viol, menaces, ivresse au volant, corruption…), rattachés à la chambre qui les juge
 | d'après l'ordonnance. Ajout seulement : ce que le tribunal a déjà modifié ou supprimé n'est pas touché, et les doublons sont ignorés.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (require database_path('seeders/data/objets_penaux.php') as [$chambre, $matiere, $nature, $objets]) {
            $matiereId = Matiere::where('libelle', $matiere)->value('id');
            if (! $matiereId) {
                continue;   // base neuve : les référentiels ne sont pas encore chargés, le seeder ajoutera ces mots-clés
            }
            $natureId = $nature ? NatureAffaire::where('libelle', $nature)->value('id') : null;
            foreach ($objets as $libelle) {
                ObjetLitige::firstOrCreate(['libelle' => $libelle, 'chambre_libelle' => $chambre], ['matiere_id' => $matiereId, 'nature_affaire_id' => $natureId, 'actif' => true]);
            }
        }
    }

    public function down(): void
    {
        // Les mots-clés ajoutés restent : le tribunal a pu les modifier entre-temps.
    }
};
