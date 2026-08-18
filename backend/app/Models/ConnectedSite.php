<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConnectedSite extends Model
{
    protected $guarded = [];

    protected $hidden = ['token_hash'];

    protected function casts(): array
    {
        return ['last_seen_at' => 'datetime'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function usageLogs()
    {
        return $this->hasMany(UsageLog::class);
    }
}
