<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLES = ['types_audience', 'modes_saisine', 'matieres', 'natures_affaire', 'delais_procedure', 'objets_litige'];

    /* Les pages du menu Référentiels n'ont plus de notion d'ordre : les listes s'affichent par ordre alphabétique. */
    public function up(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, fn (Blueprint $t) => $t->dropColumn('ordre'));
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, fn (Blueprint $t) => $t->unsignedSmallInteger('ordre')->default(0));
        }
    }
};
