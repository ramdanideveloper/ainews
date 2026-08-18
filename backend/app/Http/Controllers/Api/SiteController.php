<?php

namespace App\Http\Controllers\Api;

use App\Models\ConnectedSite;
use App\Services\SiteTokenService;
use Illuminate\Http\Request;

class SiteController extends ApiController
{
    public function connect(Request $r, SiteTokenService $tokens)
    {
        $v = $r->validate(['site_name' => 'required|string|max:150', 'site_url' => 'required|url|max:255', 'install_id' => 'required|uuid']);
        $domain = strtolower(parse_url($v['site_url'], PHP_URL_HOST));
        $site = ConnectedSite::where('install_id', $v['install_id'])->first();
        if ($site && $site->user_id !== $r->user()->id) {
            return $this->fail('install_already_connected', 'Install ID sudah terhubung ke akun lain.', 409);
        }$site = ConnectedSite::updateOrCreate(['install_id' => $v['install_id']], ['user_id' => $r->user()->id, 'site_name' => $v['site_name'], 'site_url' => $v['site_url'], 'domain' => $domain, 'status' => 'active', 'last_seen_at' => now(), 'token_hash' => $site?->token_hash ?: hash('sha256', str()->random(64)), 'token_last_four' => $site?->token_last_four ?: 'init']);
        $token = $tokens->rotate($site);

        return $this->ok(['site' => $site->only(['id', 'site_name', 'site_url', 'domain', 'status']), 'site_token' => $token], 'Connected Site berhasil. Simpan token ini karena tidak ditampilkan kembali.');
    }

    public function status(Request $r)
    {
        return $this->ok(['sites' => $r->user()->connectedSites()->get(['id', 'site_name', 'site_url', 'domain', 'status', 'token_last_four', 'last_seen_at'])]);
    }

    public function rotate(Request $r, ConnectedSite $site, SiteTokenService $tokens)
    {
        abort_unless($site->user_id === $r->user()->id, 403);

        return $this->ok(['site_token' => $tokens->rotate($site)], 'Site token berhasil dirotasi.');
    }
}
