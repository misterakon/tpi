<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 | Droits par type d'affaire : les affaires de mineurs et les dossiers d'instruction sont protégés (droits spéciaux affaires.famille.mineurs et
 | affaires.famille.instruction). Pour ne rien casser, les profils qui voient déjà toutes les chambres (Présidente, coordonnateur du greffe…) les reçoivent ;
 | les autres profils ne les ont pas : à donner expressément, dans « Profils et droits », au personnel des cabinets et des juridictions concernés.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (DB::table('profils')->get(['id', 'droits']) as $p) {
            $droits = json_decode((string) $p->droits, true) ?: [];
            if (in_array('affaires.toutes_chambres', $droits, true)) {
                $nouveaux = array_values(array_unique(array_merge($droits, ['affaires.famille.mineurs', 'affaires.famille.instruction'])));
                DB::table('profils')->where('id', $p->id)->update(['droits' => json_encode($nouveaux)]);
            }
        }
    }

    public function down(): void
    {
        foreach (DB::table('profils')->get(['id', 'droits']) as $p) {
            $droits = json_decode((string) $p->droits, true) ?: [];
            DB::table('profils')->where('id', $p->id)->update(['droits' => json_encode(array_values(array_diff($droits, ['affaires.famille.mineurs', 'affaires.famille.instruction'])))]);
        }
    }
};
