<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('terrain_horaires', function (Blueprint $table) {
            $table->id();

            $table->foreignId('terrain_id')
                ->constrained()
                ->onDelete('cascade');

            $table->string('jour');
            // ex: lundi, mardi, mercredi...

            $table->time('heure_ouverture')->nullable();
            $table->time('heure_fermeture')->nullable();

            $table->boolean('is_closed')->default(false);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('terrain_horaires');
    }
};
