<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 | Catégories du personnel : magistrat, greffier, assesseur, personnel administratif et technique (agents inter-ministériels),
 | membre extérieur (bureaux, commissions). Les agents déjà enregistrés sont classés d'après leur fonction ;
 | aucune donnée existante n'est modifiée ni supprimée.
 */
return new class extends Migration
{
    private const PAR_FONCTION = [
        'magistrat' => ['presidente', 'vice_president', 'juge', 'juge_instruction', 'juge_enfants'],
        'greffier' => ['greffier_en_chef', 'greffier_en_chef_adjoint', 'greffier', 'secretaire'],
        'assesseur' => ['assesseur_spjej', 'assesseur_employeur', 'assesseur_travailleur'],
        'administratif' => ['service_social', 'autre'],
    ];

    public function up(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            $table->string('categorie', 20)->default('administratif')->after('fonction')->index();
        });

        foreach (self::PAR_FONCTION as $categorie => $fonctions) {
            DB::table('agents')->whereIn('fonction', $fonctions)->update(['categorie' => $categorie]);
        }
    }

    public function down(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            $table->dropIndex(['categorie']);
            $table->dropColumn('categorie');
        });
    }
};
