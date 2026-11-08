<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 | Reprise de l'existant (lot T) : import des registres tenus sous Excel. Un import suit une machine à états (téléversé, analysé, colonnes
 | choisies, contrôlé, importé) ; ses lignes sont lues une fois, contrôlées puis importées par paquets, ce qui donne une vraie progression.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('imports_reprise', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 26)->unique();                // identifiant public (ULID) : jamais l'identifiant interne dans les adresses
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('serie', 4);                                // RG | RP | RI | RE : une série par import
            $table->string('nom_fichier', 200);
            $table->string('chemin', 80)->nullable();                  // fichier privé, supprimé quand l'import est terminé ou abandonné
            $table->unsignedBigInteger('taille')->default(0);
            $table->string('empreinte', 64)->index();                  // SHA-256 : repère un fichier déjà importé
            $table->string('statut', 12)->default('televerse');      // televerse | analyse | colonnes | controle | pret | importe | annule | echec
            $table->string('feuille', 120)->nullable();
            $table->json('feuilles')->nullable();
            $table->unsignedSmallInteger('ligne_titres')->nullable();
            $table->json('colonnes')->nullable();                      // titres et trois exemples par colonne
            $table->json('correspondance')->nullable();                // champ de l'application => numéro de colonne
            $table->foreignId('chambre_defaut_id')->nullable()->constrained('chambres')->nullOnDelete();
            $table->unsignedInteger('total')->default(0);
            $table->unsignedInteger('traitees')->default(0);          // lignes contrôlées (puis importées) à ce stade
            $table->unsignedInteger('valides')->default(0);
            $table->unsignedInteger('erreurs')->default(0);
            $table->unsignedInteger('doublons')->default(0);
            $table->unsignedInteger('importees')->default(0);
            $table->string('message', 300)->nullable();
            $table->timestamp('verrou_jusqu_a')->nullable();           // un seul paquet à la fois pour un même import
            $table->foreignId('importe_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('importe_le')->nullable();
            $table->timestamp('annule_le')->nullable();
            $table->timestamps();
        });

        Schema::create('imports_reprise_lignes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_id')->constrained('imports_reprise')->cascadeOnDelete();
            $table->unsignedInteger('ligne');                          // numéro de la ligne dans le fichier
            $table->string('cle', 30)->nullable();                     // SÉRIE|ANNÉE|NUMÉRO : repère les doublons dans le fichier
            $table->json('cellules');                                  // les valeurs lues, en texte
            $table->string('statut', 10)->default('attente');        // attente | valide | erreur | doublon | importee
            $table->json('messages')->nullable();
            $table->json('resultat')->nullable();                      // données normalisées, prêtes à importer
            $table->foreignId('affaire_id')->nullable()->constrained('affaires')->nullOnDelete();
            $table->index(['import_id', 'statut', 'ligne']);
            $table->index(['import_id', 'cle']);
        });

        Schema::table('affaires', function (Blueprint $table) {
            $table->foreignId('reprise_import_id')->nullable()->after('cree_par')->constrained('imports_reprise')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('affaires', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reprise_import_id');
        });
        Schema::dropIfExists('imports_reprise_lignes');
        Schema::dropIfExists('imports_reprise');
    }
};
