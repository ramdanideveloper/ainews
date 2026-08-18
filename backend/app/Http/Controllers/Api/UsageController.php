<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;

class UsageController extends ApiController
{
    public function history(Request $r)
    {
        return $this->ok($r->user()->usageLogs()->latest()->paginate(25));
    }
}
