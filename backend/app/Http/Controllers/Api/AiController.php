<?php

namespace App\Http\Controllers\Api;

use App\Services\AiGatewayService;
use Illuminate\Http\Request;
use Throwable;

class AiController extends ApiController
{
    public function detect(Request $r, AiGatewayService $g)
    {
        return $this->text($r, $g, 'detect_news_type');
    }

    public function generateNews(Request $r, AiGatewayService $g)
    {
        return $this->text($r, $g, 'generate_news');
    }

    public function generateArticle(Request $r, AiGatewayService $g)
    {
        return $this->text($r, $g, 'generate_article');
    }

    public function rewrite(Request $r, AiGatewayService $g)
    {
        return $this->text($r, $g, 'rewrite');
    }

    public function text(Request $r, AiGatewayService $gateway, string $type)
    {
        $payload = $r->validate(['title' => 'nullable|string|max:500', 'payload' => 'nullable|array', 'payload.length' => 'nullable|integer|min:200|max:900', 'payload.structure' => 'nullable|in:standard,listicle,tutorial,faq', 'payload.point_count' => 'nullable|integer|min:2|max:20', 'content' => 'nullable|string|max:100000', 'editorial_data' => 'nullable|array']);
        $site = $r->attributes->get('connected_site');
        try {
            return $this->ok($gateway->text($type, $payload, $site));
        } catch (Throwable $e) {
            $code = $e->getMessage() === 'Saldo tidak mencukupi.' ? 'insufficient_balance' : 'ai_request_failed';

            return $this->fail($code, $e->getMessage(), $code === 'insufficient_balance' ? 402 : 503);
        }
    }

    public function image(Request $r, AiGatewayService $gateway)
    {
        $payload = $r->validate(['title' => 'required|string|max:500', 'content_summary' => 'nullable|string|max:5000', 'keyword' => 'nullable|string|max:200', 'style' => 'nullable|string|max:100', 'aspect_ratio' => 'nullable|in:1:1,16:9,9:16', 'custom_prompt' => 'nullable|string|max:5000', 'use_as_thumbnail' => 'boolean']);
        try {
            return $this->ok($gateway->image($payload, $r->attributes->get('connected_site')));
        } catch (Throwable $e) {
            $code = $e->getMessage() === 'Saldo tidak mencukupi.' ? 'insufficient_balance' : 'image_generation_failed';

            return $this->fail($code, $e->getMessage(), $code === 'insufficient_balance' ? 402 : 503);
        }
    }
}
