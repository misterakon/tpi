<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 | Rôles d'audience (lot J) : un rôle par chambre et par date d'audience, avec trois rubriques comme dans les modèles de la greffière en chef
 | (affaires en délibéré, affaires anciennes, affaires nouvelles). Les lignes reprennent les affaires sans ressaisie ; chaque publication
 | garde une version figée (texte du rôle tel qu'il a été diffusé).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles_audience', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chambre_id')->constrained('chambres')->restrictOnDelete();
            $table->date('date_audience');
            $table->time('heure')->nullable();
            $table->foreignId('salle_id')->nullable()->constrained('salles')->nullOnDelete();
            $table->string('statut', 10)->default('brouillon');       // brouillon | publie
            $table->unsignedSmallInteger('version')->default(1);
            $table->boolean('avec_avocats')->default(false);          // « (Me X) » sous les parties, comme sur le rôle de janvier
            $table->string('fait_a', 80)->default('Bingerville');
            $table->date('date_signature')->nullable();
            $table->string('signataire', 80)->default('Le Président');
            $table->foreignId('cree_par')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('publie_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('publie_le')->nullable();
            $table->timestamps();
            $table->unique(['chambre_id', 'date_audience']);
            $table->index(['date_audience', 'salle_id']);
        });

        Schema::create('role_lignes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')->constrained('roles_audience')->cascadeOnDelete();
            $table->string('rubrique', 10);                           // deliberes | anciennes | nouvelles
            $table->unsignedSmallInteger('ordre')->default(0);
            $table->foreignId('affaire_id')->nullable()->constrained('affaires')->nullOnDelete();
            $table->string('numero', 120)->default('');              // « 690/26 et 848/26 » (jonction) ; vide = ligne libre
            $table->text('demandeurs')->nullable();                   // une partie par ligne
            $table->text('defendeurs')->nullable();
            $table->text('avocats_demandeurs')->nullable();
            $table->text('avocats_defendeurs')->nullable();
            $table->text('objet')->nullable();
            $table->text('observations')->nullable();
            $table->string('issue', 12)->nullable();                  // suite donnée à l'audience : renvoi | delibere | juge | radie | joint
            $table->date('renvoi_au')->nullable();
            $table->string('origine', 8)->default('auto');            // auto | ajout | libre
            $table->timestamps();
            $table->index(['role_id', 'rubrique', 'ordre']);
            $table->index('affaire_id');
        });

        Schema::create('role_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')->constrained('roles_audience')->cascadeOnDelete();
            $table->unsignedSmallInteger('version');
            $table->string('motif', 300)->nullable();
            $table->longText('contenu');                              // le rôle figé (JSON)
            $table->foreignId('par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['role_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_versions');
        Schema::dropIfExists('role_lignes');
        Schema::dropIfExists('roles_audience');
    }
};
