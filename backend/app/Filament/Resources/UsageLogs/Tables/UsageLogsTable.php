<?php

namespace App\Filament\Resources\UsageLogs\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class UsageLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')->dateTime()->sortable(), TextColumn::make('request_type')->badge(), TextColumn::make('user.email')->searchable(), TextColumn::make('site_url')->limit(30), TextColumn::make('provider'), TextColumn::make('model'), TextColumn::make('total_tokens')->numeric()->sortable(), TextColumn::make('image_count')->label('Images')->numeric()->sortable(), TextColumn::make('charged_amount')->money('IDR')->sortable(), TextColumn::make('status')->badge(), TextColumn::make('error_message')->limit(40)->toggleable(),
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
