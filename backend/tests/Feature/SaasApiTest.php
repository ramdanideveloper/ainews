<?php

namespace Tests\Feature;

use App\Models\AiProvider;
use App\Models\UsageLog;
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

    public function test_successful_image_is_logged_and_debited_once(): void
    {
        $auth = $this->postJson('/api/auth/register', ['name' => 'Image Editor', 'email' => 'image@example.test', 'password' => 'password123', 'password_confirmation' => 'password123']);
        $site = $this->withToken($auth->json('data.token'))->postJson('/api/sites/connect', ['site_name' => 'Image Site', 'site_url' => 'https://image.example.test', 'install_id' => '018f5b2d-3b5a-7f41-8c2e-fedcbafedcba']);

        $this->withToken($site->json('data.site_token'))->withHeader('X-Site-URL', 'https://image.example.test')->postJson('/api/ai/generate-image', [
            'title' => 'Thumbnail editorial',
            'aspect_ratio' => '16:9',
            'use_as_thumbnail' => true,
        ])->assertOk()->assertJsonPath('data.charged_amount', 1500)->assertJsonPath('data.balance_after', 3500);

        $this->assertDatabaseHas('usage_logs', ['request_type' => 'image_generate', 'image_count' => 1, 'status' => 'success']);
        $this->assertSame(1, UsageLog::where('request_type', 'image_generate')->count());
    }

    public function test_article_word_target_must_be_between_two_hundred_and_nine_hundred(): void
    {
        $auth = $this->postJson('/api/auth/register', ['name' => 'Word Target Editor', 'email' => 'words@example.test', 'password' => 'password123', 'password_confirmation' => 'password123']);
        $site = $this->withToken($auth->json('data.token'))->postJson('/api/sites/connect', ['site_name' => 'Word Target Site', 'site_url' => 'https://words.example.test', 'install_id' => '018f5b2d-3b5a-7f41-8c2e-aabbccddeeff']);

        $this->withToken($site->json('data.site_token'))->withHeader('X-Site-URL', 'https://words.example.test')->postJson('/api/ai/generate-article', [
            'title' => 'Artikel dengan target kata',
            'payload' => ['length' => 100],
        ])->assertUnprocessable()->assertJsonValidationErrors('payload.length');
    }
}
