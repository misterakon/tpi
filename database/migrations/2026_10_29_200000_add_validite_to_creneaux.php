<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /*
     | D6 : les jours et heures d'audience changent d'une année à l'autre et en cours d'année.
     | Un créneau a donc une période de validité (début / fin, vides = sans limite) et, si on le souhaite,
     | l'ordonnance archivée qui l'a fixé.
     */
    public function up(): void
    {
        Schema::table('creneaux', function (Blueprint $table) {
            $table->date('debut')->nullable()->after('precision');
            $table->date('fin')->nullable()->after('debut');
            $table->foreignId('ordonnance_id')->nullable()->after('fin')->constrained('ordonnances')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('creneaux', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ordonnance_id');
            $table->dropColumn(['debut', 'fin']);
        });
    }
};
