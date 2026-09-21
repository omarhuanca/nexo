<?php
namespace App\Modules\Integration\Xero\Controller;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Modules\Integration\Xero\Service\XeroApiService;
use App\Modules\Integration\Xero\Service\XeroConnectionService;
use App\Modules\Integration\Xero\Service\XeroOauthService;
use App\Shared\Exceptions\BusinessConflictException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use OpenApi\Attributes as OA;

#[OA\Tag(
    name:"Xero Integration",
    description:"Endpoints for connecting and interacting with the Xero accounting platform via OAuth2."
)]
class XeroController extends Controller
{
    public function __construct(
        private readonly XeroOauthService $xeroOauthService,
        private readonly XeroConnectionService $xeroConnectionService,
        private readonly XeroApiService $xeroApiService) {}

    #[OA\Get(
        path: '/api/integrations/xero/connect',
        tags: ['Xero Integration'],
        summary: 'Initiate Xero OAuth2 authorization',
        description: 'Encodes the organization_id (and optional popup mode) into a signed OAuth `state` parameter and redirects to the Xero authorization page to begin the OAuth2 flow. After the user grants access, Xero redirects to the callback URL where the connection is saved and linked to this organization.',
        operationId: 'xeroConnect',
        parameters: [
            new OA\Parameter(name: 'organization_id',in: 'query',required: true,description: 'ID of the organization to link the Xero connection to.',schema: new OA\Schema(type: 'integer', example: 1)),
            new OA\Parameter(name: 'mode',in: 'query',required: false,description: 'Set to `popup` when /connect is opened in a popup window, so the callback responds with a `postMessage` page instead of redirecting the browser.',schema: new OA\Schema(type: 'string', enum: ['popup']))
        ],
        responses: [
            new OA\Response(
                response: 302,
                description: 'Redirect to Xero authorization page.'
            ),
            new OA\Response(
                response: 422,
                description: 'Validation error — organization_id missing or not found.'
            )
        ]
    )]
    public function connect(Request $request)
    {
        $request->validate([
            'organization_id' => 'required|integer|exists:organizations,id',
            'mode' => 'nullable|in:popup',
        ]);

        return redirect(
            $this->xeroOauthService->getAuthorizationUrl(
                $request->integer('organization_id'),
                $request->input('mode')
            )
        );
    }

    #[OA\Get(
        path: '/api/integrations/xero/callback',
        tags: ['Xero Integration'],
        summary: 'Xero OAuth2 callback',
        description: 'Handles the redirect from Xero after the user authorizes the app. Exchanges the authorization code for tokens, links the connection to the organization encoded in the state parameter (set during /connect), and persists the XeroConnection record. If /connect was initiated with `mode=popup`, responds with an HTML page that posts a `{type: "xero-oauth-result", status, message}` message to the window that opened the popup and closes itself. Otherwise, redirects the browser back to the frontend integrations page with a `xero` status query parameter (`connected` or `error`). This endpoint is called automatically by Xero — do not call it directly.',
        operationId: 'xeroCallback',
        parameters: [
            new OA\Parameter(name: 'code',in: 'query',required: true,description: 'Authorization code returned by Xero.',schema: new OA\Schema(type: 'string', example: 'abc123def456')),
            new OA\Parameter(name: 'state',in: 'query',required: true,description: 'CSRF state token returned by Xero to verify the request origin.',schema: new OA\Schema(type: 'string', example: '145486afa9b821b18dc677cf3e1db200'))
        ],
        responses: [
            new OA\Response(
                response: 302,
                description: 'Redirect to the frontend integrations page (default flow), with `?xero=connected` on success or `?xero=error&message=...` on failure.'
            ),
            new OA\Response(
                response: 200,
                description: 'Popup flow only (state was built with `mode=popup`): an HTML page that posts the result to `window.opener` via `postMessage` and closes itself.',
                content: new OA\MediaType(mediaType: 'text/html')
            )
        ]
    )]
    public function callback(Request $request): RedirectResponse|Response
    {
        $state = $request->input('state');

        if (!$state) {
            return $this->respond(null, 'error', 'Invalid OAuth callback: missing state parameter.');
        }

        $mode = null;

        try {
            $decoded = $this->xeroOauthService->decodeState($state);
            $organizationId = $decoded['organization_id'];
            $mode = $decoded['mode'];

            $data = $this->xeroOauthService->xeroCallback($request, $organizationId);
            $this->xeroConnectionService->saveOrUpdate($data['tokens'], $data['connection'], $organizationId);

            return $this->respond($mode, 'connected');
        } catch (BusinessConflictException $e) {
            return $this->respond($mode, 'error', $e->getMessage());
        } catch (\Throwable $e) {
            report($e);

            return $this->respond($mode, 'error', 'Unexpected error connecting to Xero.');
        }
    }

    private function respond(?string $mode, string $status, ?string $message = null): RedirectResponse|Response
    {
        if ($mode === 'popup') {
            $targetOrigin = $this->frontendOrigin();
            $payload = json_encode([
                'type' => 'xero-oauth-result',
                'status' => $status,
                'message' => $message,
            ]);

            $html = <<<HTML
            <!doctype html>
            <html>
            <body>
            <script>
            (function () {
                if (window.opener) {
                    window.opener.postMessage({$payload}, "{$targetOrigin}");
                }
                window.close();
            })();
            </script>
            </body>
            </html>
            HTML;

            return response($html)->header('Content-Type', 'text/html');
        }

        $frontendUrl = config('xero.frontend_callback_url');
        $query = $message ? "xero={$status}&message=" . urlencode($message) : "xero={$status}";

        return redirect("{$frontendUrl}?{$query}");
    }

    private function frontendOrigin(): string
    {
        $parts = parse_url(config('xero.frontend_callback_url'));

        $origin = ($parts['scheme'] ?? 'http') . '://' . ($parts['host'] ?? 'localhost');

        if (isset($parts['port'])) {
            $origin .= ':' . $parts['port'];
        }

        return $origin;
    }

    #[OA\Get(
        path: '/api/integrations/xero/status',
        tags: ['Xero Integration'],
        summary: 'Get Xero connection status for an organization',
        description: 'Returns whether the given organization currently has an active Xero connection, and a summary of it if so.',
        operationId: 'xeroStatus',
        parameters: [
            new OA\Parameter(name: 'organization_id',in: 'query',required: true,description: 'ID of the organization to check.',schema: new OA\Schema(type: 'integer', example: 1))
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Xero connection status for the organization.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Active Xero connection found.'),
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'connected', type: 'boolean', example: true),
                                new OA\Property(property: 'tenant_name', type: 'string', example: 'Demo Company (AU)'),
                                new OA\Property(property: 'tenant_type', type: 'string', example: 'ORGANISATION'),
                                new OA\Property(property: 'expires_at', type: 'string', format: 'date-time', example: '2026-05-25T13:00:00.000000Z')
                            ]
                        )
                    ]
                )
            ),
            new OA\Response(
                response: 422,
                description: 'Validation error — organization_id missing or not found.'
            )
        ]
    )]
    public function status(Request $request): JsonResponse
    {
        $request->validate(['organization_id' => 'required|integer|exists:organizations,id']);

        $connection = $this->xeroConnectionService->findActiveByOrganizationOrNull(
            $request->integer('organization_id')
        );

        if (!$connection) {
            return ApiResponse::success('No active Xero connection for this organization.', 200, [
                'connected' => false,
            ]);
        }

        return ApiResponse::success('Active Xero connection found.', 200, [
            'connected' => true,
            'tenant_name' => $connection->getTenantName(),
            'tenant_type' => $connection->getTenantType(),
            'expires_at' => $connection->getExpiresAt(),
        ]);
    }

    #[OA\Delete(
        path: '/api/integrations/xero/disconnect',
        tags: ['Xero Integration'],
        summary: 'Disconnect the Xero connection for an organization',
        description: 'Revokes the Xero tokens and deactivates the connection for the given organization.',
        operationId: 'xeroDisconnect',
        parameters: [
            new OA\Parameter(name: 'organization_id',in: 'query',required: true,description: 'ID of the organization to disconnect.',schema: new OA\Schema(type: 'integer', example: 1))
        ],
        responses: [
            new OA\Response(response: 200,description: 'Xero connection disconnected.'),
            new OA\Response(response: 404,description: 'No active Xero connection found for this organization.'),
            new OA\Response(response: 422,description: 'Validation error — organization_id missing or not found.')
        ]
    )]
    public function disconnect(Request $request): JsonResponse
    {
        $request->validate(['organization_id' => 'required|integer|exists:organizations,id']);

        $this->xeroConnectionService->disconnect($request->integer('organization_id'));

        return ApiResponse::success('Xero connection disconnected.', 200);
    }

    #[OA\Get(
        path: '/api/integrations/xero/{connectionId}/contacts',
        tags: ['Xero Integration'],
        summary: 'List Xero contacts for a connection',
        description: 'Fetches the list of contacts from Xero using the given connection. The connection must belong to an organization. Tokens are refreshed automatically if expired.',
        operationId: 'xeroGetContacts',
        parameters: [
            new OA\Parameter(
                name: 'connectionId',
                in: 'path',
                required: true,
                description: 'ID of the Xero connection (returned by the callback endpoint).',
                schema: new OA\Schema(type: 'integer', example: 1)
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Xero contacts fetched successfully.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Fetched Xero contacts successfully'),
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            properties: [
                                new OA\Property(
                                    property: 'Contacts',
                                    type: 'array',
                                    items: new OA\Items(
                                        properties: [
                                            new OA\Property(property: 'ContactID', type: 'string', format: 'uuid', example: 'a1b2c3d4-e5f6-7890-a1b2-c3d4e5f67890'),
                                            new OA\Property(property: 'Name', type: 'string', example: 'John Smith'),
                                            new OA\Property(property: 'EmailAddress', type: 'string', format: 'email', example: 'john.smith@example.com'),
                                            new OA\Property(property: 'IsSupplier', type: 'boolean', example: false),
                                            new OA\Property(property: 'IsCustomer', type: 'boolean', example: true)
                                        ],
                                        type: 'object'
                                    )
                                )
                            ]
                        )
                    ]
                )
            ),
            new OA\Response(
                response: 404,
                description: 'Xero connection not found.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: false),
                        new OA\Property(property: 'message', type: 'string', example: 'Resource not found.')
                    ]
                )
            )
        ]
    )]
    public function getContacts(string $connectionId): JsonResponse
    {
        $connection = $this->xeroConnectionService->findById($connectionId);
        $contacts = $this->xeroApiService->get($connection, 'Contacts')->json();

        return ApiResponse::success("Fetched Xero contacts successfully", 200, $contacts);
    }
}