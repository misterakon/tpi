<?php

namespace App\Console\Commands;

use App\Models\Affaire;
use App\Models\Avocat;
use App\Models\Chambre;
use App\Models\Matiere;
use App\Models\ModeSaisine;
use App\Models\NatureAffaire;
use App\Models\Partie;
use App\Services\NumerotationRegistre;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/*
 | Dix NOUVELLES affaires civiles FICTIVES (1re chambre civile) pour travailler : établir des rôles, des audiences, rédiger des décisions.
 | Elles sont enregistrées mais n'ont encore ni rôle ni audience : à la création du rôle de leur date, l'application les range
 | toute seule dans « Affaires nouvelles ». Les noms des parties sont inventés. Marque : ancien numéro « TRAVAIL ».
 |
 |   php artisan demo:travail            crée les dix affaires (relançable : elles sont d'abord retirées)
 |   php artisan demo:travail --retirer  les retire seulement
 */
class DemoTravail extends Command
{
    protected $signature = 'demo:travail {--retirer : Retire seulement les affaires de travail}';

    protected $description = 'Crée dix nouvelles affaires civiles fictives, sans rôle, pour établir des rôles et des audiences';

    public function handle(): int
    {
        $ids = DB::table('affaires')->where('ancien_numero', 'TRAVAIL')->pluck('id');
        if ($ids->isNotEmpty()) {
            DB::table('role_lignes')->whereIn('affaire_id', $ids)->delete();
            DB::table('parties')->whereIn('affaire_id', $ids)->delete();
            DB::table('affaires')->whereIn('id', $ids)->delete();
        }
        if ($this->option('retirer')) {
            $this->info($ids->count().' affaire(s) de travail retirée(s).');

            return self::SUCCESS;
        }

        $chambre = Chambre::where('libelle', '1re chambre civile')->firstOrFail();
        $annee = (int) now()->year;
        $mode = ModeSaisine::where('libelle', 'Assignation')->value('id') ?? ModeSaisine::where('registre', 'civil')->value('id');
        $nature = NatureAffaire::where('libelle', 'Civile ordinaire')->value('id') ?? NatureAffaire::where('registre', 'RG')->value('id');
        $matiere = Matiere::where('libelle', 'Civile')->value('id') ?? Matiere::value('id');

        $h = fn (string $nom, string $prenoms, string $role = 'demandeur', ?string $avocat = null) => ['role' => $role, 'nature' => 'homme', 'nom' => $nom, 'prenoms' => $prenoms, 'nationalite' => 'ivoirienne', 'domicile' => 'Bingerville'] + ($avocat ? ['avocat' => $avocat] : []);
        $f = fn (string $nom, string $prenoms, string $role = 'demandeur', ?string $avocat = null) => ['role' => $role, 'nature' => 'femme', 'nom' => $nom, 'prenoms' => $prenoms, 'nationalite' => 'ivoirienne', 'domicile' => 'Bingerville'] + ($avocat ? ['avocat' => $avocat] : []);
        $m = fn (string $nom, string $role = 'demandeur', ?string $avocat = null) => ['role' => $role, 'nature' => 'morale', 'denomination' => $nom, 'domicile' => 'Abidjan'] + ($avocat ? ['avocat' => $avocat] : []);

        $p1 = '2026-12-03';
        $p2 = '2026-12-10';
        $jeu = [
            [2201, 'Paiement de loyers impayés et expulsion', $p1, [$h('KOFFI', 'Aka Marcel', 'demandeur', 'Maître KONE Catherine'), $f('TRAORE', 'Fatoumata', 'defendeur')]],
            [2202, 'Paiement de créance civile', $p1, [$m('Société AGRO-CÔTE SARL', 'demandeur', 'Cabinet ABI & Associés'), $h('YAO', 'Kouamé Alphonse', 'defendeur')]],
            [2203, 'Déguerpissement', $p1, [$f('BAMBA', 'Awa', 'demandeur', "Maître N'GUESSAN Paul"), $h('SORO', 'Lacina', 'defendeur')]],
            [2204, 'Validation de congé avec expulsion', $p1, [$h('DOSSO', 'Mamadou', 'demandeur'), $f('KOUAME', 'Adjoua Rose', 'defendeur')]],
            [2205, 'Revendication de propriété foncière', $p1, [$h('N\'DRI', 'Kouadio Jean', 'demandeur', 'Maître KONE Catherine'), $h('ASSI', 'Kablan Eugène', 'defendeur')]],
            [2206, 'Dommages-intérêts pour inexécution de contrat', $p1, [$m('Société BTP IVOIRE SA', 'demandeur', 'Cabinet ABI & Associés'), $m('Société LAGUNE IMMOBILIER SARL', 'defendeur')]],
            [2207, 'Paiement de loyers impayés', $p2, [$f('GNAGNE', 'Prisca', 'demandeur'), $h('TOURE', 'Ibrahim', 'defendeur')]],
            [2208, 'Démolition de constructions illégales', $p2, [$h('ZOKORA', 'Pascal', 'demandeur', "Maître N'GUESSAN Paul"), $h('KRA', 'Konan Désiré', 'defendeur')]],
            [2209, 'Paiement de créance civile', $p2, [$h('COULIBALY', 'Seydou', 'demandeur'), $h('OUATTARA', 'Drissa', 'defendeur')]],
            [2210, 'Expulsion de locataire', $p2, [$f('ADOU', 'Marie-Laure', 'demandeur', 'Maître KONE Catherine'), $h('BOLOU', 'Tapé Honoré', 'defendeur')]],
        ];

        foreach ($jeu as [$numero, $objet, $premiere, $parties]) {
            $a = Affaire::create([
                'serie' => 'RG', 'annee' => $annee, 'numero' => $numero, 'numero_complet' => NumerotationRegistre::formater('RG', $numero, $annee), 'numero_saisi' => true,
                'ancien_numero' => 'TRAVAIL', 'date_rg' => ($numero % 2 ? today()->subDay() : today())->toDateString(), 'statut' => 'enrolee', 'objet' => $objet,
                'mode_saisine_id' => $mode, 'nature_affaire_id' => $nature, 'matiere_id' => $matiere, 'chambre_id' => $chambre->id, 'date_premiere_audience' => $premiere,
            ]);
            foreach ($parties as $i => $p) {
                if (! empty($p['avocat'])) {
                    [$p['avocat_id'], $p['avocat']] = Avocat::rattacher($p['avocat'], true);
                }
                $physique = in_array($p['nature'], ['homme', 'femme'], true);
                $a->parties()->create($p + ['ordre' => $i, 'cle' => Partie::cle($physique ? ($p['nom'] ?? '').' '.($p['prenoms'] ?? '') : (string) ($p['denomination'] ?? ''))]);
            }
        }

        $this->info('Dix affaires de travail créées (RG 2201/26 à 2210/26), 1re chambre civile.');
        $this->line("  Première audience du $p1 : 2201 à 2206 · du $p2 : 2207 à 2210. Aucun rôle : à créer dans Audiences > Rôles d'audience.");

        return self::SUCCESS;
    }
}
