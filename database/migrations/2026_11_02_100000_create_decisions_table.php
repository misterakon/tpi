<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /*
     | Décisions rédigées à partir d'un modèle (page Rédaction). Une décision garde la version du modèle qu'elle a utilisée et les
     | valeurs saisies. À la validation, le texte final est figé (« rendu ») : une modification ultérieure du modèle ne le change plus.
     | Le rattachement à une affaire (lot Enrôlement) viendra s'y ajouter : en attendant, la référence est saisie à la main.
     */
    public function up(): void
    {
        Schema::create('decisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('modele_decision_id')->constrained('modeles_decision')->restrictOnDelete();
            $table->unsignedSmallInteger('version_numero');
            $table->string('reference', 120);                     // ex. RG 017/2026 ; sera remplacée par le lien vers l'affaire
            $table->string('statut', 12)->default('brouillon');   // brouillon | validee
            $table->longText('valeurs')->nullable();              // JSON : code de variable => valeur saisie
            $table->longText('rendu')->nullable();                // JSON : texte final figé à la validation (entete, qualite, factum)
            $table->timestamp('validee_le')->nullable();
            $table->foreignId('validee_par')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('cree_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index('statut');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('decisions');
    }
};
