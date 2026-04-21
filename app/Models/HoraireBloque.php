<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HoraireBloque extends Model
{
    use HasFactory;

    protected $table = 'horaire_bloques';

    protected $fillable = [
        'terrain_id',
        'date',
        'heure_debut',
        'heure_fin',
        'raison',
    ];

    protected $casts = [
        'date' => 'date:Y-m-d',
    ];

    // ================= RELATION =================

    public function terrain()
    {
        return $this->belongsTo(Terrain::class);
    }
}
