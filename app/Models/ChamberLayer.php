<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChamberLayer extends Model
{
    protected $fillable = [
        'chamber_id', 'layer_no', 'product_model_id', 'lot_no', 'lot_quantity',
        'rx_type', 'unit_weight_grams', 'weight_per_tray_kg', 'remarks',
        'work_order_id', 'authorized_pic_id',
    ];

    protected $casts = [
        'unit_weight_grams' => 'decimal:3',
        'weight_per_tray_kg' => 'decimal:4',
    ];

    protected $appends = ['is_filled'];

    public function chamber(): BelongsTo
    {
        return $this->belongsTo(Chamber::class);
    }

    public function productModel(): BelongsTo
    {
        return $this->belongsTo(ProductModel::class, 'product_model_id');
    }

    public function authorizedPic(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'authorized_pic_id');
    }

    public function getIsFilledAttribute(): bool
    {
        return ! is_null($this->product_model_id);
    }
}
