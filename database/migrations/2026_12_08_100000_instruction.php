<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 | Lot R, instruction (cabinets d'instruction et cabinet du juge des enfants) : ce que les classeurs « INFO JI » et « INFO JE » suivent en plus de l'enrôlement :
 |  - le dossier (réquisitoire, textes visés, faits, ordonnance de clôture, transmission au parquet) ;
 |  - le suivi de chaque inculpé (statut, détention et renouvellements, liberté provisoire, clôture) ;
 |  - le suivi de chaque victime (auditions, clôture) ;
 |  - les commissions rogatoires, par cabinet, mois, sens (reçues ou émises) et portée (nationale ou internationale).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dossiers_instruction', function (Blueprint $t) {
            $t->id();
            $t->foreignId('affaire_id')->unique()->constrained('affaires')->cascadeOnDelete();
            $t->date('date_requisitoire')->nullable();
            $t->string('textes_vises', 300)->nullable();
            $t->date('date_faits')->nullable();
            $t->string('lieu_faits', 160)->nullable();
            $t->string('nature_infraction', 15)->nullable();            // crime | delit | contravention
            $t->string('infraction_specifique', 80)->nullable();
            $t->string('autre_specificite', 80)->nullable();
            $t->date('date_cloture')->nullable();
            $t->string('nature_cloture', 120)->nullable();
            $t->string('autres_ordonnances', 200)->nullable();
            $t->string('infraction_retenue', 200)->nullable();
            $t->date('date_transmission_parquet')->nullable();
            $t->string('observations', 500)->nullable();
            $t->foreignId('cree_par')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('suivis_inculpes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('partie_id')->unique()->constrained('parties')->cascadeOnDelete();
            $t->boolean('traite')->default(false);
            $t->string('statut', 20)->nullable();                        // libre | garde_provisoire | controle_judiciaire | detenu | autre
            $t->string('precisions_statut', 200)->nullable();
            $t->date('date_ogp')->nullable();                            // ordonnance de garde provisoire (mineur)
            $t->date('date_titre_detention')->nullable();
            $t->unsignedSmallInteger('duree_detention_mois')->nullable();
            $t->date('date_renouvellement')->nullable();
            $t->unsignedSmallInteger('duree_renouvellement_mois')->nullable();
            $t->date('date_liberte_provisoire')->nullable();
            $t->date('date_mainlevee_cj')->nullable();
            $t->date('date_cloture')->nullable();
            $t->string('nature_cloture', 120)->nullable();
            $t->string('observations', 500)->nullable();
            $t->timestamps();
        });

        Schema::create('suivis_victimes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('partie_id')->unique()->constrained('parties')->cascadeOnDelete();
            $t->boolean('traite')->default(false);
            $t->string('infraction_subie', 200)->nullable();
            $t->date('audition_1')->nullable();
            $t->date('audition_2')->nullable();
            $t->date('audition_3')->nullable();
            $t->date('date_cloture')->nullable();
            $t->date('date_notification_cloture')->nullable();
            $t->string('observations', 500)->nullable();
            $t->timestamps();
        });

        Schema::create('commissions_rogatoires', function (Blueprint $t) {
            $t->id();
            $t->foreignId('chambre_id')->constrained('chambres')->cascadeOnDelete();
            $t->unsignedSmallInteger('annee');
            $t->unsignedTinyInteger('mois');
            $t->string('sens', 6);                                       // recu | emis
            $t->string('portee', 14);                                    // nationale | internationale
            $t->unsignedSmallInteger('stock_initial')->default(0);
            $t->unsignedSmallInteger('nouvelles')->default(0);
            $t->unsignedSmallInteger('executees')->default(0);
            $t->timestamps();
            $t->unique(['chambre_id', 'annee', 'mois', 'sens', 'portee'], 'cr_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commissions_rogatoires');
        Schema::dropIfExists('suivis_victimes');
        Schema::dropIfExists('suivis_inculpes');
        Schema::dropIfExists('dossiers_instruction');
    }
};
