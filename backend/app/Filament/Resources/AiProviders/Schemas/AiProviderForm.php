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
                TextInput::make('name')->required()->maxLength(100), Select::make('provider')->options(['gemini' => 'Gemini', 'huggingface' => 'Hugging Face', 'openai' => 'OpenAI', 'deepseek' => 'DeepSeek', 'openrouter' => 'OpenRouter', 'mock' => 'Mock'])->required(), TextInput::make('model_id')->label('Model ID')->required()->helperText('Contoh gambar Hugging Face: black-forest-labs/FLUX.1-schnell atau stabilityai/stable-diffusion-3.5-large.'), TextInput::make('api_key')->password()->revealable()->required(fn (string $operation) => $operation === 'create')->dehydrated(fn ($state) => filled($state))->helperText('Hugging Face memerlukan token dengan izin Inference Providers. Token disimpan terenkripsi.'), TextInput::make('base_url')->label('Base URL')->url()->helperText('Opsional. Kosongkan untuk router Hugging Face; isi hanya jika memakai dedicated Inference Endpoint.'), Select::make('status')->options(['active' => 'Active', 'inactive' => 'Inactive'])->required(), TextInput::make('priority')->numeric()->default(100)->required(), TextInput::make('fallback_order')->numeric()->default(100)->required(), Toggle::make('supports_text')->label('Supports Article/Text')->default(true), Toggle::make('supports_image')->label('Supports Image'), TextInput::make('daily_token_limit')->numeric(), TextInput::make('monthly_token_limit')->numeric(), TextInput::make('price_input_per_1m')->label('Provider Input Price / 1M Tokens')->numeric()->prefix('Rp')->helperText('Biaya dasar provider dalam Rupiah.'), TextInput::make('price_output_per_1m')->label('Provider Output Price / 1M Tokens')->numeric()->prefix('Rp')->helperText('Biaya dasar provider dalam Rupiah.'), TextInput::make('price_image_per_generate')->label('Provider Price / Image')->numeric()->prefix('Rp')->helperText('Biaya dasar satu generate gambar.'),
            ]);
    }
}
