<?php

namespace App\Console\Commands;

use App\Models\Profil;
use Illuminate\Console\Command;

/*
 | Pour les essais : ajoute aux profils de test (database/sql/comptes_test.sql) les droits des lots O et P (mise en état, séances, recours, répertoire pénal,
 | infractions, signature réservée). N'enlève rien, rejouable. À n'utiliser que sur une base de test : en production, les droits se donnent dans la page Profils.
 */
class DroitsTestLotsOP extends Command
{
    protected $signature = 'tpi:droits-test-lots-o-p';

    protected $description = 'Donne aux profils de test les droits des lots O et P (pour le test pratique)';

    private const LECTURE_SAISIE = [
        'mise_en_etat.voir', 'mise_en_etat.creer', 'mise_en_etat.modifier', 'seances.voir', 'seances.creer', 'seances.modifier',
        'stats_civil.voir', 'stats_civil.detail', 'stats_civil.qualifier', 'stats_penal.voir',
        'instruction.voir', 'instruction.modifier', 'stats_instruction.voir', 'assistance.voir', 'assistance.creer', 'assistance.modifier', 'jap.voir', 'stats_registres.voir',
        'recours.voir', 'recours.creer', 'recours.modifier', 'repertoire_penal.voir', 'repertoire_penal.creer', 'repertoire_penal.modifier', 'requetes.voir', 'requetes.creer', 'requetes.modifier',
    ];

    private const PAR_PROFIL = [
        'Juge d\'instruction (test)' => ['instruction.voir', 'instruction.modifier', 'stats_instruction.voir', 'stats_registres.voir', 'stats_registres.exporter'],
        'Juge des enfants (test)' => ['instruction.voir', 'instruction.modifier', 'stats_instruction.voir', 'stats_registres.voir', 'stats_registres.exporter'],
        'President' => ['instruction.voir', 'stats_instruction.voir', 'assistance.voir', 'jap.voir', 'stats_registres.voir', 'stats_registres.exporter', 'stats_civil.voir', 'stats_civil.detail', 'stats_civil.exporter', 'stats_penal.voir', 'stats_penal.exporter', 'mise_en_etat.voir', 'seances.voir', 'recours.voir', 'repertoire_penal.voir', 'requetes.voir', 'requetes.signer', 'requetes.repartir'],
        'Magistrat (test)' => ['stats_civil.voir', 'stats_penal.voir', 'mise_en_etat.voir', 'mise_en_etat.modifier', 'seances.voir', 'seances.creer', 'seances.modifier', 'recours.voir', 'repertoire_penal.voir', 'repertoire_penal.creer', 'repertoire_penal.modifier', 'requetes.voir'],
        'Greffier (test)' => self::LECTURE_SAISIE,
        'Coordonnateur du greffe (test)' => [
            'mise_en_etat.annuler', 'seances.annuler', 'recours.annuler', 'recours.certifier', 'recours.delais', 'repertoire_penal.supprimer', 'requetes.annuler', 'requetes.repartir',
            'stats_civil.exporter', 'stats_registres.voir', 'stats_registres.exporter', 'assistance.supprimer', 'jap.modifier', 'stats_penal.detail', 'stats_penal.exporter', 'infractions.voir', 'infractions.creer', 'infractions.modifier', 'infractions.supprimer', ...self::LECTURE_SAISIE,
        ],
    ];

    public function handle(): int
    {
        $n = 0;
        foreach (self::PAR_PROFIL as $nom => $droits) {
            $profil = Profil::where('nom', $nom)->first();
            if (! $profil) {
                $this->warn("Profil « $nom » absent : importez d'abord database/sql/comptes_test.sql.");

                continue;
            }
            $avant = (array) $profil->droits;
            $profil->droits = array_values(array_unique(array_merge($avant, $droits)));
            $profil->save();
            $n++;
            $this->line("$nom : ".(count($profil->droits) - count($avant)).' droit(s) ajouté(s)');
        }

        $this->info("$n profil(s) mis à jour.");

        return self::SUCCESS;
    }
}
