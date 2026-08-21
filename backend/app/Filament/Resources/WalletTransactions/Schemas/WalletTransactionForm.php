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
                Select::make('user_id')->label('User')->relationship('user', 'email')->searchable()->preload()->required(),
                Select::make('operation')->label('Perubahan Saldo')->options(['add' => 'Tambah Saldo', 'deduct' => 'Potong Saldo'])->default('add')->required(),
                TextInput::make('amount')->label('Nominal')->numeric()->prefix('Rp')->minValue(1)->required(),
                TextInput::make('description')->label('Catatan')->default('Penyesuaian saldo oleh admin')->maxLength(255)->required(),
            ]);
    }
}
