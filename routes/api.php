<?php

use App\Modules\Integration\Xero\Controller\XeroWebhookController;
use Illuminate\Support\Facades\Route;
use App\Modules\Auth\Controller\AuthController;
use App\Modules\Organization\Controller\OrganizationController;
use App\Modules\Connector\Controller\ConnectorController;
use App\Modules\Integration\TaxCore\Controller\TaxCoreController;
use App\Modules\Integration\Xero\Controller\XeroController;
use App\Modules\Integration\Xero\Controller\XeroItemController;
use App\Modules\IntegrationEvent\Controller\IntegrationEventController;
use App\Modules\Sale\Controller\SaleController;
use App\Modules\Agent\Controller\AgentController;
use App\Modules\Buyer\Controller\BuyerController;
use App\Modules\LineItem\Controller\LineItemController;
use App\Modules\Payment\Controller\PaymentController;

// =====================================================================
// PUBLIC — Admin authentication
// =====================================================================

Route::prefix('auth')->group(function () {
    Route::post('login',   [AuthController::class, 'login'])->middleware('throttle:5,1');
    Route::post('refresh', [AuthController::class, 'refresh'])->middleware('throttle:10,1');
});

// =====================================================================
// PROTECTED — JWT + scope middleware
// =====================================================================

Route::middleware(['jwt'])->group(function () {

    Route::get('auth/me',     [AuthController::class, 'me']);
    Route::post('auth/logout',[AuthController::class, 'logout']);

    // ORGANIZATIONS
    Route::middleware('scope:organizations:read')->group(function () {
        Route::get('/organizations', [OrganizationController::class, 'index']);
        Route::get('/organizations/{id}', [OrganizationController::class, 'show']);
    });
    Route::middleware('scope:organizations:write')->group(function () {
        Route::post('/organizations', [OrganizationController::class, 'store']);
        Route::put('/organizations/{id}', [OrganizationController::class, 'update']);
        Route::delete('/organizations/{id}', [OrganizationController::class, 'destroy']);
    });

    // CONNECTORS
    Route::middleware('scope:connectors:read')->group(function () {
        Route::get('/connectors', [ConnectorController::class, 'index']);
        Route::get('/connectors/{id}', [ConnectorController::class, 'show']);
    });
    Route::middleware('scope:connectors:write')->group(function () {
        Route::post('/connectors', [ConnectorController::class, 'store']);
        Route::put('/connectors/{id}', [ConnectorController::class, 'update']);
        Route::delete('/connectors/{id}', [ConnectorController::class, 'destroy']);
    });

    // BUYERS
    Route::middleware('scope:buyers:read')->group(function () {
        Route::get('/buyers', [BuyerController::class, 'index']);
        Route::get('/buyers/{id}', [BuyerController::class, 'show']);
    });
    Route::middleware('scope:buyers:write')->group(function () {
        Route::post('/buyers', [BuyerController::class, 'store']);
        Route::put('/buyers/{id}', [BuyerController::class, 'update']);
        Route::delete('/buyers/{id}', [BuyerController::class, 'destroy']);
    });

    // PAYMENTS
    Route::middleware('scope:payments:read')->group(function () {
        Route::get('/sales/{saleId}/payments', [PaymentController::class, 'index']);
        Route::get('/payments/{id}', [PaymentController::class, 'show']);
    });
    Route::middleware('scope:payments:write')->group(function () {
        Route::post('/sales/{saleId}/payments', [PaymentController::class, 'store']);
        Route::put('/payments/{id}', [PaymentController::class, 'update']);
        Route::delete('/payments/{id}', [PaymentController::class, 'destroy']);
    });

    // TAXCORE
    Route::middleware('scope:taxcore:manage')->group(function () {
        Route::post('/integrations/taxcore/connect-agent', [TaxCoreController::class, 'connectAgent']);
    });
    Route::middleware('scope:invoices:read')->group(function () {
        Route::get('/integrations/taxcore/invoices', [SaleController::class, 'index']);
        Route::get('/integrations/taxcore/invoices/{id}', [SaleController::class, 'getInvoice']);
    });

    // XERO
    Route::middleware('scope:xero:manage')->group(function () {
        Route::get('/integrations/xero/connect', [XeroController::class, 'connect']);
        Route::get('/integrations/xero/callback', [XeroController::class, 'callback']);
        Route::get('/integrations/xero/{connectionId}/contacts', [XeroController::class, 'getContacts']);
    });

    // AGENT
    Route::middleware('scope:agents:issue')->group(function () {
        Route::post('/agent/token', [AgentController::class, 'createToken']);
    });
});

// =====================================================================
// PROTECTED — Connector Bearer token (unchanged)
// =====================================================================

Route::middleware('connector.auth')->group(function () {
    Route::post('/integration-events', [IntegrationEventController::class, 'store']);
    Route::post('/integrations/products', [XeroItemController::class, 'sync']);

    // Sales: async Xero Invoice + TaxCore fiscal signing
    Route::post('/sales', [SaleController::class, 'store']);
    Route::get('/sales/{id}', [SaleController::class, 'show']);
});

// =====================================================================
// PUBLIC — Agent token issuance (no auth by design — see README)
// NOTA: movido dentro del grupo jwt con scope:agents:issue arriba.
// Esta línea queda comentada para referencia histórica.
// =====================================================================
// Route::post('/agent/token', [AgentController::class, 'createToken']);

// =====================================================================
// PROTECTED — Agent Bearer token (unchanged)
// =====================================================================

Route::middleware('agent.auth')->group(function () {
    Route::get('/agent/config',             [AgentController::class, 'config']);
    Route::get('/agent/pending',            [AgentController::class, 'pending']);
    Route::post('/agent/result',            [AgentController::class, 'result']);
    Route::post('/agent/broadcasting-auth', [AgentController::class, 'broadcastingAuth']);
});

// =====================================================================
// WEBHOOK RECEIVER — Xero signature verified (unchanged)
// =====================================================================

Route::post('/integrations/xero/webhook', [XeroWebhookController::class, 'receive']);

// LINE ITEMS ROUTES

Route::get('/sales/{saleId}/lineItems', [LineItemController::class, 'index']);
Route::post('/sales/{saleId}/lineItems', [LineItemController::class, 'store']);
Route::get('/lineItems/{id}', [LineItemController::class, 'show']);
Route::put('/lineItems/{id}', [LineItemController::class, 'update']);
Route::delete('/lineItems/{id}', [LineItemController::class, 'destroy']);

