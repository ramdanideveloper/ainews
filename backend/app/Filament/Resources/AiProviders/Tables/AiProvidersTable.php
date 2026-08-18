<?php

namespace App\Filament\Resources\AiProviders\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AiProvidersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(), TextColumn::make('provider')->badge(), TextColumn::make('model_id')->searchable(), TextColumn::make('status')->badge(), TextColumn::make('priority')->sortable(), IconColumn::make('supports_text')->boolean(), IconColumn::make('supports_image')->boolean(), TextColumn::make('daily_token_limit')->numeric(),
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
