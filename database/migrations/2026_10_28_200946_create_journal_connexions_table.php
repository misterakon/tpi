<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /*
     | Journal des connexions, échecs, étapes de double authentification et déconnexions.
     | Sur MySQL, des déclencheurs interdisent toute modification ou suppression des lignes :
     | le journal ne peut donc pas être réécrit, même par un accès direct à la base.
     */
    public function up(): void
    {
        Schema::create('journal_connexions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('email_saisi', 255)->nullable();
            $table->string('evenement', 40)->index();
            $table->boolean('succes')->default(true);
            $table->string('motif', 255)->nullable();
            $table->string('ip', 45)->nullable()->index();
            $table->string('navigateur', 255)->nullable();
            $table->string('session_ref', 64)->nullable()->index();
            $table->json('details')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });

        $this->verrouiller('journal_connexions');
    }

    public function down(): void
    {
        $this->deverrouiller('journal_connexions');
        Schema::dropIfExists('journal_connexions');
    }

    private function verrouiller(string $table): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }
        DB::unprepared("CREATE TRIGGER {$table}_no_update BEFORE UPDATE ON {$table} FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Journal non modifiable'");
        DB::unprepared("CREATE TRIGGER {$table}_no_delete BEFORE DELETE ON {$table} FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Journal non modifiable'");
    }

    private function deverrouiller(string $table): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }
        DB::unprepared("DROP TRIGGER IF EXISTS {$table}_no_update");
        DB::unprepared("DROP TRIGGER IF EXISTS {$table}_no_delete");
    }
};
