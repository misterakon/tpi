<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('affaires', function (Blueprint $table) {
            $table->string('articles', 300)->nullable()->after('objet');   // articles du code pénal visés (affaires pénales et d'instruction)
        });
        Schema::table('parties', function (Blueprint $table) {
            $table->date('mandat_depot_date')->nullable()->after('domicile');   // prévenu ou inculpé détenu : date du mandat de dépôt
        });

        // L'information judiciaire relève de l'instruction, pas du pénal de jugement.
        DB::table('modes_saisine')->where('libelle', 'Information judiciaire')->update(['registre' => 'instruction']);
    }

    public function down(): void
    {
        Schema::table('parties', function (Blueprint $table) {
            $table->dropColumn('mandat_depot_date');
        });
        Schema::table('affaires', function (Blueprint $table) {
            $table->dropColumn('articles');
        });
        DB::table('modes_saisine')->where('libelle', 'Information judiciaire')->update(['registre' => 'penal']);
    }
};
