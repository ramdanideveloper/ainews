<?php

namespace Tests\Unit;

use App\Models\AiProvider;
use App\Services\Providers\HuggingFaceProvider;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HuggingFaceProviderTest extends TestCase
{
    public function test_it_routes_a_widescreen_image_through_live_fal_mapping(): void
    {
        Http::fake(function ($request) {
            return match (true) {
                str_contains($request->url(), 'huggingface.co/api/models/') => Http::response(['inferenceProviderMapping' => ['fal-ai' => ['status' => 'live', 'providerId' => 'fal-ai/flux/schnell']]]),
                str_contains($request->url(), '/status') => Http::response(['status' => 'COMPLETED']),
                str_contains($request->url(), 'router.huggingface.co/fal-ai/fal-ai/flux/schnell/requests/test') => Http::response(['images' => [['url' => 'https://cdn.example.test/image.jpg']]]),
                str_contains($request->url(), 'router.huggingface.co/fal-ai/fal-ai/flux/schnell') => Http::response(['request_id' => 'test', 'status' => 'IN_QUEUE', 'response_url' => 'https://queue.fal.run/fal-ai/flux/schnell/requests/test']),
                default => Http::response('image-bytes', 200, ['Content-Type' => 'image/jpeg']),
            };
        });
        $provider = new AiProvider(['model_id' => 'black-forest-labs/FLUX.1-schnell', 'api_key' => 'hf_test']);
        $result = (new HuggingFaceProvider)->image($provider, ['prompt' => 'Editorial photo', 'aspect_ratio' => '16:9']);

        $this->assertSame(base64_encode('image-bytes'), $result['image_base64']);
        $this->assertSame('image/jpeg', $result['mime_type']);
        Http::assertSent(fn ($request) => str_contains($request->url(), 'router.huggingface.co/fal-ai/fal-ai/flux/schnell') && $request->method() === 'POST' && $request->hasHeader('Authorization', 'Bearer hf_test') && $request['image_size']['width'] === 1280 && $request['image_size']['height'] === 672);
    }

    public function test_it_uses_a_dedicated_endpoint_when_configured(): void
    {
        Http::fake(fn () => Http::response('png', 200, ['Content-Type' => 'image/png']));
        $provider = new AiProvider(['base_url' => 'https://example.endpoints.huggingface.cloud/', 'model_id' => 'ignored/model', 'api_key' => 'hf_test']);
        (new HuggingFaceProvider)->image($provider, ['prompt' => 'News image']);
        Http::assertSent(fn ($request) => $request->url() === 'https://example.endpoints.huggingface.cloud');
    }

    public function test_it_explains_when_a_model_has_no_live_fal_provider(): void
    {
        Http::fake(fn () => Http::response(['inferenceProviderMapping' => ['hf-inference' => ['status' => 'live']]]));
        $provider = new AiProvider(['model_id' => 'deprecated/model', 'api_key' => 'hf_test']);
        $this->expectExceptionMessage('tidak tersedia melalui Fal AI');
        (new HuggingFaceProvider)->image($provider, ['prompt' => 'News image']);
    }
}
