<?php

namespace App\Filament\Resources\UsageLogs\Pages;

use App\Filament\Resources\UsageLogs\UsageLogResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditUsageLog extends EditRecord
{
    protected static string $resource = UsageLogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
