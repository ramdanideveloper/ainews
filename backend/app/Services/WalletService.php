<?php

namespace App\Services;

use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class WalletService
{
    public function credit(User $u, float $amount, string $type, string $description, ?string $ref = null, ?int $by = null): WalletTransaction
    {
        return $this->change($u, abs($amount), $type, $description, $ref, $by);
    }

    public function debit(User $u, float $amount, string $description, string $ref): WalletTransaction
    {
        return $this->change($u, -abs($amount), 'usage_debit', $description, $ref);
    }

    public function adminAdjustment(User $user, float $amount, string $operation, string $description, ?int $adminId = null): WalletTransaction
    {
        $delta = $operation === 'deduct' ? -abs($amount) : abs($amount);
        $type = $operation === 'deduct' ? 'admin_debit' : 'admin_credit';

        return $this->change($user, $delta, $type, $description, 'admin-'.str()->uuid(), $adminId);
    }

    public function refund(WalletTransaction $debit, string $description = 'AI request refund'): WalletTransaction
    {
        if ($debit->type !== 'usage_debit') {
            throw new RuntimeException('Only usage debits can be refunded.');
        }

        return $this->credit($debit->user, abs((float) $debit->amount), 'refund', $description, 'refund-'.$debit->id);
    }

    private function change(User $u, float $delta, string $type, string $description, ?string $ref, ?int $by = null): WalletTransaction
    {
        return DB::transaction(function () use ($u, $delta, $type, $description, $ref, $by) {
            $wallet = Wallet::query()->where('user_id', $u->id)->lockForUpdate()->firstOrCreate(['user_id' => $u->id], ['balance_amount' => 0]);
            $before = (float) $wallet->balance_amount;
            $after = $before + $delta;
            if ($after < 0) {
                throw new RuntimeException('Saldo tidak mencukupi.');
            }$wallet->update(['balance_amount' => $after]);

            return WalletTransaction::create(['user_id' => $u->id, 'type' => $type, 'amount' => $delta, 'balance_before' => $before, 'balance_after' => $after, 'description' => $description, 'reference_id' => $ref, 'created_by' => $by]);
        });
    }
}
