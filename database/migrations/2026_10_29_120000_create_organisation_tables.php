<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /*
     | Lot D (organisation du tribunal) : année judiciaire, salles et lieux, personnel (magistrats et greffiers).
     | Les formations, affectations et calendriers d'audience s'appuieront sur ces tables.
     */
    public function up(): void
    {
        Schema::create('annees_judiciaires', function (Blueprint $table) {
            $table->id();
            $table->string('libelle', 20)->unique();          // ex. 2026-2027
            $table->date('debut');
            $table->date('fin');
            $table->string('ordonnance', 120)->nullable();     // ordonnance de référence
            $table->boolean('courante')->default(false)->index();
            $table->timestamps();
        });

        Schema::create('salles', function (Blueprint $table) {
            $table->id();
            $table->string('nom', 100)->unique();
            $table->string('type', 20);                        // audience | cabinet | reunion | autre
            $table->string('emplacement', 150)->nullable();
            $table->boolean('actif')->default(true);
            $table->timestamps();
        });

        Schema::create('agents', function (Blueprint $table) {
            $table->id();
            $table->string('nom', 80);
            $table->string('prenoms', 120);
            $table->string('fonction', 30);
            $table->string('matricule', 40)->nullable()->unique();
            $table->string('telephone', 30)->nullable();
            $table->string('email', 255)->nullable();
            $table->foreignId('user_id')->nullable()->unique()->constrained('users')->nullOnDelete();
            $table->boolean('actif')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['nom', 'prenoms']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agents');
        Schema::dropIfExists('salles');
        Schema::dropIfExists('annees_judiciaires');
    }
};
