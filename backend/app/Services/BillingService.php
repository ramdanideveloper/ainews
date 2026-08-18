<?php

namespace App\Services;

use App\Models\AiProvider;
use App\Models\AppSetting;

class BillingService
{
    public function estimateTokens(string $text): int
    {
        return max(1, (int) ceil(strlen($text) / 4));
    }

    public function textCharge(int $in, int $out, AiProvider $p): array
    {
        $total = $in + $out;
        $rate = (float) AppSetting::valueOf('text_selling_rate_per_token', 0.10);
        $min = (float) AppSetting::valueOf('minimum_text_request_fee', 100);
        $cost = ($in / 1000000 * (float) $p->price_input_per_1m) + ($out / 1000000 * (float) $p->price_output_per_1m);

        return ['total_tokens' => $total, 'provider_cost_idr' => $cost, 'charged_amount' => round(max($min, $total * $rate, $cost * 3), 2)];
    }

    public function imageCharge(bool $thumbnail, AiProvider $p): array
    {
        $fee = (float) AppSetting::valueOf($thumbnail ? 'image_with_thumbnail_seo_fee' : 'image_standard_fee', $thumbnail ? 1500 : 1000);
        $cost = (float) ($p->price_image_per_generate ?? 0);

        return ['provider_cost_idr' => $cost, 'charged_amount' => round(max($fee, $cost * 3), 2)];
    }
}
