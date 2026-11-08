<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 | Lot M2 : le cycle de vie d'une décision. brouillon → rédigée → validée (texte figé) → signée → délivrée, avec un numéro de décision attribué
 | sans trou ni doublon, une année après l'autre, au moment réglé dans Réglages > Décisions (à la validation ou à la signature).
 |  - decisions       : numéro, dates et auteurs de chaque étape ;
 |  - numeros_decision: le compteur par année (verrouillé le temps de l'attribution) ;
 |  - delivrances     : chaque expédition, grosse ou copie remise, avec à qui et quand.
 | Deux délais d'étape (validation, signature) alimentent les alertes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('decisions', function (Blueprint $table) {
            $table->date('date_decision')->nullable()->after('statut');            // date de la décision (prononcé), reprise des valeurs à la validation
            $table->unsignedInteger('numero_seq')->nullable()->after('date_decision');
            $table->unsignedSmallInteger('numero_annee')->nullable()->after('numero_seq');
            $table->string('numero', 20)->nullable()->after('numero_annee');       // « 145 » : numéro de la décision dans l'année
            $table->timestamp('numero_le')->nullable()->after('numero');
            $table->timestamp('redigee_le')->nullable();
            $table->foreignId('redigee_par')->nullable()->constrained('users')->nullOnDelete();
            $table->date('date_signature')->nullable();
            $table->timestamp('signee_le')->nullable();
            $table->foreignId('signee_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('delivree_le')->nullable();
            $table->unique(['numero_annee', 'numero_seq'], 'decisions_numero_unique');   // jamais deux fois le même numéro dans l'année
        });

        Schema::create('numeros_decision', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('annee')->unique();
            $table->unsignedInteger('dernier')->default(0);
            $table->timestamps();
        });

        Schema::create('delivrances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('decision_id')->constrained('decisions')->restrictOnDelete();
            $table->string('type', 12);                                            // expedition | grosse | copie
            $table->string('delivre_a', 160);
            $table->string('qualite', 60)->nullable();                             // partie, avocat, parquet…
            $table->date('delivre_le');
            $table->string('observation', 300)->nullable();
            $table->foreignId('delivre_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index('decision_id');
        });

        $maintenant = now();
        foreach ([
            ['validation_decision', 'Validation de la décision rédigée', 3, 'jours_ouvrables', 'Président de la formation', "Nombre de jours après la fin de la rédaction pour valider la décision."],
            ['signature_decision', 'Signature de la décision validée', 3, 'jours_ouvrables', 'Président de la formation', 'Nombre de jours après la validation pour signer la décision.'],
        ] as $i => [$code, $libelle, $valeur, $unite, $acteur, $aide]) {
            DB::table('delais_etape')->insertOrIgnore(['code' => $code, 'libelle' => $libelle, 'valeur' => $valeur, 'unite' => $unite, 'acteur' => $acteur, 'aide' => $aide, 'ordre' => 6 + $i, 'created_at' => $maintenant, 'updated_at' => $maintenant]);
        }
    }

    public function down(): void
    {
        DB::table('delais_etape')->whereIn('code', ['validation_decision', 'signature_decision'])->delete();
        Schema::dropIfExists('delivrances');
        Schema::dropIfExists('numeros_decision');
        Schema::table('decisions', function (Blueprint $table) {
            $table->dropUnique('decisions_numero_unique');
            $table->dropConstrainedForeignId('redigee_par');
            $table->dropConstrainedForeignId('signee_par');
            $table->dropColumn(['date_decision', 'numero_seq', 'numero_annee', 'numero', 'numero_le', 'redigee_le', 'date_signature', 'signee_le', 'delivree_le']);
        });
    }
};
