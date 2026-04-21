<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Reservation extends Model
{
    use HasFactory;

    protected $table = 'reservations';

    protected $fillable = [
        'terrain_id',
        'user_id',
        'date',
        'heure_debut',
        'heure_fin',
        'statut',
    ];

    protected $casts = [
        'date' => 'date:Y-m-d',
    ];

    // ================= RELATIONS =================

    public function terrain()
    {
        return $this->belongsTo(Terrain::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function payment()
    {
        return $this->hasOne(Payment::class);
    }

    // ================= HELPERS =================

    /**
     * Vrai si cette réservation a un paiement en cours non expiré.
     * → Bloquer toute nouvelle tentative de paiement sur ce créneau.
     */
    public function hasPaiementEnCoursActif(): bool
    {
        if ($this->statut !== 'paiement_en_cours') {
            return false;
        }

        $payment = $this->payment;

        if (!$payment) {
            return false;
        }

        // Si le paiement est expiré, on ne bloque plus
        return !$payment->isExpiredAndPending();
    }
}
