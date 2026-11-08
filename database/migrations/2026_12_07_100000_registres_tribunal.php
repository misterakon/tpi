<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/* Registres au modèle du tribunal : les colonnes des classeurs que l'application ne connaissait pas encore (caractère de la décision civile, statut des adoptants, observations). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('resultats_affaire', function (Blueprint $table) {
            $table->string('caractere', 20)->nullable()->after('resultat');            // contradictoire | defaut | iteratif_defaut | contumace | mixte
            $table->string('adoptant_ivoirien', 15)->nullable()->after('adoption_garcons');   // homme | femme | couple | institution | autre
            $table->string('adoptant_etranger', 15)->nullable()->after('adoptant_ivoirien');
            $table->string('observations', 300)->nullable()->after('sous_categorie');
        });
    }

    public function down(): void
    {
        Schema::table('resultats_affaire', function (Blueprint $table) {
            $table->dropColumn(['caractere', 'adoptant_ivoirien', 'adoptant_etranger', 'observations']);
        });
    }
};
