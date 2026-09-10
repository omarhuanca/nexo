<?php

use App\Modules\Integration\Xero\Controller\XeroWebhookController;
use Illuminate\Support\Facades\Route;
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
use App\Modules\Audit\Controller\AuditLogController;
use App\Modules\Product\Controller\ProductController;

// ORGANIZATIONS ROUTES

Route::get('/organizations', [OrganizationController::class, 'index']);
Route::post('/organizations', [OrganizationController::class, 'store']);
Route::get('/organizations/{id}', [OrganizationController::class, 'show']);
Route::put('/organizations/{id}', [OrganizationController::class, 'update']);
Route::delete('/organizations/{id}', [OrganizationController::class, 'destroy']);

// CONNECTORS ROUTES

Route::get('/connectors', [ConnectorController::class, 'index']);
Route::post('/connectors', [ConnectorController::class, 'store']);
Route::get('/connectors/{id}', [ConnectorController::class, 'show']);
Route::put('/connectors/{id}', [ConnectorController::class, 'update']);
Route::delete('/connectors/{id}', [ConnectorController::class, 'destroy']);

// BUYERS ROUTES

Route::get('/buyers', [BuyerController::class, 'index']);
Route::post('/buyers', [BuyerController::class, 'store']);
Route::get('/buyers/{id}', [BuyerController::class, 'show']);
Route::put('/buyers/{id}', [BuyerController::class, 'update']);
Route::delete('/buyers/{id}', [BuyerController::class, 'destroy']);

// PRODUCTS ROUTES

Route::get('/products', [ProductController::class, 'index']);
Route::post('/products', [ProductController::class, 'store']);

// PAYMENTS ROUTES

Route::get('/sales/{saleId}/payments', [PaymentController::class, 'index']);
Route::post('/sales/{saleId}/payments', [PaymentController::class, 'store']);
Route::get('/payments/{id}', [PaymentController::class, 'show']);
Route::put('/payments/{id}', [PaymentController::class, 'update']);
Route::delete('/payments/{id}', [PaymentController::class, 'destroy']);

// INTEGRATION EVENTS ROUTES

Route::middleware('connector.auth')->group(function () {
    Route::post('/integration-events', [IntegrationEventController::class, 'store']);
    Route::post('/integrations/products', [XeroItemController::class, 'sync']);

    // Sales: async Xero Invoice + TaxCore fiscal signing
    Route::post('/sales', [SaleController::class, 'store']);
    Route::get('/sales/{id}', [SaleController::class, 'show']);
});

Route::get('/integrations/xero/connect', [XeroController::class, 'connect']);
Route::get('/integrations/xero/callback', [XeroController::class, 'callback']);

Route::get('/integrations/xero/{connectionId}/contacts', [XeroController::class, 'getContacts']);

// TAXCORE ROUTES

Route::post('/integrations/taxcore/connect-agent', [TaxCoreController::class, 'connectAgent']);

Route::get('/integrations/taxcore/invoices', [SaleController::class, 'index']);
Route::get('/integrations/taxcore/invoices/{id}', [SaleController::class, 'getInvoice']);


// AGENT ROUTES

Route::post('/agent/token', [AgentController::class, 'createToken']);

Route::middleware('agent.auth')->group(function () {
    Route::get('/agent/config',             [AgentController::class, 'config']);
    Route::get('/agent/pending',            [AgentController::class, 'pending']);
    Route::post('/agent/result',            [AgentController::class, 'result']);
    Route::post('/agent/broadcasting-auth', [AgentController::class, 'broadcastingAuth']);
});

// WEBHOOK RECEIVER

Route::post('/integrations/xero/webhook', [XeroWebhookController::class, 'receive']);

// LINE ITEMS ROUTES

Route::get('/sales/{saleId}/lineItems', [LineItemController::class, 'index']);
Route::post('/sales/{saleId}/lineItems', [LineItemController::class, 'store']);
Route::get('/lineItems/{id}', [LineItemController::class, 'show']);
Route::put('/lineItems/{id}', [LineItemController::class, 'update']);
Route::delete('/lineItems/{id}', [LineItemController::class, 'destroy']);

// AUDITLOG ROUTES
Route::get('/audit-logs', [AuditLogController::class, 'show']);
Route::get('/audit-logs/all', [AuditLogController::class, 'index']);
