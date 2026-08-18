<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use App\Models\Wallet;
use App\Services\WalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends ApiController
{
    public function register(Request $r, WalletService $wallets)
    {
        $v = $r->validate(['name' => 'required|string|max:100', 'email' => 'required|email|max:190|unique:users,email', 'password' => 'required|string|min:8|confirmed', 'install_id' => 'nullable|uuid', 'site_url' => 'nullable|url']);
        $user = DB::transaction(function () use ($v, $wallets) {
            $u = User::create(['name' => $v['name'], 'email' => strtolower($v['email']), 'password' => $v['password'], 'status' => 'active', 'role' => 'user', 'welcome_credit_granted_at' => now()]);
            Wallet::create(['user_id' => $u->id, 'balance_amount' => 0]);
            $wallets->credit($u, 5000, 'welcome_bonus', 'Welcome credit Rp5.000', 'welcome-'.$u->id);

            return $u;
        });

        return $this->ok(['token' => $user->createToken('wordpress-account')->plainTextToken, 'user' => $user->only(['id', 'name', 'email']), 'balance' => 5000], 'Registrasi berhasil dan welcome credit diberikan.');
    }

    public function login(Request $r)
    {
        $v = $r->validate(['email' => 'required|email', 'password' => 'required']);
        $u = User::where('email', strtolower($v['email']))->first();
        if (! $u || ! Hash::check($v['password'], $u->password)) {
            throw ValidationException::withMessages(['email' => 'Kredensial tidak valid.']);
        }if ($u->status !== 'active') {
            return $this->fail('user_suspended', 'Akun ditangguhkan.', 403);
        }

return $this->ok(['token' => $u->createToken('wordpress-account')->plainTextToken, 'user' => $u->only(['id', 'name', 'email'])]);
    }
}
