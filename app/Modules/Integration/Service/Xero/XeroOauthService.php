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

    public function xeroCallback(Request $request): array
    {
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

        if (!$response->successful())
            throw new BusinessConflictException('Failed to retrieve access token from Xero: ' . $response->body());

        $tokens = $response->json();

        $connections = $this->getConnections($tokens['access_token']);

        if (empty($connections)) throw new BusinessConflictException('No Xero connections found for this account.');
        if(count($connections) > 1) throw new BusinessConflictException('Multiple Xero connections found for this account. Please disconnect other connections and try again.');

        return [
            'tokens' => $tokens,
            'connection' => $connections[0],
        ];
    }

    public function getConnections(string $accessToken)
    {
        $response = Http::withoutVerifying()
        ->withToken($accessToken)
        ->get('https://api.xero.com/connections');

        if (!$response->successful()) throw new BusinessConflictException('Failed to retrieve connections from Xero: ' . $response->body());

        return $response->json();
    }
}   