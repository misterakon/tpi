<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /*
     | Modèles de décision (cahier des charges DEC-03, DEC-04, DEC-06, PAR-10) :
     |  - un modèle par type de décision (hérédité, divorce…), en deux parties : la qualité et le factum ;
     |  - chaque modification des textes crée une nouvelle version : une décision garde la version qu'elle a utilisée ;
     |  - des blocs de texte réutilisables (visas, dépens, clôture) ;
     |  - des variables propres à un modèle, en plus du catalogue commun ;
     |  - le rattachement aux objets du litige et aux chambres, pour proposer le bon modèle.
     */
    public function up(): void
    {
        Schema::create('blocs_texte', function (Blueprint $table) {
            $table->id();
            $table->string('code', 60)->unique();               // utilisé dans les modèles : {{> code }}
            $table->string('nom', 160);
            $table->string('categorie', 20);                    // visas | motifs | dispositif | depens | cloture | formule | autre
            $table->longText('contenu');                        // texte mis en forme, avec variables
            $table->boolean('actif')->default(true);
            $table->timestamps();
        });

        Schema::create('modeles_decision', function (Blueprint $table) {
            $table->id();
            $table->string('nom', 160)->unique();
            $table->foreignId('matiere_id')->nullable()->constrained('matieres')->nullOnDelete();
            $table->string('description', 255)->nullable();
            $table->string('etat', 12)->default('brouillon');   // brouillon | actif | archive
            $table->timestamps();
        });

        Schema::create('modeles_decision_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('modele_decision_id')->constrained('modeles_decision')->cascadeOnDelete();
            $table->unsignedSmallInteger('numero');
            $table->longText('qualite')->nullable();
            $table->longText('factum')->nullable();
            $table->string('note', 255)->nullable();            // ce qui a changé
            $table->foreignId('cree_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['modele_decision_id', 'numero']);
        });

        Schema::create('modeles_decision_variables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('modele_decision_id')->constrained('modeles_decision')->cascadeOnDelete();
            $table->string('code', 60);                         // ex. defunt.nom
            $table->string('libelle', 160);
            $table->string('type', 12);                         // texte | date | nombre | genre | liste | personne | personnes
            $table->text('exemple')->nullable();                // valeur fictive pour l'aperçu (JSON pour une liste ou une personne)
            $table->timestamps();
            $table->unique(['modele_decision_id', 'code']);
        });

        Schema::create('modeles_decision_objets', function (Blueprint $table) {
            $table->foreignId('modele_decision_id')->constrained('modeles_decision')->cascadeOnDelete();
            $table->foreignId('objet_litige_id')->constrained('objets_litige')->cascadeOnDelete();
            $table->primary(['modele_decision_id', 'objet_litige_id']);
        });

        Schema::create('modeles_decision_chambres', function (Blueprint $table) {
            $table->foreignId('modele_decision_id')->constrained('modeles_decision')->cascadeOnDelete();
            $table->string('chambre_libelle', 120);             // le libellé d'une chambre reste stable d'une année à l'autre
            $table->primary(['modele_decision_id', 'chambre_libelle']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('modeles_decision_chambres');
        Schema::dropIfExists('modeles_decision_objets');
        Schema::dropIfExists('modeles_decision_variables');
        Schema::dropIfExists('modeles_decision_versions');
        Schema::dropIfExists('modeles_decision');
        Schema::dropIfExists('blocs_texte');
    }
};
