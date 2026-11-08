<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('decisions', function (Blueprint $table) {
            // Chambre qui rend la décision : sa composition (ordonnance) remplit le formulaire.
            $table->foreignId('chambre_id')->nullable()->after('modele_decision_id')->constrained('chambres')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('decisions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('chambre_id');
        });
    }
};
