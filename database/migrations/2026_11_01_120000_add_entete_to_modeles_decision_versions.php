<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /*
     | Le timbre : le bloc d'en-tête de la colonne de gauche de la première page (cour d'appel, tribunal, numéro du RG, chambre,
     | type de décision, numéro, affaire et parties), tel qu'il figure dans les documents Word du tribunal. Il fait partie du modèle.
     */
    public function up(): void
    {
        Schema::table('modeles_decision_versions', function (Blueprint $table) {
            $table->longText('entete')->nullable()->after('numero');
        });
    }

    public function down(): void
    {
        Schema::table('modeles_decision_versions', function (Blueprint $table) {
            $table->dropColumn('entete');
        });
    }
};
