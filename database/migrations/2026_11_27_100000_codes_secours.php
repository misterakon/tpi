<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 | Codes de secours : huit codes à usage unique par utilisateur, pour se connecter quand le code par e-mail n'arrive pas.
 | Seule l'empreinte (HMAC) de chaque code est conservée : on ne peut plus les relire après leur affichage.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('codes_secours', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('empreinte', 64);
            $table->timestamp('utilise_le')->nullable();
            $table->string('utilise_ip', 45)->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'empreinte']);
            $table->index(['user_id', 'utilise_le']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('codes_secours');
    }
};
