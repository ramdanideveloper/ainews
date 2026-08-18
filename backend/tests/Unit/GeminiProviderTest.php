<?php

namespace Tests\Unit;

use App\Models\AiProvider;
use App\Services\Providers\GeminiProvider;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GeminiProviderTest extends TestCase
{
    public function test_it_normalizes_gemini_base_url_and_model_name(): void
    {
        Http::fake([
            'https://generativelanguage.googleapis.com/v1beta/openai/chat/completions' => Http::response([
                'choices' => [['message' => ['content' => '{"ok":true}']]],
                'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 5],
            ]),
        ]);

        $provider = new AiProvider([
            'base_url' => 'https://generativelanguage.googleapis.com/v1beta',
            'model_id' => 'models/gemini-2.5-flash',
            'api_key' => 'test-key',
        ]);

        (new GeminiProvider)->text($provider, [['role' => 'user', 'content' => 'Test']]);

        Http::assertSent(fn ($request) => $request->url() === 'https://generativelanguage.googleapis.com/v1beta/openai/chat/completions'
            && $request['model'] === 'gemini-2.5-flash');
    }
}
