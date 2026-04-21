<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('reservation_id')
                ->constrained()
                ->onDelete('cascade');

            $table->foreignId('user_id')
                ->constrained()
                ->onDelete('cascade');

            // Montants
            $table->decimal('montant_total', 10, 2);
            $table->decimal('montant_paye', 10, 2);
            $table->decimal('reste', 10, 2)->nullable();

            // Type et méthode
            $table->enum('type', ['partiel', 'entier']);
            $table->enum('methode', ['wave', 'orange_money', 'cash', 'card']);

            // Statut — rembourse ajouté pour les doublons de créneau
            $table->enum('statut', ['en_attente', 'valide', 'echoue', 'rembourse'])
                ->default('en_attente');

            // ID transaction PayTech — unique pour bloquer les doublons d'IPN
            $table->string('transaction_id')->nullable()->unique();

            // Token PayTech retourné lors de l'initiation du paiement
            $table->string('paytech_token')->nullable()->unique();

            // Timestamp d'initiation — pour détecter les paiements expirés (15 min)
            $table->timestamp('paiement_initie_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
