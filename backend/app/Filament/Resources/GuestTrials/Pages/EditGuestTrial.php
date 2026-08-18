<?php

namespace App\Filament\Resources\GuestTrials\Pages;

use App\Filament\Resources\GuestTrials\GuestTrialResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditGuestTrial extends EditRecord
{
    protected static string $resource = GuestTrialResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
