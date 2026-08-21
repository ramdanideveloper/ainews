<?php

namespace App\Services\Providers;

use App\Contracts\AiProviderInterface;
use App\Models\AiProvider;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
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
        [$width, $height] = $this->dimensions($payload['aspect_ratio'] ?? '1:1');
        $parameters = ['prompt' => $payload['prompt'], 'width' => $width, 'height' => $height, 'negative_prompt' => $payload['negative_prompt'] ?? 'text, watermark, logo, blurry, distorted, gore'];

        try {
            return filled($provider->base_url)
                ? $this->dedicatedEndpoint($provider, $parameters)
                : $this->falInferenceProvider($provider, $parameters);
        } catch (ConnectionException) {
            throw new RuntimeException('Tidak dapat terhubung ke Hugging Face. Periksa DNS atau firewall server.');
        }
    }

    private function falInferenceProvider(AiProvider $provider, array $parameters): array
    {
        $model = trim($provider->model_id, '/');
        $metadata = Http::connectTimeout(12)->timeout(30)->get('https://huggingface.co/api/models/'.$model, ['expand' => 'inferenceProviderMapping']);
        if (! $metadata->successful()) {
            throw new RuntimeException($this->safeError($this->responseError($metadata), $metadata->status()));
        }
        $mapping = $metadata->json('inferenceProviderMapping.fal-ai');
        if (! is_array($mapping) || ($mapping['status'] ?? null) !== 'live' || empty($mapping['providerId'])) {
            throw new RuntimeException('Model tidak tersedia melalui Fal AI. Pilih model text-to-image Hugging Face yang berstatus live pada provider fal-ai.');
        }

        $providerModel = trim($mapping['providerId'], '/');
        $parameters['image_size'] = ['width' => $parameters['width'], 'height' => $parameters['height']];
        unset($parameters['width'], $parameters['height']);
        $parameters['num_images'] = 1;
        $parameters['output_format'] = 'jpeg';
        $query = '?_subdomain=queue';
        $headers = ['Authorization' => 'Bearer '.$provider->api_key, 'Accept' => 'application/json'];
        $queued = Http::connectTimeout(12)->timeout(60)->withHeaders($headers)->post('https://router.huggingface.co/fal-ai/'.$providerModel.$query, $parameters);
        if (! $queued->successful()) {
            throw new RuntimeException($this->safeError($this->responseError($queued), $queued->status()));
        }

        $responsePath = parse_url((string) $queued->json('response_url'), PHP_URL_PATH);
        if (! is_string($responsePath) || $responsePath === '') {
            throw new RuntimeException('Hugging Face/Fal AI tidak mengembalikan URL antrean yang valid.');
        }
        $base = 'https://router.huggingface.co/fal-ai/'.ltrim($responsePath, '/');
        $status = (string) $queued->json('status');
        for ($attempt = 0; $status !== 'COMPLETED' && $attempt < 120; $attempt++) {
            usleep(500000);
            $poll = Http::connectTimeout(12)->timeout(30)->withHeaders($headers)->get($base.'/status'.$query);
            if (! $poll->successful()) {
                throw new RuntimeException($this->safeError($this->responseError($poll), $poll->status()));
            }
            $status = (string) $poll->json('status');
            if (in_array($status, ['FAILED', 'CANCELLED'], true)) {
                throw new RuntimeException('Hugging Face/Fal AI gagal memproses gambar.');
            }
        }
        if ($status !== 'COMPLETED') {
            throw new RuntimeException('Waktu pembuatan gambar Hugging Face habis. Silakan coba lagi.');
        }

        $result = Http::connectTimeout(12)->timeout(60)->withHeaders($headers)->get($base.$query);
        if (! $result->successful()) {
            throw new RuntimeException($this->safeError($this->responseError($result), $result->status()));
        }
        $imageUrl = $result->json('images.0.url');
        if (! is_string($imageUrl) || ! filter_var($imageUrl, FILTER_VALIDATE_URL)) {
            throw new RuntimeException('Hugging Face/Fal AI tidak mengembalikan URL gambar yang valid.');
        }

        return $this->downloadImage($imageUrl);
    }

    private function dedicatedEndpoint(AiProvider $provider, array $parameters): array
    {
        $response = Http::connectTimeout(12)->timeout(180)->retry([750, 1500], fn ($exception) => $exception instanceof ConnectionException, throw: false)->withToken($provider->api_key)->accept('image/*')->post(rtrim($provider->base_url, '/'), ['inputs' => $parameters['prompt'], 'parameters' => array_diff_key($parameters, ['prompt' => true])]);
        if (! $response->successful()) {
            throw new RuntimeException($this->safeError($this->responseError($response), $response->status()));
        }

        return $this->binaryImage($response);
    }

    private function downloadImage(string $url): array
    {
        $response = Http::connectTimeout(12)->timeout(90)->get($url);
        if (! $response->successful()) {
            throw new RuntimeException('Gagal mengunduh hasil gambar Hugging Face.');
        }

        return $this->binaryImage($response);
    }

    private function binaryImage(Response $response): array
    {
        $body = $response->body();
        $mime = strtolower(trim(explode(';', $response->header('Content-Type') ?: 'image/png')[0]));
        if ($body === '' || ! str_starts_with($mime, 'image/')) {
            throw new RuntimeException('Hugging Face tidak mengembalikan data gambar yang valid.');
        }

        return ['image_base64' => base64_encode($body), 'mime_type' => $mime];
    }

    private function dimensions(string $ratio): array
    {
        return match ($ratio) {
            // Generate above the final WordPress thumbnail size so it can be
            // resized cleanly to Rank Math's recommended 1200 x 630 pixels.
            '16:9' => [1280, 672], '9:16' => [576, 1024], default => [1024, 1024]
        };
    }

    private function responseError(Response $response): string
    {
        $message = $response->json('error') ?: $response->json('message');

        return is_string($message) ? $message : '';
    }

    private function safeError(string $message, int $status): string
    {
        $message = preg_replace('/(?:hf|api)_[A-Za-z0-9_-]+/', '[REDACTED]', trim($message));

        return $message !== '' ? 'Hugging Face: '.$message : 'Hugging Face request gagal dengan HTTP '.$status.'.';
    }
}
