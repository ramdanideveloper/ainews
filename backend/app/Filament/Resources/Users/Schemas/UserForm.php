<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')->required(), TextInput::make('email')->email()->required()->unique(ignoreRecord: true), TextInput::make('password')->password()->revealable()->required(fn (string $operation) => $operation === 'create')->dehydrated(fn ($state) => filled($state)), Select::make('status')->options(['active' => 'Active', 'suspended' => 'Suspended'])->required(), Select::make('role')->options(['user' => 'User', 'admin' => 'Admin'])->required(),
            ]);
    }
}
