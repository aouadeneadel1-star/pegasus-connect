<?php

declare(strict_types=1);

namespace App\Core;

final class Csrf
{
    private const SESSION_KEY = '_csrf';

    public static function token(): string
    {
        $token = Session::get(self::SESSION_KEY);
        if (!is_string($token) || strlen($token) !== 64) {
            $token = bin2hex(random_bytes(32));
            Session::set(self::SESSION_KEY, $token);
        }

        return $token;
    }

    public static function rotate(): void
    {
        Session::set(self::SESSION_KEY, bin2hex(random_bytes(32)));
    }

    public static function isValid(Request $request, string $appUrl): bool
    {
        if (!self::originMatches($request, $appUrl)) {
            return false;
        }

        $sent = $request->header('X-CSRF-Token') ?? $request->input('_csrf') ?? '';
        $expected = Session::get(self::SESSION_KEY);

        return is_string($expected) && $sent !== '' && hash_equals($expected, $sent);
    }

    private static function originMatches(Request $request, string $appUrl): bool
    {
        $expected = self::origin($appUrl);
        $origin = $request->header('Origin');
        if ($origin !== null && $origin !== '' && $origin !== 'null') {
            return hash_equals($expected, rtrim($origin, '/'));
        }

        // Certains navigateurs n'envoient pas Origin sur un POST de même origine : le Referer prend le relais.
        $referer = $request->header('Referer');
        if ($referer !== null && $referer !== '') {
            return hash_equals($expected, self::origin($referer));
        }

        return false;
    }

    private static function origin(string $url): string
    {
        $parts = parse_url($url);
        if (!is_array($parts) || !isset($parts['scheme'], $parts['host'])) {
            return '';
        }

        $port = isset($parts['port']) ? ':' . $parts['port'] : '';

        return strtolower($parts['scheme'] . '://' . $parts['host'] . $port);
    }
}
