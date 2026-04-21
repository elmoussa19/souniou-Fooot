<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Payment extends Model
{
    use HasFactory;

    protected $table = 'payments';

    protected $fillable = [
        'reservation_id',
        'user_id',
        'montant_total',
        'montant_paye',
        'reste',
        'type',
        'methode',
        'statut',
        'transaction_id',
        'paytech_token',
        'paiement_initie_at',
    ];

    protected $casts = [
        'montant_total'      => 'decimal:2',
        'montant_paye'       => 'decimal:2',
        'reste'              => 'decimal:2',
        'paiement_initie_at' => 'datetime',
    ];

    // ================= RELATIONS =================

    public function reservation()
    {
        return $this->belongsTo(Reservation::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // ================= HELPERS =================

    public function isPaid(): bool
    {
        return $this->statut === 'valide';
    }

    public function isPartial(): bool
    {
        return $this->type === 'partiel';
    }

    /**
     * Vrai si le paiement dépasse 15 minutes sans confirmation.
     */
    public function isExpired(): bool
    {
        if (!$this->paiement_initie_at) {
            return false;
        }
        return $this->paiement_initie_at->diffInMinutes(Carbon::now()) >= 15;
    }

    /**
     * Vrai si le paiement est en attente ET expiré.
     * → On peut libérer le créneau.
     */
    public function isExpiredAndPending(): bool
    {
        return $this->statut === 'en_attente' && $this->isExpired();
    }
}
