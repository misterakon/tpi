<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /*
     | Journal d'audit : qui a fait quoi, sur quel enregistrement, avec les valeurs avant et après.
     | Même protection que le journal de connexion : aucune modification ni suppression possible.
     */
    public function up(): void
    {
        Schema::create('journal_audit', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('action', 40)->index();
            $table->string('cible_type', 120)->nullable();
            $table->string('cible_id', 40)->nullable();
            $table->string('libelle', 255)->nullable();
            $table->json('avant')->nullable();
            $table->json('apres')->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('navigateur', 255)->nullable();
            $table->timestamp('created_at')->useCurrent()->index();

            $table->index(['cible_type', 'cible_id']);
        });

        if (DB::getDriverName() === 'mysql') {
            DB::unprepared("CREATE TRIGGER journal_audit_no_update BEFORE UPDATE ON journal_audit FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Journal non modifiable'");
            DB::unprepared("CREATE TRIGGER journal_audit_no_delete BEFORE DELETE ON journal_audit FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Journal non modifiable'");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::unprepared('DROP TRIGGER IF EXISTS journal_audit_no_update');
            DB::unprepared('DROP TRIGGER IF EXISTS journal_audit_no_delete');
        }
        Schema::dropIfExists('journal_audit');
    }
};
