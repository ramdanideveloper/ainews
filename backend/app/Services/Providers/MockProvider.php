<?php

namespace App\Services\Providers;

use App\Contracts\AiProviderInterface;
use App\Models\AiProvider;

class MockProvider implements AiProviderInterface
{
    public function text(AiProvider $p, array $messages, array $options = []): array
    {
        return ['content' => json_encode(['title' => 'Mock article', 'content_html' => '<p>Mock response.</p>', 'review_status' => 'Needs Verification']), 'input_tokens' => 100, 'output_tokens' => 100, 'raw' => []];
    }

    public function image(AiProvider $p, array $payload): array
    {
        return ['image_url' => 'https://placehold.co/1024x1024/png', 'raw' => []];
    }
}
