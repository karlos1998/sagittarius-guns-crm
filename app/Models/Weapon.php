<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Weapon extends Model
{
    protected $fillable = [
        'name',
        'description',
        'price',
        'photos',
    ];

    protected $casts = [
        'photos' => 'array',
        'price' => 'decimal:2',
    ];
}
