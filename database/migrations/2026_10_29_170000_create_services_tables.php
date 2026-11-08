<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /*
     | Lot D4 : services, commissions, délégations et fonctions particulières fixés par l'ordonnance de l'année
     | (coordination, comité de direction, services annexes, bureaux locaux, juges taxateurs…), avec les agents concernés.
     */
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('annee_judiciaire_id')->constrained('annees_judiciaires')->cascadeOnDelete();
            $table->string('categorie', 20);                      // coordination | comite | delegation | service | fonction | bureau
            $table->string('libelle', 255);
            $table->unsignedSmallInteger('ordre')->default(0);
            $table->text('remarque')->nullable();                 // personnes extérieures au tribunal, mission, précisions
            $table->timestamps();
            $table->index(['annee_judiciaire_id', 'categorie']);
        });

        Schema::create('service_agents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained('services')->cascadeOnDelete();
            $table->foreignId('agent_id')->constrained('agents')->restrictOnDelete();
            $table->string('fonction', 160)->nullable();          // ex. Secrétaire général, Juge délégué, Membre
            $table->unsignedSmallInteger('ordre')->default(0);
            $table->timestamps();
            $table->index('service_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_agents');
        Schema::dropIfExists('services');
    }
};
