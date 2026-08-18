<?php

namespace App\Filament\Resources\UsageLogs;

use App\Filament\Resources\UsageLogs\Pages\ListUsageLogs;
use App\Filament\Resources\UsageLogs\Schemas\UsageLogForm;
use App\Filament\Resources\UsageLogs\Tables\UsageLogsTable;
use App\Models\UsageLog;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class UsageLogResource extends Resource
{
    protected static ?string $model = UsageLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return UsageLogForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return UsageLogsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsageLogs::route('/'),
        ];
    }
}
