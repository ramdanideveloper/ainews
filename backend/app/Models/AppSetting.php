<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppSetting extends Model
{
    protected $guarded = [];

    public static function valueOf(string $key, mixed $default = null): mixed
    {
        $row = static::query()->where('key', $key)->first();
        if (! $row) {
            return $default;
        }

return match ($row->type) {
            'integer' => (int) $row->value,'decimal' => (float) $row->value,'boolean' => filter_var($row->value, FILTER_VALIDATE_BOOL),default => $row->value
        };
    }
}
