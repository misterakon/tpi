<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/* Référentiel des avocats et cabinets : un nom toujours écrit de la même façon, rattaché aux parties des affaires. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('avocats', function (Blueprint $table) {
            $table->id();
            $table->string('type', 10)->default('avocat');            // avocat | cabinet
            $table->string('nom', 160);
            $table->string('prenoms', 160)->nullable();
            $table->string('cabinet', 200)->nullable();
            $table->string('barreau_numero', 60)->nullable();
            $table->string('telephone', 40)->nullable();
            $table->string('email', 160)->nullable();
            $table->string('cle', 200)->index();                       // nom normalisé : sert à repérer un doublon
            $table->boolean('actif')->default(true);
            $table->boolean('a_completer')->default(false);            // créé à la volée à l'enrôlement, fiche à compléter
            $table->timestamps();
        });

        Schema::table('parties', function (Blueprint $table) {
            $table->foreignId('avocat_id')->nullable()->after('avocat')->constrained('avocats')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('parties', function (Blueprint $table) {
            $table->dropConstrainedForeignId('avocat_id');
        });
        Schema::dropIfExists('avocats');
    }
};
