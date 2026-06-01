<?php

namespace App\Modules\Integration\Xero\Service;
use App\Modules\Integration\Xero\Domain\XeroConnection;
use App\Shared\Helpers\ErrorResponseHelper;
use App\Shared\Helpers\HttpClientHelper;
use Illuminate\Http\Client\Response;

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
        
        $response =  HttpClientHelper::http()->withToken($connection->getAccessToken())
        ->withHeaders([
                'Xero-tenant-id' => $connection->getTenantId(),
                "Accept" => "application/json",
            ])
            ->get('https://api.xero.com/api.xro/2.0/' . $endpoint);
        
        ErrorResponseHelper::handleErrors($response);
        return $response;
    }

    public function post(XeroConnection $connection, string $endpoint, array $data): Response
    {
        $connection = $this->refreshIfNeeded($connection);
        
        $response = HttpClientHelper::http()->withToken($connection->getAccessToken())
        ->withHeaders([
                'Xero-tenant-id' => $connection->getTenantId(),
                "Accept" => "application/json",
            ])
            ->post('https://api.xero.com/api.xro/2.0/' . $endpoint, $data); 
        
        ErrorResponseHelper::handleErrors($response);
        return $response;
    }

    public function refreshIfNeeded(XeroConnection $connection): XeroConnection
    {
        if(now()->greaterThanOrEqualTo($connection->getExpiresAt())){
            return $this->oauthService->refreshAccessToken($connection);
        }
        return $connection;
    }
}