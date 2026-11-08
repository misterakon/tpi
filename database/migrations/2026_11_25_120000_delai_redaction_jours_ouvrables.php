<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 | Rédaction de la décision : 30 jours ouvrables (choix du client, nov. 2026), comme la ligne « Rédaction de la décision – Civile ordinaire » des délais de procédure.
 | Avant : 15 jours calendaires.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('delais_etape')->where('code', 'redaction_decision')->update([
            'valeur' => 30, 'unite' => 'jours_ouvrables', 'aide' => "Nombre de jours ouvrables après l'audience où l'affaire est jugée pour rédiger et valider la décision.",
        ]);
    }

    public function down(): void
    {
        DB::table('delais_etape')->where('code', 'redaction_decision')->update([
            'valeur' => 15, 'unite' => 'jours', 'aide' => "Nombre de jours après l'audience où l'affaire est jugée pour rédiger et valider la décision.",
        ]);
    }
};
