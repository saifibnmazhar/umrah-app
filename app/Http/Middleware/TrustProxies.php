<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies as Middleware;
use Monicahq\Cloudflare\Http\Middleware\TrustProxies as CloudflareTrustProxies;
use Symfony\Component\HttpFoundation\Request;

/**
 * TrustProxies middleware for Cloudflare deployments.
 *
 * Extends the monicahq/laravel-cloudflare TrustProxies middleware to
 * automatically trust Cloudflare IP ranges, set REMOTE_ADDR from
 * Cf-Connecting-Ip header, and cache the Cloudflare IP list.
 * The daily cloudflare:reload command refreshes the cached IP list.
 *
 * Behind Cloudflare (Full/Strict TLS), this ensures:
 * - X-Forwarded-For/Proto headers are trusted (real client IP)
 * - Session cookies work correctly with SESSION_SECURE_COOKIE=true
 * - URL::forceScheme('https') produces correct URLs
 * - Request->ip() returns the real client IP instead of Cloudflare's
 */
class TrustProxies extends CloudflareTrustProxies
{
    /**
     * The trusted proxies for this application.
     *
     * Uses '*' with the monicahq/laravel-cloudflare package which
     * filters by actual Cloudflare IP ranges at runtime.
     */
    // protected $proxies is inherited from parent; override if needed.
    // Default: '*' (the package merges actual Cloudflare IPs)

    /**
     * The headers that should be used to detect proxies.
     *
     * HEADER_X_FORWARDED_TRAEFIK includes all X-Forwarded-* headers:
     * - X-Forwarded-For (client IP)
     * - X-Forwarded-Host (original host)
     * - X-Forwarded-Proto (original scheme: https)
     * - X-Forwarded-Port
     * - X-Forwarded-Prefix
     */
    protected $headers = Request::HEADER_X_FORWARDED_TRAEFIK;
}
