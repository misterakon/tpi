<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('profil_id')->after('password')->constrained('profils')->restrictOnDelete();
            $table->boolean('protege')->default(false)->after('profil_id');
            $table->boolean('actif')->default(true)->after('protege');
            $table->unsignedSmallInteger('echecs_connexion')->default(0)->after('actif');
            $table->timestamp('verrouille_jusqu_a')->nullable()->after('echecs_connexion');
            $table->timestamp('last_login_at')->nullable()->after('verrouille_jusqu_a');
            $table->string('last_login_ip', 45)->nullable()->after('last_login_at');
            $table->timestamp('mot_de_passe_modifie_le')->nullable()->after('last_login_ip');
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->dropConstrainedForeignId('profil_id');
            $table->dropColumn([
                'protege', 'actif', 'echecs_connexion', 'verrouille_jusqu_a',
                'last_login_at', 'last_login_ip', 'mot_de_passe_modifie_le',
            ]);
        });
    }
};
