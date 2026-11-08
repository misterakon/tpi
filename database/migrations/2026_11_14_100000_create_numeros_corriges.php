<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/* Historique des corrections de numéro (numéro reporté du greffe avec une faute de frappe) : on garde l'ancien numéro, le motif et l'auteur. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('affaire_numeros_corriges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('affaire_id')->constrained('affaires')->cascadeOnDelete();
            $table->string('ancien', 20);
            $table->string('nouveau', 20);
            $table->string('motif', 300);
            $table->foreignId('par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('affaire_numeros_corriges');
    }
};
