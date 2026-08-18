<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RoutingRule extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['prefer_lowest_cost' => 'boolean', 'is_active' => 'boolean', 'config' => 'array'];
    }

    public function preferredProvider()
    {
        return $this->belongsTo(AiProvider::class, 'preferred_provider_id');
    }
}
