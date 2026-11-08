<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('affaires', function (Blueprint $table) {
            // Un même numéro RG peut figurer sur plusieurs affaires quand il s'agit d'une jonction : l'unicité est contrôlée par l'application.
            $table->dropUnique(['numero_complet']);
            $table->dropUnique(['serie', 'annee', 'numero']);
            $table->index('numero_complet');
            $table->index(['serie', 'annee', 'numero']);
            $table->boolean('numero_saisi')->default(false)->after('numero_complet');   // numéro reporté du greffe (saisi à la main)
            $table->boolean('meme_numero')->default(false)->after('jointe_a_id');       // jonction : elle porte le même numéro que l'affaire principale
        });
    }

    public function down(): void
    {
        Schema::table('affaires', function (Blueprint $table) {
            $table->dropColumn(['numero_saisi', 'meme_numero']);
            $table->dropIndex(['numero_complet']);
            $table->dropIndex(['serie', 'annee', 'numero']);
            $table->unique('numero_complet');
            $table->unique(['serie', 'annee', 'numero']);
        });
    }
};
