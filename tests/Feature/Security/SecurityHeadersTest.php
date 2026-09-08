<?php

namespace Tests\Feature\Security;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * `App\Http\Middleware\SecurityHeaders` is registered globally (plan.md
 * Fase 8), so a single guest-facing page is enough to prove it runs on every
 * response rather than re-testing it per route.
 */
class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    public function test_baseline_security_headers_are_present(): void
    {
        $response = $this->get('/login');

        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        $csp = $response->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("default-src 'self'", $csp);
        $this->assertStringContainsString("frame-ancestors 'none'", $csp);
        $this->assertStringContainsString("object-src 'none'", $csp);
    }
}
