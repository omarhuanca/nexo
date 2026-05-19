<?php

namespace App\Modules\Organization\Controller;

use App\Http\Controllers\Controller;
use App\Http\Requests\OrganizationRequest;
use App\Http\Resources\OrganizationResource;
use App\Http\Responses\ApiResponse;
use App\Modules\Organization\Service\OrganizationService;
use Illuminate\Http\JsonResponse;
use OpenApi\Annotations as OA;

/**
 * @OA\Info(
 *     title="Nexo API",
 *     version="1.0.0",
 *     description="Fiscal centralizing API for integration between sales systems, government and accounting platforms."
 * )
 *
 * @OA\Server(
 *     url="http://127.0.0.1:8000",
 *     description="Local Server"
 * )
 *
 * @OA\Tag(
 *     name="Organizations",
 *     description="Endpoints for managing organizations"
 * )
 */
class OrganizationController extends Controller
{
    private OrganizationService $organizationService;

    public function __construct(OrganizationService $organizationService)
    {
        $this->organizationService = $organizationService;
    }

    

    /**
     * @OA\Get(
     *     path="/api/organizations",
     *     tags={"Organizations"},
     *     summary="List organizations",
     *     description="Returns a paginated list of organizations. Optionally filter by name using the search parameter.",
     *     operationId="indexOrganizations",
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         required=false,
     *         description="Filter organizations by name (case-insensitive partial match).",
     *         @OA\Schema(type="string", example="Acme")
     *     ),
     *     @OA\Parameter(
     *         name="perPage",
     *         in="query",
     *         required=false,
     *         description="Number of results per page. Defaults to 10.",
     *         @OA\Schema(type="integer", default=10, example=10)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Organizations retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Organizations retrieved successfully."),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Acme Corporation"),
     *                     @OA\Property(property="tax_id", type="string", example="12345678901"),
     *                     @OA\Property(property="active", type="boolean", example=true)
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="pagination",
     *                 type="object",
     *                 @OA\Property(property="current_page", type="integer", example=1),
     *                 @OA\Property(property="last_page", type="integer", example=3),
     *                 @OA\Property(property="per_page", type="integer", example=10),
     *                 @OA\Property(property="total", type="integer", example=25),
     *                 @OA\Property(property="from", type="integer", example=1),
     *                 @OA\Property(property="to", type="integer", example=10)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="A database error occurred."),
     *             @OA\Property(property="data", type="array", @OA\Items())
     *         )
     *     )
     * )
     */
    public function index(\Illuminate\Http\Request $request): JsonResponse
    {
        $search  = $request->query('search');
        $perPage = (int) $request->query('perPage', 10);
        $organizations = $this->organizationService->listOrganizations($search, $perPage);
        return ApiResponse::paginated(
            'Organizations retrieved successfully.',
            $organizations,
            OrganizationResource::class
        );
    }

        /**
     * @OA\Post(
     *     path="/api/organizations",
     *     tags={"Organizations"},
     *     summary="Create a new organization",
     *     description="Creates an organization after validating the request at both HTTP and domain levels. The name must be unique (case-insensitive).",
     *     operationId="storeOrganization",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name", "tax_id", "active"},
     *             @OA\Property(
     *                 property="name",
     *                 type="string",
     *                 minLength=3,
     *                 maxLength=255,
     *                 description="Organization display name. Must be unique.",
     *                 example="Acme Corporation"
     *             ),
     *             @OA\Property(
     *                 property="tax_id",
     *                 type="string",
     *                 minLength=11,
     *                 maxLength=20,
     *                 description="Tax identification number. Digits and hyphens only.",
     *                 example="12345678901"
     *             ),
     *             @OA\Property(
     *                 property="active",
     *                 type="boolean",
     *                 description="Whether the organization is active.",
     *                 example=true
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Organization created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Organization created successfully."),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="Acme Corporation"),
     *                 @OA\Property(property="tax_id", type="string", example="12345678901"),
     *                 @OA\Property(property="active", type="boolean", example=true)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error — request or domain",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Validation Error"),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(
     *                     property="name",
     *                     type="array",
     *                     @OA\Items(type="string", example="The organization name must be at least 3 characters long.")
     *                 ),
     *                 @OA\Property(
     *                     property="tax_id",
     *                     type="array",
     *                     @OA\Items(type="string", example="The organization's tax ID must be at least 11 characters long.")
     *                 ),
     *                 @OA\Property(
     *                     property="active",
     *                     type="array",
     *                     @OA\Items(type="string", example="The active field is required.")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=409,
     *         description="Conflict — organization name already exists",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="An organization with the same name already exists."),
     *             @OA\Property(property="data", type="array", @OA\Items())
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="A database error occurred."),
     *             @OA\Property(property="data", type="array", @OA\Items())
     *         )
     *     )
     * )
     */

    public function store(OrganizationRequest $request): JsonResponse 
    {
        $validatedOrganization = $request->validated();

        $organization = $this->organizationService->createOrganization(
            $validatedOrganization['name'], 
            $validatedOrganization['tax_id'], 
            $validatedOrganization['active']
        );

        return ApiResponse::created(
            'Organization created successfully.',
            new OrganizationResource($organization)
        );
    }

    /**
     * @OA\Get(
     *     path="/api/organizations/{id}",
     *     tags={"Organizations"},
     *     summary="Get an organization by ID",
     *     description="Returns a single organization by its ID.",
     *     operationId="showOrganization",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the organization.",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Organization retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Organization retrieved successfully."),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="Acme Corporation"),
     *                 @OA\Property(property="tax_id", type="string", example="12345678901"),
     *                 @OA\Property(property="active", type="boolean", example=true)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Organization not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Organization not found with ID: 99"),
     *             @OA\Property(property="data", type="array", @OA\Items())
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="A database error occurred."),
     *             @OA\Property(property="data", type="array", @OA\Items())
     *         )
     *     )
     * )
     */
    public function show(int $id): JsonResponse
    {
        $organization = $this->organizationService->getOrganizationById($id);
        return ApiResponse::success(
            'Organization retrieved successfully.',
            200,
            new OrganizationResource($organization)
        );
    }

    /**
     * @OA\Put(
     *     path="/api/organizations/{id}",
     *     tags={"Organizations"},
     *     summary="Update an organization",
     *     description="Updates an existing organization. The name must remain unique (case-insensitive) across all organizations.",
     *     operationId="updateOrganization",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the organization to update.",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name", "tax_id", "active"},
     *             @OA\Property(
     *                 property="name",
     *                 type="string",
     *                 minLength=3,
     *                 maxLength=255,
     *                 description="Organization display name. Must be unique.",
     *                 example="Acme Corporation Updated"
     *             ),
     *             @OA\Property(
     *                 property="tax_id",
     *                 type="string",
     *                 minLength=11,
     *                 maxLength=20,
     *                 description="Tax identification number. Digits and hyphens only.",
     *                 example="12345678901"
     *             ),
     *             @OA\Property(
     *                 property="active",
     *                 type="boolean",
     *                 description="Whether the organization is active.",
     *                 example=true
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Organization updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Organization updated successfully."),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="Acme Corporation Updated"),
     *                 @OA\Property(property="tax_id", type="string", example="12345678901"),
     *                 @OA\Property(property="active", type="boolean", example=true)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Organization not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Organization not found with ID: 99"),
     *             @OA\Property(property="data", type="array", @OA\Items())
     *         )
     *     ),
     *     @OA\Response(
     *         response=409,
     *         description="Conflict — organization name already exists",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="An organization with the same name already exists."),
     *             @OA\Property(property="data", type="array", @OA\Items())
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Validation Error"),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(
     *                     property="name",
     *                     type="array",
     *                     @OA\Items(type="string", example="The organization name must be at least 3 characters long.")
     *                 ),
     *                 @OA\Property(
     *                     property="tax_id",
     *                     type="array",
     *                     @OA\Items(type="string", example="The organization's tax ID must be at least 11 characters long.")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="A database error occurred."),
     *             @OA\Property(property="data", type="array", @OA\Items())
     *         )
     *     )
     * )
     */
    public function update(int $id, OrganizationRequest $request): JsonResponse
    {
        $validatedOrganization = $request->validated();

        $organization = $this->organizationService->updateOrganization($id, $validatedOrganization);

        return ApiResponse::success(
            'Organization updated successfully.',
            200,
            new OrganizationResource($organization)
        );
    }

    /**
     * @OA\Delete(
     *     path="/api/organizations/{id}",
     *     tags={"Organizations"},
     *     summary="Delete an organization",
     *     description="Deletes an organization by ID. The operation is rejected if the organization has associated records (e.g. connectors) to avoid orphaned references.",
     *     operationId="destroyOrganization",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the organization to delete.",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Organization deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Organization deleted successfully."),
     *             @OA\Property(property="data", type="array", @OA\Items())
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Organization not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Organization not found with ID: 1"),
     *             @OA\Property(property="data", type="array", @OA\Items())
     *         )
     *     ),
     *     @OA\Response(
     *         response=409,
     *         description="Cannot delete — organization has associated records",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Cannot delete Organization: it has associated records in [connectors]."),
     *             @OA\Property(property="data", type="array", @OA\Items())
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="A database error occurred."),
     *             @OA\Property(property="data", type="array", @OA\Items())
     *         )
     *     )
     * )
     */
    public function destroy(int $id): JsonResponse
    {
        $this->organizationService->deleteOrganization($id);
        return ApiResponse::success('Organization deleted successfully.', 200);
    }
}