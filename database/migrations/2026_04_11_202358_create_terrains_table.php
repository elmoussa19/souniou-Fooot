<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('terrains', function (Blueprint $table) {
            $table->id();

            $table->string('name');
            $table->string('type');

            $table->double('latitude');
            $table->double('longitude');

            $table->string('image1')->nullable();
            $table->string('image2')->nullable();
            $table->string('image3')->nullable();

            $table->string('adresse');
            $table->string('quartier');
            $table->string('ville');

            $table->string('type_surface');
            $table->integer('capacite')->nullable();

            // relation fournisseur
            $table->foreignId('fournisseur_id')
                ->constrained('fournisseurs')
                ->onDelete('cascade');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('terrains');
    }
};
