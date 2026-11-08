<?php

namespace App\Console\Commands;

use App\Services\Reglages;
use Illuminate\Console\Command;

/* Secours : si un mauvais réglage (ex. serveur SMTP) empêche de se connecter, on revient aux valeurs du fichier .env. */
class ReinitialiserReglages extends Command
{
    protected $signature = 'tpi:reglages:reinitialiser {rubrique : organisation, securite, courriels ou interface}';

    protected $description = "Rétablit les valeurs par défaut (fichier .env) d'une rubrique de la page Réglages";

    public function handle(Reglages $reglages): int
    {
        $rubrique = $this->argument('rubrique');
        if (! isset(Reglages::catalogue()[$rubrique])) {
            $this->error('Rubrique inconnue. Choix : '.implode(', ', array_keys(Reglages::catalogue())));

            return self::FAILURE;
        }

        $n = count($reglages->reinitialiser($rubrique));
        $this->info("Rubrique « $rubrique » rétablie ($n réglage(s) modifié(s)).");

        return self::SUCCESS;
    }
}
