<?php

namespace Tests\Feature;

use App\Models\AiProvider;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SaasApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        AiProvider::where('provider', 'mock')->update(['status' => 'active']);
    }

    public function test_guest_has_ten_successful_generations(): void
    {
        $base = ['install_id' => '018f5b2d-3b5a-7f41-8c2e-123456789abc', 'site_url' => 'https://example.test', 'plugin_version' => '1.0', 'request_type' => 'generate_news', 'payload' => ['title' => 'Berita uji']];
        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/api/guest/generate', $base)->assertOk()->assertJsonPath('success', true);
        }
        $this->postJson('/api/guest/generate', $base)->assertStatus(402)->assertJsonPath('code', 'guest_trial_exhausted');
    }

    public function test_registration_grants_bonus_and_site_can_connect(): void
    {
        $response = $this->postJson('/api/auth/register', ['name' => 'Editor', 'email' => 'editor@example.test', 'password' => 'password123', 'password_confirmation' => 'password123'])->assertOk()->assertJsonPath('data.balance', 5000);
        $token = $response->json('data.token');
        $this->withToken($token)->postJson('/api/sites/connect', ['site_name' => 'News Site', 'site_url' => 'https://news.example.test', 'install_id' => '018f5b2d-3b5a-7f41-8c2e-abcdefabcdef'])->assertOk()->assertJsonStructure(['data' => ['site_token']]);
        $this->assertEquals(5000, (float) User::whereEmail('editor@example.test')->first()->wallet->balance_amount);
    }
}
