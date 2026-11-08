<?php

namespace App\Http\Controllers\Auth;

use Illuminate\Validation\Rules\Password;

/** Règle unique de robustesse des mots de passe, utilisée partout. */
class MotDePasse
{
    public static function regle(): Password
    {
        return Password::min((int) config('securite.mot_de_passe_min'));
    }
}
