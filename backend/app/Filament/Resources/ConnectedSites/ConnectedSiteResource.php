<?php

namespace App\Filament\Resources\ConnectedSites;

use App\Filament\Resources\ConnectedSites\Pages\EditConnectedSite;
use App\Filament\Resources\ConnectedSites\Pages\ListConnectedSites;
use App\Filament\Resources\ConnectedSites\Schemas\ConnectedSiteForm;
use App\Filament\Resources\ConnectedSites\Tables\ConnectedSitesTable;
use App\Models\ConnectedSite;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ConnectedSiteResource extends Resource
{
    protected static ?string $model = ConnectedSite::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return ConnectedSiteForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ConnectedSitesTable::configure($table);
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
            'index' => ListConnectedSites::route('/'),
            'edit' => EditConnectedSite::route('/{record}/edit'),
        ];
    }
}
