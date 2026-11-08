<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /*
     | Codes de double authentification envoyés par e-mail.
     | Le code n'est jamais stocké en clair : seule son empreinte l'est.
     */
    public function up(): void
    {
        Schema::create('codes_double_auth', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('empreinte', 64);
            $table->unsignedTinyInteger('tentatives')->default(0);
            $table->unsignedTinyInteger('renvois')->default(0);
            $table->timestamp('expire_le');
            $table->timestamp('envoye_le');
            $table->timestamps();

            $table->unique('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('codes_double_auth');
    }
};
