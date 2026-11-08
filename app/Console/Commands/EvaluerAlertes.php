<?php

namespace App\Console\Commands;

use App\Support\Alertes\MoteurAlertes;
use Illuminate\Support\Carbon;
use Illuminate\Console\Command;
use Throwable;

class EvaluerAlertes extends Command
{
    protected $signature = 'alertes:evaluer
        {--simuler : Affiche ce qui partirait, sans rien envoyer ni enregistrer}
        {--date= : Évalue seulement ce jour (AAAA-MM-JJ) ; sans option, tous les jours depuis la dernière évaluation (rattrapage)}';

    protected $description = "Évalue les échéances du circuit des dossiers et envoie les alertes du jour (rattrape les jours manqués)";

    public function handle(): int
    {
        $simuler = (bool) $this->option('simuler');
        $detail = $simuler || $this->getOutput()->isVerbose();
        $moteur = new MoteurAlertes($simuler, function (string $texte, string $niveau) use ($detail) {
            if ($niveau === 'warn') {
                $this->warn($texte);
            } elseif ($detail) {
                $this->line($texte);
            }
        });

        try {
            if ($this->option('date')) {
                $jour = Carbon::createFromFormat('Y-m-d', (string) $this->option('date'))->startOfDay();
                $this->info(($simuler ? '[SIMULATION] ' : '').'Évaluation du '.$jour->format('d/m/Y'));
                $stats = $moteur->evaluer($jour);
            } else {
                $this->info(($simuler ? '[SIMULATION] ' : '').'Évaluation des alertes (rattrapage des jours manqués)');
                $stats = $moteur->rattraper(today());
            }
        } catch (Throwable $e) {
            $this->error('Impossible d\'évaluer les alertes : '.$e->getMessage());

            return self::FAILURE;
        }

        $this->table(['Indicateur', 'Valeur'], [
            ['Échéances suivies', $stats['echeances']],
            ['Règles déclenchées', $stats['declenchees']],
            [$simuler ? 'Alertes à envoyer' : 'Alertes envoyées', $stats['envoyees']],
            ['Déjà envoyées (anti-doublon)', $stats['deja_envoyees']],
            ['Suspendues (retard justifié)', $stats['justifiees']],
            ['Sans destinataire', $stats['sans_destinataire']],
            ['Alertes refermées (action faite)', $stats['refermees']],
            [$simuler ? 'E-mails récapitulatifs à envoyer' : 'E-mails récapitulatifs envoyés', $stats['courriels']],
            ['Échecs', $stats['echecs']],
        ]);

        return $stats['echecs'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
