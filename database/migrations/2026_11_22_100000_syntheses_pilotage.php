<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 | Analyse et pilotage : l'historique des notes de synthèse (automatiques ou rédigées par l'IA). Une note garde le texte et les chiffres
 | qui l'ont produite, pour pouvoir la relire et la comparer. Le plafond quotidien de l'IA se compte sur cette table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('syntheses_pilotage', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source', 10);                          // modele | ia
            $table->string('periode_type', 12);                    // mois | trimestre | annee
            $table->date('periode_debut');
            $table->date('periode_fin');
            $table->string('perimetre', 120)->nullable();           // « Toutes les chambres » ou la liste des chambres
            $table->longText('contenu');                            // texte de la note (Markdown simple : titres « ## » et puces « - »)
            $table->longText('chiffres')->nullable();               // JSON : les totaux qui ont servi
            $table->string('modele_ia', 60)->nullable();
            $table->timestamps();
            $table->index(['source', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('syntheses_pilotage');
    }
};
