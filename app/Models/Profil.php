<?php

namespace App\Models;

use App\Support\Droits;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Profil extends Model
{
    protected $table = 'profils';

    protected $fillable = ['nom', 'description', 'droits'];

    protected function casts(): array
    {
        return ['droits' => 'array', 'systeme' => 'boolean'];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /** Le profil Admin (créé par la migration, marqué « système »). */
    public static function admin(): self
    {
        return self::where('systeme', true)->firstOrFail();
    }

    /** Ce profil est-il le profil Admin du système (tous les droits, non modifiable) ? */
    public function estSysteme(): bool
    {
        return $this->systeme === true;
    }

    public function autorise(string $droit): bool
    {
        return $this->systeme || in_array($droit, $this->droits ?? [], true);
    }

    /** Nombre de sous-menus auxquels ce profil donne accès (Admin : tous). */
    public function nombrePages(): int
    {
        return $this->systeme ? count(Droits::pages()) : Droits::nombrePagesAccessibles($this->droits ?? []);
    }
}
