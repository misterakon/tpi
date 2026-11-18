<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 | Circuit de la décision au TPI : le juge ne « délivre » pas, il TRANSMET la décision signée au greffe ; c'est le greffe qui la délivre aux parties.
 |   - decisions.transmise_le / transmise_par : la transmission au greffe (les décisions déjà signées sont considérées comme transmises à leur signature) ;
 |   - droit « redaction.transmettre » : aux profils qui signent (les juges) ;
 |   - droit « redaction.word » : télécharger la décision en Word, à tous les profils qui voient la rédaction (juges, greffiers, coordination).
 | Le droit « redaction.delivrer » reste celui du greffe. Aucun droit n'est retiré.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('decisions', function (Blueprint $table) {
            $table->timestamp('transmise_le')->nullable()->after('delivree_le');
            $table->unsignedBigInteger('transmise_par')->nullable()->after('transmise_le');
        });

        DB::statement("UPDATE decisions SET transmise_le = COALESCE(delivree_le, signee_le, updated_at) WHERE statut IN ('signee', 'delivree') AND transmise_le IS NULL");

        foreach (DB::table('profils')->where('systeme', false)->get() as $profil) {
            $droits = json_decode((string) $profil->droits, true) ?: [];
            $avant = $droits;
            if (in_array('redaction.signer', $droits, true)) {
                $droits[] = 'redaction.transmettre';
            }
            if (in_array('redaction.voir', $droits, true)) {
                $droits[] = 'redaction.word';
            }
            $droits = array_values(array_unique($droits));
            if ($droits !== $avant) {
                DB::table('profils')->where('id', $profil->id)->update(['droits' => json_encode($droits), 'updated_at' => now()]);
            }
        }
    }

    public function down(): void
    {
        foreach (DB::table('profils')->where('systeme', false)->get() as $profil) {
            $droits = json_decode((string) $profil->droits, true) ?: [];
            $reste = array_values(array_diff($droits, ['redaction.transmettre', 'redaction.word']));
            if ($reste !== $droits) {
                DB::table('profils')->where('id', $profil->id)->update(['droits' => json_encode($reste), 'updated_at' => now()]);
            }
        }
        Schema::table('decisions', function (Blueprint $table) {
            $table->dropColumn(['transmise_le', 'transmise_par']);
        });
    }
};
