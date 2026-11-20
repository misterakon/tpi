<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 | Le classeur de l'ordonnance s'appuie sur les numéros (id) de la base, qui sont des entiers : les codes texte essayés un moment sur les agents,
 | salles, chambres, formations et services sont retirés. (Le code interne des sections, qui existait déjà, reste.)
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['agents', 'salles', 'chambres', 'formations', 'services'] as $table) {
            if (Schema::hasColumn($table, 'code')) {
                Schema::table($table, function (Blueprint $t) use ($table) {
                    $t->dropIndex($table.'_code_index');
                    $t->dropColumn('code');
                });
            }
        }
    }

    public function down(): void
    {
        // Pas de retour.
    }
};
