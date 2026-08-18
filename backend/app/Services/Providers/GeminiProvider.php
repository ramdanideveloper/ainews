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
        $r = Http::timeout(60)->withToken($p->api_key)->post($url, ['model' => $model, 'messages' => $messages, 'temperature' => $options['temperature'] ?? 0.3, 'response_format' => ['type' => 'json_object']]);
        if (! $r->successful()) {
            $message = $r->json('error.message') ?: trim($r->body());
            throw new RuntimeException((string) ($message ?: "Gemini request failed with HTTP {$r->status()} for model {$model} at {$url}"));
        }$j = $r->json();

        return ['content' => $j['choices'][0]['message']['content'] ?? '', 'input_tokens' => (int) ($j['usage']['prompt_tokens'] ?? 0), 'output_tokens' => (int) ($j['usage']['completion_tokens'] ?? 0), 'raw' => $j];
    }

    public function image(AiProvider $p, array $payload): array
    {
        $url = rtrim($p->base_url ?: 'https://generativelanguage.googleapis.com/v1beta', '/').'/models/'.$p->model_id.':generateContent?key='.urlencode($p->api_key);
        $r = Http::timeout(120)->post($url, ['contents' => [['parts' => [['text' => $payload['prompt']]]]], 'generationConfig' => ['responseModalities' => ['TEXT', 'IMAGE']]]);
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
