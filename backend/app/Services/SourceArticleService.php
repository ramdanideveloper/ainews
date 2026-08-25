<?php

namespace App\Services;

use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class SourceArticleService
{
    public function extract(string $url): array
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        if ($host === '' || $host === 'localhost' || str_ends_with($host, '.local')) {
            throw new RuntimeException('URL sumber tidak valid.');
        }
        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : array_values(array_unique(array_merge(gethostbynamel($host) ?: [], $this->ipv6($host))));
        if ($ips === [] || collect($ips)->contains(fn (string $ip) => ! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE))) {
            throw new RuntimeException('URL lokal atau jaringan privat tidak diizinkan.');
        }

        $response = Http::connectTimeout(8)->timeout(20)->withOptions(['allow_redirects' => false])->withHeaders([
            'User-Agent' => 'AI News Assistant Source Reader/1.0',
            'Accept' => 'text/html,application/xhtml+xml',
        ])->get($url);
        if ($response->redirect()) {
            throw new RuntimeException('URL sumber mengalihkan halaman. Gunakan URL tujuan akhir setelah redirect.');
        }
        if (! $response->successful()) {
            throw new RuntimeException('Halaman sumber tidak dapat diakses (HTTP '.$response->status().').');
        }
        $contentType = strtolower((string) $response->header('Content-Type'));
        if (! str_contains($contentType, 'text/html') && ! str_contains($contentType, 'application/xhtml+xml')) {
            throw new RuntimeException('URL sumber bukan halaman HTML.');
        }
        if (strlen($response->body()) > 2_000_000) {
            throw new RuntimeException('Halaman sumber terlalu besar untuk dianalisis.');
        }

        return $this->parse($url, $response->body());
    }

    private function parse(string $url, string $html): array
    {
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="utf-8" ?>'.$html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $xpath = new DOMXPath($document);
        foreach (['//script', '//style', '//noscript', '//nav', '//footer', '//aside', '//form', '//iframe', '//svg'] as $query) {
            foreach ($xpath->query($query) ?: [] as $node) {
                $node->parentNode?->removeChild($node);
            }
        }
        $title = $this->meta($xpath, 'property', 'og:title') ?: trim((string) ($xpath->query('//title')->item(0)?->textContent));
        $author = $this->meta($xpath, 'name', 'author') ?: $this->meta($xpath, 'property', 'article:author');
        $published = $this->meta($xpath, 'property', 'article:published_time') ?: $this->meta($xpath, 'name', 'date');
        $siteName = $this->meta($xpath, 'property', 'og:site_name') ?: (string) parse_url($url, PHP_URL_HOST);
        $container = $xpath->query('//article')->item(0) ?: $xpath->query('//main')->item(0) ?: $document->getElementsByTagName('body')->item(0);
        $paragraphs = [];
        if ($container) {
            foreach ((new DOMXPath($document))->query('.//p|.//h2|.//h3|.//blockquote', $container) ?: [] as $node) {
                $text = preg_replace('/\s+/u', ' ', trim($node->textContent));
                if (mb_strlen($text) >= 30) {
                    $paragraphs[] = $text;
                }
                if (mb_strlen(implode("\n", $paragraphs)) >= 25_000) {
                    break;
                }
            }
        }
        $content = trim(implode("\n", array_values(array_unique($paragraphs))));
        if (mb_strlen($content) < 150) {
            throw new RuntimeException('Isi utama artikel tidak berhasil ditemukan. Halaman mungkin dilindungi, memakai paywall, atau memerlukan JavaScript.');
        }

        return ['source_url' => $url, 'source_domain' => (string) parse_url($url, PHP_URL_HOST), 'source_media' => $siteName, 'source_title' => $title, 'source_author' => $author, 'source_published_at' => $published, 'source_text' => $content];
    }

    private function meta(DOMXPath $xpath, string $attribute, string $value): string
    {
        $node = $xpath->query('//meta[translate(@'.$attribute.', "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz")="'.strtolower($value).'"]/@content')->item(0);

        return trim((string) ($node?->nodeValue));
    }

    private function ipv6(string $host): array
    {
        $records = dns_get_record($host, DNS_AAAA);

        return array_values(array_filter(array_map(fn (array $record) => $record['ipv6'] ?? null, is_array($records) ? $records : [])));
    }
}
