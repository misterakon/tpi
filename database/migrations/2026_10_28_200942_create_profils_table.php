<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /*
     | Profils d'utilisateurs : autant que l'on veut, chacun avec la liste de ses droits (JSON).
     | Le profil « Admin » est créé ici, marqué « systeme » : il a tous les droits, y compris
     | ceux des modules à venir, et ne peut être ni modifié ni supprimé.
     */
    public function up(): void
    {
        Schema::create('profils', function (Blueprint $table) {
            $table->id();
            $table->string('nom', 80)->unique();
            $table->string('description', 255)->nullable();
            $table->json('droits')->nullable();
            $table->boolean('systeme')->default(false);
            $table->timestamps();
        });

        DB::table('profils')->insert([
            'nom' => 'Admin',
            'description' => 'Tous les droits : gère les comptes, les profils et consulte les journaux.',
            'droits' => json_encode([]),
            'systeme' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('profils');
    }
};
