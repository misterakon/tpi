<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 | Fusionne les chambres écrites de deux façons (« 1ere chambre civile » et « 1re chambre civile ») : on garde la plus ancienne, toutes les
 | références (affaires, décisions, rattachements de modèles…) lui sont ramenées, l'autre disparaît. Sans doublon, la migration ne fait rien.
 */
return new class extends Migration
{
    public function up(): void
    {
        $cle = fn (string $libelle) => preg_replace('/^(\d+)\s*(?:ere|ère|re|er|ème|eme|e)(?=\s)/iu', '$1', mb_strtolower(trim($libelle)));

        $groupes = DB::table('chambres')->orderBy('id')->get()->groupBy(fn ($c) => $c->annee_judiciaire_id.'|'.$cle($c->libelle));
        $tables = collect(Schema::getTables())->pluck('name')->filter(fn ($t) => $t !== 'chambres' && Schema::hasColumn($t, 'chambre_id'));

        DB::transaction(function () use ($groupes, $tables) {
            foreach ($groupes as $groupe) {
                if ($groupe->count() < 2) {
                    continue;
                }
                $garde = $groupe->first();
                foreach ($groupe->slice(1) as $double) {
                    foreach ($tables as $table) {
                        // Une ligne qui existerait déjà pour la chambre gardée (clé unique) fait disparaître sa jumelle plutôt que de la déplacer.
                        try {
                            DB::table($table)->where('chambre_id', $double->id)->update(['chambre_id' => $garde->id]);
                        } catch (\Throwable) {
                            DB::table($table)->where('chambre_id', $double->id)->delete();
                        }
                    }
                    foreach (['chambre_avant_id', 'chambre_apres_id'] as $colonne) {
                        if (Schema::hasColumn('affaire_attributions', $colonne)) {
                            DB::table('affaire_attributions')->where($colonne, $double->id)->update([$colonne => $garde->id]);
                        }
                    }
                    DB::table('objets_litige')->where('chambre_libelle', $double->libelle)->update(['chambre_libelle' => $garde->libelle]);
                    DB::table('chambres')->where('id', $double->id)->delete();   // ses formations, affectations et créneaux (doublons) partent avec elle
                }
            }
        });
    }

    public function down(): void
    {
        // Rien à défaire : la chambre en double n'avait pas de raison d'être.
    }
};
