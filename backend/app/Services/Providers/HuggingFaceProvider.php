<?php

namespace App\Services\Providers;

use App\Contracts\AiProviderInterface;
use App\Models\AiProvider;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class HuggingFaceProvider implements AiProviderInterface
{
    public function text(AiProvider $provider, array $messages, array $options = []): array
    {
        throw new RuntimeException('Hugging Face provider ini dikonfigurasi khusus untuk pembuatan gambar.');
    }

    public function image(AiProvider $provider, array $payload): array
    {
        $url = $this->imageUrl($provider);
        [$width, $height] = $this->dimensions($payload['aspect_ratio'] ?? '1:1');

        try {
            $response = Http::connectTimeout(12)
                ->timeout(180)
                ->retry([750, 1500], fn ($exception) => $exception instanceof ConnectionException, throw: false)
                ->withToken($provider->api_key)
                ->accept('image/*')
                ->post($url, [
                    'inputs' => $payload['prompt'],
                    'parameters' => [
                        'width' => $width,
                        'height' => $height,
                        'negative_prompt' => $payload['negative_prompt'] ?? 'text, watermark, logo, blurry, distorted, gore',
                    ],
                ]);
        } catch (ConnectionException) {
            throw new RuntimeException('Tidak dapat terhubung ke Hugging Face. Periksa DNS atau firewall server.');
        }

        if (! $response->successful()) {
            $message = $response->json('error') ?: $response->json('message');
            throw new RuntimeException($this->safeError(is_string($message) ? $message : '', $response->status()));
        }

        $body = $response->body();
        $mime = strtolower(trim(explode(';', $response->header('Content-Type') ?: 'image/png')[0]));
        if ($body === '' || ! str_starts_with($mime, 'image/')) {
            throw new RuntimeException('Hugging Face tidak mengembalikan data gambar yang valid.');
        }

        return ['image_base64' => base64_encode($body), 'mime_type' => $mime];
    }

    private function imageUrl(AiProvider $provider): string
    {
        if (filled($provider->base_url)) {
            return rtrim($provider->base_url, '/');
        }

        return 'https://router.huggingface.co/hf-inference/models/'.trim($provider->model_id, '/');
    }

    private function dimensions(string $ratio): array
    {
        return match ($ratio) {
            '16:9' => [1024, 576],
            '9:16' => [576, 1024],
            default => [1024, 1024],
        };
    }

    private function safeError(string $message, int $status): string
    {
        $message = preg_replace('/(?:hf|api)_[A-Za-z0-9_-]+/', '[REDACTED]', trim($message));

        return $message !== ''
            ? 'Hugging Face: '.$message
            : 'Hugging Face request gagal dengan HTTP '.$status.'.';
    }
}
