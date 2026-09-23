<?php

namespace App\Shared\Security;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Rejects callback URLs that would make nexo call into private infrastructure (SSRF):
 * non-HTTPS schemes, localhost, and literal private/reserved IP addresses.
 * Hostnames are re-checked against their resolved IPs at delivery time.
 */
class PublicHttpsUrl implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!is_string($value) || !self::isAllowed($value, resolve: false)) {
            $fail('The :attribute must be a public HTTPS URL.');
        }
    }

    public static function isAllowed(string $url, bool $resolve = true): bool
    {
        $parts = parse_url($url);

        if (($parts['scheme'] ?? null) !== 'https' || empty($parts['host'])) return false;

        $host = strtolower(trim($parts['host'], '[]'));

        if ($host === 'localhost' || str_ends_with($host, '.localhost') || str_ends_with($host, '.internal')) return false;

        if (filter_var($host, FILTER_VALIDATE_IP)) return self::isPublicIp($host);

        if (!$resolve) return true;

        $ips = gethostbynamel($host) ?: [];
        if ($ips === []) return false;

        foreach ($ips as $ip) {
            if (!self::isPublicIp($ip)) return false;
        }

        return true;
    }

    private static function isPublicIp(string $ip): bool
    {
        return (bool) filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
    }
}
