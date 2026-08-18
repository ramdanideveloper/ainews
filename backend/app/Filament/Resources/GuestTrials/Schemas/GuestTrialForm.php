<?php

namespace App\Filament\Resources\GuestTrials\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class GuestTrialForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('install_id')->disabled(), TextInput::make('site_url')->disabled(), TextInput::make('domain')->disabled(), TextInput::make('free_generate_total')->numeric()->required(), TextInput::make('free_generate_used')->numeric()->required(), TextInput::make('free_image_total')->numeric()->required(), Select::make('status')->options(['active' => 'Active', 'blocked' => 'Blocked'])->required(),
            ]);
    }
}
