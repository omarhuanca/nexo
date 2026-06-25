<?php

namespace App\Modules\Integration\Xero\Service;

use App\Events\Xero\XeroApiCall;
use App\Events\Xero\XeroTokenRefreshed;
use App\Modules\Integration\Xero\Domain\XeroConnection;
use App\Shared\Helpers\ErrorResponseHelper;
use App\Shared\Helpers\HttpClientHelper;
use Illuminate\Http\Client\Response;
use Throwable;

class XeroApiService
{
    private XeroOauthService $oauthService;

    public function __construct(XeroOauthService $oauthService)
    {
        $this->oauthService = $oauthService;
    }


    public function get(XeroConnection $connection, string $endpoint): Response
    {
        $connection = $this->refreshIfNeeded($connection);

        $startedAt = (int) (microtime(true) * 1000);

        try {
            $response = HttpClientHelper::http()->withToken($connection->getAccessToken())
                ->withHeaders([
                    'Xero-tenant-id' => $connection->getTenantId(),
                    "Accept" => "application/json",
                ])
                ->get('https://api.xero.com/api.xro/2.0/' . $endpoint);
        } catch (Throwable $e) {
            throw $e;
        }

        $durationMs = (int) (microtime(true) * 1000) - $startedAt;
        event(new XeroApiCall('GET', $endpoint, $response->status(), $durationMs, $connection->getTenantId()));

        ErrorResponseHelper::handleErrors($response);
        return $response;
    }

    public function post(XeroConnection $connection, string $endpoint, array $data): Response
    {
        $connection = $this->refreshIfNeeded($connection);

        $startedAt = (int) (microtime(true) * 1000);

        try {
            $response = HttpClientHelper::http()->withToken($connection->getAccessToken())
                ->withHeaders([
                    'Xero-tenant-id' => $connection->getTenantId(),
                    "Accept" => "application/json",
                ])
                ->post('https://api.xero.com/api.xro/2.0/' . $endpoint, $data);
        } catch (Throwable $e) {
            throw $e;
        }

        $durationMs = (int) (microtime(true) * 1000) - $startedAt;
        event(new XeroApiCall('POST', $endpoint, $response->status(), $durationMs, $connection->getTenantId()));

        ErrorResponseHelper::handleErrors($response);
        return $response;
    }

    public function refreshIfNeeded(XeroConnection $connection): XeroConnection
    {
        if (now()->greaterThanOrEqualTo($connection->getExpiresAt())) {
            $refreshed = $this->oauthService->refreshAccessToken($connection);

            event(new XeroTokenRefreshed(
                $connection->getId(),
                $connection->getTenantId(),
                1800,
            ));

            return $refreshed;
        }
        return $connection;
    }
}