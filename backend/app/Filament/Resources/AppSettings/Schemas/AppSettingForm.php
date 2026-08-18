<?php

namespace App\Filament\Resources\AppSettings\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class AppSettingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('key')->required()->unique(ignoreRecord: true), TextInput::make('value')->required(), Select::make('type')->options(['string' => 'String', 'integer' => 'Integer', 'decimal' => 'Decimal', 'boolean' => 'Boolean'])->required(),
            ]);
    }
}
