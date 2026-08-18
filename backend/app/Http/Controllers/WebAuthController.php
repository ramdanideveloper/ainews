<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Wallet;
use App\Services\WalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class WebAuthController extends Controller
{
    public function loginForm()
    {
        return view('auth.login');
    }

    public function login(Request $r)
    {
        $v = $r->validate(['email' => 'required|email', 'password' => 'required']);
        if (! Auth::attempt($v, true)) {
            return back()->withErrors(['email' => 'Kredensial tidak valid.'])->onlyInput('email');
        }$r->session()->regenerate();
        if ($r->user()->status !== 'active') {
            Auth::logout();

            return back()->withErrors(['email' => 'Akun ditangguhkan.']);
        }

return redirect()->intended('/dashboard');
    }

    public function registerForm()
    {
        return view('auth.register');
    }

    public function register(Request $r, WalletService $wallets)
    {
        $v = $r->validate(['name' => 'required|max:100', 'email' => 'required|email|unique:users,email', 'password' => 'required|min:8|confirmed']);
        $u = DB::transaction(function () use ($v, $wallets) {
            $u = User::create(['name' => $v['name'], 'email' => strtolower($v['email']), 'password' => $v['password'], 'status' => 'active', 'role' => 'user', 'welcome_credit_granted_at' => now()]);
            Wallet::create(['user_id' => $u->id, 'balance_amount' => 0]);
            $wallets->credit($u, 5000, 'welcome_bonus', 'Welcome credit Rp5.000', 'welcome-'.$u->id);

            return $u;
        });
        Auth::login($u);

        return redirect('/dashboard');
    }

    public function logout(Request $r)
    {
        Auth::logout();
        $r->session()->invalidate();
        $r->session()->regenerateToken();

        return redirect('/login');
    }
}
