<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductModel extends Model
{
    // Table name spelled out because Eloquent would otherwise guess
    // "product_models" from a class also named ProductModel - harmless
    // here, but explicit beats implicit for a table this central.
    protected $table = 'product_models';

    protected $fillable = ['area_id', 'model_name', 'unit_weight_grams', 'checked_by_id', 'is_active'];

    protected $casts = [
        'unit_weight_grams' => 'decimal:3',
        'is_active' => 'boolean',
    ];

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    public function checkedBy(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'checked_by_id');
    }
}
