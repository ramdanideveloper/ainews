<?php

namespace App\Filament\Resources\ConnectedSites\Pages;

use App\Filament\Resources\ConnectedSites\ConnectedSiteResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditConnectedSite extends EditRecord
{
    protected static string $resource = ConnectedSiteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
