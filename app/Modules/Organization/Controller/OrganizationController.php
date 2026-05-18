<?php

namespace App\Modules\Organization\Controller;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateOrganizationRequest;
use App\Http\Resources\OrganizationResource;
use App\Http\Responses\ApiResponse;
use App\Modules\Organization\Service\OrganizationService;
use Illuminate\Http\JsonResponse;

class OrganizationController extends Controller
{
    private OrganizationService $organizationService;

    public function __construct(OrganizationService $organizationService)
    {
        $this->organizationService = $organizationService;
    }

    public function store(CreateOrganizationRequest $request): JsonResponse 
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
}