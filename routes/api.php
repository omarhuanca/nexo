<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Organization\Controller\OrganizationController;
use App\Modules\Connector\Controller\ConnectorController;

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

use App\Modules\IntegrationEvent\Controller\IntegrationEventController;

Route::middleware('connector.auth')->group(function () {
    Route::post('/integration-events', [IntegrationEventController::class, 'store']);
});
