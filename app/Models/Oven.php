<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Oven extends Model
{
    protected $fillable = ['area_id', 'oven_no', 'capacity_kg', 'peak_temp_target_c', 'is_active'];

    protected $casts = [
        'capacity_kg' => 'decimal:2',
        'peak_temp_target_c' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }
}
