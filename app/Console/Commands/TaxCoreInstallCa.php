<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class TaxCoreInstallCa extends Command
{
    protected $signature = 'taxcore:install-ca
                            {root : Path to the Root CA .cer file (DER format)}
                            {issuing : Path to the Issuing CA .cer file (DER format)}
                            {--environment=sandbox : Target environment (sandbox or production)}';

    protected $description = 'Converts TaxCore CA certificates from DER to PEM and builds the CA bundle used for SSL verification.';

    public function handle(): int
    {
        $rootPath    = $this->argument('root');
        $issuingPath = $this->argument('issuing');
        $environment = $this->option('environment');

        if (!in_array($environment, ['sandbox', 'production'])) {
            $this->error("Invalid environment '{$environment}'. Use 'sandbox' or 'production'.");
            return self::FAILURE;
        }

        foreach (['root' => $rootPath, 'issuing' => $issuingPath] as $label => $path) {
            if (!file_exists($path)) {
                $this->error("File not found ({$label}): {$path}");
                return self::FAILURE;
            }
        }

        $rootPem    = $this->derToPem($rootPath);
        $issuingPem = $this->derToPem($issuingPath);

        if ($rootPem === null) {
            $this->error("Failed to parse Root CA. Make sure the file is a valid DER-encoded X.509 certificate.");
            return self::FAILURE;
        }

        if ($issuingPem === null) {
            $this->error("Failed to parse Issuing CA. Make sure the file is a valid DER-encoded X.509 certificate.");
            return self::FAILURE;
        }

        $bundlePath = storage_path("certs/taxcore_{$environment}_ca_bundle.pem");
        $bundle     = $rootPem . PHP_EOL . $issuingPem;

        if (file_put_contents($bundlePath, $bundle) === false) {
            $this->error("Could not write bundle to: {$bundlePath}");
            return self::FAILURE;
        }

        $this->info("CA bundle created: {$bundlePath}");
        $this->line('  Root CA: ' . $this->subjectFromPem($rootPem));
        $this->line('  Issuing CA: ' . $this->subjectFromPem($issuingPem));
        $this->newLine();
        $this->info("All companies connecting to the '{$environment}' environment will now use this bundle for SSL verification.");

        return self::SUCCESS;
    }

    private function derToPem(string $path): ?string
    {
        $der  = file_get_contents($path);
        $cert = openssl_x509_read(
            '-----BEGIN CERTIFICATE-----' . PHP_EOL .
            chunk_split(base64_encode($der), 64, PHP_EOL) .
            '-----END CERTIFICATE-----'
        );

        if ($cert === false) {
            return null;
        }

        openssl_x509_export($cert, $pem);
        return $pem;
    }

    private function subjectFromPem(string $pem): string
    {
        $cert   = openssl_x509_read($pem);
        $parsed = openssl_x509_parse($cert);
        return $parsed['subject']['CN'] ?? 'Unknown';
    }
}
