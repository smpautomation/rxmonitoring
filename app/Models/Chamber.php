<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Chamber extends Model
{
    protected $fillable = [
        'area_id', 'chamber_number', 'shift_date', 'status',
        'authorized_by_id',
        'oven_id', 'oven_capacity_kg', 'peak_temp_target_c',
        'total_weight_kg', 'weight_status',
        'start_temperature_c', 'loaded_by_id', 'start_time',
        'peak_checked_by_id', 'peak_temp_time',
        'stop_temperature_c', 'unloaded_by_id', 'stop_time',
        'cooling_start_time', 'cooling_end_time',
        'closed_by_id', 'closed_time',
    ];

    protected $casts = [
        'shift_date' => 'date',
        'oven_capacity_kg' => 'decimal:2',
        'peak_temp_target_c' => 'decimal:2',
        'total_weight_kg' => 'decimal:4',
        'start_temperature_c' => 'decimal:2',
        'stop_temperature_c' => 'decimal:2',
        'start_time' => 'datetime',
        'peak_temp_time' => 'datetime',
        'stop_time' => 'datetime',
        'cooling_start_time' => 'datetime',
        'cooling_end_time' => 'datetime',
        'closed_time' => 'datetime',
    ];

    protected $appends = ['chamber_label', 'current_step'];

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    public function oven(): BelongsTo
    {
        return $this->belongsTo(Oven::class);
    }

    public function layers(): HasMany
    {
        return $this->hasMany(ChamberLayer::class)->orderBy('layer_no');
    }

    public function authorizedBy(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'authorized_by_id');
    }

    public function loadedBy(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'loaded_by_id');
    }

    public function peakCheckedBy(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'peak_checked_by_id');
    }

    public function unloadedBy(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'unloaded_by_id');
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'closed_by_id');
    }

    public function getChamberLabelAttribute(): string
    {
        return 'Chamber '.str_pad((string) $this->chamber_number, 2, '0', STR_PAD_LEFT);
    }

    public function getCurrentStepAttribute(): string
    {
        if (! $this->oven_id) return 'oven_setup';
        if (! $this->start_time) return 'before_rx';
        if (! $this->peak_temp_time) return 'peak_temp';
        if (! $this->stop_time) return 'after_rx';
        if (! $this->cooling_end_time) return 'cooling';
        if ($this->status !== 'closed') return 'confirmation';

        return 'closed';
    }

    public function recalculateWeight(): void
    {
        $total = $this->layers()->sum('weight_per_tray_kg');

        $status = 'unset';
        if ($this->oven_capacity_kg !== null && (float) $this->oven_capacity_kg > 0) {
            $status = $total >= (float) $this->oven_capacity_kg ? 'overweight' : 'ok';
        }

        $this->update([
            'total_weight_kg' => $total,
            'weight_status' => $status,
        ]);
    }
}
