<?php

namespace App\Filament\Resources\GuestTrials;

use App\Filament\Resources\GuestTrials\Pages\EditGuestTrial;
use App\Filament\Resources\GuestTrials\Pages\ListGuestTrials;
use App\Filament\Resources\GuestTrials\Schemas\GuestTrialForm;
use App\Filament\Resources\GuestTrials\Tables\GuestTrialsTable;
use App\Models\GuestTrial;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class GuestTrialResource extends Resource
{
    protected static ?string $model = GuestTrial::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return GuestTrialForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return GuestTrialsTable::configure($table);
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
            'index' => ListGuestTrials::route('/'),
            'edit' => EditGuestTrial::route('/{record}/edit'),
        ];
    }
}
