<?php

use Illuminate\Support\Facades\Route;
use Ingenius\Discounts\Http\Controllers\DiscountController;

/*
|--------------------------------------------------------------------------
| Tenant Routes
|--------------------------------------------------------------------------
|
| Here is where you can register tenant-specific routes for your package.
| These routes are loaded by the RouteServiceProvider within a group which
| contains the tenant middleware for multi-tenancy support.
|
*/

// Route::get('tenant-example', function () {
//     return 'Hello from tenant-specific route! Current tenant: ' . tenant('id');
// });

Route::middleware(['api', 'tenant.user'])
    ->prefix('api')->group(function() {
        Route::prefix('discounts-campaigns')->group(function(){
            Route::get('/', [DiscountController::class, 'index'])
                ->middleware('tenant.has.feature:list-discounts');
            Route::post('/', [DiscountController::class, 'store'])
                ->middleware(['tenant.has.feature:create-discount']);
            Route::get('/{discountCampaign}', [DiscountController::class, 'show'])
                ->middleware('tenant.has.feature:view-discount');
            Route::put('/{discountCampaign}', [DiscountController::class, 'update'])
                ->middleware(['tenant.has.feature:update-discount']);
            Route::patch('/{discountCampaign}', [DiscountController::class, 'patch'])
                ->middleware(['tenant.has.feature:update-discount']);
            Route::delete('/{discountCampaign}', [DiscountController::class, 'destroy'])
                ->middleware(['tenant.has.feature:delete-discount']);
        });
        Route::prefix('discount-campaigns-edit')->group(function(){
            Route::get('/{discountCampaign}', [DiscountController::class, 'edit'])
                ->middleware('tenant.has.feature:update-discount');
        });
    });