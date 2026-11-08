<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 | Types de rôle (d'après les modèles du tribunal) : rôle civil, rôle de référé, rôle pénal composé et signé par le parquet.
 | Le rôle pénal porte un intitulé d'audience (« AUDIENCE PUBLIQUE ORDINAIRE DES FLAGRANTS DELITS »), peut être « additif » et
 | garde le bloc président / assesseurs / procureur / greffier, rempli à la main sur les rôles papier.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles_audience', function (Blueprint $table) {
            $table->string('type', 8)->default('civil')->after('chambre_id');      // civil | refere | penal
            $table->string('intitule', 160)->nullable()->after('type');
            $table->boolean('additif')->default(false)->after('intitule');
            $table->string('president', 200)->nullable()->after('additif');
            $table->string('assesseurs', 300)->nullable()->after('president');
            $table->string('procureur', 200)->nullable()->after('assesseurs');
            $table->string('greffier', 200)->nullable()->after('procureur');
        });
    }

    public function down(): void
    {
        Schema::table('roles_audience', function (Blueprint $table) {
            $table->dropColumn(['type', 'intitule', 'additif', 'president', 'assesseurs', 'procureur', 'greffier']);
        });
    }
};
