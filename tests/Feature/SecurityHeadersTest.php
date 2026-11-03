<?php

it('devuelve las cabeceras de seguridad en una vista web', function () {
    $response = $this->get('/login');

    $response->assertOk()
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-XSS-Protection', '1; mode=block')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');

    $csp = (string) $response->headers->get('Content-Security-Policy');

    expect($csp)
        ->toContain("default-src 'self'")
        ->toContain("script-src 'self'")
        ->toContain('https://cdn.tailwindcss.com')
        ->toContain('https://cdn.jsdelivr.net')
        ->toContain('https://cdnjs.cloudflare.com')
        ->toContain("frame-ancestors 'none'");
});

it('devuelve las cabeceras de seguridad tambien en el endpoint de salud', function () {
    $this->get('/up')
        ->assertOk()
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
});
