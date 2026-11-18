<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 | La délivrance n'est plus une étape de l'application : le travail du juge s'arrête à la transmission au greffe, et la date de transmission
 | vaut date de délivrance. Le cycle devient : brouillon, rédigée, validée, signée, transmise au greffe.
 |   - les décisions « délivrées » et les décisions signées déjà transmises passent au statut « transmise » ;
 |   - transmise_le et delivree_le reçoivent la même date ;
 |   - le droit « redaction.delivrer » est retiré des profils. L'historique des remises (table delivrances) est conservé, en lecture seule.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("UPDATE decisions SET transmise_le = COALESCE(transmise_le, delivree_le, signee_le, updated_at) WHERE statut = 'delivree' AND transmise_le IS NULL");
        DB::statement("UPDATE decisions SET statut = 'transmise' WHERE statut = 'delivree' OR (statut = 'signee' AND transmise_le IS NOT NULL)");
        DB::statement("UPDATE decisions SET delivree_le = transmise_le WHERE statut = 'transmise'");

        foreach (DB::table('profils')->get() as $profil) {
            $droits = json_decode((string) $profil->droits, true) ?: [];
            $restants = array_values(array_diff($droits, ['redaction.delivrer']));
            if ($restants !== $droits) {
                DB::table('profils')->where('id', $profil->id)->update(['droits' => json_encode($restants), 'updated_at' => now()]);
            }
        }
    }

    public function down(): void
    {
        // Pas de retour : on ne sait plus quelles décisions avaient été délivrées par le greffe avant la fusion avec la transmission.
    }
};
