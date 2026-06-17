<?php
namespace App\Modules\Integration\Xero\Service;

use App\Modules\Integration\Xero\Domain\XeroConnection;
use App\Modules\Integration\Xero\Repository\XeroConnectionRepository;
use App\Shared\Exceptions\BusinessConflictException;
use App\Shared\Helpers\HttpClientHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use League\OAuth2\Client\Provider\GenericProvider;

class XeroOauthService
{
    private GenericProvider $provider;
    private XeroConnectionRepository $repository;

    public function __construct(XeroConnectionRepository $xeroConnectionRepository)
    {
        $this->repository = $xeroConnectionRepository;

        $this->provider = new GenericProvider([
            'clientId' => config('xero.client_id'),
            'clientSecret' => config('xero.client_secret'),
            'redirectUri' => config('xero.redirect_uri'),
            'urlAuthorize' => config('xero.url_authorize'),
            'urlAccessToken' => config('xero.url_access_token'),
            'urlResourceOwnerDetails' => config('xero.url_resource_owner'),
        ], [
            'httpClient' => HttpClientHelper::guzzle(),
        ]);
    }
    

    public function getAuthorizationUrl(int $nexoOrganizationId): string
    {
        $state = $this->buildState($nexoOrganizationId);

        return $this->provider->getAuthorizationUrl([
            'scope' => config('xero.scopes'),
            'state' => $state,
        ]);
    }

    public function extractNexoOrganizationIdFromState(string $state): int
    {
        if (!str_contains($state, '.')) {
            throw new BusinessConflictException('Invalid OAuth state format.');
        }

        [$payload, $signature] = explode('.', $state, 2);

        $expected = hash_hmac('sha256', $payload, config('app.key'));

        if (!hash_equals($expected, $signature)) {
            throw new BusinessConflictException('Invalid OAuth state: signature verification failed.');
        }

        $data = json_decode(base64_decode($payload), true);

        if (empty($data['nexo_organization_id'])) {
            throw new BusinessConflictException('Invalid OAuth state: missing organization context.');
        }

        return (int) $data['nexo_organization_id'];
    }

    private function buildState(int $nexoOrganizationId): string
    {
        $payload   = base64_encode(json_encode([
            'nexo_organization_id' => $nexoOrganizationId,
            'nonce' => Str::random(16),
        ]));
        $signature = hash_hmac('sha256', $payload, config('app.key'));

        return $payload . '.' . $signature;
    }

    public function xeroCallback(Request $request, int $organizationId): array
    {
        $response = HttpClientHelper::http()->asForm()->post(
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

        event(new \App\Events\Xero\XeroOAuthCallbackSuccess(
            $organizationId,
            $connections[0]['tenantId'] ?? 'unknown',
            $connections[0]['tenantName'] ?? 'unknown',
        ));

        return [
            'tokens' => $tokens,
            'connection' => $connections[0],
        ];
    }

    public function getConnections(string $accessToken)
    {
        $response = HttpClientHelper::http()
        ->withToken($accessToken)
        ->get('https://api.xero.com/connections');

        if (!$response->successful()) throw new BusinessConflictException('Failed to retrieve connections from Xero: ' . $response->body());

        return $response->json();
    }

    public function refreshAccessToken(XeroConnection $connection): XeroConnection
    {
        $response = HttpClientHelper::http()->asForm()->post(
            'https://identity.xero.com/connect/token',
            [
                'grant_type' => 'refresh_token',
                'client_id' => config('xero.client_id'),
                'client_secret' => config('xero.client_secret'),
                'refresh_token' => $connection->getRefreshToken(),
            ]
        );

        if (!$response->successful()) throw new BusinessConflictException('Failed to refresh access token from Xero: ' . $response->body());
        
        $tokens = $response->json();

        $connection->setAccessToken($tokens['access_token']);
        $connection->setRefreshToken($tokens['refresh_token']);
        $connection->setExpiresAt(now()->addSeconds($tokens['expires_in']));
        
        $this->repository->save($connection);
        return $connection;
    }
}