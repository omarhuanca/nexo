<?php

namespace App\Shared\Logging;

use Illuminate\Support\Facades\Log;

class LoggerService
{
    public const CHANNEL_AUDIT = 'audit';

    public function audit(string $level, string $message, array $context = []): void
    {
        $sanitized = $this->sanitize($context);
        Log::channel(self::CHANNEL_AUDIT)->{$level}($message, $sanitized);
    }

    public function info(string $message, array $context = []): void
    {
        $this->audit('info', $message, $context);
    }

    public function warning(string $message, array $context = []): void
    {
        $this->audit('warning', $message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->audit('error', $message, $context);
    }

    public function channel(string $name)
    {
        return Log::channel($name);
    }

    private const SENSITIVE_KEYS = [
        'password',
        'pac',
        'access_token',
        'refresh_token',
        'certificate',
        'token',
        'authorization',
        'cookie',
        'client_secret',
        'secret',
    ];

    public function sanitize(array $data): array
    {
        $sanitized = [];

        foreach ($data as $key => $value) {
            $lowerKey = strtolower((string) $key);

            foreach (self::SENSITIVE_KEYS as $sensitive) {
                if (str_contains($lowerKey, $sensitive)) {
                    $sanitized[$key] = '***REDACTED***';
                    continue 2;
                }
            }

            if (is_array($value)) {
                $sanitized[$key] = $this->sanitize($value);
            } else {
                $sanitized[$key] = $value;
            }
        }

        return $sanitized;
    }
}
