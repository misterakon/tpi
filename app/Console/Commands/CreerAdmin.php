<?php

namespace App\Console\Commands;

use App\Http\Controllers\Auth\MotDePasse;
use App\Models\Profil;
use App\Models\User;
use App\Services\Journal;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

use function Laravel\Prompts\password;

/*
 | Création du compte admin principal depuis le serveur, ou changement de son mot de passe.
 | Ce compte est « protégé » : aucun autre utilisateur ne peut le modifier ni le supprimer
 | depuis l'application.
 */
class CreerAdmin extends Command
{
    protected $signature = 'tpi:admin {email : Adresse e-mail du compte} {--nom= : Nom affiché}';

    protected $description = 'Crée un compte admin, ou en change le mot de passe (saisie masquée).';

    public function handle(): int
    {
        $email = mb_strtolower(trim($this->argument('email')));

        if (Validator::make(['email' => $email], ['email' => ['required', 'email', 'max:255']])->fails()) {
            $this->error('Adresse e-mail invalide.');

            return self::FAILURE;
        }

        $user = User::withTrashed()->where('email', $email)->first();

        $mot = password('Mot de passe (min. '.config('securite.mot_de_passe_min').' caractères, saisie masquée)');
        $confirmation = password('Confirmez le mot de passe');

        $validation = Validator::make(
            ['password' => $mot, 'password_confirmation' => $confirmation],
            ['password' => ['required', 'confirmed', MotDePasse::regle()]],
        );
        if ($validation->fails()) {
            foreach ($validation->errors()->all() as $erreur) {
                $this->error($erreur);
            }

            return self::FAILURE;
        }

        $nouveau = ! $user;
        $user ??= new User;
        $user->forceFill([
            'name' => $this->option('nom') ?: ($user->name ?: 'Administrateur'),
            'email' => $email,
            'password' => $mot,
            'profil_id' => Profil::admin()->id,
            'protege' => true,   // ne peut être ni modifié ni supprimé par un autre utilisateur
            'actif' => true,
            'echecs_connexion' => 0,
            'verrouille_jusqu_a' => null,
            'mot_de_passe_modifie_le' => now(),
            'deleted_at' => null,
        ])->save();

        Journal::audit($nouveau ? 'admin_cree' : 'admin_mot_de_passe_change', $user, $email, auteur: null);

        $this->info($nouveau ? "Compte admin créé : {$email}" : "Mot de passe de l'admin changé : {$email}");

        return self::SUCCESS;
    }
}
