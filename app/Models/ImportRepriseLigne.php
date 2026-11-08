<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Une ligne d'un fichier de reprise : ses cellules, le résultat du contrôle et, une fois importée, l'affaire créée. */
class ImportRepriseLigne extends Model
{
    public $timestamps = false;

    protected $table = 'imports_reprise_lignes';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['cellules' => 'array', 'messages' => 'array', 'resultat' => 'array'];
    }
}
