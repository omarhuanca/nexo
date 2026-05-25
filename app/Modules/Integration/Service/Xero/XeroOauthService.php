<?php
namespace App\Modules\Integration\Service\Xero;

use App\Shared\Exceptions\BusinessConflictException;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use League\OAuth2\Client\Provider\GenericProvider;

class XeroOauthService
{
    private GenericProvider $provider;

    public function __construct()
    {
        $this->provider = new GenericProvider([
            'clientId' => config('xero.client_id'),
            'clientSecret' => config('xero.client_secret'),
            'redirectUri' => config('xero.redirect_uri'),
            'urlAuthorize' => config('xero.url_authorize'),
            'urlAccessToken' => config('xero.url_access_token'),
            'urlResourceOwnerDetails' => config('xero.url_resource_owner'),
        ], [
            'httpClient' => $this->httpClient(),
        ]);
    }

    private function httpClient(): Client
    {
        return new Client(['verify' => false]);
    }

    public function getAuthorizationUrl(): string
    {
        return $this->provider->getAuthorizationUrl(['scope' => config('xero.scopes')]);
    }

    public function xeroCallback(Request $request){
        $response = Http::withoutVerifying()->asForm()->post(
            'https://identity.xero.com/connect/token',
            [
                'grant_type' => 'authorization_code',
                'client_id' => config('xero.client_id'),
                'client_secret' => config('xero.client_secret'),
                'redirect_uri' => config('xero.redirect_uri'),
                'code' => $request->input('code'),
            ]

        );

         if (!$response->successful()) {
            throw new BusinessConflictException('Failed to retrieve access token from Xero: ' . $response->body());
        }

        return $response->json();
    }
}   