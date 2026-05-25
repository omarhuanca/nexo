<?php
namespace App\Modules\Integration\Controller;

use App\Http\Responses\ApiResponse;
use App\Modules\Integration\Service\Xero\XeroOauthService;
use App\Modules\Integration\Service\Xero\XeroConnectionService;
use Illuminate\Http\Request;

class XeroController extends IntegrationController
{
    public function __construct(private readonly XeroOauthService $xeroOauthService, private readonly XeroConnectionService $xeroConnectionService) {}

    public function connect()
    {
        return redirect(
            $this->xeroOauthService->getAuthorizationUrl()
        );
    }

    public function callback(Request $request)
    {
        $data = $this->xeroOauthService->xeroCallback($request);
        $connection = $this->xeroConnectionService->saveOrUpdate($data['tokens'], $data['connection']);

        return ApiResponse::success("Connected to Xero successfully", 200, $connection);
    }
}