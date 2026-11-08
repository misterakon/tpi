<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /*
     | Lot D : organisation du siège pour une année judiciaire.
     |   section  >  chambre (attributions)  >  formation (facultative : composition et audiences)
     | Une chambre qui n'a pas de formation nommée porte sa composition dans une formation unique (nom vide).
     | Les affectations (président, assesseurs, greffiers…) ont des dates de validité pour suivre les remplacements.
     */
    public function up(): void
    {
        Schema::create('sections', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('libelle', 120);
            $table->unsignedSmallInteger('ordre')->default(0);
            $table->timestamps();
        });

        Schema::create('chambres', function (Blueprint $table) {
            $table->id();
            $table->foreignId('annee_judiciaire_id')->constrained('annees_judiciaires')->cascadeOnDelete();
            $table->foreignId('section_id')->constrained('sections')->restrictOnDelete();
            $table->string('groupe', 120)->nullable();            // ex. Les juridictions criminelles, Les juridictions correctionnelles
            $table->string('libelle', 120);                       // ex. 2e chambre civile
            $table->string('intitule', 160)->nullable();          // ex. Immobilière et des paiements
            $table->text('attributions')->nullable();             // attributions communes à la chambre
            $table->text('remarque')->nullable();                 // ex. juges chargés de la mise en état
            $table->unsignedSmallInteger('ordre')->default(0);
            $table->boolean('actif')->default(true);
            $table->timestamps();
            $table->unique(['annee_judiciaire_id', 'libelle']);
        });

        Schema::create('formations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chambre_id')->constrained('chambres')->cascadeOnDelete();
            $table->string('nom', 120)->nullable();               // ex. Formation A, 1re formation ; vide = composition de la chambre
            $table->string('intitule', 160)->nullable();          // ex. Tribunal de simple police
            $table->text('attributions')->nullable();             // attributions propres à la formation
            $table->unsignedSmallInteger('ordre')->default(0);
            $table->boolean('actif')->default(true);
            $table->timestamps();
            $table->index('chambre_id');
        });

        Schema::create('affectations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('formation_id')->constrained('formations')->cascadeOnDelete();
            $table->foreignId('agent_id')->constrained('agents')->restrictOnDelete();
            $table->string('role', 30);                           // president, assesseur, juge_delegue, juge, greffier, assesseur_spjej, ...
            $table->boolean('suppleant')->default(false);
            $table->boolean('chef_section')->default(false);     // greffier chef de section
            $table->date('debut')->nullable();
            $table->date('fin')->nullable();
            $table->string('remarque', 200)->nullable();
            $table->timestamps();
            $table->index(['formation_id', 'role']);
            $table->index('agent_id');
        });

        Schema::create('creneaux', function (Blueprint $table) {
            $table->id();
            $table->foreignId('formation_id')->constrained('formations')->cascadeOnDelete();
            $table->string('type', 160);                          // audience publique, conciliation, réception des requêtes, référé, session…
            $table->unsignedTinyInteger('jour')->nullable();      // 1 = lundi … 7 = dimanche
            $table->time('heure')->nullable();
            $table->string('frequence', 20)->default('hebdomadaire');   // hebdomadaire | trimestrielle | a_la_demande
            $table->foreignId('salle_id')->nullable()->constrained('salles')->nullOnDelete();
            $table->string('precision', 200)->nullable();         // ex. après l'audience de la formation A
            $table->timestamps();
            $table->index('formation_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('creneaux');
        Schema::dropIfExists('affectations');
        Schema::dropIfExists('formations');
        Schema::dropIfExists('chambres');
        Schema::dropIfExists('sections');
    }
};
