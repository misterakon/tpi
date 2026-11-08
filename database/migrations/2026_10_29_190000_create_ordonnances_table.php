<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /*
     | Archive des ordonnances signées par la Présidente (ordonnance de l'année, ordonnances modificatives en cours d'année).
     | Le PDF est conservé hors du dossier public ; son empreinte SHA-256 prouve qu'il n'a pas été altéré.
     */
    public function up(): void
    {
        Schema::create('ordonnances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('annee_judiciaire_id')->constrained('annees_judiciaires')->restrictOnDelete();
            $table->string('type', 20);                           // annuelle | modificative
            $table->string('numero', 60);                         // ex. 600/2026
            $table->string('objet', 255)->nullable();
            $table->date('date_signature');
            $table->date('date_effet')->nullable();
            $table->text('observations')->nullable();
            $table->string('chemin', 120);                        // nom aléatoire dans storage/app/private/ordonnances
            $table->string('nom_original', 200);
            $table->unsignedBigInteger('taille');
            $table->char('empreinte', 64)->unique();
            $table->foreignId('depose_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['annee_judiciaire_id', 'date_signature']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ordonnances');
    }
};
