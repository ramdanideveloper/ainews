<?php

namespace App\Filament\Resources\WalletTransactions\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class WalletTransactionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('user_id')->relationship('user', 'email')->searchable()->preload()->required(), Select::make('type')->options(['topup' => 'Top Up', 'admin_adjustment' => 'Admin Adjustment'])->default('topup')->required(), TextInput::make('amount')->numeric()->prefix('Rp')->minValue(1)->required(), TextInput::make('description')->default('Manual top up by admin')->required(),
            ]);
    }
}
