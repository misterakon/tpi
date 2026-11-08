<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /*
     | Réglages modifiables depuis l'application (page « Réglages »).
     | Une ligne n'existe que pour un réglage qui diffère de sa valeur par défaut
     | (celle des fichiers de configuration) : supprimer la ligne rétablit le défaut.
     | Les réglages sensibles (accès à la base, clés, mots de passe de messagerie) n'y figurent jamais.
     */
    public function up(): void
    {
        Schema::create('reglages', function (Blueprint $table) {
            $table->id();
            $table->string('cle', 120)->unique();
            $table->text('valeur')->nullable();
            $table->unsignedBigInteger('modifie_par')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reglages');
    }
};
