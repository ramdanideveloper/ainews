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
                TextColumn::make('created_at')->label('Tanggal')->dateTime()->sortable(), TextColumn::make('user.email')->label('User')->searchable(), TextColumn::make('type')->label('Jenis')->badge(), TextColumn::make('amount')->label('Perubahan')->money('IDR')->color(fn ($state) => (float) $state >= 0 ? 'success' : 'danger')->sortable(), TextColumn::make('balance_before')->label('Saldo Sebelum')->money('IDR')->toggleable(), TextColumn::make('balance_after')->label('Saldo Setelah')->money('IDR'), TextColumn::make('description')->label('Catatan')->limit(50), TextColumn::make('createdBy.email')->label('Oleh Admin')->placeholder('System')->toggleable(), TextColumn::make('reference_id')->label('Referensi')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([])->toolbarActions([]);
    }
}
