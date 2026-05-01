<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('favoris', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained('users')
                ->onDelete('cascade');

            $table->foreignId('terrain_id')
                ->constrained('terrains')
                ->onDelete('cascade');

            // Empêcher les doublons : un user ne peut pas ajouter
            // le même terrain en favori deux fois
            $table->unique(['user_id', 'terrain_id']);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('favoris');
    }
};
