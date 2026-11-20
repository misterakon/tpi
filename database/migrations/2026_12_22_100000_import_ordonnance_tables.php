<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 | Chargement de l'ordonnance par fichier Excel :
 |   - imports_ordonnance : chaque fichier chargé, de son dépôt (brouillon : rien n'est encore modifié) à son application, puis son éventuelle annulation ;
 |     les données lues, le contrôle, les choix de rapprochement et la liste des changements y sont gardés pour pouvoir tout rejouer et tout défaire ;
 |   - agents_alias : écritures d'un nom (« N'GUESSAN Appa Brigitte ») que l'utilisateur a rattachées à un agent : elles sont reconnues d'elles-mêmes la fois suivante ;
 |   - droit « ordonnances.importer », donné au profil de la Présidente (l'administrateur a tous les droits).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('imports_ordonnance', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('annee_judiciaire_id')->nullable()->constrained('annees_judiciaires')->nullOnDelete();
            $t->string('nom_fichier', 200);
            $t->char('empreinte', 40);                       // sha1 du fichier : le même fichier n'est pas appliqué deux fois
            $t->string('mode', 10)->default('maj');           // maj (ajouter et modifier) ou complet (le fichier est l'ordonnance entière)
            $t->string('statut', 12)->default('brouillon');   // brouillon, applique, annule, abandonne
            $t->longText('donnees')->nullable();              // le classeur lu, normalisé
            $t->longText('controle')->nullable();             // erreurs, avertissements, comptes par bloc, noms à confirmer
            $t->longText('rapprochements')->nullable();       // choix de l'utilisateur pour les noms incertains
            $t->longText('changements')->nullable();          // ce qui va changer (ou a changé, avec l'état d'avant pour pouvoir annuler)
            $t->timestamp('applique_le')->nullable();
            $t->foreignId('applique_par')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('annule_le')->nullable();
            $t->foreignId('annule_par')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->index(['statut', 'created_at']);
        });

        Schema::create('agents_alias', function (Blueprint $t) {
            $t->id();
            $t->foreignId('agent_id')->constrained('agents')->cascadeOnDelete();
            $t->string('alias', 200)->unique();              // nom normalisé (sans accent, sans titre, mots triés)
            $t->string('ecriture', 250)->nullable();         // la façon dont c'était écrit, pour l'affichage
            $t->foreignId('cree_par')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        foreach (DB::table('profils')->where('systeme', false)->get() as $profil) {
            $droits = json_decode((string) $profil->droits, true) ?: [];
            if (in_array('ordonnances.creer', $droits, true) && mb_strtolower($profil->nom) === 'president' && ! in_array('ordonnances.importer', $droits, true)) {
                $droits[] = 'ordonnances.importer';
                DB::table('profils')->where('id', $profil->id)->update(['droits' => json_encode(array_values($droits)), 'updated_at' => now()]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('agents_alias');
        Schema::dropIfExists('imports_ordonnance');
    }
};
