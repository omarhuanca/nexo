<?php
namespace App\Modules\Integration\Xero\Controller;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Modules\Integration\Xero\Service\XeroApiService;
use App\Modules\Integration\Xero\Service\XeroConnectionService;
use App\Modules\Integration\Xero\Service\XeroOauthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Annotations as OA;

/**
 * @OA\Tag(
 *     name="Xero Integration",
 *     description="Endpoints for connecting and interacting with the Xero accounting platform via OAuth2."
 * )
 */
class XeroController extends Controller
{
    public function __construct(
        private readonly XeroOauthService $xeroOauthService,
        private readonly XeroConnectionService $xeroConnectionService,
        private readonly XeroApiService $xeroApiService) {}

    /**
     * @OA\Get(
     *     path="/api/integrations/xero/connect",
     *     tags={"Xero Integration"},
     *     summary="Initiate Xero OAuth2 authorization",
     *     description="Redirects the user to the Xero authorization page to begin the OAuth2 flow. After the user grants access, Xero will redirect to the callback URL.",
     *     operationId="xeroConnect",
     *     @OA\Response(
     *         response=302,
     *         description="Redirect to Xero authorization page"
     *     )
     * )
     */
    public function connect()
    {
        return redirect(
            $this->xeroOauthService->getAuthorizationUrl()
        );
    }

    /**
     * @OA\Get(
     *     path="/api/integrations/xero/callback",
     *     tags={"Xero Integration"},
     *     summary="Xero OAuth2 callback",
     *     description="Handles the callback from Xero after the user authorizes the app. Exchanges the authorization code for tokens and persists the connection. This endpoint is called automatically by Xero — do not call it directly.",
     *     operationId="xeroCallback",
     *     @OA\Parameter(
     *         name="code",
     *         in="query",
     *         required=true,
     *         description="Authorization code returned by Xero.",
     *         @OA\Schema(type="string", example="abc123def456")
     *     ),
     *     @OA\Parameter(
     *         name="state",
     *         in="query",
     *         required=true,
     *         description="CSRF state token returned by Xero to verify the request origin.",
     *         @OA\Schema(type="string", example="145486afa9b821b18dc677cf3e1db200")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Xero connection established successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Connected to Xero successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="b2c3d4e5-f6a7-8901-b2c3-d4e5f6a78901"),
     *                 @OA\Property(property="tenant_name", type="string", example="Demo Company (AU)"),
     *                 @OA\Property(property="tenant_type", type="string", example="ORGANISATION"),
     *                 @OA\Property(property="access_token", type="string", example="eyJhbGciOiJSUzI1NiIsInR5cCI6IkpXVCJ9..."),
     *                 @OA\Property(property="refresh_token", type="string", example="r3fr3shT0k3n..."),
     *                 @OA\Property(property="expires_at", type="string", format="date-time", example="2026-05-25T13:00:00.000000Z"),
     *                 @OA\Property(property="scopes", type="string", example="openid email profile offline_access accounting.settings accounting.transactions accounting.contacts"),
     *                 @OA\Property(property="active", type="boolean", example=true)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Invalid or expired authorization code",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Invalid authorization code."),
     *             @OA\Property(property="data", type="array", @OA\Items())
     *         )
     *     )
     * )
     */
    public function callback(Request $request): JsonResponse
    {
        $data = $this->xeroOauthService->xeroCallback($request);
        $connection = $this->xeroConnectionService->saveOrUpdate($data['tokens'], $data['connection']);

        return ApiResponse::success("Connected to Xero successfully", 200, $connection);
    }

    /**
     * @OA\Get(
     *     path="/api/integrations/xero/{connectionId}/contacts",
     *     tags={"Xero Integration"},
     *     summary="List Xero contacts",
     *     description="Fetches the list of contacts from Xero for the given connection.",
     *     operationId="xeroGetContacts",
     *     @OA\Parameter(
     *         name="connectionId",
     *         in="path",
     *         required=true,
     *         description="ID of the Xero connection.",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Xero contacts fetched successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Fetched Xero contacts successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="Contacts",
     *                     type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="ContactID", type="string", format="uuid", example="a1b2c3d4-e5f6-7890-a1b2-c3d4e5f67890"),
     *                         @OA\Property(property="Name", type="string", example="John Smith"),
     *                         @OA\Property(property="EmailAddress", type="string", format="email", example="john.smith@example.com"),
     *                         @OA\Property(property="IsSupplier", type="boolean", example=false),
     *                         @OA\Property(property="IsCustomer", type="boolean", example=true)
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Xero connection not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Xero connection not found with ID: 99"),
     *             @OA\Property(property="data", type="array", @OA\Items())
     *         )
     *     )
     * )
     */
    public function getContacts(string $connectionId): JsonResponse
    {
        $connection = $this->xeroConnectionService->findById($connectionId);
        $contacts = $this->xeroApiService->get($connection, 'Contacts')->json();

        return ApiResponse::success("Fetched Xero contacts successfully", 200, $contacts);
    }
}