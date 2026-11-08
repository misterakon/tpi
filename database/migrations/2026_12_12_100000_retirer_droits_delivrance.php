<?php

use App\Models\Profil;
use Illuminate\Database\Migrations\Migration;

/** La page « Délivrance » n'existe plus (la délivrance se fait dans Rédaction) : ses droits sont retirés des profils. */
return new class extends Migration
{
    public function up(): void
    {
        foreach (Profil::all() as $profil) {
            $droits = (array) $profil->droits;
            $restants = array_values(array_filter($droits, fn ($d) => ! str_starts_with((string) $d, 'delivrance.')));
            if ($restants !== $droits) {
                $profil->forceFill(['droits' => $restants])->save();
            }
        }
    }

    public function down(): void
    {
        // Rien à rétablir : la page n'existe plus.
    }
};
