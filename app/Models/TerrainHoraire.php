<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TerrainHoraire extends Model
{
    use HasFactory;

    protected $table = 'terrain_horaires';

    protected $fillable = [
        'terrain_id',
        'jour',
        'heure_ouverture',
        'heure_fermeture',
        'is_closed',
    ];

    protected $casts = [
        'is_closed' => 'boolean',
    ];

    // ================= RELATIONS =================

    public function terrain()
    {
        return $this->belongsTo(Terrain::class);
    }
}
