<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 | Délais de recours : le cahier des charges n'en fixe aucun, on s'appuie sur la norme usuelle (procédure civile, commerciale et administrative ; procédure pénale) :
 | appel civil 1 mois, opposition 15 jours, appel en référé 15 jours, appel correctionnel 10 jours. Ils sont confirmés d'office et restent modifiables dans « Délais de recours » (droit recours.delais).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delais_recours', function (Blueprint $table) {
            $table->string('source', 160)->nullable()->after('valide_le');
        });
        DB::table('delais_recours')->where('valide', false)->update(['valide' => true, 'valide_le' => now(), 'source' => 'Norme usuelle (procédure civile ou pénale), modifiable par le greffe']);
    }

    public function down(): void
    {
        Schema::table('delais_recours', function (Blueprint $table) {
            $table->dropColumn('source');
        });
    }
};
