<?php

namespace App\Http\Middleware;

use App\Services\SiteTokenService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateSiteToken
{
    public function __construct(private SiteTokenService $tokens) {}

    public function handle(Request $request, Closure $next): Response
    {
        $plain = $request->bearerToken();
        $site = $plain ? $this->tokens->find($plain) : null;
        if (! $site || $site->status !== 'active' || $site->user->status !== 'active') {
            return response()->json(['success' => false, 'code' => 'invalid_site_token', 'message' => 'Site token tidak valid atau akses ditangguhkan.', 'data' => null], 401);
        }$sentDomain = parse_url((string) $request->header('X-Site-URL'), PHP_URL_HOST);
        if (! $sentDomain || strtolower($sentDomain) !== strtolower($site->domain)) {
            return response()->json(['success' => false, 'code' => 'site_domain_mismatch', 'message' => 'Domain request tidak cocok dengan Connected Site.', 'data' => null], 403);
        }$site->update(['last_seen_at' => now()]);
        $request->attributes->set('connected_site', $site);

        return $next($request);
    }
}
