<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('affaires', function (Blueprint $table) {
            // Dérogation de la Présidente quand la date limite d'enrôlement est dépassée.
            $table->string('derogation_motif', 300)->nullable()->after('date_premiere_audience');
            $table->foreignId('derogation_par')->nullable()->after('derogation_motif')->constrained('users')->nullOnDelete();
            // Jonction des causes : l'affaire jointe pointe vers l'affaire principale, chacune garde son numéro.
            $table->foreignId('jointe_a_id')->nullable()->after('statut')->constrained('affaires')->nullOnDelete();
        });

        Schema::table('decisions', function (Blueprint $table) {
            // Affaire dont la décision est rédigée : ses données (numéro, date du RG, chambre, demandeur) remplissent le formulaire.
            $table->foreignId('affaire_id')->nullable()->after('chambre_id')->constrained('affaires')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('decisions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('affaire_id');
        });
        Schema::table('affaires', function (Blueprint $table) {
            $table->dropConstrainedForeignId('jointe_a_id');
            $table->dropConstrainedForeignId('derogation_par');
            $table->dropColumn('derogation_motif');
        });
    }
};
