<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class UsageLog extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['free_trial_used' => 'boolean', 'provider_cost_idr' => 'decimal:4', 'charged_amount' => 'decimal:2', 'metadata' => 'array'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function connectedSite()
    {
        return $this->belongsTo(ConnectedSite::class);
    }
}
