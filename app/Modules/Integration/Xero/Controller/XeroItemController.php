<?php

namespace App\Modules\Integration\Xero\Controller;

use App\Http\Controllers\Controller;
use App\Http\Requests\XeroItemsRequest;
use App\Http\Responses\ApiResponse;
use App\Modules\Integration\Xero\Service\XeroConnectionService;
use App\Modules\Integration\Xero\Service\XeroItemService;
use Illuminate\Http\JsonResponse;
use OpenApi\Annotations as OA;

/**
 * @OA\Tag(
 *     name="Xero Products",
 *     description="Endpoints for syncing products (Items) to the Xero accounting platform. Requires an active Xero connection for the organization and a valid connector Bearer token."
 * )
 */
class XeroItemController extends Controller
{
    public function __construct(
        private readonly XeroConnectionService $connectionService,
        private readonly XeroItemService $itemService,
    ) {}

    /**
     * @OA\Post(
     *     path="/api/integrations/products",
     *     tags={"Xero Products"},
     *     summary="Sync products to Xero",
     *     description="Creates or updates one or more Items (Products & Services) in the Xero account linked to the connector's organization. Xero performs an upsert by `code` — if the item already exists it is updated, otherwise it is created. The calling system has full control over pricing and account codes. Requires the connector's Bearer token.",
     *     operationId="syncXeroItems",
     *     security={{"connectorToken": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"items"},
     *             @OA\Property(
     *                 property="items",
     *                 type="array",
     *                 minItems=1,
     *                 @OA\Items(
     *                     required={"code","name","salePrice","costPrice","salesAccountCode","purchaseAccountCode"},
     *                     @OA\Property(property="code", type="string",  maxLength=30,   example="PROD-001",  description="Unique item code used as the upsert key in Xero."),
     *                     @OA\Property(property="name", type="string",  maxLength=50,   example="Producto A", description="Display name of the item."),
     *                     @OA\Property(property="description", type="string",  maxLength=4000, example="Descripción del producto.", nullable=true),
     *                     @OA\Property(property="salePrice", type="number",  format="float", example=50.00, description="Unit sale price (SalesDetails.UnitPrice in Xero)."),
     *                     @OA\Property(property="costPrice", type="number",  format="float", example=35.00, description="Unit cost price (PurchaseDetails.UnitPrice in Xero)."),
     *                     @OA\Property(property="salesAccountCode", type="string",  maxLength=10,   example="200", description="Xero account code for sales (e.g. Revenue account)."),
     *                     @OA\Property(property="purchaseAccountCode", type="string",  maxLength=10,   example="310", description="Xero account code for purchases (e.g. COGS account).")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Items synced successfully. Returns the raw Xero API response.",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string",  example="Items synced to Xero successfully."),
     *             @OA\Property(property="data",    type="object",  description="Raw Xero Items API response.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized — missing or invalid connector token."
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="No active Xero connection found for this organization."
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error — missing or invalid fields."
     *     )
     * )
     */
    public function sync(XeroItemsRequest $request): JsonResponse
    {
        $connector = $request->attributes->get('connector');
        $connection = $this->connectionService->findActiveByOrganization($connector->getOrganizationId());

        $result = $this->itemService->syncItems($connection, $request->validated()['items']);

        return ApiResponse::success('Items synced to Xero successfully.', 200, $result);
    }
}
