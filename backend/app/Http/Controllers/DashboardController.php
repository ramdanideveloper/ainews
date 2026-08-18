<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $r)
    {
        $u = $r->user();

        return view('dashboard', ['user' => $u, 'sites' => $u->connectedSites()->latest()->get(), 'usage' => $u->usageLogs()->latest()->limit(20)->get(), 'transactions' => $u->walletTransactions()->latest()->limit(20)->get(), 'balance' => (float) ($u->wallet()->value('balance_amount') ?? 0)]);
    }
}
