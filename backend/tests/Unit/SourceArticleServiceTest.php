<?php

namespace Tests\Unit;

use App\Services\SourceArticleService;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class SourceArticleServiceTest extends TestCase
{
    public function test_it_extracts_public_article_content_and_metadata(): void
    {
        Http::fake(fn () => Http::response('<html><head><meta property="og:title" content="Judul Sumber"><meta property="og:site_name" content="Media Uji"><meta name="author" content="Reporter"></head><body><nav>Menu</nav><article><p>Paragraf fakta pertama yang cukup panjang untuk dibaca dan dianalisis oleh sistem redaksi.</p><p>Paragraf fakta kedua berisi konteks tambahan yang juga cukup panjang untuk proses ekstraksi.</p></article></body></html>', 200, ['Content-Type' => 'text/html; charset=UTF-8']));

        $result = (new SourceArticleService)->extract('https://93.184.216.34/news');

        $this->assertSame('Judul Sumber', $result['source_title']);
        $this->assertSame('Media Uji', $result['source_media']);
        $this->assertStringContainsString('Paragraf fakta pertama', $result['source_text']);
        $this->assertStringNotContainsString('Menu', $result['source_text']);
    }

    public function test_it_rejects_private_network_urls(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('jaringan privat');

        (new SourceArticleService)->extract('http://127.0.0.1/private');
    }
}
