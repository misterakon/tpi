<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 | Lot O, matière pénale :
 |  - numéro du parquet sur l'affaire (enrôlement RP depuis le numéro du parquet) ;
 |  - liste des infractions (libellé et articles) proposée à l'enrôlement ; les articles ne sont pas inventés : ils se renseignent dans la page ;
 |  - décisions pénales (répertoire) : une ligne par prévenu et par décision, avec la peine et la date de transmission de la minute.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('affaires', function (Blueprint $table) {
            $table->string('numero_parquet', 40)->nullable()->after('numero_complet');
            $table->index('numero_parquet');
        });

        Schema::create('infractions', function (Blueprint $table) {
            $table->id();
            $table->string('libelle', 200)->unique();
            $table->string('articles', 300)->nullable();      // ex. « Arts 457 et 458 du CP » : à renseigner par le tribunal
            $table->boolean('actif')->default(true);
            $table->timestamps();
        });
        $now = now();
        foreach (['Vol', 'Vol avec effraction', 'Abus de confiance', 'Escroquerie', 'Coups et blessures volontaires', 'Destruction volontaire de biens', 'Détention de stupéfiants',
            'Homicide involontaire', 'Blessures involontaires (circulation)', 'Défaut de permis de conduire', 'Recel', 'Faux et usage de faux', 'Association de malfaiteurs', 'Menaces'] as $libelle) {
            DB::table('infractions')->insert(['libelle' => $libelle, 'actif' => true, 'created_at' => $now, 'updated_at' => $now]);
        }

        Schema::create('decisions_penales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('affaire_id')->constrained('affaires')->cascadeOnDelete();
            $table->foreignId('partie_id')->nullable()->constrained('parties')->nullOnDelete();   // le prévenu concerné
            $table->date('date_decision');
            $table->string('contradiction', 20)->default('contradictoire');   // contradictoire | defaut | reputee_contradictoire
            $table->string('issue', 30);                                       // condamne | relaxe | acquitte | dispense_de_peine | irrecevable | incompetent | autre
            $table->unsignedSmallInteger('emprisonnement_mois')->nullable();
            $table->unsignedSmallInteger('sursis_mois')->nullable();
            $table->unsignedInteger('amende')->nullable();                     // francs CFA
            $table->string('autres_peines', 300)->nullable();                  // suspension de permis, confiscation…
            $table->text('dispositif')->nullable();
            $table->string('numero_decision', 40)->nullable();                 // numéro du jugement au répertoire
            $table->date('date_transmission_minute')->nullable();              // transmission de la minute (au greffe central ou au parquet)
            $table->string('minute_transmise_a', 120)->nullable();
            $table->foreignId('cree_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['affaire_id', 'date_decision']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('decisions_penales');
        Schema::dropIfExists('infractions');
        Schema::table('affaires', function (Blueprint $table) {
            $table->dropIndex(['numero_parquet']);
            $table->dropColumn('numero_parquet');
        });
    }
};
