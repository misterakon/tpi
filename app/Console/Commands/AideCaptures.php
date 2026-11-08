<?php

namespace App\Console\Commands;

use App\Models\Affaire;
use App\Models\Chambre;
use App\Models\Decision;
use App\Models\Profil;
use App\Models\RoleAudience;
use App\Models\User;
use App\Services\CodesSecours;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\Process\Process;

/*
 | Refait toutes les captures d'écran du manuel d'aide (public/assets/aide/*.png) avec un vrai navigateur Chrome sans fenêtre :
 |
 |   php artisan aide:captures                  refait toutes les captures
 |   php artisan aide:captures --seulement=role-composition --seulement=recours-etats
 |   php artisan aide:captures --base=http://localhost/tpi --chrome="C:\...\chrome.exe"
 |
 | À relancer chaque fois qu'un écran change : les épingles numérotées des chapitres (voir resources/aide/*.md) restent en place.
 | Prérequis : les données de formation (php artisan tpi:formation) et Node.js. Un compte administrateur de test « aide.captures@tpi.test »
 | est créé s'il manque (mot de passe d'essai) ; il disparaît avec les comptes de test (comptes_test_retirer.sql).
 */
class AideCaptures extends Command
{
    protected $signature = 'aide:captures {--base=http://localhost/tpi : Adresse de l\'application} {--chrome= : Chemin de chrome.exe (sinon recherché)} {--seulement=* : Ne refaire que ces captures (nom sans .png)}';

    protected $description = "Refait les captures d'écran du manuel d'aide avec un vrai navigateur";

    public function handle(CodesSecours $codes): int
    {
        $chrome = $this->option('chrome') ?: collect([
            'C:\Program Files\Google\Chrome\Application\chrome.exe', 'C:\Program Files (x86)\Google\Chrome\Application\chrome.exe', 'C:\Program Files (x86)\Microsoft\Edge\Application\msedge.exe',
            'C:\Program Files\Microsoft\Edge\Application\msedge.exe', '/usr/bin/google-chrome', '/usr/bin/chromium',
        ])->first(fn ($p) => is_file($p));
        if (! $chrome) {
            $this->error('Chrome ou Edge introuvable : indiquez-le avec --chrome="chemin".');

            return self::FAILURE;
        }

        $admin = Profil::where('systeme', true)->first();
        if (! $admin) {
            $this->error('Profil administrateur introuvable.');

            return self::FAILURE;
        }
        $mdp = 'Tpi@2026';
        $user = User::firstOrNew(['email' => 'aide.captures@tpi.test']);
        $user->forceFill(['name' => 'Captures Aide', 'password' => Hash::make($mdp), 'profil_id' => $admin->id, 'actif' => true])->save();
        $code = $codes->generer($user)[0];

        $liste = $this->liste();
        $seulement = (array) $this->option('seulement');
        if ($seulement) {
            $liste = array_values(array_filter($liste, fn ($c) => in_array(str_replace('.png', '', $c['fichier']), $seulement, true)));
        }

        $config = storage_path('app/aide_captures.json');
        file_put_contents($config, json_encode([
            'base' => rtrim((string) $this->option('base'), '/'), 'chrome' => $chrome, 'sortie' => public_path('assets/aide'), 'compte' => $user->email, 'mdp' => $mdp, 'code' => $code, 'captures' => $liste,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        $this->info(count($liste).' capture(s) à faire, plus la page du code de connexion.');
        $processus = new Process(['node', base_path('tools/captures/captures.mjs'), $config], base_path(), null, null, 900);
        $processus->run(fn ($type, $texte) => $this->output->write($texte));
        @unlink($config);

        return $processus->isSuccessful() ? self::SUCCESS : self::FAILURE;
    }

    /** @return list<array<string, mixed>> */
    private function liste(): array
    {
        $chemin = fn (string $nom, array $p = []) => route($nom, $p, false);
        $affaire = fn (string $numero) => Affaire::where('numero_complet', $numero)->orderBy('id')->first();
        $role = fn (string $chambre, string $statut, ?string $apres = null) => RoleAudience::where('statut', $statut)->whereHas('chambre', fn ($q) => $q->where('libelle', $chambre))
            ->when($apres, fn ($q) => $q->whereDate('date_audience', '>=', $apres))->orderBy('date_audience')->first();
        $liste = [
            ['fichier' => 'menu-principal.png', 'url' => $chemin('dashboard')],
            ['fichier' => 'recherche-dossier.png', 'url' => $chemin('recherche', ['q' => 'KOFFI loyers'])],
            ['fichier' => 'liste-affaires.png', 'url' => $chemin('affaires.liste')],
            ['fichier' => 'requetes-liste.png', 'url' => $chemin('affaires.requetes')],
            ['fichier' => 'mise-en-etat.png', 'url' => $chemin('affaires.mise_en_etat')],
            ['fichier' => 'detenus.png', 'url' => $chemin('affaires.detenus')],
            ['fichier' => 'planning-conflit.png', 'url' => $chemin('audiences.roles.planning').'?du='.now()->addDays(20)->toDateString()],
            ['fichier' => 'roles-liste.png', 'url' => $chemin('audiences.roles')],
            ['fichier' => 'role-jour.png', 'url' => $chemin('audiences.jour')],
            ['fichier' => 'renvois-delibere.png', 'url' => $chemin('audiences.renvois')],
            ['fichier' => 'decisions-registre.png', 'url' => $chemin('decisions.registre')],
            ['fichier' => 'decision-circuit.png', 'url' => $chemin('decisions.redaction')],
            ['fichier' => 'recours-etats.png', 'url' => $chemin('decisions.recours')],
            ['fichier' => 'instruction-liste.png', 'url' => $chemin('instruction')],
            ['fichier' => 'assistance-judiciaire.png', 'url' => $chemin('assistance')],
            ['fichier' => 'statistiques-civiles.png', 'url' => $chemin('statistiques.civil')],
            ['fichier' => 'registres-tribunal.png', 'url' => $chemin('statistiques.registres')],
            ['fichier' => 'alertes-suivi.png', 'url' => $chemin('alertes.suivi')],
            ['fichier' => 'reglages-onglets.png', 'url' => $chemin('reglages.index')],
            ['fichier' => 'comptes-liste.png', 'url' => $chemin('utilisateur.compte')],
            ['fichier' => 'profils-droits.png', 'url' => $chemin('profils.index')],
            ['fichier' => 'chambres.png', 'url' => $chemin('organisation.chambres')],
            ['fichier' => 'aide-panneau.png', 'url' => $chemin('affaires.liste'), 'avant' => 'window.TPIAide && window.TPIAide.ouvrir()', 'apres' => 2200],
            ['fichier' => 'aide-sommaire.png', 'url' => $chemin('aide.index')],
            ['fichier' => 'aide-chapitre.png', 'url' => $chemin('aide.chapitre', ['slug' => 'audiences']), 'avant' => "document.querySelector('.aide-capture')?.scrollIntoView({ block: 'center' })", 'apres' => 3500],
        ];
        if ($a = $affaire('RG-1391/26')) {
            $liste[] = ['fichier' => 'fiche-affaire.png', 'url' => $chemin('affaires.liste.fiche', ['id' => $a->id])];
        }
        if ($r = $role('1re chambre civile', 'brouillon')) {
            $liste[] = ['fichier' => 'role-composition.png', 'url' => $chemin('audiences.roles.show', ['id' => $r->id])];
        }
        if ($r = RoleAudience::where('statut', 'publie')->whereNull('audience_terminee_le')->whereHas('chambre', fn ($q) => $q->where('libelle', '5e chambre civile'))->orderByDesc('date_audience')->first()) {
            $liste[] = ['fichier' => 'feuille-audience.png', 'url' => $chemin('audiences.feuille.show', ['id' => $r->id])];
        }
        if ($d = Decision::where('statut', 'brouillon')->orderBy('id')->first()) {
            $liste[] = ['fichier' => 'redaction-apercu.png', 'url' => $chemin('decisions.redaction.edition', ['id' => $d->id])];
        }

        return $liste;
    }
}
