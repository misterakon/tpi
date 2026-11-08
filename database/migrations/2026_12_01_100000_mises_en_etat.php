<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 | Mise en état (cahier AUD-07, AUD-08) : pour chaque affaire civile confiée à la mise en état, la date de réception du dossier, le juge chargé,
 | la date de première audience de mise en état, le numéro et la date de l'ordonnance de clôture ; la durée entre la première audience et la clôture
 | se calcule. À la clôture, l'affaire est versée à la chambre de jugement pour inscription au rôle (date de l'audience de jugement).
 | Tant qu'elle est « en cours », l'affaire ne figure pas sur les rôles de jugement et ne déclenche pas d'alerte de mise au rôle.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mises_en_etat', function (Blueprint $table) {
            $table->id();
            $table->foreignId('affaire_id')->constrained('affaires')->cascadeOnDelete();
            $table->date('date_reception');
            $table->foreignId('juge_id')->nullable()->constrained('agents')->nullOnDelete();
            $table->date('date_premiere_audience')->nullable();
            $table->string('numero_ordonnance_cloture', 60)->nullable();
            $table->date('date_cloture')->nullable();
            $table->foreignId('chambre_jugement_id')->nullable()->constrained('chambres')->nullOnDelete();
            $table->date('date_audience_jugement')->nullable();
            $table->string('statut', 20)->default('en_cours');   // en_cours, cloturee, retiree
            $table->text('observations')->nullable();
            $table->string('motif_retrait', 300)->nullable();
            $table->timestamp('versee_le')->nullable();
            $table->foreignId('cree_par')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('cloture_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['affaire_id', 'statut']);
            $table->index(['statut', 'date_cloture']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mises_en_etat');
    }
};
