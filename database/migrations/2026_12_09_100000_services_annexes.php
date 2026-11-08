<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 | Lot S, services annexes :
 |  - assistance judiciaire : les demandes examinées par le bureau local, avec décision et recours (classeur « Assistance judiciaire ») ;
 |  - activités du juge de l'application des peines : des compteurs mensuels par rubrique (classeur « Activités du JAP »).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assistance_judiciaire', function (Blueprint $t) {
            $t->id();
            $t->string('numero', 40);                                  // « N° et date du RG »
            $t->date('date_rg');
            $t->string('demandeur', 200);
            $t->string('objet', 300)->nullable();
            $t->string('nature', 60)->nullable();
            $t->date('date_audience')->nullable();
            $t->string('decision', 10)->nullable();                    // accord | rejet
            $t->string('numero_decision', 40)->nullable();
            $t->date('date_decision')->nullable();
            $t->date('date_recours')->nullable();
            $t->date('date_transmission_recours')->nullable();         // transmission à la DACP
            $t->string('observation', 500)->nullable();
            $t->foreignId('cree_par')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->index('date_rg');
        });

        Schema::create('activites_jap', function (Blueprint $t) {
            $t->id();
            $t->unsignedSmallInteger('annee');
            $t->unsignedTinyInteger('mois');
            $t->string('cle', 40);                                     // ex. « placement_recues », « visites_effectuees »
            $t->unsignedInteger('valeur')->default(0);
            $t->timestamps();
            $t->unique(['annee', 'mois', 'cle']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activites_jap');
        Schema::dropIfExists('assistance_judiciaire');
    }
};
