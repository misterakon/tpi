<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 | Statistiques (lots N et Q) :
 |  - resultats_affaire : ce que les classeurs du TPI relèvent en plus de la date : accordée ou rejetée, nombre d'enfants adoptés, sous-catégorie
 |    (état civil, matrimonial) corrigée à la main, et, quand l'application ne la connaît pas, la sortie de l'affaire (ex. PV de conciliation totale) ;
 |  - décisions pénales : infraction retenue, nature, traite des personnes, victimes (hommes, femmes, garçons, filles).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resultats_affaire', function (Blueprint $table) {
            $table->id();
            $table->foreignId('affaire_id')->unique()->constrained('affaires')->cascadeOnDelete();
            $table->string('type_sortie', 30)->nullable();        // decision_au_fond | radiation | pv_conciliation | desistement
            $table->date('date_sortie')->nullable();
            $table->string('resultat', 10)->nullable();           // accord | rejet
            $table->unsignedSmallInteger('adoption_filles')->nullable();
            $table->unsignedSmallInteger('adoption_garcons')->nullable();
            $table->string('sous_categorie', 40)->nullable();
            $table->foreignId('cree_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('decisions_penales', function (Blueprint $table) {
            $table->string('infraction_retenue', 200)->nullable()->after('contradiction');
            $table->string('nature_infraction', 15)->nullable()->after('infraction_retenue');   // crime | delit | contravention
            $table->boolean('traite')->default(false)->after('nature_infraction');
            $table->unsignedSmallInteger('victimes_hommes')->nullable()->after('traite');
            $table->unsignedSmallInteger('victimes_femmes')->nullable()->after('victimes_hommes');
            $table->unsignedSmallInteger('victimes_garcons')->nullable()->after('victimes_femmes');
            $table->unsignedSmallInteger('victimes_filles')->nullable()->after('victimes_garcons');
        });
    }

    public function down(): void
    {
        Schema::table('decisions_penales', function (Blueprint $table) {
            $table->dropColumn(['infraction_retenue', 'nature_infraction', 'traite', 'victimes_hommes', 'victimes_femmes', 'victimes_garcons', 'victimes_filles']);
        });
        Schema::dropIfExists('resultats_affaire');
    }
};
