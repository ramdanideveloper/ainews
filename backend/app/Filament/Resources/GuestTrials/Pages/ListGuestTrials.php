<?php

namespace App\Filament\Resources\GuestTrials\Pages;

use App\Filament\Resources\GuestTrials\GuestTrialResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListGuestTrials extends ListRecords
{
    protected static string $resource = GuestTrialResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
