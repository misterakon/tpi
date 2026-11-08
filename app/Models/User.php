<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;

/*
 | Le profil, l'état (actif), la protection et les compteurs de sécurité ne sont volontairement pas
 | « fillable » : ils ne peuvent pas être modifiés par un formulaire détourné.
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'actif' => 'boolean',
            'protege' => 'boolean',
            'verrouille_jusqu_a' => 'datetime',
            'last_login_at' => 'datetime',
            'mot_de_passe_modifie_le' => 'datetime',
        ];
    }

    public function profil(): BelongsTo
    {
        return $this->belongsTo(Profil::class);
    }

    /** Le profil de l'utilisateur lui accorde-t-il ce droit ? (Admin : tous les droits.) */
    /** L'agent (magistrat, greffier…) relié à ce compte, s'il y en a un. */
    public function agent(): HasOne
    {
        return $this->hasOne(Agent::class);
    }

    public function peut(string $droit): bool
    {
        return $this->actif && $this->profil?->autorise($droit) === true;
    }

    /*
     | Rôles « à la Spatie », sans le paquet : le rôle d'un utilisateur est le NOM de son profil.
     | Le profil administrateur du système passe tous les tests de rôle.
     | À réserver aux blocs qui dépendent d'une fonction (ex. « Président ») ; pour une action, préférer @can / peut().
     */
    public function hasRole(string $role): bool
    {
        return $this->hasAnyRole([$role]);
    }

    /** @param  string|array<int, string>  $roles  liste, ou texte séparé par « | » */
    public function hasAnyRole(string|array $roles): bool
    {
        if (! $this->actif || $this->profil === null) {
            return false;
        }
        if ($this->estAdmin()) {
            return true;
        }
        $voulus = [];
        foreach ((array) $roles as $groupe) {
            foreach (explode('|', (string) $groupe) as $r) {
                $voulus[] = self::normaliserRole($r);
            }
        }

        return in_array(self::normaliserRole($this->profil->nom), $voulus, true);
    }

    /** Nom de rôle comparable : sans majuscules, sans accents, espaces simplifiés (« Président » = « president »). */
    private static function normaliserRole(string $nom): string
    {
        $nom = mb_strtolower(trim(preg_replace('/\s+/u', ' ', $nom) ?? $nom));
        $sansAccents = transliterator_transliterate('NFD; [:Nonspacing Mark:] Remove; NFC', $nom);

        return $sansAccents === false ? $nom : $sansAccents;
    }

    public function hasAllRoles(array $roles): bool
    {
        foreach ($roles as $role) {
            if (! $this->hasRole($role)) {
                return false;
            }
        }

        return $roles !== [];
    }

    public function estAdmin(): bool
    {
        return $this->profil?->systeme === true;
    }

    /** Initiales affichées dans la pastille du compte (ex. « Awa Koné » devient « AK »). */
    public function initiales(): string
    {
        $mots = preg_split('/\s+/u', trim($this->name)) ?: [];
        $lettres = array_map(fn ($m) => mb_strtoupper(mb_substr($m, 0, 1)), array_slice($mots, 0, 2));

        return implode('', $lettres) ?: '?';
    }

    public function estVerrouille(): bool
    {
        return $this->verrouille_jusqu_a !== null && $this->verrouille_jusqu_a->isFuture();
    }

    public function peutSeConnecter(): bool
    {
        return $this->actif && ! $this->estVerrouille();
    }

    /** Empreinte factice utilisée pour garder le même temps de réponse quand le compte n'existe pas. */
    public static function empreinteFactice(): string
    {
        static $e = null;

        return $e ??= Hash::make('mot-de-passe-factice');
    }
}
