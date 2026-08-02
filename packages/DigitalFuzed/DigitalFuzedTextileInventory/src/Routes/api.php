<?php

use DigitalFuzed\TextileInventory\Http\Controllers\Api\TextileInventoryApiController;
use Illuminate\Support\Facades\Route;

Route::prefix('api')->middleware(['api.json'])->group(function () {
    Route::group(['middleware' => ['auth:sanctum'], 'prefix' => 'textile-inventory'], function () {
        Route::post('reserve', [TextileInventoryApiController::class, 'reserve']);
        Route::get('availability/{lotReference}', [TextileInventoryApiController::class, 'availability']);
    });
});
