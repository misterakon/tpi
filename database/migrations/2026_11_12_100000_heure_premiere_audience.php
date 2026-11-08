<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/* L'heure de la première audience (l'ordonnance donne un jour et une heure par créneau d'audience). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('affaires', function (Blueprint $table) {
            $table->time('heure_premiere_audience')->nullable()->after('date_premiere_audience');
        });
    }

    public function down(): void
    {
        Schema::table('affaires', function (Blueprint $table) {
            $table->dropColumn('heure_premiere_audience');
        });
    }
};
