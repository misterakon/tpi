<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 | Le mot « enrôlement » n'est plus employé : il évoque le travail du greffe, alors que l'enregistrement des affaires est fait par les greffiers
 | des chambres. Les textes enregistrés en base (descriptions de profils) suivent.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('profils')->where('description', 'like', '%enrôlement%')->update(['description' => DB::raw("REPLACE(description, 'enrôlement', 'enregistrement')")]);
        // L'étape « délivrance » n'existe plus (la transmission au greffe en tient lieu).
        DB::table('profils')->where('description', 'like', '%délivrance%')->update(['description' => DB::raw("REPLACE(REPLACE(description, ', délivrance', ''), 'délivrance', 'transmission au greffe')")]);
    }

    public function down(): void
    {
        // Pas de retour.
    }
};
