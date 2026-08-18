<?php

namespace App\Filament\Resources\WalletTransactions\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class WalletTransactionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')->dateTime()->sortable(), TextColumn::make('user.email')->searchable(), TextColumn::make('type')->badge(), TextColumn::make('amount')->money('IDR')->sortable(), TextColumn::make('balance_after')->money('IDR'), TextColumn::make('description')->limit(50), TextColumn::make('reference_id')->toggleable(),
            ])
            ->filters([
                //
            ])
            ->recordActions([])->toolbarActions([]);
    }
}
