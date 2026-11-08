<?php

namespace App\Policies;

use App\Models\Profil;
use App\Models\User;

class ProfilPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->peut('profils.voir');
    }

    public function create(User $user): bool
    {
        return $user->peut('profils.creer');
    }

    /** Le profil Admin (système) n'est ni modifiable ni supprimable. */
    public function update(User $user, Profil $profil): bool
    {
        return $user->peut('profils.modifier') && ! $profil->systeme;
    }

    public function delete(User $user, Profil $profil): bool
    {
        return $user->peut('profils.supprimer') && ! $profil->systeme && ! $profil->users()->exists();
    }
}
