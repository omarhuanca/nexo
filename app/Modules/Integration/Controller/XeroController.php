<?php
namespace App\Modules\Integration\Controller;

use App\Modules\Integration\Service\Xero\XeroOauthService;
use Illuminate\Http\Request;

class XeroController extends IntegrationController
{
    public function __construct(private readonly XeroOauthService $xeroOauthService) {}

    public function connect()
    {
        return redirect(
            $this->xeroOauthService->getAuthorizationUrl()
        );
    }

    public function callback(Request $request)
    {
        $tokens = $this->xeroOauthService->xeroCallback($request);

        return response()->json([
            'success' => true,
            'tokens' => $tokens,
        ]);
    }
}