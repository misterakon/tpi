<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /*
     | Les délais de procédure deviennent des délais par étape du circuit d'une affaire, avec la personne qui doit agir.
     | Les règles existantes (civil, correctionnel, référé-expulsion) concernent la procédure complète. Les seuils d'alerte
     | passeront par une page de règles d'alerte à part : la colonne « seuil_alerte » disparaît.
     */
    public function up(): void
    {
        Schema::table('delais_procedure', function (Blueprint $table) {
            $table->string('etape', 30)->default('procedure_complete')->after('libelle');
            $table->string('acteur', 30)->nullable()->after('etape');
            $table->dropColumn('seuil_alerte');
        });
    }

    public function down(): void
    {
        Schema::table('delais_procedure', function (Blueprint $table) {
            $table->dropColumn(['etape', 'acteur']);
            $table->unsignedSmallInteger('seuil_alerte')->nullable();
        });
    }
};
