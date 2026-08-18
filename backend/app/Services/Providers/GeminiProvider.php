<?php

namespace App\Services\Providers;

use App\Contracts\AiProviderInterface;
use App\Models\AiProvider;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GeminiProvider implements AiProviderInterface
{
    public function text(AiProvider $p, array $messages, array $options = []): array
    {
        $url = $this->chatUrl($p->base_url);
        $model = preg_replace('#^models/#', '', trim($p->model_id));
        $r = Http::connectTimeout(8)->timeout(40)->withToken($p->api_key)->post($url, ['model' => $model, 'messages' => $messages, 'reasoning_effort' => $options['reasoning_effort'] ?? 'low', 'max_completion_tokens' => $options['max_completion_tokens'] ?? 6000, 'response_format' => ['type' => 'json_object']]);
        if (! $r->successful()) {
            $message = $r->json('error.message') ?: trim($r->body());
            throw new RuntimeException((string) ($message ?: "Gemini request failed with HTTP {$r->status()} for model {$model} at {$url}"));
        }$j = $r->json();

        return ['content' => $j['choices'][0]['message']['content'] ?? '', 'input_tokens' => (int) ($j['usage']['prompt_tokens'] ?? 0), 'output_tokens' => (int) ($j['usage']['completion_tokens'] ?? 0), 'raw' => $j];
    }

    public function image(AiProvider $p, array $payload): array
    {
        $base = preg_replace('#/openai(?:/.*)?$#', '', rtrim($p->base_url ?: 'https://generativelanguage.googleapis.com/v1beta', '/'));
        $model = preg_replace('#^models/#', '', trim($p->model_id));
        $url = $base.'/models/'.$model.':generateContent?key='.urlencode($p->api_key);
        $r = Http::connectTimeout(8)->timeout(80)->post($url, ['contents' => [['parts' => [['text' => $payload['prompt']]]]], 'generationConfig' => ['responseModalities' => ['TEXT', 'IMAGE'], 'responseFormat' => ['image' => ['aspectRatio' => $payload['aspect_ratio'] ?? '1:1']]]]);
        if (! $r->successful()) {
            throw new RuntimeException((string) ($r->json('error.message') ?: 'Gemini image request failed'));
        }$j = $r->json();
        $parts = $j['candidates'][0]['content']['parts'] ?? [];
        foreach ($parts as $part) {
            if (isset($part['inlineData']['data'])) {
                return ['image_base64' => $part['inlineData']['data'], 'mime_type' => $part['inlineData']['mimeType'] ?? 'image/png', 'raw' => $j];
            }
        }throw new RuntimeException('Gemini did not return image data.');
    }

    private function chatUrl(?string $configured): string
    {
        $base = rtrim($configured ?: 'https://generativelanguage.googleapis.com/v1beta/openai', '/');
        if (str_ends_with($base, '/chat/completions')) {
            return $base;
        }
        if (preg_match('#/v1beta$#', $base)) {
            return $base.'/openai/chat/completions';
        }

        return $base.'/chat/completions';
    }
}
