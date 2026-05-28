<?php

namespace App\Modules\Integration\TaxCore\Service;

use App\Modules\Integration\TaxCore\Domain\TaxCoreConnection;
use App\Shared\Exceptions\BusinessConflictException;

class TaxCoreCertificateService
{
    public function __construct(private readonly TaxCoreEncryptionService $encryptionService) {}


    public function parsePfx(TaxCoreConnection $connection): array
    {
        $pfxBinary = $this->encryptionService->decodeCertificate(
            $connection->getCertificateEncrypted()
        );
        $password = $this->encryptionService->decodePassword(
            $connection->getCertificatePasswordEncrypted()
        );

        // Try the native PHP function first (works for modern PFX files).
        $certs = [];
        if (openssl_pkcs12_read($pfxBinary, $certs, $password) && !empty($certs['cert'])) {
            return $certs;
        }

        // Clear the error queue before the next attempt.
        while (openssl_error_string() !== false) {}

        // Fallback: TaxCore PFX files commonly use legacy PBE algorithms
        // (pbeWithSHAAnd3-KeyTripleDES-CBC) that OpenSSL 3.x disables by default.
        // Use the openssl CLI with the -legacy flag to handle them.
        return $this->parsePfxLegacy($pfxBinary, $password);
    }

    /**
     * Parse a PFX file that uses legacy encryption algorithms (e.g. 3DES / RC2)
     * by invoking the openssl command-line tool with the -legacy flag.
     *
     * The private key is held only in a temp file for the duration of the
     * proc_open call and is deleted in the finally block.
     */
    private function parsePfxLegacy(string $pfxBinary, string $password): array
    {
        $tmpPfx = tempnam(sys_get_temp_dir(), 'nxpfx_');

        try {
            file_put_contents($tmpPfx, $pfxBinary, LOCK_EX);

            $opensslBin = $this->findOpensslBinary();

            $process = proc_open(
                [$opensslBin, 'pkcs12', '-legacy', '-in', $tmpPfx, '-nodes', '-passin', 'pass:' . $password],
                [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
                $pipes
            );

            if (!is_resource($process)) {
                throw new BusinessConflictException(
                    'Failed to parse the certificate. The openssl binary is not available.',
                    500
                );
            }

            fclose($pipes[0]);
            $stdout   = stream_get_contents($pipes[1]);
            $stderr   = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            $exitCode = proc_close($process);

            if ($exitCode !== 0 || !str_contains($stdout, '-----BEGIN CERTIFICATE-----')) {
                throw new BusinessConflictException(
                    'Failed to parse the certificate. Verify the file and password.',
                    400
                );
            }

            preg_match_all('/-----BEGIN CERTIFICATE-----.*?-----END CERTIFICATE-----/s', $stdout, $certMatches);
        preg_match('/-----BEGIN [A-Z ]*PRIVATE KEY-----.*?-----END [A-Z ]*PRIVATE KEY-----/s', $stdout, $keyMatch);
            if (empty($certMatches[0])) {
                throw new BusinessConflictException('No certificate found in the PFX file.', 400);
            }

            return [
                'cert' => $certMatches[0][0],
                'pkey' => $keyMatch[0] ?? '',
            ];

        } finally {
            @unlink($tmpPfx);
        }
    }

    /**
     * Locate the openssl binary, checking common Windows and Linux paths.
     */
    private function findOpensslBinary(): string
    {
        $candidates = ['openssl'];

        if (PHP_OS_FAMILY === 'Windows') {
            $phpDir = dirname(PHP_BINARY);
            $candidates = array_merge($candidates, [
                $phpDir . '\\openssl.exe',
                $phpDir . '\\extras\\openssl\\openssl.exe',
                'C:\\Program Files\\Git\\mingw64\\bin\\openssl.exe',
                'C:\\Program Files\\Git\\usr\\bin\\openssl.exe',
                'C:\\Program Files\\OpenSSL-Win64\\bin\\openssl.exe',
                'C:\\Program Files (x86)\\OpenSSL-Win32\\bin\\openssl.exe',
                'C:\\xampp\\apache\\bin\\openssl.exe',
            ]);
        }

        foreach ($candidates as $bin) {
            $out = @shell_exec(PHP_OS_FAMILY === 'Windows' ? "\"$bin\" version 2>&1" : "$bin version 2>&1");
            if ($out && str_starts_with(trim($out), 'OpenSSL')) {
                return $bin;
            }
        }

        throw new BusinessConflictException(
            'Failed to parse the certificate. The openssl binary is not available on this server.',
            500
        );
    }

    public function extractVsdcUrl(TaxCoreConnection $connection): string
    {
        $certs  = $this->parsePfx($connection);
        $parsed = openssl_x509_parse($certs['cert']);

        if (!$parsed) {
            throw new BusinessConflictException('Failed to read certificate extensions.', 400);
        }

        foreach ($parsed['extensions'] ?? [] as $oid => $value) {
            if (preg_match('/^1\.3\.6\.1\.4\.1\.49952\.\d+\.\d+\.7$/', $oid)) {
                $url = trim($value);
                if (filter_var($url, FILTER_VALIDATE_URL) === false) {
                    throw new BusinessConflictException(
                        'Certificate OID contains an invalid URL: ' . $url,
                        400
                    );
                }
                return $url;
            }
        }

        throw new BusinessConflictException(
            'V-SDC URL OID (1.3.6.1.4.1.49952.X.Y.7) not found in certificate.',
            400
        );
    }

    /**
     * Validates that the certificate has not expired.
     *
     * @throws BusinessConflictException if expired
     */
    public function assertNotExpired(TaxCoreConnection $connection): void
    {
        $certs  = $this->parsePfx($connection);
        $parsed = openssl_x509_parse($certs['cert']);

        if (!$parsed) {
            throw new BusinessConflictException('Failed to read certificate data.', 400);
        }

        $validTo = $parsed['validTo_time_t'] ?? 0;

        if (time() > $validTo) {
            throw new BusinessConflictException(
                'The certificate has expired. Please upload a renewed certificate.',
                400
            );
        }
    }

    /**
     * Returns the plain-text PFX password used to open the certificate file.
     */
    public function getPfxPassword(TaxCoreConnection $connection): string
    {
        return $this->encryptionService->decodePassword(
            $connection->getCertificatePasswordEncrypted()
        );
    }

    /**
     * Returns the plain-text PAC value sent as HTTP header on every API request.
     * This is separate from the PFX password — TaxCore provides them independently.
     */
    public function getPac(TaxCoreConnection $connection): string
    {
        return $this->encryptionService->decodePac(
            $connection->getPacEncrypted()
        );
    }
}
