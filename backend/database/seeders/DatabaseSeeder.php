<?php

namespace Database\Seeders;

use App\Models\AiProvider;
use App\Models\AppSetting;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::updateOrCreate(['email' => env('ADMIN_EMAIL', 'admin@ainews.local')], ['name' => env('ADMIN_NAME', 'AI News Admin'), 'password' => env('ADMIN_PASSWORD', 'change-me-before-production'), 'role' => 'admin', 'status' => 'active']);
        Wallet::firstOrCreate(['user_id' => $admin->id], ['balance_amount' => 0]);
        foreach (['text_selling_rate_per_token' => ['0.10', 'decimal'], 'minimum_text_request_fee' => ['100', 'decimal'], 'image_standard_fee' => ['1000', 'decimal'], 'image_with_thumbnail_seo_fee' => ['1500', 'decimal'], 'guest_free_generate_total' => ['10', 'integer'], 'guest_image_trial_enabled' => ['false', 'boolean']] as $key => $item) {
            AppSetting::updateOrCreate(['key' => $key], ['value' => $item[0], 'type' => $item[1]]);
        }AiProvider::firstOrCreate(['provider' => 'mock', 'model_id' => 'mock-news-v1'], ['name' => 'Mock Provider (Local Demo)', 'api_key' => 'not-used', 'status' => 'inactive', 'priority' => 999, 'fallback_order' => 999, 'supports_text' => true, 'supports_image' => true]);
    }
}
