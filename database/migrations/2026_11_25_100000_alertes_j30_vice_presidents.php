<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 | Retard critique (J+30) : la Présidente, les vice-présidents et le coordonnateur du greffe sont prévenus (décision du client, nov. 2026).
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (DB::table('regles_alerte')->where('code', 'J+30')->get(['id', 'destinataires']) as $r) {
            $liste = json_decode((string) $r->destinataires, true) ?: [];
            if (! in_array('vice_presidents', $liste, true)) {
                DB::table('regles_alerte')->where('id', $r->id)->update(['destinataires' => json_encode(array_values(array_merge($liste, ['vice_presidents'])))]);
            }
        }
    }

    public function down(): void
    {
        foreach (DB::table('regles_alerte')->where('code', 'J+30')->get(['id', 'destinataires']) as $r) {
            $liste = array_values(array_diff(json_decode((string) $r->destinataires, true) ?: [], ['vice_presidents']));
            DB::table('regles_alerte')->where('id', $r->id)->update(['destinataires' => json_encode($liste)]);
        }
    }
};
