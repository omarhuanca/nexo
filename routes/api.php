<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Organization\Controller\OrganizationController;
use App\Modules\Connector\Controller\ConnectorController;
use App\Modules\Integration\Xero\Controller\XeroController;
use App\Modules\Integration\Xero\Controller\XeroItemController;
use App\Modules\IntegrationEvent\Controller\IntegrationEventController;
use App\Modules\Sale\Controller\SaleController;
use App\Modules\Integration\TaxCore\Controller\TaxCoreController;

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

Route::post('/integrations/taxcore/connect', [TaxCoreController::class, 'connect']);
Route::get('/integrations/taxcore/status', [TaxCoreController::class, 'status']);
Route::get('/integrations/taxcore/environment-parameters',  [TaxCoreController::class, 'environmentParameters']);
Route::post('/integrations/taxcore/invoices', [TaxCoreController::class, 'signInvoice']);