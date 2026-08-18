<?php

namespace App\Http\Controllers\Api;

use App\Models\GuestTrial;
use App\Services\AiGatewayService;
use Illuminate\Http\Request;
use Throwable;

class GuestController extends ApiController
{
    private function trial(Request $r): GuestTrial
    {
        $v = $r->validate(['install_id' => 'required|uuid', 'site_url' => 'required|url', 'plugin_version' => 'nullable|string|max:30']);
        $domain = strtolower(parse_url($v['site_url'], PHP_URL_HOST));

        return GuestTrial::firstOrCreate(['install_id' => $v['install_id']], ['site_url' => $v['site_url'], 'domain' => $domain, 'plugin_version' => $v['plugin_version'] ?? null, 'free_generate_total' => 10, 'free_generate_used' => 0, 'free_image_total' => 0, 'free_image_used' => 0, 'status' => 'active', 'first_seen_at' => now(), 'last_used_at' => now()]);
    }

    public function status(Request $r)
    {
        $g = $this->trial($r);

        return $this->ok(['status' => $g->status, 'free_generate_total' => $g->free_generate_total, 'free_generate_used' => $g->free_generate_used, 'free_generate_remaining' => max(0, $g->free_generate_total - $g->free_generate_used), 'registration_required' => $g->free_generate_used >= $g->free_generate_total]);
    }

    public function generate(Request $r, AiGatewayService $gateway)
    {
        $g = $this->trial($r);
        if ($g->status !== 'active') {
            return $this->fail('guest_blocked', 'Guest trial diblokir.', 403);
        }if ($g->free_generate_used >= $g->free_generate_total) {
            return $this->fail('guest_trial_exhausted', 'Trial 10x telah habis. Silakan register untuk mendapat welcome credit Rp5.000.', 402);
        }$payload = $r->validate(['request_type' => 'required|in:detect_news_type,generate_news,generate_article,rewrite,seo_refresh,social_caption', 'payload' => 'required|array']);
        try {
            return $this->ok($gateway->text($payload['request_type'], $payload['payload'], $g, true));
        } catch (Throwable $e) {
            return $this->fail('ai_request_failed', $e->getMessage(), 503);
        }
    }
}
