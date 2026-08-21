<?php

namespace Tests\Unit;

use App\Models\AiProvider;
use App\Services\Providers\HuggingFaceProvider;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HuggingFaceProviderTest extends TestCase
{
    public function test_it_generates_a_widescreen_image_through_hugging_face_router(): void
    {
        Http::fake(fn () => Http::response('image-bytes', 200, ['Content-Type' => 'image/jpeg']));
        $provider = new AiProvider([
            'model_id' => 'black-forest-labs/FLUX.1-schnell',
            'api_key' => 'hf_test',
        ]);

        $result = (new HuggingFaceProvider)->image($provider, [
            'prompt' => 'Editorial photo',
            'aspect_ratio' => '16:9',
        ]);

        $this->assertSame(base64_encode('image-bytes'), $result['image_base64']);
        $this->assertSame('image/jpeg', $result['mime_type']);
        Http::assertSent(fn ($request) => $request->url() === 'https://router.huggingface.co/hf-inference/models/black-forest-labs/FLUX.1-schnell'
            && $request->hasHeader('Authorization', 'Bearer hf_test')
            && $request['parameters']['width'] === 1024
            && $request['parameters']['height'] === 576);
    }

    public function test_it_uses_a_dedicated_endpoint_when_configured(): void
    {
        Http::fake(fn () => Http::response('png', 200, ['Content-Type' => 'image/png']));
        $provider = new AiProvider([
            'base_url' => 'https://example.endpoints.huggingface.cloud/',
            'model_id' => 'ignored/model',
            'api_key' => 'hf_test',
        ]);

        (new HuggingFaceProvider)->image($provider, ['prompt' => 'News image']);

        Http::assertSent(fn ($request) => $request->url() === 'https://example.endpoints.huggingface.cloud');
    }
}
