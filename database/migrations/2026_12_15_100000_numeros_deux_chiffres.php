<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 | Numéros des registres sur deux chiffres au moins : RG-01/26, RG-12/26, RG-123/26 (et non plus RG-0001/26).
 | Seul le texte « numero_complet » (utilisé par la recherche) est réécrit ; le numéro et l'année ne changent pas.
 | On passe par le builder (et non par le modèle) car le modèle Affaire interdit toute modification du numéro.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("UPDATE affaires SET numero_complet = CONCAT(serie, '-', IF(numero < 10, CONCAT('0', numero), numero), '/', LPAD(annee MOD 100, 2, '0')) WHERE numero_complet LIKE '%-%/%'");
        DB::statement("UPDATE requetes SET numero_complet = CONCAT('RQ-', IF(numero < 10, CONCAT('0', numero), numero), '/', LPAD(annee MOD 100, 2, '0')) WHERE numero_complet LIKE 'RQ-%/%'");
    }

    public function down(): void
    {
        DB::statement("UPDATE affaires SET numero_complet = CONCAT(serie, '-', LPAD(numero, GREATEST(4, CHAR_LENGTH(numero)), '0'), '/', LPAD(annee MOD 100, 2, '0')) WHERE numero_complet LIKE '%-%/%'");
        DB::statement("UPDATE requetes SET numero_complet = CONCAT('RQ-', LPAD(numero, GREATEST(4, CHAR_LENGTH(numero)), '0'), '/', LPAD(annee MOD 100, 2, '0')) WHERE numero_complet LIKE 'RQ-%/%'");
    }
};
