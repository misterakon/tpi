<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 | Délais de procédure confirmés par le client d'après l'ordonnance (page 8) : civil 6 mois, correctionnel 3 mois, référé-expulsion 15 jours.
 | Ils ne sont plus « à valider ». Les autres délais (par étape) restent à confirmer par la Présidente.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('delais_procedure')->where('etape', 'procedure_complete')
            ->where(fn ($q) => $q->where(['libelle' => 'Civil', 'valeur' => 6, 'unite' => 'mois'])
                ->orWhere(fn ($w) => $w->where(['libelle' => 'Correctionnel', 'valeur' => 3, 'unite' => 'mois']))
                ->orWhere(fn ($w) => $w->where(['libelle' => 'Référé-expulsion', 'valeur' => 15, 'unite' => 'jours'])))
            ->update(['a_valider' => false]);
    }

    public function down(): void
    {
        DB::table('delais_procedure')->where('etape', 'procedure_complete')->update(['a_valider' => true]);
    }
};
