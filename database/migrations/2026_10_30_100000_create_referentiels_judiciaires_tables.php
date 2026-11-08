<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /*
     | Lot E : référentiels judiciaires (types d'audience, caractères de décision, issues, modes de comparution et de saisine,
     | qualités procédurales, natures de partie, matières, natures d'affaire, délais de procédure, objets du litige, textes de loi).
     */
    public function up(): void
    {
        Schema::create('types_audience', function (Blueprint $table) {
            $table->id();
            $table->string('libelle', 160);
            $table->unsignedSmallInteger('ordre')->default(0);
            $table->boolean('actif')->default(true);
            $table->timestamps();
            $table->unique(['libelle']);
        });

        Schema::create('caracteres_decision', function (Blueprint $table) {
            $table->id();
            $table->string('libelle', 120);
            $table->unsignedSmallInteger('ordre')->default(0);
            $table->boolean('actif')->default(true);
            $table->boolean('a_valider')->default(false);
            $table->timestamps();
            $table->unique(['libelle']);
        });

        Schema::create('issues_audience', function (Blueprint $table) {
            $table->id();
            $table->string('libelle', 120);
            $table->boolean('demande_date')->default(false);
            $table->unsignedSmallInteger('ordre')->default(0);
            $table->boolean('actif')->default(true);
            $table->timestamps();
            $table->unique(['libelle']);
        });

        Schema::create('modes_comparution', function (Blueprint $table) {
            $table->id();
            $table->string('libelle', 120);
            $table->unsignedSmallInteger('ordre')->default(0);
            $table->boolean('actif')->default(true);
            $table->timestamps();
            $table->unique(['libelle']);
        });

        Schema::create('qualites_procedurales', function (Blueprint $table) {
            $table->id();
            $table->string('libelle', 120);
            $table->unsignedSmallInteger('ordre')->default(0);
            $table->boolean('actif')->default(true);
            $table->timestamps();
            $table->unique(['libelle']);
        });

        Schema::create('natures_partie', function (Blueprint $table) {
            $table->id();
            $table->string('libelle', 120);
            $table->unsignedSmallInteger('ordre')->default(0);
            $table->boolean('actif')->default(true);
            $table->timestamps();
            $table->unique(['libelle']);
        });

        Schema::create('modes_saisine', function (Blueprint $table) {
            $table->id();
            $table->string('libelle', 160);
            $table->string('registre', 30);
            $table->unsignedSmallInteger('ordre')->default(0);
            $table->boolean('actif')->default(true);
            $table->boolean('a_valider')->default(false);
            $table->timestamps();
            $table->unique(['libelle', 'registre']);
        });

        Schema::create('matieres', function (Blueprint $table) {
            $table->id();
            $table->string('libelle', 120);
            $table->unsignedSmallInteger('ordre')->default(0);
            $table->boolean('actif')->default(true);
            $table->timestamps();
            $table->unique(['libelle']);
        });

        Schema::create('natures_affaire', function (Blueprint $table) {
            $table->id();
            $table->string('libelle', 160);
            $table->string('registre', 30);
            $table->unsignedSmallInteger('ordre')->default(0);
            $table->boolean('actif')->default(true);
            $table->boolean('exclue_enrolement')->default(false);
            $table->timestamps();
            $table->unique(['libelle', 'registre']);
        });

        Schema::create('delais_procedure', function (Blueprint $table) {
            $table->id();
            $table->string('libelle', 160);
            $table->string('registre', 30)->nullable();
            $table->foreignId('nature_affaire_id')->nullable()->constrained('natures_affaire')->nullOnDelete();
            $table->unsignedSmallInteger('valeur');
            $table->string('unite', 30);
            $table->unsignedSmallInteger('renvois_max')->nullable();
            $table->unsignedSmallInteger('seuil_alerte')->nullable();
            $table->string('point_depart', 30);
            $table->unsignedSmallInteger('ordre')->default(0);
            $table->boolean('actif')->default(true);
            $table->boolean('a_valider')->default(true);
            $table->timestamps();
            $table->unique(['libelle']);
        });

        Schema::create('objets_litige', function (Blueprint $table) {
            $table->id();
            $table->string('libelle', 160);
            $table->foreignId('matiere_id')->constrained('matieres')->restrictOnDelete();
            $table->foreignId('nature_affaire_id')->nullable()->constrained('natures_affaire')->nullOnDelete();
            $table->string('chambre_libelle', 120)->nullable();
            $table->unsignedSmallInteger('ordre')->default(0);
            $table->boolean('actif')->default(true);
            $table->timestamps();
            $table->unique(['libelle', 'chambre_libelle']);
        });

        Schema::create('textes_loi', function (Blueprint $table) {
            $table->id();
            $table->string('texte', 200);
            $table->string('article', 60);
            $table->string('objet', 255)->nullable();
            $table->unsignedSmallInteger('ordre')->default(0);
            $table->boolean('actif')->default(true);
            $table->timestamps();
            $table->unique(['texte', 'article']);
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('textes_loi');
        Schema::dropIfExists('objets_litige');
        Schema::dropIfExists('delais_procedure');
        Schema::dropIfExists('natures_affaire');
        Schema::dropIfExists('matieres');
        Schema::dropIfExists('modes_saisine');
        Schema::dropIfExists('natures_partie');
        Schema::dropIfExists('qualites_procedurales');
        Schema::dropIfExists('modes_comparution');
        Schema::dropIfExists('issues_audience');
        Schema::dropIfExists('caracteres_decision');
        Schema::dropIfExists('types_audience');
    }
};
