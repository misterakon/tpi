<?php

namespace App\Policies;

use App\Models\User;

/*
 | Règles d'accès à la gestion des comptes. Au-delà du droit du profil :
 |  - un compte protégé (le premier admin) ne peut être ni modifié ni supprimé par un autre utilisateur ;
 |  - on ne peut pas supprimer son propre compte ;
 |  - seul un admin peut toucher à un compte admin.
 */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->peut('comptes.voir');
    }

    public function create(User $user): bool
    {
        return $user->peut('comptes.creer');
    }

    public function update(User $user, User $cible): bool
    {
        return $user->peut('comptes.modifier') && $this->peutAgirSur($user, $cible);
    }

    public function delete(User $user, User $cible): bool
    {
        return $user->peut('comptes.supprimer') && $user->isNot($cible) && $this->peutAgirSur($user, $cible);
    }

    public function resetPassword(User $user, User $cible): bool
    {
        return $user->peut('comptes.mot_de_passe') && $cible->actif && $this->peutAgirSur($user, $cible);
    }

    private function peutAgirSur(User $user, User $cible): bool
    {
        if ($cible->protege && $user->isNot($cible)) {
            return false;
        }
        if ($cible->estAdmin() && ! $user->estAdmin()) {
            return false;
        }

        return true;
    }
}
