<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 | Base du chargement de l'ordonnance par fichier Excel :
 |   - les articles de l'ordonnance, avec leur texte complet (un article inconnu n'est jamais perdu) ;
 |   - les membres des bureaux locaux qui sont extérieurs au tribunal (trésorier, commissaire de justice, avocat, sous-préfet…).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('articles_ordonnance', function (Blueprint $t) {
            $t->id();
            $t->foreignId('annee_judiciaire_id')->constrained('annees_judiciaires')->cascadeOnDelete();
            $t->unsignedSmallInteger('ordre')->default(0);
            $t->string('numero', 40);                 // « Article 7 », « Article premier » : étiquette libre, jamais utilisée comme clé
            $t->string('intitule', 200)->nullable();
            $t->longText('texte');
            $t->string('rattache_a', 40)->nullable(); // feuille du classeur que l'article pilote : services, composition, dates_limites, audiences, ou vide
            $t->timestamps();
        });

        Schema::create('membres_exterieurs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('service_id')->constrained('services')->cascadeOnDelete();
            $t->string('nom', 200);
            $t->string('qualite', 300)->nullable();
            $t->string('institution', 300)->nullable();
            $t->unsignedSmallInteger('ordre')->default(0);
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('membres_exterieurs');
        Schema::dropIfExists('articles_ordonnance');
    }
};
