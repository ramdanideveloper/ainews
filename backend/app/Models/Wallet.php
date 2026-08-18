<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Wallet extends Model
{
    protected $fillable = ['user_id', 'balance_amount'];

    protected function casts(): array
    {
        return ['balance_amount' => 'decimal:2'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
