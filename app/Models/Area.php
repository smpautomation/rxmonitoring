<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Area extends Model
{
    protected $fillable = ['code', 'name', 'chamber_count', 'layer_count', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function ovens(): HasMany
    {
        return $this->hasMany(Oven::class);
    }

    public function productModels(): HasMany
    {
        return $this->hasMany(ProductModel::class);
    }

    public function chambers(): HasMany
    {
        return $this->hasMany(Chamber::class);
    }
}
