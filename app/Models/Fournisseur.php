<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;


class Fournisseur extends Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens;

    protected $table = 'fournisseurs'; // important si table différente

    protected $fillable = [
        'name',
        'email',
        'phone',
        'image',
        'is_active',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function terrains()
{
    return $this->hasMany(Terrain::class);
}
}
