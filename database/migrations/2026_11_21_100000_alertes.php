<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 | Lot L : délais, alertes et circuit.
 |  - notifications : les notifications de l'application (cloche de l'en-tête, page « Mes notifications ») ;
 |  - regles_alerte : règles de déclenchement paramétrables (code J-3, J+1…, décalage en jours par rapport à l'échéance, canaux, destinataires) ;
 |  - alertes : une ligne par situation qui a déclenché au moins une règle ; elle se referme toute seule quand l'action est faite ;
 |  - alertes_envois : ce qui est parti, à qui et par quel canal (jamais deux fois la même alerte) ;
 |  - alertes_evaluations : une ligne par jour évalué (rattrapage si le serveur est resté éteint) ;
 |  - delais_etape : le délai de chaque étape du circuit, modifiable dans Paramètres.
 | Les règles et les délais usuels sont installés d'office ; le tribunal les ajuste ensuite.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('notifications')) {
            Schema::create('notifications', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('type');
                $table->morphs('notifiable');
                $table->text('data');
                $table->timestamp('read_at')->nullable();
                $table->timestamps();
            });
        }

        Schema::create('regles_alerte', function (Blueprint $table) {
            $table->id();
            $table->string('code', 12)->unique();                 // J-3, J+1…
            $table->string('libelle', 80);
            $table->smallInteger('decalage_jours');               // négatif : avant l'échéance ; positif : après
            $table->text('canaux');                               // JSON : application, email
            $table->text('destinataires');                        // JSON : greffier_chambre, president_formation…
            $table->text('types')->nullable();                    // JSON : types d'échéance visés (null : tous)
            $table->string('niveau', 12)->default('attention');   // info | attention | critique
            $table->boolean('actif')->default(true);
            $table->timestamps();
        });

        Schema::create('alertes', function (Blueprint $table) {
            $table->id();
            $table->string('cle', 120)->unique();                 // type:objet:échéance : une situation, une alerte
            $table->string('type', 30);
            $table->foreignId('affaire_id')->nullable()->constrained('affaires')->nullOnDelete();
            $table->foreignId('chambre_id')->nullable()->constrained('chambres')->nullOnDelete();
            $table->unsignedBigInteger('objet_id')->nullable();
            $table->date('echeance');
            $table->string('titre', 200);
            $table->string('action', 200);
            $table->string('url', 255)->nullable();
            $table->string('niveau', 12)->default('attention');   // le plus haut niveau atteint
            $table->timestamp('ouverte_le')->useCurrent();
            $table->timestamp('resolue_le')->nullable();
            $table->date('justifiee_jusqu')->nullable();
            $table->string('justification', 500)->nullable();
            $table->foreignId('justifiee_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('justifiee_le')->nullable();
            $table->timestamps();
            $table->index(['resolue_le', 'chambre_id']);
            $table->index('echeance');
        });

        Schema::create('alertes_envois', function (Blueprint $table) {
            $table->id();
            $table->foreignId('alerte_id')->constrained('alertes')->cascadeOnDelete();
            $table->foreignId('regle_alerte_id')->constrained('regles_alerte')->cascadeOnDelete();
            $table->string('canal', 20);
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('envoye_le')->useCurrent();
            $table->unique(['alerte_id', 'regle_alerte_id', 'canal', 'user_id'], 'alertes_envois_unique');
        });

        Schema::create('alertes_evaluations', function (Blueprint $table) {
            $table->id();
            $table->date('jour')->unique();
            $table->unsignedInteger('declenchees')->default(0);
            $table->unsignedInteger('envoyees')->default(0);
            $table->unsignedInteger('refermees')->default(0);
            $table->unsignedInteger('echecs')->default(0);
            $table->timestamps();
        });

        Schema::create('delais_etape', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('libelle', 120);
            $table->unsignedSmallInteger('valeur');
            $table->string('unite', 20)->default('jours');         // jours | jours_ouvrables | mois
            $table->string('acteur', 120);
            $table->string('aide', 300)->nullable();
            $table->unsignedSmallInteger('ordre')->default(0);
            $table->timestamps();
        });

        $maintenant = now();
        $delais = [
            ['mise_au_role', "Mise de l'affaire au rôle", 2, 'jours_ouvrables', 'Greffier de chambre', "Nombre de jours avant la première audience où l'affaire doit figurer sur un rôle."],
            ['publication_role', 'Publication du rôle', 2, 'jours_ouvrables', 'Greffier de chambre ou président de formation', "Nombre de jours avant l'audience où le rôle doit être publié."],
            ['saisie_suites', "Saisie des suites de l'audience", 1, 'jours_ouvrables', 'Greffier de chambre', "Nombre de jours après l'audience pour noter la suite de chaque affaire (renvoi, délibéré, jugée…)."],
            ['delibere_sans_date', 'Délibéré sans date de prononcé', 30, 'jours', 'Juge de la formation', "Échéance par défaut après la mise en délibéré quand aucune date de prononcé n'est fixée."],
            ['redaction_decision', 'Rédaction et validation de la décision', 30, 'jours_ouvrables', 'Juge de la formation', "Nombre de jours ouvrables après l'audience où l'affaire est jugée pour rédiger et valider la décision."],
        ];
        foreach ($delais as $i => [$code, $libelle, $valeur, $unite, $acteur, $aide]) {
            DB::table('delais_etape')->insert(['code' => $code, 'libelle' => $libelle, 'valeur' => $valeur, 'unite' => $unite, 'acteur' => $acteur, 'aide' => $aide, 'ordre' => $i + 1, 'created_at' => $maintenant, 'updated_at' => $maintenant]);
        }

        // [code, libellé, décalage, canaux, destinataires, types (null = tous), niveau, actif]
        $app = ['application'];
        $deux = ['application', 'email'];
        $regles = [
            ['J-15', 'Échéance dans quinze jours', -15, $app, ['greffier_chambre', 'president_formation'], ['delai_procedure', 'detention'], 'info', true],
            ['J-7', 'Échéance dans une semaine', -7, $app, ['greffier_chambre'], null, 'info', true],
            ['J-3', 'Échéance imminente', -3, $deux, ['greffier_chambre', 'president_formation'], null, 'attention', true],
            ['J-1', "Échéance demain", -1, $deux, ['greffier_chambre', 'president_formation'], null, 'attention', true],
            ['J0', "Échéance aujourd'hui", 0, $deux, ['greffier_chambre', 'president_formation'], null, 'attention', true],
            ['J+1', 'Échéance dépassée', 1, $deux, ['greffier_chambre', 'president_formation'], null, 'attention', true],
            ['J+7', 'Retard persistant', 7, $deux, ['president_formation', 'vice_presidents'], null, 'critique', true],
            ['J+15', 'Retard grave', 15, $deux, ['presidente', 'greffier_en_chef', 'vice_presidents'], null, 'critique', true],
            ['J+30', 'Retard critique', 30, $app, ['presidente', 'greffier_en_chef', 'vice_presidents'], null, 'critique', false],
        ];
        foreach ($regles as [$code, $libelle, $decalage, $canaux, $destinataires, $types, $niveau, $actif]) {
            DB::table('regles_alerte')->insert([
                'code' => $code, 'libelle' => $libelle, 'decalage_jours' => $decalage, 'canaux' => json_encode($canaux), 'destinataires' => json_encode($destinataires),
                'types' => $types ? json_encode($types) : null, 'niveau' => $niveau, 'actif' => $actif, 'created_at' => $maintenant, 'updated_at' => $maintenant,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('delais_etape');
        Schema::dropIfExists('alertes_evaluations');
        Schema::dropIfExists('alertes_envois');
        Schema::dropIfExists('alertes');
        Schema::dropIfExists('regles_alerte');
    }
};
