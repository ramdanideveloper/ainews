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
            && $request['model'] === 'gemini-2.5-flash'
            && $request['reasoning_effort'] === 'low'
            && $request['max_completion_tokens'] === 6000
            && ! isset($request['temperature']));
    }

    public function test_it_uses_native_nano_banana_endpoint_and_aspect_ratio(): void
    {
        Http::fake(fn () => Http::response([
            'candidates' => [['content' => ['parts' => [['inlineData' => ['data' => 'aW1hZ2U=', 'mimeType' => 'image/png']]]]]],
        ]));

        $provider = new AiProvider([
            'base_url' => 'https://generativelanguage.googleapis.com/v1beta/openai',
            'model_id' => 'models/gemini-2.5-flash-image',
            'api_key' => 'test-key',
        ]);

        $result = (new GeminiProvider)->image($provider, ['prompt' => 'Editorial image', 'aspect_ratio' => '16:9']);

        $this->assertSame('aW1hZ2U=', $result['image_base64']);
        Http::assertSent(fn ($request) => str_starts_with($request->url(), 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash-image:generateContent')
            && data_get($request->data(), 'generationConfig.responseFormat.image.aspectRatio') === '16:9');
    }
}
