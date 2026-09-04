<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Personnel extends Model
{
    protected $fillable = ['employee_id', 'name', 'role', 'is_active', 'last_scanned_at'];

    protected $casts = [
        'is_active' => 'boolean',
        'last_scanned_at' => 'datetime',
    ];

    public function isPic(): bool
    {
        return $this->role === 'pic';
    }
}
