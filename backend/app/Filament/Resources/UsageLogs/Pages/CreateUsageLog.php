<?php

namespace App\Filament\Resources\UsageLogs\Pages;

use App\Filament\Resources\UsageLogs\UsageLogResource;
use Filament\Resources\Pages\CreateRecord;

class CreateUsageLog extends CreateRecord
{
    protected static string $resource = UsageLogResource::class;
}
