<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Organization\Controller\OrganizationController;

Route::get('/organizations', [OrganizationController::class, 'index']);
Route::post('/organizations', [OrganizationController::class, 'store']);
Route::get('/organizations/{id}', [OrganizationController::class, 'show']);
Route::put('/organizations/{id}', [OrganizationController::class, 'update']);
Route::delete('/organizations/{id}', [OrganizationController::class, 'destroy']);
