<?php

namespace App\Filament\Resources\ConnectedSites\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ConnectedSiteForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('user_id')->relationship('user', 'email')->disabled(), TextInput::make('site_name')->required(), TextInput::make('site_url')->disabled(), TextInput::make('domain')->disabled(), TextInput::make('install_id')->disabled(), Select::make('status')->options(['active' => 'Active', 'suspended' => 'Suspended'])->required(), TextInput::make('token_last_four')->label('Token ending')->disabled(),
            ]);
    }
}
