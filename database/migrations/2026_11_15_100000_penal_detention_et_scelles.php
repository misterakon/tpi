<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 | Détails du pénal (lot O) : suivi de la détention (lieu, échéance du mandat, mise en liberté) et scellés d'une affaire.
 | L'échéance se saisit si on la connaît (mandat, prolongation) ; sinon elle est calculée d'après la durée réglée dans Réglages > Affaires.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('parties', function (Blueprint $table) {
            $table->string('lieu_detention', 160)->nullable()->after('mandat_depot_date');
            $table->date('echeance_detention')->nullable()->after('lieu_detention');
            $table->date('libere_le')->nullable()->after('echeance_detention');
        });

        Schema::create('scelles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('affaire_id')->constrained('affaires')->cascadeOnDelete();
            $table->string('numero', 40);                            // « S1 », « Scellé n° 2 » : tel qu'il figure sur le procès-verbal
            $table->string('designation', 250);
            $table->string('nature', 20)->default('autre');           // arme | stupefiant | argent | vehicule | document | telephone | autre
            $table->date('date_saisie')->nullable();
            $table->string('lieu_conservation', 160)->nullable();
            $table->string('etat', 20)->default('conserve');          // conserve | restitue | detruit | confisque | remis_domaine
            $table->date('date_etat')->nullable();
            $table->string('observations', 500)->nullable();
            $table->foreignId('cree_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['affaire_id', 'etat']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scelles');
        Schema::table('parties', function (Blueprint $table) {
            $table->dropColumn(['lieu_detention', 'echeance_detention', 'libere_le']);
        });
    }
};
