<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Product extends Model
{
    /** @use HasFactory<\Database\Factories\ProductFactory> */
    use HasFactory;

    public function stocks(): HasMany
    {
        return $this->hasMany(Stock::class);
    }

    protected $hidden = [
        'created_at',
        'updated_at',
    ];
}
