<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/* « 1ere chambre civile » s'écrit « 1re chambre civile » (la fusion des doublons avait gardé la plus ancienne, écrite « 1ere »). */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('chambres')->where('libelle', '1ere chambre civile')->update(['libelle' => '1re chambre civile']);
    }

    public function down(): void
    {
        DB::table('chambres')->where('libelle', '1re chambre civile')->update(['libelle' => '1ere chambre civile']);
    }
};
