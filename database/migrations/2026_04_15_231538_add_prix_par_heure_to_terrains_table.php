<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('terrains', function (Blueprint $table) {
            $table->decimal('prix_par_heure', 10, 2)->default(25000.00); // prix en FCFA par heure
        });
    }

    public function down(): void
    {
        Schema::table('terrains', function (Blueprint $table) {
            $table->dropColumn('prix_par_heure');
        });
    }
};
