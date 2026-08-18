<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiProvider extends Model
{
    protected $guarded = [];

    protected $hidden = ['api_key'];

    protected function casts(): array
    {
        return ['api_key' => 'encrypted', 'supports_text' => 'boolean', 'supports_image' => 'boolean', 'price_input_per_1m' => 'decimal:4', 'price_output_per_1m' => 'decimal:4', 'price_image_per_generate' => 'decimal:2'];
    }
}
