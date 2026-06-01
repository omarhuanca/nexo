<?php

namespace App\Shared\Helpers;

class TemporaryCertificateHelper
{
    public static function create(string $certificateBinary): string
    {
        $path = tempnam(sys_get_temp_dir(), 'taxcore_');
        file_put_contents($path, $certificateBinary);
        return $path;
    }

    public static function delete(string $path): void
    {
        if ($path && file_exists($path)) unlink($path);
    }
}