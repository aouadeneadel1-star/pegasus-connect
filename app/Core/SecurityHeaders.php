<?php

declare(strict_types=1);

namespace App\Core;

final class SecurityHeaders
{
    private const CSP = "default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data: blob:; "
        . "media-src 'self' blob:; connect-src 'self'; font-src 'self'; worker-src 'self'; manifest-src 'self'; "
        . "frame-ancestors 'none'; base-uri 'self'; form-action 'self'; object-src 'none'";

    public static function apply(Response $response, bool $https): Response
    {
        $response
            ->withHeader('Content-Security-Policy', $response->header('Content-Security-Policy') ?? self::CSP)
            ->withHeader('X-Content-Type-Options', 'nosniff')
            ->withHeader('X-Frame-Options', 'DENY')
            ->withHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->withHeader('Permissions-Policy', 'camera=(self), geolocation=(self), microphone=(), payment=(), usb=()')
            ->withHeader('Cross-Origin-Opener-Policy', 'same-origin');

        if ($https) {
            $response->withHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        if ($response->header('Cache-Control') === null && str_starts_with((string) $response->header('Content-Type'), 'text/html')) {
            $response->withHeader('Cache-Control', 'no-store');
        }

        return $response;
    }
}
