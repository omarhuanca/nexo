<?php

namespace App\Modules\Integration\TaxCore\Controller;

use App\Http\Controllers\Controller;
use App\Http\Requests\TaxCoreConnectionRequest;
use App\Http\Requests\TaxCoreInvoiceRequest;
use App\Http\Responses\ApiResponse;
use App\Modules\Integration\TaxCore\Service\TaxCoreApiService;
use App\Modules\Integration\TaxCore\Service\TaxCoreConnectionService;
use Illuminate\Http\Request;
use OpenApi\Annotations as OA;

/**
 * @OA\Tag(
 *     name="TaxCore",
 *     description="Endpoints for managing TaxCore fiscal integration. Requires an active TaxCore connection for the organization (registered via /connect)."
 * )
 */
class TaxCoreController extends Controller
{
    public function __construct(
        private readonly TaxCoreConnectionService $connectionService,
        private readonly TaxCoreApiService $apiService,
    ) {}

    /**
     * @OA\Post(
     *     path="/api/integrations/taxcore/connect",
     *     tags={"TaxCore"},
     *     summary="Register a TaxCore connection for an organization",
     *     description="Uploads a PFX/P12 certificate and registers the TaxCore V-SDC connection for an organization. The certificate must contain OID 1.3.6.1.4.1.58220.2.7 (V-SDC URL). Only one active connection per organization is allowed.",
     *     operationId="taxcoreConnect",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"organization_id","certificate","password","pac","environment"},
     *                 @OA\Property(property="organization_id", type="integer", example=1, description="ID of the organization to register the connection for."),
     *                 @OA\Property(property="certificate", type="string", format="binary", description="PFX or P12 certificate file issued by TaxCore."),
     *                 @OA\Property(property="password", type="string", example="WKURRSGU", description="Password to decrypt the PFX certificate."),
     *                 @OA\Property(property="pac", type="string", example="4YHBDH", description="PAC (POS Authentication Code) included in the certificate."),
     *                 @OA\Property(property="environment", type="string", enum={"sandbox","production"}, example="sandbox", description="Target environment.")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Connection registered successfully.",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Connected to TaxCore successfully."),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1, description="Internal connection ID."),
     *                 @OA\Property(property="environment", type="string", example="sandbox"),
     *                 @OA\Property(property="vsdc_url", type="string", example="https://vsdc.sandbox.taxcore.online"),
     *                 @OA\Property(property="active", type="boolean", example=true)
     *             )
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation error — missing or invalid fields."),
     *     @OA\Response(response=409, description="Business conflict — certificate expired, invalid, or connection already exists.")
     * )
     */
    public function connect(TaxCoreConnectionRequest $request)
    {
        $connection = $this->connectionService->connect(
            $request->integer('organization_id'),
            $request->file('certificate'),
            $request->string('password'),
            $request->string('pac'),
            $request->string('environment'),
        );

        return ApiResponse::success(
            'Connected to TaxCore successfully.',
            200,
            [
                'id' => $connection->getId(),
                'environment' => $connection->getEnvironment(),
                'vsdc_url' => $connection->getVsdcUrl(),
                'active' => $connection->getActive(),
            ]
        );
    }


    /**
     * @OA\Get(
     *     path="/api/integrations/taxcore/status",
     *     tags={"TaxCore"},
     *     summary="Check V-SDC status for an organization",
     *     description="Queries the V-SDC status endpoint using the active TaxCore connection for the given organization. Returns SDC date/time, UID, supported languages and tax rates.",
     *     operationId="taxcoreStatus",
     *     @OA\Parameter(
     *         name="organization_id",
     *         in="query",
     *         required=true,
     *         description="ID of the organization whose active TaxCore connection will be used.",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="V-SDC status retrieved successfully.",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="TaxCore connection is active."),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="sdcDateTime", type="string", format="date-time", example="2026-05-27T22:10:00+02:00"),
     *                 @OA\Property(property="uid", type="string", example="Dt1Ov1o0"),
     *                 @OA\Property(property="taxCoreApi", type="string", example="https://api.sandbox.taxcore.online"),
     *                 @OA\Property(
     *                     property="supportedLanguages",
     *                     type="array",
     *                     @OA\Items(type="string", example="en")
     *                 ),
     *                 @OA\Property(
     *                     property="taxRates",
     *                     type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="label", type="string", example="A"),
     *                         @OA\Property(property="rate", type="number", example=9),
     *                         @OA\Property(property="categoryName", type="string", example="VAT"),
     *                         @OA\Property(property="categoryType", type="integer", example=0)
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=404, description="No active TaxCore connection found for the organization."),
     *     @OA\Response(response=422, description="Validation error — organization_id missing or invalid.")
     * )
     */
    public function status(Request $request)
    {
        $organizationId = $request->integer('organization_id');
        $connection = $this->connectionService->findActiveByOrganization($organizationId);

        $response = $this->apiService->get($connection, '/api/v3/status');

        return ApiResponse::success('TaxCore connection is active.', 200, $response->json());
    }

    /**
     * @OA\Get(
     *     path="/api/integrations/taxcore/environment-parameters",
     *     tags={"TaxCore"},
     *     summary="Get V-SDC environment parameters for an organization",
     *     description="Returns fiscal environment parameters from the V-SDC: organization name, TIN, timezone, city, and relevant API endpoints.",
     *     operationId="taxcoreEnvironmentParameters",
     *     @OA\Parameter(
     *         name="organization_id",
     *         in="query",
     *         required=true,
     *         description="ID of the organization whose active TaxCore connection will be used.",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Environment parameters retrieved successfully.",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Environment parameters retrieved."),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="organizationName", type="string", example="Worth It Ltd"),
     *                 @OA\Property(property="tin", type="string", example="718587"),
     *                 @OA\Property(property="locationName", type="string", example="Worth It Ltd"),
     *                 @OA\Property(property="address", type="string", example="Joint Court"),
     *                 @OA\Property(property="city", type="string", example="Beograd"),
     *                 @OA\Property(property="timeZone", type="string", example="Europe/Belgrade")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=404, description="No active TaxCore connection found for the organization."),
     *     @OA\Response(response=422, description="Validation error — organization_id missing or invalid.")
     * )
     */
    public function environmentParameters(Request $request)
    {
        $organizationId = $request->integer('organization_id');
        $connection = $this->connectionService->findActiveByOrganization($organizationId);

        $response = $this->apiService->get($connection, '/api/v3/environment-parameters');

        return ApiResponse::success('Environment parameters retrieved.', 200, $response->json());
    }

    /**
     * @OA\Post(
     *     path="/api/integrations/taxcore/invoices",
     *     tags={"TaxCore"},
     *     summary="Sign and fiscalize an invoice",
     *     description="Sends an invoice to the V-SDC for fiscal signing. The active TaxCore connection for the given organization is used automatically. Supports all invoice types (Normal, ProForma, Copy, Training, Advance) and transaction types (Sale, Refund). To cancel/reverse a previously signed invoice, send invoiceType=0 + transactionType=1 + referentDocumentNumber pointing to the original invoiceNumber.",
     *     operationId="taxcoreSignInvoice",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"organization_id","invoiceType","transactionType","items","payment"},
     *             @OA\Property(property="organization_id", type="integer", example=1, description="ID of the organization whose active TaxCore connection will be used."),
     *             @OA\Property(property="invoiceType", type="integer", enum={0,1,2,3,4}, example=0, description="0=Normal, 1=ProForma, 2=Copy, 3=Training, 4=Advance."),
     *             @OA\Property(property="transactionType", type="integer", enum={0,1}, example=0, description="0=Sale, 1=Refund."),
     *             @OA\Property(property="dateAndTimeOfIssue", type="string", format="date-time", nullable=true, example="2026-05-27T22:00:00+02:00", description="Issue date/time. Defaults to current time if omitted."),
     *             @OA\Property(property="cashier", type="string", nullable=true, maxLength=50, example="Juan Pérez"),
     *             @OA\Property(property="buyerId", type="string", nullable=true, maxLength=20, example="12345678"),
     *             @OA\Property(property="buyerCostCenterId", type="string", nullable=true, maxLength=50, example="CC-001"),
     *             @OA\Property(property="invoiceNumber", type="string", nullable=true, maxLength=60, example="2026/001", description="Optional internal invoice number."),
     *             @OA\Property(property="referentDocumentNumber", type="string", nullable=true, maxLength=50, example="CPLP77KX-Dt1Ov1o0-1", description="Required for Refund (transactionType=1), Copy (invoiceType=2), and Advance-linked invoices (invoiceType=4). Must match the invoiceNumber returned by the original signed invoice."),
     *             @OA\Property(property="referentDocumentDT", type="string", nullable=true, example="2026-05-27T22:00:00+02:00", description="Issue date/time of the referent document."),
     *             @OA\Property(
     *                 property="items",
     *                 type="array",
     *                 minItems=1,
     *                 @OA\Items(
     *                     type="object",
     *                     required={"name","quantity","unitPrice","totalAmount","labels"},
     *                     @OA\Property(property="name", type="string", example="Producto A"),
     *                     @OA\Property(property="quantity", type="number", format="float", example=2),
     *                     @OA\Property(property="unitPrice", type="number", format="float", example=50.00),
     *                     @OA\Property(property="totalAmount", type="number", format="float", example=100.00),
     *                     @OA\Property(property="labels", type="array", minItems=1, @OA\Items(type="string", example="A"), description="Tax category labels (e.g. A=VAT 9%)."),
     *                     @OA\Property(property="gtin", type="string", nullable=true, minLength=8, maxLength=14, example="01234567890128", description="Optional barcode / GTIN.")
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="payment",
     *                 type="array",
     *                 minItems=1,
     *                 @OA\Items(
     *                     type="object",
     *                     required={"amount","paymentType"},
     *                     @OA\Property(property="amount", type="number", format="float", example=100.00),
     *                     @OA\Property(property="paymentType", type="integer", enum={0,1,2,3,4,5,6}, example=1, description="0=Other, 1=Cash, 2=Card, 3=Check, 4=WireTransfer, 5=Voucher, 6=MobileMoney.")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Invoice signed and fiscalized successfully.",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Invoice signed successfully."),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="invoiceNumber", type="string", example="CPLP77KX-Dt1Ov1o0-5", description="Signed invoice number. Use as referentDocumentNumber for future Refund/Copy."),
     *                 @OA\Property(property="requestedBy", type="string", example="CPLP77KX"),
     *                 @OA\Property(property="signedBy", type="string", example="Dt1Ov1o0"),
     *                 @OA\Property(property="sdcDateTime", type="string", format="date-time", example="2026-05-27T22:17:41+02:00"),
     *                 @OA\Property(property="invoiceCounter", type="string", example="5/5N"),
     *                 @OA\Property(property="totalAmount", type="number", example=100.00),
     *                 @OA\Property(property="verificationUrl", type="string", example="https://sandbox.taxcore.online/v/?vl=..."),
     *                 @OA\Property(property="verificationQRCode", type="string", description="Base64-encoded QR code image (GIF)."),
     *                 @OA\Property(property="journal", type="string", description="Printable ticket text."),
     *                 @OA\Property(property="signature", type="string", description="Digital signature of the invoice."),
     *                 @OA\Property(property="businessName", type="string", example="Worth It Ltd"),
     *                 @OA\Property(property="tin", type="string", example="718587"),
     *                 @OA\Property(property="locationName", type="string", example="Worth It Ltd"),
     *                 @OA\Property(property="address", type="string", example="Joint Court"),
     *                 @OA\Property(
     *                     property="taxItems",
     *                     type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="label", type="string", example="A"),
     *                         @OA\Property(property="categoryName", type="string", example="VAT"),
     *                         @OA\Property(property="rate", type="number", example=9),
     *                         @OA\Property(property="amount", type="number", example=8.26),
     *                         @OA\Property(property="categoryType", type="integer", example=0)
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=404, description="No active TaxCore connection found for the organization."),
     *     @OA\Response(response=409, description="V-SDC rejected the invoice — check items, labels or referentDocumentNumber."),
     *     @OA\Response(response=422, description="Validation error — missing or invalid fields.")
     * )
     */
    public function signInvoice(TaxCoreInvoiceRequest $request)
    {
        $connection = $this->connectionService->findActiveByOrganization(
            $request->integer('organization_id')
        );

        $payload = $request->except('organization_id');

        $response = $this->apiService->post($connection, '/api/v3/invoices', $payload);

        return ApiResponse::success('Invoice signed successfully.', 200, $response->json());
    }
}
