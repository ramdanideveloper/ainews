<?php

namespace App\Filament\Widgets;

use App\Models\ConnectedSite;
use App\Models\GuestTrial;
use App\Models\UsageLog;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class OverviewStats extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Total Users', User::count()), Stat::make('Active Connected Sites', ConnectedSite::where('status', 'active')->count()), Stat::make('Guest Uses', GuestTrial::sum('free_generate_used')), Stat::make('Revenue Today', 'Rp '.number_format(UsageLog::whereDate('created_at', today())->sum('charged_amount'), 0, ',', '.')), Stat::make('Revenue This Month', 'Rp '.number_format(UsageLog::whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])->sum('charged_amount'), 0, ',', '.')), Stat::make('Tokens Today', number_format(UsageLog::whereDate('created_at', today())->sum('total_tokens'))), Stat::make('Images This Month', UsageLog::where('request_type', 'image_generate')->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])->where('status', 'success')->count()), Stat::make('Estimated Profit', 'Rp '.number_format(UsageLog::whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])->sum('charged_amount') - UsageLog::whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])->sum('provider_cost_idr'), 0, ',', '.')),
        ];
    }
}
