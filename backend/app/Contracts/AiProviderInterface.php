<?php

namespace App\Contracts;

use App\Models\AiProvider;

interface AiProviderInterface
{
    public function text(AiProvider $provider, array $messages, array $options = []): array;

    public function image(AiProvider $provider, array $payload): array;
}
