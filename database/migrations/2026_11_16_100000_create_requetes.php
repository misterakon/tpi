<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 | Requêtes (ENR-10), dont les requêtes d'hérédité : déposées au greffe, transmises au secrétariat de la Présidente qui les répartit
 | entre les formations. Une requête n'a pas de défendeur ; elle a ses propres numéros (série RQ, compteur par année).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('requetes', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('annee');
            $table->unsignedInteger('numero');
            $table->string('numero_complet', 20)->unique();           // RQ-0001/26 : jamais réutilisé
            $table->string('type', 24);                               // heredite | ordonnance_requete | voies_execution | injonction | bref_delai | autre
            $table->date('date_depot');
            $table->text('objet');
            $table->text('requerants');                                // un requérant par ligne
            $table->string('defunt_nom', 200)->nullable();             // hérédité : le défunt
            $table->date('defunt_date_deces')->nullable();
            $table->string('defunt_lieu_deces', 160)->nullable();
            $table->string('statut', 12)->default('recue');           // recue | repartie | traitee | classee
            $table->foreignId('chambre_id')->nullable()->constrained('chambres')->nullOnDelete();
            $table->foreignId('reparti_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reparti_le')->nullable();
            $table->string('note_repartition', 300)->nullable();
            $table->date('traitee_le')->nullable();
            $table->string('reference_decision', 120)->nullable();    // « Ordonnance n° … », « Jugement d'hérédité n° … », ou motif du classement
            $table->foreignId('affaire_id')->nullable()->constrained('affaires')->nullOnDelete();
            $table->foreignId('cree_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['annee', 'numero']);
            $table->index(['statut', 'chambre_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('requetes');
    }
};
