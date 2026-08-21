<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class WalletServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_add_and_deduct_balance_with_an_audit_trail(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create();
        $wallets = app(WalletService::class);

        $credit = $wallets->adminAdjustment($user, 25000, 'add', 'Manual top up', $admin->id);
        $debit = $wallets->adminAdjustment($user, 5000, 'deduct', 'Correction', $admin->id);

        $this->assertSame('admin_credit', $credit->type);
        $this->assertSame('admin_debit', $debit->type);
        $this->assertSame('-5000.00', $debit->amount);
        $this->assertSame('20000.00', $debit->balance_after);
        $this->assertSame($admin->id, $debit->created_by);
        $this->assertDatabaseCount('wallet_transactions', 2);
    }

    public function test_admin_cannot_deduct_more_than_the_available_balance(): void
    {
        $user = User::factory()->create();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Saldo tidak mencukupi.');
        app(WalletService::class)->adminAdjustment($user, 1, 'deduct', 'Invalid adjustment');
    }
}
