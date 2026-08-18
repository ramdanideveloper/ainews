# AI News Assistant SaaS Backend

Laravel 12, Filament 5, and Sanctum backend for guest trials, accounts, Connected Sites, pay-as-you-go wallet billing, AI routing, usage logs, and image generation. Provider keys stay encrypted on the backend and are never returned to WordPress.

## Install locally

Requirements: PHP 8.2+, Composer 2, and MySQL 8/MariaDB 10.6+. SQLite can be used for tests.

```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
```

Configure `.env` with `APP_URL`, MySQL credentials, and strong `ADMIN_EMAIL` / `ADMIN_PASSWORD`, then:

```bash
php artisan migrate --seed
php artisan storage:link
php artisan serve
```

- Filament admin: `/admin`
- User dashboard: `/dashboard`
- API base: `/api`

In production point the web root to `backend/public`; only `storage` and `bootstrap/cache` should be writable. Change the seeded admin password before deployment.

## Provider setup

In **Admin → AI Providers**, add an active model. Gemini text base URL is `https://generativelanguage.googleapis.com/v1beta/openai`; OpenAI is `https://api.openai.com/v1`. Prices are IDR per 1M tokens or per image. API keys use Laravel's encrypted cast and are masked in Filament.

Priority/fallback and daily/monthly limits are enforced by the router. Optional Routing Rules select a preferred model. Failed providers fall through to the next model and never debit the wallet.

### Article and image models

Create separate provider records so routing and prices remain independent:

- Article/text: Gemini `gemini-2.5-flash` when the API account has access. Newer accounts that reject 2.5 can use `gemini-3.5-flash-lite` or another active text fallback.
- Image/Nano Banana: Gemini `gemini-2.5-flash-image`, base URL `https://generativelanguage.googleapis.com/v1beta`, `supports_image` enabled and `supports_text` disabled.

Set provider input/output prices per one million tokens and image price per generation in **Admin → AI Providers**. Set the customer-facing minimum/rates in **Admin → App Settings** (`text_selling_rate_per_token`, `minimum_text_request_fee`, `image_standard_fee`, and `image_with_thumbnail_seo_fee`). A successful article and a successful image create separate usage logs and wallet debits. Failed provider requests do not debit the wallet.

## Billing

- Text: `max(Rp100, total_tokens × Rp0.10, provider_cost × 3)`
- Standard image: Rp1.000
- Image plus thumbnail SEO: Rp1.500
- One-time welcome credit: Rp5.000

Edit rates in **Admin → App Settings**. Wallet changes use database transactions and row locks. Manual top-up is available through Wallet Transactions.

## Authentication flow

1. Guest sends `install_id` and `site_url` to `/guest/status` or `/guest/generate`.
2. After 10 successful text requests the API returns `guest_trial_exhausted`.
3. Register/login returns a Sanctum account token.
4. `/sites/connect` returns a site token once.
5. AI calls use that site token as Bearer token plus `X-Site-URL`; the domain must match.

Public endpoints: `POST /guest/status`, `/guest/generate`, `/auth/register`, `/auth/login`.

Account endpoints: `POST /sites/connect`, `GET /sites/status`, `POST /sites/{id}/rotate-token`, `GET /wallet/balance`, `/wallet/transactions`, `/usage/history`.

Site endpoints: `POST /ai/detect-news-type`, `/ai/generate-news`, `/ai/generate-article`, `/ai/rewrite`, `/ai/generate-image`.

All API responses follow `{ success, code, message, data }` and are rate-limited by install ID, account/site token, and IP.

## Test

```bash
php artisan test
```

Tests cover the 10-use guest limit, welcome credit, Connected Site creation, and minimum billing fee.
