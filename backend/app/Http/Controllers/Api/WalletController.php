<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;

class WalletController extends ApiController
{
    public function balance(Request $r)
    {
        return $this->ok(['balance_amount' => (float) ($r->user()->wallet()->value('balance_amount') ?? 0), 'currency' => 'IDR']);
    }

    public function transactions(Request $r)
    {
        return $this->ok($r->user()->walletTransactions()->latest()->paginate(25));
    }
}
