<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /*
     | D6 : dates limites d'enrôlement (article 7 de l'ordonnance). Pour chaque jour d'audience, l'enrôlement des affaires
     | civiles, commerciales et administratives se clôt à un jour et une heure donnés de la semaine précédente.
     */
    public function up(): void
    {
        Schema::create('delais_enrolement', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('jour_audience')->unique();   // 1 = lundi … 7 = dimanche
            $table->unsignedTinyInteger('jour_limite');               // jour de la semaine précédant l'audience
            $table->time('heure');
            $table->string('remarque', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delais_enrolement');
    }
};
