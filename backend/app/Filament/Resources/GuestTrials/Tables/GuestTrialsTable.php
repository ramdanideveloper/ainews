<?php

namespace App\Filament\Resources\GuestTrials\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class GuestTrialsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('domain')->searchable(), TextColumn::make('install_id')->copyable()->limit(12), TextColumn::make('free_generate_used')->label('Used'), TextColumn::make('free_generate_total')->label('Total'), TextColumn::make('status')->badge(), TextColumn::make('last_used_at')->since(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
