<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dossier_pieces', function (Blueprint $table) {
            $table->id();
            $table->foreignId('affaire_id')->constrained('affaires')->cascadeOnDelete();
            $table->string('type', 30);                                  // assignation, conclusions, jugement… (liste du modèle PieceDossier)
            $table->string('titre', 200);
            $table->date('date_piece')->nullable();                      // date de la pièce elle-même
            $table->string('chemin', 80);                                // nom du fichier sur le disque privé : jamais celui de l'utilisateur
            $table->string('nom_original', 200);
            $table->string('mime', 60);
            $table->unsignedBigInteger('taille');
            $table->string('empreinte', 64);                             // SHA-256 : détecte une pièce déjà déposée
            $table->foreignId('depose_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('retiree_le')->nullable();                 // une pièce se retire (motif tracé), le fichier est conservé
            $table->foreignId('retiree_par')->nullable()->constrained('users')->nullOnDelete();
            $table->string('motif_retrait', 300)->nullable();
            $table->timestamps();
            $table->index(['affaire_id', 'retiree_le']);
        });

        Schema::create('dossier_consultations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('piece_id')->constrained('dossier_pieces')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 12);                                // vue | telechargee
            $table->timestamp('created_at')->useCurrent();
            $table->index(['piece_id', 'created_at']);
        });

        Schema::table('affaires', function (Blueprint $table) {
            $table->string('localisation_dossier', 200)->nullable()->after('articles');   // où se trouve le dossier papier (armoire, carton…)
        });
    }

    public function down(): void
    {
        Schema::table('affaires', function (Blueprint $table) {
            $table->dropColumn('localisation_dossier');
        });
        Schema::dropIfExists('dossier_consultations');
        Schema::dropIfExists('dossier_pieces');
    }
};
