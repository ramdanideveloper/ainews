<?php

namespace App\Services;

use App\Contracts\AiProviderInterface;
use App\Models\AiProvider;
use App\Models\RoutingRule;
use App\Models\UsageLog;
use App\Services\Providers\GeminiProvider;
use App\Services\Providers\MockProvider;
use App\Services\Providers\OpenAiProvider;
use RuntimeException;
use Throwable;

class AiRouter
{
    public function text(string $requestType, array $messages, array $options = []): array
    {
        return $this->route($requestType, false, fn ($adapter, $model) => $adapter->text($model, $messages, $options));
    }

    public function image(string $requestType, array $payload): array
    {
        return $this->route($requestType, true, fn ($adapter, $model) => $adapter->image($model, $payload));
    }

    private function route(string $type, bool $image, callable $run): array
    {
        $rule = RoutingRule::query()->where('request_type', $type)->where('is_active', true)->first();
        $q = AiProvider::query()->where('status', 'active')->where($image ? 'supports_image' : 'supports_text', true);
        if ($rule?->preferred_provider_id) {
            $q->orderByRaw('id = ? desc', [$rule->preferred_provider_id]);
        }if ($rule?->prefer_lowest_cost) {
            $q->orderBy($image ? 'price_image_per_generate' : 'price_input_per_1m');
        }$models = $q->orderBy('priority')->orderBy('fallback_order')->get();
        $errors = [];
        foreach ($models as $model) {
            if ($this->overLimit($model)) {
                continue;
            }try {
                $result = $run($this->adapter($model->provider), $model);
                $result['provider'] = $model->provider;
                $result['model'] = $model->model_id;
                $result['provider_model'] = $model;

                return $result;
            } catch (Throwable $e) {
                $errors[] = $model->provider.': '.$e->getMessage();
                report($e);
            }
        }throw new RuntimeException('Semua provider AI sedang tidak tersedia. '.implode(' | ', $errors));
    }

    private function overLimit(AiProvider $p): bool
    {
        $daily = UsageLog::query()->where('provider', $p->provider)->where('model', $p->model_id)->whereDate('created_at', today())->sum('total_tokens');
        $monthly = UsageLog::query()->where('provider', $p->provider)->where('model', $p->model_id)->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])->sum('total_tokens');

        return ($p->daily_token_limit && $daily >= $p->daily_token_limit) || ($p->monthly_token_limit && $monthly >= $p->monthly_token_limit);
    }

    private function adapter(string $provider): AiProviderInterface
    {
        return match ($provider) {
            'gemini' => app(GeminiProvider::class),'openai','openrouter','deepseek' => app(OpenAiProvider::class),'mock' => app(MockProvider::class),default => throw new RuntimeException('Unsupported provider: '.$provider)
        };
    }
}
