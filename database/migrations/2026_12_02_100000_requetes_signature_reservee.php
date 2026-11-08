<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 | Ordonnances sur requête et injonctions de payer (AUT-05) : la signature est réservée (droit « requetes.signer »).
 | On garde qui a signé et quand ; le greffe enregistre et répartit, il ne signe pas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('requetes', function (Blueprint $table) {
            $table->foreignId('signee_par')->nullable()->after('traitee_le')->constrained('users')->nullOnDelete();
            $table->timestamp('signee_le')->nullable()->after('signee_par');
        });
    }

    public function down(): void
    {
        Schema::table('requetes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('signee_par');
            $table->dropColumn('signee_le');
        });
    }
};
