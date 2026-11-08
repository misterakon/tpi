<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /*
     | D6 : jours fériés, vacances judiciaires et fermetures exceptionnelles du tribunal pour une année judiciaire.
     | Aucune audience n'est tenue ces jours-là ; une journée seule a un début et une fin identiques.
     */
    public function up(): void
    {
        Schema::create('fermetures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('annee_judiciaire_id')->constrained('annees_judiciaires')->cascadeOnDelete();
            $table->string('type', 20);                           // ferie | vacances | fermeture
            $table->string('libelle', 160);
            $table->date('debut');
            $table->date('fin');
            $table->string('remarque', 255)->nullable();
            $table->timestamps();
            $table->index(['annee_judiciaire_id', 'debut']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fermetures');
    }
};
