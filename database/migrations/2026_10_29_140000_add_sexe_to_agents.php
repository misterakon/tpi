<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /* Sexe de l'agent (M ou F), indiqué par l'ordonnance (Mme, M., épouse) : sert aux accords dans les décisions. */
    public function up(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            $table->string('sexe', 1)->nullable()->after('prenoms');
        });
    }

    public function down(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            $table->dropColumn('sexe');
        });
    }
};
