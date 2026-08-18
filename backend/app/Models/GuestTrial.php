<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GuestTrial extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['first_seen_at' => 'datetime', 'last_used_at' => 'datetime'];
    }
}
