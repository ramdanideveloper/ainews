<?php

namespace App\Services;

use App\Models\ConnectedSite;
use Illuminate\Support\Str;

class SiteTokenService
{
    public function rotate(ConnectedSite $site): string
    {
        $plain = 'ains_'.Str::random(48);
        $site->update(['token_hash' => hash('sha256', $plain), 'token_last_four' => substr($plain, -4)]);

        return $plain;
    }

    public function find(string $plain): ?ConnectedSite
    {
        return ConnectedSite::query()->where('token_hash', hash('sha256', $plain))->first();
    }
}
