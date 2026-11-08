<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Un compteur par série (RG, RP, RI, RE) et par année : verrouillé à chaque attribution, il évite doublons et trous.
        Schema::create('compteurs_registre', function (Blueprint $table) {
            $table->id();
            $table->string('serie', 4);
            $table->unsignedSmallInteger('annee');
            $table->unsignedInteger('dernier')->default(0);
            $table->timestamps();
            $table->unique(['serie', 'annee']);
        });

        Schema::create('affaires', function (Blueprint $table) {
            $table->id();
            $table->string('serie', 4)->default('RG');
            $table->unsignedSmallInteger('annee');
            $table->unsignedInteger('numero');
            $table->string('numero_complet', 20)->unique();           // RG-0001/26 : ne change jamais, n'est jamais réutilisé
            $table->string('ancien_numero', 40)->nullable();          // numéro d'avant l'application (reprise de l'existant)
            $table->date('date_rg');
            $table->foreignId('mode_saisine_id')->nullable()->constrained('modes_saisine')->nullOnDelete();
            $table->text('objet');                                    // objet du litige, saisi librement
            $table->foreignId('nature_affaire_id')->nullable()->constrained('natures_affaire')->nullOnDelete();
            $table->foreignId('matiere_id')->nullable()->constrained('matieres')->nullOnDelete();
            $table->foreignId('chambre_id')->nullable()->constrained('chambres')->nullOnDelete();
            $table->date('date_premiere_audience')->nullable();
            $table->string('statut', 20)->default('enrolee');       // enrolee | annulee
            $table->string('motif_annulation', 300)->nullable();
            $table->timestamp('annulee_le')->nullable();
            $table->foreignId('annulee_par')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('cree_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['serie', 'annee', 'numero']);
            $table->index('statut');
        });

        Schema::create('parties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('affaire_id')->constrained('affaires')->cascadeOnDelete();
            $table->string('role', 12);                               // demandeur | defendeur
            $table->string('nature', 12);                             // homme | femme | couple | morale | etat
            $table->string('nom', 160)->nullable();                   // personne physique : nom
            $table->string('prenoms', 200)->nullable();
            $table->string('denomination', 250)->nullable();          // couple, personne morale, État ou collectivité
            $table->string('cle', 200)->index();                      // nom normalisé : sert à repérer les doublons
            $table->string('naissance_mode', 5)->nullable();          // date | annee
            $table->date('naissance_date')->nullable();
            $table->unsignedSmallInteger('naissance_annee')->nullable();
            $table->string('lieu_naissance', 120)->nullable();
            $table->string('nationalite', 60)->nullable();
            $table->string('profession', 120)->nullable();
            $table->string('domicile', 250)->nullable();
            $table->string('avocat', 200)->nullable();                // en attendant le référentiel des tiers
            $table->unsignedSmallInteger('autres')->default(0);       // « et x autres »
            $table->unsignedSmallInteger('ordre')->default(0);
            $table->timestamps();
            $table->index(['affaire_id', 'role']);
        });

        Schema::create('affaire_attributions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('affaire_id')->constrained('affaires')->cascadeOnDelete();
            $table->foreignId('chambre_avant_id')->nullable()->constrained('chambres')->nullOnDelete();
            $table->foreignId('chambre_apres_id')->nullable()->constrained('chambres')->nullOnDelete();
            $table->string('motif', 300);
            $table->foreignId('par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('affaire_attributions');
        Schema::dropIfExists('parties');
        Schema::dropIfExists('affaires');
        Schema::dropIfExists('compteurs_registre');
    }
};
