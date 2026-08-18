<?php

namespace Tests\Unit;

use App\Models\AiProvider;
use App\Services\BillingService;
use Tests\TestCase;

class BillingServiceTest extends TestCase
{
    public function test_minimum_text_charge_is_applied(): void
    {
        $provider = new AiProvider(['price_input_per_1m' => 0, 'price_output_per_1m' => 0]);
        $result = app(BillingService::class)->textCharge(10, 20, $provider);
        $this->assertSame(100.0, $result['charged_amount']);
    }
}
