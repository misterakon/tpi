<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Charge les données de départ : l'organisation du tribunal d'après l'ordonnance 2026-2027.
     * Le compte administrateur se crée à part, avec « php artisan tpi:admin » (aucun compte de test n'est créé ici).
     */
    public function run(): void
    {
        $this->call(OrdonnanceBingerville2026Seeder::class);
        $this->call(ReferentielsJudiciairesSeeder::class);
        $this->call(ModelesDecisionSeeder::class);
    }
}
