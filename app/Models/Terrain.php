<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Terrain extends Model
{
    use HasFactory;

    protected $table = 'terrains';

    protected $fillable = [
        'name',
        'type',
        'latitude',
        'longitude',
        'image1',
        'image2',
        'image3',
        'adresse',
        'quartier',
        'ville',
        'type_surface',
        'capacite',
        'fournisseur_id',
        'prix_par_heure',
    ];

    // relation avec fournisseur
    public function fournisseur()
    {
        return $this->belongsTo(Fournisseur::class);
    }

    public function horaires()
    {
        return $this->hasMany(TerrainHoraire::class);
    }

    public function reservations()
    {
        return $this->hasMany(Reservation::class);
    }

    public function horairesBloques()
    {
        return $this->hasMany(HoraireBloque::class);
    }

        // Utilisateurs qui ont ce terrain en favori
    public function usersFavoris()
    {
        return $this->belongsToMany(User::class, 'favoris')
                    ->withTimestamps();
    }
}
