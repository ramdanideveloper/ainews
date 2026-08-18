<?php

namespace App\Filament\Resources\UsageLogs\Pages;

use App\Filament\Resources\UsageLogs\UsageLogResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListUsageLogs extends ListRecords
{
    protected static string $resource = UsageLogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
