<?php

namespace App\Services;

use App\Models\ConnectedSite;
use App\Models\GuestTrial;
use App\Models\UsageLog;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class AiGatewayService
{
    public function __construct(private AiRouter $router, private BillingService $billing, private WalletService $wallets) {}

    public function text(string $type, array $payload, ConnectedSite|GuestTrial $actor, bool $guest = false): array
    {
        $messages = [['role' => 'system', 'content' => $this->systemPrompt($type)], ['role' => 'user', 'content' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]];
        try {
            $result = $this->router->text($type, $messages, [
                'reasoning_effort' => 'low',
                'max_completion_tokens' => $type === 'detect_news_type' ? 1200 : 6000,
            ]);
            $in = $result['input_tokens'] ?: $this->billing->estimateTokens(json_encode($messages));
            $out = $result['output_tokens'] ?: $this->billing->estimateTokens($result['content']);
            $bill = $this->billing->textCharge($in, $out, $result['provider_model']);
            $data = $this->decode($result['content']);

            return DB::transaction(function () use ($type, $actor, $guest, $result, $in, $out, $bill, $data) {
                $tx = null;
                $remaining = null;
                if ($guest) {
                    $locked = GuestTrial::query()->lockForUpdate()->findOrFail($actor->id);
                    if ($locked->status !== 'active' || $locked->free_generate_used >= $locked->free_generate_total) {
                        throw new RuntimeException('guest_trial_exhausted');
                    }$locked->increment('free_generate_used');
                    $locked->update(['last_used_at' => now()]);
                    $remaining = max(0, $locked->free_generate_total - $locked->free_generate_used);
                } else {
                    $tx = $this->wallets->debit($actor->user, $bill['charged_amount'], 'AI usage: '.$type, (string) str()->uuid());
                }UsageLog::create(['user_id' => $guest ? null : $actor->user_id, 'connected_site_id' => $guest ? null : $actor->id, 'install_id' => $actor->install_id, 'site_url' => $actor->site_url, 'request_type' => $type, 'provider' => $result['provider'], 'model' => $result['model'], 'input_tokens' => $in, 'output_tokens' => $out, 'total_tokens' => $in + $out, 'provider_cost_idr' => $bill['provider_cost_idr'], 'charged_amount' => $guest ? 0 : $bill['charged_amount'], 'free_trial_used' => $guest, 'wallet_transaction_id' => $tx?->id, 'status' => 'success']);
                $balance = $guest ? null : (float) $actor->user->wallet()->value('balance_amount');

                $usage = ['input_tokens' => $in, 'output_tokens' => $out, 'total_tokens' => $in + $out, 'charged_amount' => $guest ? 0 : $bill['charged_amount'], 'balance_after' => $balance, 'free_trial_remaining' => $remaining];

                return $type === 'detect_news_type' ? $this->formatDetection($data, $usage) : $this->formatText($data, $usage);
            });
        } catch (Throwable $e) {
            UsageLog::create(['user_id' => $guest ? null : $actor->user_id, 'connected_site_id' => $guest ? null : $actor->id, 'install_id' => $actor->install_id, 'site_url' => $actor->site_url, 'request_type' => $type, 'free_trial_used' => false, 'status' => 'failed', 'error_message' => substr($e->getMessage(), 0, 1000)]);
            throw $e;
        }
    }

    public function image(array $payload, ConnectedSite $site): array
    {
        $style = $payload['style'] ?? 'editorial photojournalism';
        $prompt = ! empty($payload['custom_prompt']) ? $payload['custom_prompt'] : trim(($payload['title'] ?? '').' '.($payload['content_summary'] ?? '').' Keyword: '.($payload['keyword'] ?? '').' Style: '.$style.'. No text, watermark, logo, gore, or identifiable minor.');
        try {
            $result = $this->router->image('image_generate', ['prompt' => $prompt, 'size' => $this->size($payload['aspect_ratio'] ?? '1:1'), 'aspect_ratio' => $payload['aspect_ratio'] ?? '1:1']);
            $bill = $this->billing->imageCharge((bool) ($payload['use_as_thumbnail'] ?? false), $result['provider_model']);

            return DB::transaction(function () use ($payload, $site, $prompt, $result, $bill) {
                $tx = $this->wallets->debit($site->user, $bill['charged_amount'], 'AI image generation', (string) str()->uuid());
                UsageLog::create(['user_id' => $site->user_id, 'connected_site_id' => $site->id, 'install_id' => $site->install_id, 'site_url' => $site->site_url, 'request_type' => 'image_generate', 'provider' => $result['provider'], 'model' => $result['model'], 'image_count' => 1, 'provider_cost_idr' => $bill['provider_cost_idr'], 'charged_amount' => $bill['charged_amount'], 'wallet_transaction_id' => $tx->id, 'status' => 'success']);

                return ['image_url' => $result['image_url'] ?? null, 'image_base64' => $result['image_base64'] ?? null, 'mime_type' => $result['mime_type'] ?? 'image/png', 'prompt_used' => $prompt, 'alt_text' => $payload['keyword'] ?? $payload['title'], 'caption' => $payload['title'], 'suggested_filename' => str($payload['title'])->slug().'.png', 'charged_amount' => $bill['charged_amount'], 'balance_after' => (float) $site->user->wallet()->value('balance_amount')];
            });
        } catch (Throwable $e) {
            UsageLog::create(['user_id' => $site->user_id, 'connected_site_id' => $site->id, 'install_id' => $site->install_id, 'site_url' => $site->site_url, 'request_type' => 'image_generate', 'status' => 'failed', 'error_message' => substr($e->getMessage(), 0, 1000)]);
            throw $e;
        }
    }

    private function decode(string $content): array
    {
        $content = trim(preg_replace('/^```(?:json)?\s*|\s*```$/i', '', $content));
        $data = json_decode($content, true);
        if (! is_array($data)) {
            preg_match('/\{.*\}/s', $content, $m);
            $data = json_decode($m[0] ?? '', true);
        }if (! is_array($data)) {
            throw new RuntimeException('Provider returned invalid JSON.');
        }

        return $data;
    }

    private function formatText(array $d, array $usage): array
    {
        return ['title' => $d['title'] ?? $d['main_title'] ?? '', 'alternative_titles' => (array) ($d['alternative_titles'] ?? []), 'lead' => $d['lead'] ?? '', 'content_html' => $d['content_html'] ?? $d['content'] ?? '', 'summary_points' => (array) ($d['summary_points'] ?? []), 'verification_notes' => (array) ($d['verification_notes'] ?? []), 'fact_checklist' => $d['fact_checklist'] ?? [], 'seo_title' => $d['seo_title'] ?? ($d['seo']['seo_title'] ?? ''), 'meta_description' => $d['meta_description'] ?? ($d['seo']['meta_description'] ?? ''), 'focus_keyword' => $d['focus_keyword'] ?? ($d['seo']['focus_keyword'] ?? ''), 'slug' => $d['slug'] ?? ($d['seo']['slug'] ?? ''), 'tags' => (array) ($d['tags'] ?? ($d['seo']['tags'] ?? [])), 'category_suggestion' => $d['category_suggestion'] ?? ($d['seo']['category_suggestion'] ?? ''), 'social_captions' => $d['social_captions'] ?? [], 'review_status' => $d['review_status'] ?? 'Needs Verification', 'usage' => $usage];
    }

    private function formatDetection(array $data, array $usage): array
    {
        return ['news_type' => $data['news_type'] ?? 'explainer', 'news_type_label' => $data['news_type_label'] ?? 'Explainer', 'subtype' => $data['subtype'] ?? 'Informasi / Analisis', 'confidence' => (int) ($data['confidence'] ?? 50), 'required_data' => (array) ($data['required_data'] ?? []), 'warnings' => (array) ($data['warnings'] ?? []), 'review_status' => $data['review_status'] ?? 'Needs Verification', 'usage' => $usage];
    }

    private function systemPrompt(string $type): string
    {
        if ($type === 'detect_news_type') {
            return 'Anda adalah editor berita Indonesia. Klasifikasikan judul tanpa mengarang fakta. Keluarkan JSON valid tanpa markdown dengan fields: news_type (incident/government/business/feature/event/advertorial/explainer), news_type_label, subtype, confidence (0-100), required_data (array), warnings (array), review_status. Berikan warning privasi, anak, korban, praduga tak bersalah, atau verifikasi aparat jika relevan.';
        }
        if ($type === 'generate_article') {
            return 'Anda adalah redaktur dan SEO content writer Indonesia. Tulis artikel hanya dari brief, fakta, sumber, outline, target pembaca, gaya, dan panjang pada input. Jangan mengarang statistik, kutipan, nama, waktu, atau sumber. Lead harus langsung menjawab topik. content_html berisi paragraf <p> dan heading <h2>/<h3> yang deskriptif; jangan mengulang judul, lead, nama domain, atau memakai heading generik seperti "fakta utama". Gunakan focus keyword secara alami di lead, satu heading, isi, SEO title, meta description, dan slug tanpa keyword stuffing. Keluarkan JSON valid tanpa markdown dengan fields: title, alternative_titles (array), lead, content_html, summary_points (array), verification_notes (array), fact_checklist (object: who, what, when, where, why, how, source_available, needs_verification), seo_title, meta_description, focus_keyword, slug, tags (array), category_suggestion, social_captions (object), review_status.';
        }

        return 'Anda adalah redaktur berita Indonesia. Request type: '.$type.'. Gunakan hanya fakta input dan tandai data kosong untuk verifikasi. Tulis lead ringkas yang langsung memuat fakta terpenting. content_html hanya berisi paragraf lanjutan dengan tag <p>, tanpa mengulang judul atau lead, tanpa heading generik seperti "fakta utama", tanpa nama domain, dan tanpa markdown. Kutipan langsung ditulis sebagai paragraf tersendiri menggunakan tanda kutip Indonesia. Keluarkan JSON valid dengan fields: title, alternative_titles (array), lead, content_html, summary_points (array), verification_notes (array), fact_checklist (object dengan key who, what, when, where, why, how, source_available, needs_verification), seo_title, meta_description, focus_keyword, slug, tags, category_suggestion, social_captions, review_status. Isi setiap unsur 5W+1H hanya dari fakta input; gunakan string kosong jika datanya tidak tersedia. Jangan mempublikasikan atau mengarang fakta.';
    }

    private function size(string $ratio): string
    {
        return match ($ratio) {
            '16:9' => '1536x1024','9:16' => '1024x1536',default => '1024x1024'
        };
    }
}
