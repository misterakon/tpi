<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\CodesSecours;
use Illuminate\Console\Command;

/*
 | Pour les essais : génère des codes de secours pour les comptes de test (adresses en @tpi.test, voir database/sql/comptes_test.sql).
 | Ces comptes ont des boîtes fictives : le code reçu par e-mail ne leur arrive jamais, un code de secours permet de se connecter.
 | Les codes sont écrits dans un fichier local ; à n'utiliser que sur une base de test.
 */
class CodesSecoursTest extends Command
{
    protected $signature = 'tpi:codes-test {--fichier= : chemin du fichier de sortie (défaut : storage/app/codes_secours_test.txt)}';

    protected $description = 'Génère des codes de secours pour les comptes de test (@tpi.test) et les écrit dans un fichier';

    public function handle(CodesSecours $codes): int
    {
        $comptes = User::where('email', 'like', '%@tpi.test')->where('actif', true)->orderBy('name')->get();
        if ($comptes->isEmpty()) {
            $this->warn('Aucun compte de test (@tpi.test) : importez d\'abord database/sql/comptes_test.sql.');

            return self::FAILURE;
        }

        $lignes = ["Codes de secours des comptes de test — mot de passe : Tpi@2026", "Chaque code ne sert qu'une fois. Relancer cette commande en génère de nouveaux (les anciens sont annulés).", ''];
        foreach ($comptes as $u) {
            $lignes[] = $u->email.'  ('.$u->name.')';
            $lignes[] = '    '.implode('   ', $codes->generer($u));
        }

        $fichier = $this->option('fichier') ?: storage_path('app/codes_secours_test.txt');
        @mkdir(dirname($fichier), 0777, true);
        file_put_contents($fichier, implode("\n", $lignes)."\n");

        $this->info($comptes->count().' comptes : codes écrits dans '.$fichier);

        return self::SUCCESS;
    }
}
