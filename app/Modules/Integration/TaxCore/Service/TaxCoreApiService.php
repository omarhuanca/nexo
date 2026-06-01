<?php

namespace App\Modules\Integration\TaxCore\Service;

use App\Modules\Integration\TaxCore\Domain\TaxCoreConnection;
use App\Shared\Helpers\ErrorResponseHelper;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class TaxCoreApiService
{
    public function __construct(
        private readonly TaxCoreCertificateService $certificateService,
    ) {}

    public function get(TaxCoreConnection $connection, string $endpoint): Response
    {
        $response = $this->buildClient($connection)->get($endpoint);
        ErrorResponseHelper::handleErrors($response);

        return $response;
    }

    public function post(TaxCoreConnection $connection, string $endpoint, array $data): Response
    {
        $response = $this->buildClient($connection)->post($endpoint, $data);
        ErrorResponseHelper::handleErrors($response);

        return $response;
    }

    private function buildClient(TaxCoreConnection $connection): PendingRequest
    {
        $certs = $this->certificateService->parsePfx($connection);
        $pac   = $this->certificateService->getPac($connection);
        $environment = $connection->getEnvironment();


        $caBundle = storage_path("certs/taxcore_{$environment}_ca_bundle.pem");
        $verify   = file_exists($caBundle) ? $caBundle : storage_path('certs/cacert.pem');

        return Http::baseUrl(rtrim($connection->getVsdcUrl(), '/'))
            ->withHeaders([
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'PAC' => $pac,
            ])
            ->withOptions([
                'verify' => $verify,
                'curl' => [
                    CURLOPT_SSLCERT_BLOB => $certs['cert'],
                    CURLOPT_SSLKEY_BLOB  => $certs['pkey'],
                ],
            ]);
    }
}
