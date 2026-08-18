<?php

namespace App\Filament\Resources\AiProviders\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class AiProviderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')->required()->maxLength(100), Select::make('provider')->options(['gemini' => 'Gemini', 'openai' => 'OpenAI', 'deepseek' => 'DeepSeek', 'openrouter' => 'OpenRouter', 'mock' => 'Mock'])->required(), TextInput::make('model_id')->required(), TextInput::make('api_key')->password()->revealable()->required(fn (string $operation) => $operation === 'create')->dehydrated(fn ($state) => filled($state))->helperText('Encrypted at rest; leave blank when editing to keep the existing key.'), TextInput::make('base_url')->url(), Select::make('status')->options(['active' => 'Active', 'inactive' => 'Inactive'])->required(), TextInput::make('priority')->numeric()->default(100)->required(), TextInput::make('fallback_order')->numeric()->default(100)->required(), Toggle::make('supports_text')->default(true), Toggle::make('supports_image'), TextInput::make('daily_token_limit')->numeric(), TextInput::make('monthly_token_limit')->numeric(), TextInput::make('price_input_per_1m')->numeric()->prefix('Rp'), TextInput::make('price_output_per_1m')->numeric()->prefix('Rp'), TextInput::make('price_image_per_generate')->numeric()->prefix('Rp'),
            ]);
    }
}
