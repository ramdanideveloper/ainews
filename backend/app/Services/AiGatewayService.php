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
            $data = $this->decode($result['content']);
            $similarityCorrected = false;
            $similarity = $type === 'generate_news' ? $this->sourceSimilarity($data, $payload) : null;
            if ($similarity && $similarity['percent'] > 25) {
                try {
                    $rewriteMessages = $this->originalityCorrectionMessages($payload, $data, $similarity);
                    $rewriteResult = $this->router->text($type, $rewriteMessages, ['reasoning_effort' => 'low', 'max_completion_tokens' => 6000]);
                    $in += $rewriteResult['input_tokens'] ?: $this->billing->estimateTokens(json_encode($rewriteMessages));
                    $out += $rewriteResult['output_tokens'] ?: $this->billing->estimateTokens($rewriteResult['content']);
                    $rewriteData = $this->decode($rewriteResult['content']);
                    $rewriteSimilarity = $this->sourceSimilarity($rewriteData, $payload);
                    if ($rewriteSimilarity && $rewriteSimilarity['percent'] < $similarity['percent']) {
                        $data = $rewriteData;
                        $similarity = $rewriteSimilarity;
                    }
                    $similarityCorrected = true;
                } catch (Throwable $similarityError) {
                    report($similarityError);
                }
            }
            if ($similarity) {
                $similarity['ai_rewrite_performed'] = $similarityCorrected;
                $data['similarity'] = $similarity;
                $source = (array) ($payload['source_analysis'] ?? []);
                $data['source_attribution'] = array_intersect_key($source, array_flip(['source_url', 'source_domain', 'source_media', 'source_title', 'source_author', 'source_published_at', 'attribution']));
                $data['review_status'] = 'Needs Verification';
                if ($similarity['percent'] > 25) {
                    $data['verification_notes'] = array_values(array_unique(array_merge((array) ($data['verification_notes'] ?? []), ['Kemiripan frasa dengan sumber masih tinggi. Tulis ulang dan bandingkan manual sebelum publish.'])));
                }
            }
            $structure = $type === 'generate_article' ? $this->articleStructure($payload) : 'standard';
            $pointTarget = $structure !== 'standard' ? $this->articlePointTarget($payload) : null;
            $pointWords = $pointTarget ? $this->articlePointWordTarget($payload) : null;
            $actualPoints = $pointTarget ? $this->articleStructureCount($data, $structure) : null;
            $structureCorrected = false;
            if ($pointTarget && $actualPoints !== $pointTarget) {
                try {
                    $correctionMessages = $this->articleStructureCorrectionMessages($payload, $data, $structure, $pointTarget, $actualPoints);
                    $correctedResult = $this->router->text($type, $correctionMessages, ['reasoning_effort' => 'low', 'max_completion_tokens' => 6000]);
                    $in += $correctedResult['input_tokens'] ?: $this->billing->estimateTokens(json_encode($correctionMessages));
                    $out += $correctedResult['output_tokens'] ?: $this->billing->estimateTokens($correctedResult['content']);
                    $correctedData = $this->decode($correctedResult['content']);
                    $correctedPoints = $this->articleStructureCount($correctedData, $structure);
                    if ($correctedPoints === $pointTarget || abs($correctedPoints - $pointTarget) < abs($actualPoints - $pointTarget)) {
                        $data = $correctedData;
                        $actualPoints = $correctedPoints;
                    }
                    $structureCorrected = true;
                } catch (Throwable $structureError) {
                    report($structureError);
                }
            }
            $wordTarget = $type === 'generate_article' ? $this->articleWordTarget($payload) : null;
            $actualWords = $wordTarget ? $this->articleWordCount($data) : null;
            $expanded = false;
            if ($wordTarget && $actualWords < (int) ceil($wordTarget * 0.9)) {
                try {
                    $expansionMessages = $this->articleExpansionMessages($payload, $data, $wordTarget, $actualWords);
                    $expandedResult = $this->router->text($type, $expansionMessages, ['reasoning_effort' => 'low', 'max_completion_tokens' => 6000]);
                    $in += $expandedResult['input_tokens'] ?: $this->billing->estimateTokens(json_encode($expansionMessages));
                    $out += $expandedResult['output_tokens'] ?: $this->billing->estimateTokens($expandedResult['content']);
                    $expandedData = $this->decode($expandedResult['content']);
                    $expandedWords = $this->articleWordCount($expandedData);
                    $expandedPoints = $pointTarget ? $this->articleStructureCount($expandedData, $structure) : null;
                    if ($expandedWords > $actualWords && (! $pointTarget || $expandedPoints === $pointTarget)) {
                        $data = $expandedData;
                        $actualWords = $expandedWords;
                        $actualPoints = $expandedPoints;
                    }
                    $expanded = true;
                } catch (Throwable $expansionError) {
                    report($expansionError);
                }
            }
            $seoCorrected = false;
            $seoAudit = $type === 'generate_article' ? $this->articleSeoAudit($data, $payload, $wordTarget) : null;
            if ($seoAudit && $seoAudit['score'] < 85) {
                try {
                    $seoMessages = $this->articleSeoCorrectionMessages($payload, $data, $seoAudit);
                    $seoResult = $this->router->text($type, $seoMessages, ['reasoning_effort' => 'low', 'max_completion_tokens' => 6000]);
                    $in += $seoResult['input_tokens'] ?: $this->billing->estimateTokens(json_encode($seoMessages));
                    $out += $seoResult['output_tokens'] ?: $this->billing->estimateTokens($seoResult['content']);
                    $seoData = $this->decode($seoResult['content']);
                    $newAudit = $this->articleSeoAudit($seoData, $payload, $wordTarget);
                    $newPoints = $pointTarget ? $this->articleStructureCount($seoData, $structure) : null;
                    if ($newAudit['score'] > $seoAudit['score'] && (! $pointTarget || $newPoints === $pointTarget)) {
                        $data = $seoData;
                        $seoAudit = $newAudit;
                        $actualWords = $wordTarget ? $this->articleWordCount($data) : null;
                        $actualPoints = $newPoints;
                    }
                    $seoCorrected = true;
                } catch (Throwable $seoError) {
                    report($seoError);
                }
            }
            if ($seoAudit) {
                $seoAudit['ai_correction_performed'] = $seoCorrected;
                $data['seo_audit'] = $seoAudit;
            }
            if ($wordTarget && $actualWords < (int) ceil($wordTarget * 0.9)) {
                $notes = (array) ($data['verification_notes'] ?? []);
                $notes[] = "Artikel mencapai {$actualWords} dari target {$wordTarget} kata karena bahan yang tersedia belum cukup untuk diperluas tanpa mengarang fakta. Tambahkan fakta, contoh, kutipan, atau konteks pendukung.";
                $data['verification_notes'] = array_values(array_unique($notes));
            }
            if ($pointTarget && $actualPoints !== $pointTarget) {
                $notes = (array) ($data['verification_notes'] ?? []);
                $notes[] = "Struktur menghasilkan {$actualPoints} dari {$pointTarget} poin yang diminta. Periksa dan lengkapi struktur sebelum publikasi.";
                $data['verification_notes'] = array_values(array_unique($notes));
            }
            $bill = $this->billing->textCharge($in, $out, $result['provider_model']);

            return DB::transaction(function () use ($type, $payload, $actor, $guest, $result, $in, $out, $bill, $data, $wordTarget, $actualWords, $expanded, $structure, $pointTarget, $pointWords, $actualPoints, $structureCorrected, $seoAudit, $seoCorrected, $similarity, $similarityCorrected) {
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
                if ($wordTarget) {
                    $usage['word_target'] = $wordTarget;
                    $usage['actual_words'] = $actualWords;
                    $usage['target_met'] = $actualWords >= (int) ceil($wordTarget * 0.9);
                    $usage['expansion_performed'] = $expanded;
                }
                if ($pointTarget) {
                    $usage['structure'] = $structure;
                    $usage['point_target'] = $pointTarget;
                    $usage['point_word_target'] = $pointWords;
                    $usage['actual_points'] = $actualPoints;
                    $usage['structure_met'] = $actualPoints === $pointTarget;
                    $usage['structure_correction_performed'] = $structureCorrected;
                }
                if ($seoAudit) {
                    $usage['seo_score'] = $seoAudit['score'];
                    $usage['seo_target'] = 85;
                    $usage['seo_target_met'] = $seoAudit['score'] >= 85;
                    $usage['seo_correction_performed'] = $seoCorrected;
                }
                if ($similarity) {
                    $usage['source_similarity_percent'] = $similarity['percent'];
                    $usage['source_similarity_passed'] = $similarity['passed'];
                    $usage['source_rewrite_performed'] = $similarityCorrected;
                }

                return match ($type) {
                    'detect_news_type' => $this->formatDetection($data, $usage),
                    'analyze_source' => $this->formatSource($data, $payload, $usage),
                    default => $this->formatText($data, $usage),
                };
            });
        } catch (Throwable $e) {
            UsageLog::create(['user_id' => $guest ? null : $actor->user_id, 'connected_site_id' => $guest ? null : $actor->id, 'install_id' => $actor->install_id, 'site_url' => $actor->site_url, 'request_type' => $type, 'free_trial_used' => false, 'status' => 'failed', 'error_message' => substr($e->getMessage(), 0, 1000)]);
            throw $e;
        }
    }

    public function image(array $payload, ConnectedSite $site): array
    {
        $style = $payload['style'] ?? 'editorial photojournalism';
        $prompt = trim('Create a high-quality wide editorial thumbnail for: '.($payload['title'] ?? '').'. Context: '.($payload['content_summary'] ?? '').'. Focus keyword: '.($payload['keyword'] ?? '').'. Additional direction: '.($payload['custom_prompt'] ?? '').'. Style: '.$style.'. Landscape composition, main subject centered with generous safe margins, realistic details, natural lighting, sharp focus, full-frame scene. No text, watermark, logo, border, collage, gore, or identifiable minor.');
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
        return ['title' => $d['title'] ?? $d['main_title'] ?? '', 'alternative_titles' => (array) ($d['alternative_titles'] ?? []), 'lead' => $d['lead'] ?? '', 'content_html' => $d['content_html'] ?? $d['content'] ?? '', 'summary_points' => (array) ($d['summary_points'] ?? []), 'verification_notes' => (array) ($d['verification_notes'] ?? []), 'fact_checklist' => $d['fact_checklist'] ?? [], 'seo_title' => $d['seo_title'] ?? ($d['seo']['seo_title'] ?? ''), 'meta_description' => $d['meta_description'] ?? ($d['seo']['meta_description'] ?? ''), 'focus_keyword' => $d['focus_keyword'] ?? ($d['seo']['focus_keyword'] ?? ''), 'slug' => $d['slug'] ?? ($d['seo']['slug'] ?? ''), 'tags' => (array) ($d['tags'] ?? ($d['seo']['tags'] ?? [])), 'category_suggestion' => $d['category_suggestion'] ?? ($d['seo']['category_suggestion'] ?? ''), 'seo_audit' => $d['seo_audit'] ?? [], 'source_attribution' => $d['source_attribution'] ?? [], 'similarity' => $d['similarity'] ?? [], 'social_captions' => $d['social_captions'] ?? [], 'review_status' => $d['review_status'] ?? 'Needs Verification', 'usage' => $usage];
    }

    private function formatDetection(array $data, array $usage): array
    {
        return ['news_type' => $data['news_type'] ?? 'explainer', 'news_type_label' => $data['news_type_label'] ?? 'Explainer', 'subtype' => $data['subtype'] ?? 'Informasi / Analisis', 'confidence' => (int) ($data['confidence'] ?? 50), 'required_data' => (array) ($data['required_data'] ?? []), 'warnings' => (array) ($data['warnings'] ?? []), 'review_status' => $data['review_status'] ?? 'Needs Verification', 'usage' => $usage];
    }

    private function formatSource(array $data, array $payload, array $usage): array
    {
        $source = (array) data_get($payload, 'payload', []);

        return [
            'source_url' => $source['source_url'] ?? '',
            'source_domain' => $source['source_domain'] ?? '',
            'source_media' => $source['source_media'] ?? '',
            'source_title' => $source['source_title'] ?? '',
            'source_author' => $source['source_author'] ?? '',
            'source_published_at' => $source['source_published_at'] ?? '',
            'source_shingles' => $this->textShingles((string) ($source['source_text'] ?? '')),
            'suggested_title' => $data['suggested_title'] ?? $source['source_title'] ?? '',
            'news_type' => $data['news_type'] ?? 'explainer',
            'news_type_label' => $data['news_type_label'] ?? 'Explainer',
            'subtype' => $data['subtype'] ?? '',
            'facts' => (array) ($data['facts'] ?? []),
            'quotes' => $this->shortQuotes((array) ($data['quotes'] ?? [])),
            'fact_checklist' => (array) ($data['fact_checklist'] ?? []),
            'warnings' => array_values(array_unique(array_merge((array) ($data['warnings'] ?? []), ['Bandingkan kembali seluruh fakta dan kutipan dengan halaman sumber sebelum publish.']))),
            'attribution' => $data['attribution'] ?? 'Berdasarkan laporan '.$source['source_media'].'.',
            'review_status' => 'Needs Verification',
            'usage' => $usage,
        ];
    }

    private function sourceSimilarity(array $data, array $payload): ?array
    {
        $sourceHashes = array_values((array) data_get($payload, 'source_analysis.source_shingles', []));
        if ($sourceHashes === []) {
            return null;
        }
        $generated = $this->textShingles(trim((string) ($data['lead'] ?? '')).' '.strip_tags((string) ($data['content_html'] ?? $data['content'] ?? '')));
        if ($generated === []) {
            return ['percent' => 0, 'threshold' => 25, 'passed' => true, 'matched_shingles' => 0];
        }
        $matched = count(array_intersect($generated, $sourceHashes));
        $percent = round($matched / count($generated) * 100, 1);

        return ['percent' => $percent, 'threshold' => 25, 'passed' => $percent <= 25, 'matched_shingles' => $matched];
    }

    private function shortQuotes(array $quotes): array
    {
        return array_values(array_filter(array_map(function ($quote) {
            $quote = is_array($quote) ? implode(' — ', array_map('strval', array_values($quote))) : (string) $quote;
            preg_match_all('/\S+/u', trim($quote), $words);

            return implode(' ', array_slice($words[0], 0, 25));
        }, array_slice($quotes, 0, 2))));
    }

    private function textShingles(string $text): array
    {
        preg_match_all('/[\p{L}\p{N}]+/u', mb_strtolower(strip_tags($text)), $matches);
        $words = $matches[0];
        $hashes = [];
        for ($index = 0; $index <= count($words) - 7 && count($hashes) < 800; $index++) {
            $hashes[] = hash('sha256', implode(' ', array_slice($words, $index, 7)));
        }

        return array_values(array_unique($hashes));
    }

    private function originalityCorrectionMessages(array $payload, array $draft, array $similarity): array
    {
        $system = 'Anda adalah editor orisinalitas berita. Tulis ulang struktur kalimat dan urutan penyajian draft agar kemiripan frasa dengan sumber turun di bawah 25 persen. Gunakan hanya fakta atomik, checklist, dan maksimal dua kutipan pendek yang tersedia dalam source_analysis. Jangan melihat atau merekonstruksi paragraf sumber, jangan mengubah fakta atau makna kutipan, jangan menghapus atribusi dan tautan sumber. Pertahankan seluruh field JSON, SEO, serta status Needs Verification. Keluarkan JSON valid tanpa markdown.';
        $input = ['source_facts' => data_get($payload, 'source_analysis.facts', []), 'source_quotes' => data_get($payload, 'source_analysis.quotes', []), 'source_attribution' => array_intersect_key((array) ($payload['source_analysis'] ?? []), array_flip(['source_url', 'source_media', 'source_title', 'attribution'])), 'current_similarity' => $similarity, 'current_draft' => $draft];

        return [['role' => 'system', 'content' => $system], ['role' => 'user', 'content' => json_encode($input, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]];
    }

    private function articleWordTarget(array $payload): ?int
    {
        $target = (int) (data_get($payload, 'payload.length') ?? data_get($payload, 'payload.payload.length') ?? 0);

        return $target >= 200 && $target <= 900 ? $target : null;
    }

    private function articleStructure(array $payload): string
    {
        $structure = (string) (data_get($payload, 'payload.structure') ?? data_get($payload, 'payload.payload.structure') ?? 'standard');

        return in_array($structure, ['standard', 'listicle', 'tutorial', 'faq'], true) ? $structure : 'standard';
    }

    private function articlePointTarget(array $payload): int
    {
        $target = (int) (data_get($payload, 'payload.point_count') ?? data_get($payload, 'payload.payload.point_count') ?? 10);

        return min(20, max(2, $target));
    }

    private function articlePointWordTarget(array $payload): int
    {
        $target = (int) (data_get($payload, 'payload.point_word_count') ?? data_get($payload, 'payload.payload.point_word_count') ?? 100);

        return min(200, max(50, $target));
    }

    private function articleStructureCount(array $data, string $structure): int
    {
        $html = (string) ($data['content_html'] ?? $data['content'] ?? '');
        if ($structure === 'faq') {
            preg_match_all('/<section\b[^>]*class=["\'][^"\']*aina-faq-item[^"\']*["\'][^>]*>/i', $html, $matches);

            return count($matches[0]);
        }
        preg_match_all('/<section\b[^>]*class=["\'][^"\']*aina-article-point[^"\']*["\'][^>]*>/i', $html, $matches);

        return count($matches[0]);
    }

    private function articleWordCount(array $data): int
    {
        $text = trim(($data['lead'] ?? '').' '.strip_tags((string) ($data['content_html'] ?? $data['content'] ?? '')));
        if ($text === '') {
            return 0;
        }
        preg_match_all('/[\p{L}\p{N}]+(?:[-’\'][\p{L}\p{N}]+)*/u', html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8'), $matches);

        return count($matches[0]);
    }

    private function articleSeoAudit(array $data, array $payload, ?int $wordTarget): array
    {
        $keyword = trim((string) (data_get($payload, 'payload.focus_keyword') ?? data_get($payload, 'payload.payload.focus_keyword') ?? $data['focus_keyword'] ?? ''));
        $title = trim((string) ($data['seo_title'] ?? ''));
        $description = trim((string) ($data['meta_description'] ?? ''));
        $slug = trim((string) ($data['slug'] ?? ''));
        $lead = trim(strip_tags((string) ($data['lead'] ?? '')));
        $content = (string) ($data['content_html'] ?? $data['content'] ?? '');
        $plain = trim(strip_tags($content));
        $words = max(1, $this->articleWordCount($data));
        $occurrences = $keyword === '' ? 0 : substr_count(mb_strtolower($lead.' '.$plain), mb_strtolower($keyword));
        $density = $occurrences * max(1, str_word_count($keyword)) / $words * 100;
        $checks = [
            'focus_keyword_available' => $keyword !== '',
            'keyword_at_seo_title_start' => $keyword !== '' && str_starts_with(mb_strtolower($title), mb_strtolower($keyword)),
            'seo_title_length' => mb_strlen($title) >= 30 && mb_strlen($title) <= 60,
            'keyword_in_description' => $keyword !== '' && str_contains(mb_strtolower($description), mb_strtolower($keyword)),
            'description_length' => mb_strlen($description) >= 120 && mb_strlen($description) <= 160,
            'keyword_in_slug' => $keyword !== '' && str_contains($slug, str($keyword)->slug()->toString()),
            'slug_length' => strlen($slug) <= 60,
            'keyword_at_content_start' => $keyword !== '' && str_starts_with(mb_strtolower($lead), mb_strtolower($keyword)),
            'keyword_in_heading' => $keyword !== '' && (bool) preg_match('/<h[2-4][^>]*>[^<]*'.preg_quote($keyword, '/').'/iu', $content),
            'keyword_density' => $density >= 0.5 && $density <= 2.5,
            'content_length' => ! $wordTarget || $words >= (int) ceil($wordTarget * 0.9),
            'outbound_source_link' => (bool) preg_match('/<a\b[^>]*href=["\']https?:\/\//i', $content),
            'table_of_contents' => str_contains($content, 'wp-block-rank-math-toc-block'),
            'sentiment_word_in_title' => (bool) preg_match('/\b(terbaik|mudah|efektif|unggulan|penting|buruk|berbahaya)\b/iu', $title),
            'power_word_in_title' => (bool) preg_match('/\b(ampuh|lengkap|praktis|rahasia|terbukti|wajib|panduan)\b/iu', $title),
        ];
        $score = (int) round(count(array_filter($checks)) / count($checks) * 100);

        return ['score' => $score, 'target' => 85, 'passed' => $score >= 85, 'checks' => $checks, 'keyword_density' => round($density, 2), 'note' => 'Estimasi internal; skor final tetap dihitung oleh Rank Math di WordPress.'];
    }

    private function articleSeoCorrectionMessages(array $payload, array $draft, array $audit): array
    {
        $system = 'Anda adalah auditor SEO Rank Math. Perbaiki hanya bagian yang gagal pada seo_audit agar estimasi mencapai minimal 85. Focus keyword wajib muncul persis dan alami di awal lead, isi artikel, satu H2, awal SEO title, meta description, dan slug. SEO title 30-60 karakter serta, jika sesuai konteks non-hard-news, memakai kata sentimen dan power word Indonesia yang wajar seperti "terbaik", "efektif", "panduan", atau "ampuh". Meta description 120-160 karakter, slug maksimal 60 karakter, density keyword 0,5-2,5 persen. Jika source_input memiliki source_url valid, tambahkan tepat satu tautan dofollow kontekstual menuju URL tersebut; jangan membuat URL sendiri. Untuk artikel dengan minimal tiga H2, tambahkan Table of Contents Rank Math yang valid. Pertahankan seluruh fakta, jumlah section poin, format H2 bernomor, panjang per poin, dan field JSON lain. Jangan menambahkan klaim, statistik, kutipan, atau tautan palsu. Keluarkan JSON valid tanpa markdown.';
        $input = ['source_input' => $payload, 'current_draft' => $draft, 'seo_audit' => $audit];

        return [['role' => 'system', 'content' => $system], ['role' => 'user', 'content' => json_encode($input, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]];
    }

    private function articleExpansionMessages(array $payload, array $draft, int $target, int $actual): array
    {
        $structure = $this->articleStructure($payload);
        $pointTarget = $structure !== 'standard' ? $this->articlePointTarget($payload) : null;
        $pointWords = $this->articlePointWordTarget($payload);
        $minimumPointWords = (int) floor($pointWords * 0.9);
        $maximumPointWords = (int) ceil($pointWords * 1.1);
        $structureRule = $pointTarget ? " Pertahankan tepat {$pointTarget} poin dengan format {$structure}; jangan menambah, menghapus, memecah, atau menggabungkan poin. Setiap poin wajib memiliki <h2> bernomor seperti <h2>1. Judul Poin</h2>, lalu penjelasan substantif sekitar {$pointWords} kata ({$minimumPointWords}-{$maximumPointWords} kata) dalam satu atau beberapa tag <p>." : '';
        $system = 'Anda adalah redaktur senior Indonesia. Perluas artikel JSON yang diberikan hingga mendekati target kata dengan toleransi 10 persen. Hitung hanya lead dan content_html. Gunakan hanya fakta dalam source_input dan current_draft. Untuk artikel ide, Anda boleh mengembangkan gagasan kreatif yang relevan dengan brief, tetapi jangan mengarang statistik, hasil nyata, kutipan, sumber, atau klaim faktual. Tambahkan penjelasan konteks, transisi, langkah, dan elaborasi yang didukung bahan. Pertahankan struktur JSON dan seluruh field SEO/checklist.'.$structureRule.' Keluarkan JSON valid tanpa markdown.';
        $input = ['target_words' => $target, 'current_words' => $actual, 'source_input' => $payload, 'current_draft' => $draft];

        return [['role' => 'system', 'content' => $system], ['role' => 'user', 'content' => json_encode($input, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]];
    }

    private function articleStructureCorrectionMessages(array $payload, array $draft, string $structure, int $target, int $actual): array
    {
        $pointWords = $this->articlePointWordTarget($payload);
        $minimumPointWords = (int) floor($pointWords * 0.9);
        $maximumPointWords = (int) ceil($pointWords * 1.1);
        $format = match ($structure) {
            'faq' => 'Gunakan tepat '.$target.' elemen <section class="aina-faq-item"><h2>Nomor. Pertanyaan</h2><p>Jawaban sekitar '.$pointWords.' kata ('.$minimumPointWords.'-'.$maximumPointWords.' kata).</p></section>. Nomori H2 secara berurutan mulai dari 1.',
            'tutorial' => 'Gunakan tepat '.$target.' elemen <section class="aina-article-point"><h2>Nomor. Judul Langkah</h2><p>Penjelasan sekitar '.$pointWords.' kata ('.$minimumPointWords.'-'.$maximumPointWords.' kata).</p></section>. Nomori H2 secara berurutan mulai dari 1 dan jangan gunakan daftar bertingkat.',
            default => 'Gunakan tepat '.$target.' elemen <section class="aina-article-point"><h2>Nomor. Judul Ide</h2><p>Penjelasan sekitar '.$pointWords.' kata ('.$minimumPointWords.'-'.$maximumPointWords.' kata).</p></section>. Nomori H2 secara berurutan mulai dari 1 dan jangan gunakan daftar bertingkat.',
        };
        $system = "Anda adalah editor struktur artikel. Artikel memiliki {$actual} poin, tetapi wajib tepat {$target}. Perbaiki jumlah dan penomoran. Untuk artikel ide, Anda boleh melengkapi gagasan kreatif yang relevan dengan brief, tetapi jangan mengarang statistik, hasil nyata, kutipan, sumber, atau klaim faktual. {$format} Pertahankan seluruh field JSON, SEO, checklist, lead, dan informasi yang valid. Keluarkan JSON valid tanpa markdown.";
        $input = ['source_input' => $payload, 'current_draft' => $draft, 'structure' => $structure, 'required_points' => $target];

        return [['role' => 'system', 'content' => $system], ['role' => 'user', 'content' => json_encode($input, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]];
    }

    private function systemPrompt(string $type): string
    {
        if ($type === 'detect_news_type') {
            return 'Anda adalah editor berita Indonesia. Klasifikasikan judul tanpa mengarang fakta. Keluarkan JSON valid tanpa markdown dengan fields: news_type (incident/government/business/feature/event/advertorial/explainer), news_type_label, subtype, confidence (0-100), required_data (array), warnings (array), review_status. Berikan warning privasi, anak, korban, praduga tak bersalah, atau verifikasi aparat jika relevan.';
        }
        if ($type === 'analyze_source') {
            return 'Anda adalah desk riset redaksi Indonesia. Analisis teks artikel sumber hanya untuk mengekstrak fakta; jangan menulis ulang artikel dan jangan menyalin paragraf. Keluarkan JSON valid tanpa markdown dengan fields: suggested_title (judul baru dengan sudut netral, bukan salinan judul), news_type (incident/government/business/feature/event/advertorial/explainer), news_type_label, subtype, facts (array fakta atomik yang dapat diverifikasi), quotes (maksimal 2 kutipan langsung pendek, masing-masing maksimal 25 kata, pertahankan makna dan narasumber), fact_checklist (object who, what, when, where, why, how), warnings (array), attribution (kalimat atribusi yang menyebut source_media). Jangan mengambil gambar, jangan menambah fakta, jangan menyimpulkan hal yang tidak tertulis, dan tetapkan kebutuhan verifikasi.';
        }
        if ($type === 'generate_article') {
            return 'Anda adalah redaktur dan SEO content writer Indonesia. Tulis artikel hanya dari brief, fakta, sumber, outline, target pembaca, dan gaya pada input. Field length adalah target jumlah kata artikel antara 200 sampai 900 kata. Buat gabungan lead dan content_html sedekat mungkin dengan target tersebut dengan toleransi maksimal 10 persen; metadata SEO, alternatif judul, checklist, dan caption tidak dihitung. Patuhi field structure: standard memakai paragraf/heading biasa; listicle memakai tepat point_count elemen <section class="aina-article-point"><h2>Nomor. Judul Ide</h2><p>Penjelasan</p></section>; tutorial memakai format yang sama dengan judul langkah; faq memakai tepat point_count elemen <section class="aina-faq-item"><h2>Nomor. Pertanyaan</h2><p>Jawaban</p></section>. Nomori setiap H2 secara berurutan mulai dari 1. Untuk listicle, tutorial, dan FAQ, setiap poin atau jawaban wajib diberi penjelasan substantif sesuai point_word_count dengan toleransi 10 persen dalam satu atau beberapa paragraf, bukan hanya judul atau satu kalimat. Jika kebutuhan kata seluruh poin melebihi field length, kelengkapan point_count dan point_word_count lebih diprioritaskan. Untuk artikel ide, Anda boleh merumuskan gagasan kreatif yang relevan dengan brief, tetapi jangan mengarang statistik, hasil nyata, kutipan, sumber, atau klaim faktual. Lead harus diawali focus_keyword secara alami. Focus keyword wajib muncul persis di awal lead, dalam isi, dan sedikitnya satu H2. SEO title harus diawali focus_keyword, maksimal 60 karakter, serta untuk konten non-hard-news menggunakan kata sentimen dan power word Indonesia yang alami seperti "terbaik", "efektif", "panduan", atau "ampuh". Meta description 120-155 karakter serta memuat focus_keyword; slug ringkas maksimal 55 karakter dan memuat focus_keyword. Jika source_url tersedia, tambahkan satu outbound link dofollow kontekstual ke URL tersebut dan jangan membuat URL lain. Jika terdapat minimal tiga H2, buat Table of Contents Rank Math. Hindari keyword stuffing. content_html berisi HTML valid; jangan mengulang judul, lead, nama domain, atau memakai heading generik seperti "fakta utama". Keluarkan JSON valid tanpa markdown dengan fields: title, alternative_titles (array), lead, content_html, summary_points (array), verification_notes (array), fact_checklist (object: who, what, when, where, why, how, source_available, needs_verification), seo_title, meta_description, focus_keyword, slug, tags (array), category_suggestion, social_captions (object), review_status.';
        }

        return 'Anda adalah redaktur berita Indonesia. Request type: '.$type.'. Gunakan hanya fakta input dan tandai data kosong untuk verifikasi. Gunakan gaya berita langsung dan sederhana: lead ringkas sebagai kalimat berita utuh, lalu paragraf fakta utama, kronologi, kondisi pihak terlibat, keterangan narasumber, penanganan, dan perkembangan terakhir secara berurutan. Jangan menulis lead dengan pola "judul — kalimat", jangan mengulang judul sebelum tanda pisah, dan jangan menambahkan dateline atau nama domain karena plugin menanganinya. Masukkan focus keyword secara alami dalam kalimat pertama tanpa menjadikannya fragmen judul. content_html hanya boleh memakai tag <p>; jangan memakai H1, H2, H3, daftar isi, daftar bernomor, bullet, heading generik, atau markdown. Setiap paragraf membahas satu gagasan dan dibuat ringkas. Jangan mengulang judul atau lead. Kutipan langsung ditulis sebagai paragraf tersendiri menggunakan tanda kutip Indonesia. Jika source_analysis tersedia, tulis berita baru hanya dari facts, fact_checklist, dan maksimal dua quotes pendek; ubah susunan, sudut, serta struktur kalimat, jangan menyalin judul atau paragraf sumber, wajib sertakan atribusi dan source_url, dan tetapkan review_status Needs Verification. Keluarkan JSON valid dengan fields: title, alternative_titles (array), lead, content_html, summary_points (array), verification_notes (array), fact_checklist (object dengan key who, what, when, where, why, how, source_available, needs_verification), seo_title, meta_description, focus_keyword, slug, tags, category_suggestion, social_captions, review_status. Isi setiap unsur 5W+1H hanya dari fakta input; gunakan string kosong jika datanya tidak tersedia. Jangan mempublikasikan atau mengarang fakta.';
    }

    private function size(string $ratio): string
    {
        return match ($ratio) {
            '16:9' => '1536x1024','9:16' => '1024x1536',default => '1024x1024'
        };
    }
}
