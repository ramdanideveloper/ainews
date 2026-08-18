<?php

namespace App\Services\Providers;

use App\Contracts\AiProviderInterface;
use App\Models\AiProvider;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class OpenAiProvider implements AiProviderInterface
{
    public function text(AiProvider $p, array $messages, array $options = []): array
    {
        $url = rtrim($p->base_url ?: 'https://api.openai.com/v1', '/').'/chat/completions';
        $r = Http::timeout(60)->withToken($p->api_key)->post($url, ['model' => $p->model_id, 'messages' => $messages, 'temperature' => $options['temperature'] ?? 0.3, 'response_format' => ['type' => 'json_object']]);
        if (! $r->successful()) {
            throw new RuntimeException($this->error($r));
        }$j = $r->json();

        return ['content' => $j['choices'][0]['message']['content'] ?? '', 'input_tokens' => (int) ($j['usage']['prompt_tokens'] ?? 0), 'output_tokens' => (int) ($j['usage']['completion_tokens'] ?? 0), 'raw' => $j];
    }

    public function image(AiProvider $p, array $payload): array
    {
        $url = rtrim($p->base_url ?: 'https://api.openai.com/v1', '/').'/images/generations';
        $r = Http::timeout(120)->withToken($p->api_key)->post($url, ['model' => $p->model_id, 'prompt' => $payload['prompt'], 'size' => $payload['size'] ?? '1024x1024', 'response_format' => 'b64_json']);
        if (! $r->successful()) {
            throw new RuntimeException($this->error($r));
        }$j = $r->json();

        return ['image_base64' => $j['data'][0]['b64_json'] ?? null, 'image_url' => $j['data'][0]['url'] ?? null, 'raw' => $j];
    }

    private function error($r): string
    {
        return (string) ($r->json('error.message') ?: 'Provider request failed with HTTP '.$r->status());
    }
}
