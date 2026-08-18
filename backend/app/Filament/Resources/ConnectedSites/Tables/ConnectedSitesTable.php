<?php

namespace App\Filament\Resources\ConnectedSites\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ConnectedSitesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('site_name')->searchable(), TextColumn::make('domain')->searchable(), TextColumn::make('user.email')->searchable(), TextColumn::make('status')->badge(), TextColumn::make('token_last_four')->label('Token'), TextColumn::make('last_seen_at')->since(),
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
