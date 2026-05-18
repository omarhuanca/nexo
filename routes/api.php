<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Organization\Controller\OrganizationController;

Route::post('/organizations', [OrganizationController::class, 'store']);
