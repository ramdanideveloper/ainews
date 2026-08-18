<?php

namespace App\Filament\Resources\ConnectedSites\Pages;

use App\Filament\Resources\ConnectedSites\ConnectedSiteResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListConnectedSites extends ListRecords
{
    protected static string $resource = ConnectedSiteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
