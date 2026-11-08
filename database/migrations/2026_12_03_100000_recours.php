<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 | Recours (cahier CLO-03) : délais de recours, suivi de la signification de chaque décision, recours formés et transmis à la Cour d'appel,
 | certificats de non-appel et de non-opposition délivrés à l'échéance.
 |
 | IMPORTANT : les délais installés ci-dessous sont PROVISOIRES (valide = false). Un certificat ne peut pas être délivré tant que le délai applicable n'a pas été
 | confirmé par la personne habilitée (droit « recours.delais »), d'après les textes en vigueur. L'application ne décide pas de la loi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delais_recours', function (Blueprint $table) {
            $table->id();
            $table->string('libelle', 160);
            $table->string('nature', 20);                       // appel, opposition
            $table->string('registre', 4)->nullable();          // RG (civil), RP (pénal) ; vide : tous
            $table->foreignId('nature_affaire_id')->nullable()->constrained('natures_affaire')->nullOnDelete();
            $table->unsignedSmallInteger('valeur');
            $table->string('unite', 10)->default('jours');      // jours, mois
            $table->boolean('actif')->default(true);
            $table->boolean('valide')->default(false);          // confirmé par la personne habilitée
            $table->foreignId('valide_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('valide_le')->nullable();
            $table->timestamps();
        });

        Schema::create('suivis_recours', function (Blueprint $table) {
            $table->id();
            $table->foreignId('decision_id')->unique()->constrained('decisions')->cascadeOnDelete();
            $table->foreignId('affaire_id')->nullable()->constrained('affaires')->nullOnDelete();
            $table->string('nature', 20)->default('appel');     // la voie de recours dont on surveille le délai
            $table->date('date_signification')->nullable();
            $table->string('signification_a', 200)->nullable();
            $table->foreignId('saisi_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('recours', function (Blueprint $table) {
            $table->id();
            $table->foreignId('affaire_id')->constrained('affaires')->cascadeOnDelete();
            $table->foreignId('decision_id')->nullable()->constrained('decisions')->nullOnDelete();
            $table->string('nature', 20);                       // appel, opposition, pourvoi, autre
            $table->string('auteur', 200);
            $table->date('date_recours');
            $table->date('date_transmission')->nullable();      // transmission à la Cour d'appel d'Abidjan
            $table->string('reference_cour', 120)->nullable();
            $table->string('statut', 20)->default('forme');     // forme, transmis, abandonne
            $table->text('observations')->nullable();
            $table->foreignId('cree_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['decision_id', 'nature']);
        });

        Schema::create('numeros_certificat', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('annee')->unique();
            $table->unsignedInteger('dernier')->default(0);
            $table->timestamps();
        });

        Schema::create('certificats_recours', function (Blueprint $table) {
            $table->id();
            $table->foreignId('decision_id')->constrained('decisions')->cascadeOnDelete();
            $table->foreignId('affaire_id')->nullable()->constrained('affaires')->nullOnDelete();
            $table->string('type', 20);                         // non_appel, non_opposition
            $table->unsignedSmallInteger('annee');
            $table->unsignedInteger('numero');
            $table->date('date_signification');
            $table->date('echeance');
            $table->date('date_delivrance');
            $table->string('delivre_a', 200);
            $table->string('qualite', 120)->nullable();
            $table->foreignId('delivre_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['annee', 'numero']);
            $table->index(['decision_id', 'type']);
        });

        // Délais PROVISOIRES, à confirmer (valide = false) : l'appel d'un jugement civil, l'appel d'une ordonnance de référé, l'appel d'un jugement correctionnel, l'opposition.
        $refere = DB::table('natures_affaire')->whereIn('libelle', ['Référé ordinaire', 'Référé-expulsion'])->pluck('id')->all();
        $lignes = [
            ['Appel d\'un jugement civil', 'appel', 'RG', null, 1, 'mois'],
            ['Opposition à un jugement par défaut (civil)', 'opposition', 'RG', null, 15, 'jours'],
            ['Appel d\'un jugement correctionnel', 'appel', 'RP', null, 10, 'jours'],
        ];
        foreach ($refere as $id) {
            $lignes[] = ['Appel d\'une ordonnance de référé', 'appel', 'RG', $id, 15, 'jours'];
        }
        foreach ($lignes as [$libelle, $nature, $registre, $naturesAffaire, $valeur, $unite]) {
            DB::table('delais_recours')->insert(['libelle' => $libelle, 'nature' => $nature, 'registre' => $registre, 'nature_affaire_id' => $naturesAffaire, 'valeur' => $valeur, 'unite' => $unite, 'actif' => true, 'valide' => false, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('certificats_recours');
        Schema::dropIfExists('numeros_certificat');
        Schema::dropIfExists('recours');
        Schema::dropIfExists('suivis_recours');
        Schema::dropIfExists('delais_recours');
    }
};
