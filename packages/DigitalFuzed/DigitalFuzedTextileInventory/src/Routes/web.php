<?php

use DigitalFuzed\TextileInventory\Http\Controllers\TextileInventoryController;
use DigitalFuzed\TextileInventory\Http\Controllers\TextileMovementController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'verified', 'PlanModuleCheck:TextileInventory'])->group(function () {
    Route::get('/textile/inventory', [TextileInventoryController::class, 'index'])->name('textile.inventory.index');
    Route::get('/textile/inventory/lots/{lotId}', [TextileInventoryController::class, 'showLot'])->name('textile.inventory.lots.show');
    Route::post('/textile/inventory/locations', [TextileInventoryController::class, 'storeLocation'])->name('textile.inventory.locations.store');
    Route::post('/textile/inventory/locations/archive', [TextileInventoryController::class, 'archiveLocation'])->name('textile.inventory.locations.archive');
    Route::post('/textile/inventory/lots', [TextileInventoryController::class, 'storeLot'])->name('textile.inventory.lots.store');
    Route::post('/textile/inventory/lots/update', [TextileInventoryController::class, 'updateLot'])->name('textile.inventory.lots.update');
    Route::post('/textile/inventory/lots/freeze', [TextileInventoryController::class, 'freezeLot'])->name('textile.inventory.lots.freeze');
    Route::post('/textile/inventory/lots/unfreeze', [TextileInventoryController::class, 'unfreezeLot'])->name('textile.inventory.lots.unfreeze');
    Route::post('/textile/inventory/lots/archive', [TextileInventoryController::class, 'archiveLot'])->name('textile.inventory.lots.archive');
    Route::post('/textile/inventory/movements', [TextileInventoryController::class, 'storeMovement'])->name('textile.inventory.movements.store');
    Route::post('/textile/inventory/physical-verifications', [TextileInventoryController::class, 'storePhysicalVerification'])->name('textile.inventory.physical-verifications.store');
    Route::post('/textile/inventory/cycle-counts', [TextileInventoryController::class, 'storeCycleCount'])->name('textile.inventory.cycle-counts.store');
    Route::post('/textile/inventory/reservations', [TextileInventoryController::class, 'storeReservation'])->name('textile.inventory.reservations.store');
    Route::post('/textile/inventory/reservations/release', [TextileInventoryController::class, 'releaseReservation'])->name('textile.inventory.reservations.release');
    Route::post('/textile/inventory/reservations/allocate', [TextileInventoryController::class, 'allocateReservation'])->name('textile.inventory.reservations.allocate');

    Route::post('/textile-movements', [TextileMovementController::class, 'store'])->name('textile-inventory.movements.api.store');
});
