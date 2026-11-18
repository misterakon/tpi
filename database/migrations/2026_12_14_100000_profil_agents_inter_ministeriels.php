<?php

use App\Support\Droits;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 | Profil des agents inter-ministériels (agents de bureau, administratifs, informaticiens, agents techniques).
 | Accès minimal : consultation de l'organisation du tribunal, aucun dossier, aucune affaire, aucune décision.
 | Les droits se complètent ensuite dans « Profils et droits », selon les tâches confiées à ces agents.
 | Sans effet si un profil de ce nom existe déjà (rien n'est écrasé).
 */
return new class extends Migration
{
    private const NOM = 'Agent administratif et technique';

    private const DROITS = [
        'personnel.voir',      // l'annuaire du tribunal
        'chambres.voir', 'sections.voir', 'affectations.voir',   // qui siège où
        'services.voir',       // services, comités, délégations
        'salles.voir',         // les salles d'audience
        'fermetures.voir',     // jours fériés et vacances
    ];

    public function up(): void
    {
        if (DB::table('profils')->where('nom', self::NOM)->exists()) {
            return;
        }

        DB::table('profils')->insert([
            'nom' => self::NOM,
            'description' => "Agents inter-ministériels (agents de bureau, administratifs, informaticiens, techniques) : consultation de l'organisation du tribunal, sans accès aux dossiers. À compléter selon leurs tâches.",
            'droits' => json_encode(array_values(array_filter(self::DROITS, fn ($d) => Droits::existe($d)))),
            'systeme' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        $profil = DB::table('profils')->where('nom', self::NOM)->first();
        if ($profil && ! DB::table('users')->where('profil_id', $profil->id)->exists()) {
            DB::table('profils')->where('id', $profil->id)->delete();
        }
    }
};
