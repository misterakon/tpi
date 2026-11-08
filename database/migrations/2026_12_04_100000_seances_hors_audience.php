<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/* Séances hors audience publique (AUD-11) : réception des requêtes au cabinet du juge, conciliation, tutelle. Chaque séance garde les dossiers examinés et leur issue. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seances_hors_audience', function (Blueprint $t) {
            $t->id();
            $t->foreignId('chambre_id')->nullable()->constrained('chambres')->nullOnDelete();
            $t->string('type', 30);                         // reception_requetes | conciliation | tutelle
            $t->date('date_seance');
            $t->string('heure', 5)->nullable();
            $t->string('lieu', 120)->nullable();
            $t->foreignId('juge_id')->nullable()->constrained('agents')->nullOnDelete();
            $t->foreignId('greffier_id')->nullable()->constrained('agents')->nullOnDelete();
            $t->string('statut', 15)->default('prevue');   // prevue | tenue | annulee
            $t->text('observations')->nullable();
            $t->foreignId('cree_par')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->index(['date_seance', 'chambre_id']);
        });

        Schema::create('seance_dossiers', function (Blueprint $t) {
            $t->id();
            $t->foreignId('seance_id')->constrained('seances_hors_audience')->cascadeOnDelete();
            $t->foreignId('affaire_id')->nullable()->constrained('affaires')->nullOnDelete();
            $t->string('objet', 250);
            $t->string('issue', 250)->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seance_dossiers');
        Schema::dropIfExists('seances_hors_audience');
    }
};
