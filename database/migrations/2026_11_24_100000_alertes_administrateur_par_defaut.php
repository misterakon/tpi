<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 | Les administrateurs sont prévenus par défaut des règles de gravité « critique » : une alerte grave ne doit jamais dépendre d'un seul destinataire.
 | Chaque règle reste modifiable dans « Règles d'alerte » (case « Administrateurs »).
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (DB::table('regles_alerte')->where('niveau', 'critique')->get(['id', 'destinataires']) as $r) {
            $liste = json_decode((string) $r->destinataires, true) ?: [];
            if (! in_array('administrateur', $liste, true)) {
                DB::table('regles_alerte')->where('id', $r->id)->update(['destinataires' => json_encode(array_merge($liste, ['administrateur']))]);
            }
        }
    }

    public function down(): void
    {
        foreach (DB::table('regles_alerte')->where('niveau', 'critique')->get(['id', 'destinataires']) as $r) {
            $liste = array_values(array_diff(json_decode((string) $r->destinataires, true) ?: [], ['administrateur']));
            DB::table('regles_alerte')->where('id', $r->id)->update(['destinataires' => json_encode($liste)]);
        }
    }
};
