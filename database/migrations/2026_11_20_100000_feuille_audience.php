<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // L'audience tenue : ouverture, composition réellement présente, clôture.
        Schema::table('roles_audience', function (Blueprint $table) {
            $table->timestamp('audience_ouverte_le')->nullable();
            $table->unsignedBigInteger('audience_ouverte_par')->nullable();
            $table->timestamp('audience_terminee_le')->nullable();
            $table->json('composition_tenue')->nullable();
            $table->text('note_audience')->nullable();
        });

        // Le résultat de chaque affaire à l'audience : motif du renvoi, date de délibéré (et prorogations), présence des parties, remarque.
        Schema::table('role_lignes', function (Blueprint $table) {
            $table->string('motif', 200)->nullable();
            $table->date('date_delibere')->nullable();
            $table->unsignedSmallInteger('proroge')->default(0);
            $table->string('presence', 20)->nullable();
            $table->text('note_audience')->nullable();
            $table->timestamp('suite_le')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('role_lignes', function (Blueprint $table) {
            $table->dropColumn(['motif', 'date_delibere', 'proroge', 'presence', 'note_audience', 'suite_le']);
        });
        Schema::table('roles_audience', function (Blueprint $table) {
            $table->dropColumn(['audience_ouverte_le', 'audience_ouverte_par', 'audience_terminee_le', 'composition_tenue', 'note_audience']);
        });
    }
};
