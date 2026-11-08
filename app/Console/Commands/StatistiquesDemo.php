<?php

namespace App\Console\Commands;

use App\Models\Affaire;
use App\Models\Chambre;
use App\Models\DecisionPenale;
use App\Models\Infraction;
use App\Models\Matiere;
use App\Models\ModeSaisine;
use App\Models\NatureAffaire;
use App\Models\Partie;
use App\Models\ResultatAffaire;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/*
 | Jeu de données FICTIVES pour essayer les pages Statistiques (voir docs/DONNEES_FICTIVES.md) : des affaires civiles de 2022 à aujourd'hui avec leurs sorties,
 | et des décisions pénales. Toutes portent le marqueur « DEMO-STAT » dans « ancien numéro » : l'option --retirer les supprime sans toucher au reste.
 | À n'utiliser que sur une base de test.
 */
class StatistiquesDemo extends Command
{
    protected $signature = 'statistiques:demo {--retirer : retire les données fictives}';

    protected $description = 'Crée (ou retire) des affaires civiles et des décisions pénales fictives pour essayer les statistiques';

    private const MARQUE = 'DEMO-STAT';

    public function handle(): int
    {
        if ($this->option('retirer')) {
            $n = Affaire::where('ancien_numero', self::MARQUE)->count();
            Affaire::where('ancien_numero', self::MARQUE)->delete();
            $this->info($n.' affaire(s) fictive(s) retirée(s).');

            return self::SUCCESS;
        }
        if (Affaire::where('ancien_numero', self::MARQUE)->exists()) {
            $this->warn('Des données fictives existent déjà : retirez-les d\'abord avec --retirer.');

            return self::FAILURE;
        }

        mt_srand(2026);
        $civiles = Chambre::where('libelle', 'like', '%civile%')->pluck('id')->all() ?: Chambre::pluck('id')->all();
        $penales = Chambre::where('libelle', 'like', '%correctionnelle%')->pluck('id')->all() ?: Chambre::pluck('id')->all();
        $nat = fn (string $l) => NatureAffaire::where('libelle', $l)->value('id') ?? NatureAffaire::where('registre', 'RG')->value('id');
        $modeCivil = ModeSaisine::where('registre', 'civil')->value('id');
        $modePenal = ModeSaisine::where('registre', 'penal')->value('id');
        $matiere = Matiere::value('id');
        $choix = fn (array $l) => $l[mt_rand(0, count($l) - 1)];
        $types = [
            ['Civile ordinaire', 'Paiement de loyers et dommages-intérêts', 5], ['Civile ordinaire', 'Restitution de véhicule', 3], ['Commerciale', 'Paiement de factures', 3], ['Administrative', 'Annulation de décision', 1],
            ['État des personnes', 'Jugement supplétif de naissance', 4], ['État des personnes', 'Jugement supplétif de décès', 1], ['État des personnes', 'Modification de prénom', 1], ['État des personnes', "Établissement de la qualité d'héritiers", 3],
            ['Matrimoniale', 'Divorce pour faute', 3], ['Matrimoniale', 'Divorce par consentement mutuel', 2], ['Matrimoniale', 'Adoption simple', 1], ['Matrimoniale', 'Adoption plénière', 1],
            ['Référé ordinaire', 'Référé en paiement', 2], ['Référé-expulsion', 'Expulsion pour défaut de paiement', 3], ["Juge de l'exécution (voies d'exécution)", "Contestation d'une saisie", 1], ['Saisie-rémunération', 'Saisie sur salaire', 1],
        ];
        $pioche = [];
        foreach ($types as $i => $t) {
            $pioche = array_merge($pioche, array_fill(0, $t[2], $i));
        }

        $aujourdhui = today();
        $n = 0;
        $numeros = [];
        for ($an = 2022; $an <= $aujourdhui->year; $an++) {
            $nb = $an === $aujourdhui->year ? 70 : 55 + ($an - 2022) * 6;
            for ($i = 0; $i < $nb; $i++) {
                [$nature, $objet] = $types[$choix($pioche)];
                $entree = Carbon::create($an, 1, 1)->addDays(mt_rand(0, 364));
                if ($entree->gt($aujourdhui->copy()->subDays(7))) {
                    $entree = $aujourdhui->copy()->subDays(mt_rand(8, 120));
                }
                $numeros[$an] = ($numeros[$an] ?? 8000) + 1;
                $a = Affaire::create([
                    'serie' => 'RG', 'annee' => $an, 'numero' => $numeros[$an], 'numero_complet' => 'RG-'.$numeros[$an].'/'.substr((string) $an, 2), 'numero_saisi' => true, 'ancien_numero' => self::MARQUE,
                    'date_rg' => $entree->copy()->subDays(mt_rand(3, 15)), 'objet' => $objet, 'mode_saisine_id' => $modeCivil, 'nature_affaire_id' => $nat($nature), 'matiere_id' => $matiere,
                    'chambre_id' => $choix($civiles), 'statut' => 'enrolee', 'date_premiere_audience' => $entree,
                ]);
                Partie::create(['affaire_id' => $a->id, 'role' => 'demandeur', 'nature' => 'homme', 'nom' => 'DEMO'.($n + 1), 'prenoms' => 'Essai', 'cle' => 'demo'.($n + 1)]);
                $n++;

                $duree = mt_rand(25, 260);
                $sortie = $entree->copy()->addDays($duree);
                if (mt_rand(1, 100) <= 78 && $sortie->lte($aujourdhui)) {
                    $tirage = mt_rand(1, 100);
                    $type = $tirage <= 82 ? 'decision_au_fond' : ($tirage <= 90 ? 'radiation' : ($tirage <= 95 ? 'desistement' : 'pv_conciliation'));
                    $adoption = str_contains($objet, 'Adoption');
                    ResultatAffaire::create([
                        'affaire_id' => $a->id, 'type_sortie' => $type, 'date_sortie' => $sortie,
                        'resultat' => $type === 'decision_au_fond' && in_array($nature, ['État des personnes', 'Matrimoniale'], true) ? (mt_rand(1, 100) <= 82 ? 'accord' : 'rejet') : null,
                        'adoption_filles' => $adoption ? mt_rand(0, 2) : null, 'adoption_garcons' => $adoption ? mt_rand(0, 2) : null,
                    ]);
                }
            }
        }

        $infractions = Infraction::where('actif', true)->pluck('libelle')->all() ?: ['Vol'];
        $nationalites = ['ivoirienne', 'ivoirienne', 'ivoirienne', 'burkinabè', 'malienne', 'guinéenne', 'nigériane', 'béninoise'];
        $professions = ['commerçant', 'chauffeur', 'maçon', 'élève', 'sans emploi', 'cultivateur', 'mécanicien', 'vigile', 'couturière'];
        $issues = ['condamne', 'condamne', 'condamne', 'condamne', 'condamne', 'relaxe', 'dispense_de_peine'];
        $p = 0;
        for ($an = max(2024, $aujourdhui->year - 2); $an <= $aujourdhui->year; $an++) {
            for ($i = 0; $i < 70; $i++) {
                $audience = Carbon::create($an, 1, 1)->addDays(mt_rand(0, 330));
                $decision = $audience->copy()->addDays(mt_rand(7, 110));
                if ($decision->gt($aujourdhui)) {
                    continue;
                }
                $p++;
                $numeros['P'.$an] = ($numeros['P'.$an] ?? 8000) + 1;
                $a = Affaire::create([
                    'serie' => 'RP', 'annee' => $an, 'numero' => $numeros['P'.$an], 'numero_complet' => 'RP-'.$numeros['P'.$an].'/'.substr((string) $an, 2), 'numero_saisi' => true, 'ancien_numero' => self::MARQUE,
                    'date_rg' => $audience->copy()->subDays(mt_rand(2, 10)), 'objet' => $choix($infractions), 'mode_saisine_id' => $modePenal, 'nature_affaire_id' => NatureAffaire::where('registre', 'RP')->value('id'),
                    'matiere_id' => $matiere, 'chambre_id' => $choix($penales), 'statut' => 'enrolee', 'date_premiere_audience' => $audience,
                ]);
                $partie = Partie::create(['affaire_id' => $a->id, 'role' => 'prevenu', 'nature' => mt_rand(1, 100) <= 82 ? 'homme' : 'femme', 'nom' => 'DEMOP'.$p, 'prenoms' => 'Essai', 'cle' => 'demop'.$p, 'nationalite' => $choix($nationalites), 'profession' => $choix($professions)]);
                $issue = $choix($issues);
                $condamne = $issue === 'condamne';
                DecisionPenale::create([
                    'affaire_id' => $a->id, 'partie_id' => $partie->id, 'date_decision' => $decision, 'contradiction' => $choix(['contradictoire', 'contradictoire', 'defaut']), 'issue' => $issue,
                    'infraction_retenue' => $a->objet, 'nature_infraction' => $choix(['delit', 'delit', 'delit', 'contravention', 'crime']), 'traite' => mt_rand(1, 100) <= 4,
                    'emprisonnement_mois' => $condamne && mt_rand(1, 100) <= 60 ? $choix([3, 6, 12, 12, 24, 36]) : null, 'amende' => $condamne ? $choix([50000, 100000, 250000, 500000]) : null,
                    'victimes_hommes' => mt_rand(0, 2), 'victimes_femmes' => mt_rand(0, 2), 'victimes_garcons' => mt_rand(1, 100) <= 10 ? 1 : 0, 'victimes_filles' => mt_rand(1, 100) <= 10 ? 1 : 0,
                    'date_transmission_minute' => mt_rand(1, 100) <= 65 ? $decision->copy()->addDays(mt_rand(3, 45)) : null,
                ]);
            }
        }
        // Une sanction sans emprisonnement n'a pas de sursis : le jeu respecte les règles de saisie du répertoire.
        DecisionPenale::whereNull('emprisonnement_mois')->update(['sursis_mois' => null]);

        $this->info($n.' affaires civiles et '.$p.' décisions pénales fictives créées. Retrait : php artisan statistiques:demo --retirer');

        return self::SUCCESS;
    }
}
