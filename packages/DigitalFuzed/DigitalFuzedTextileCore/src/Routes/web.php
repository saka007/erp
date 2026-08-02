<?php

use Illuminate\Support\Facades\Route;
use DigitalFuzed\TextileCore\Http\Controllers\TextileSpecificationController;
use DigitalFuzed\TextileCore\Http\Controllers\TextileMasterDataController;
use DigitalFuzed\TextileCore\Http\Controllers\TextileProcurementController;
use DigitalFuzed\TextileCore\Http\Controllers\TextileSalesController;
use DigitalFuzed\TextileCore\Http\Controllers\TextileManufacturingController;
use DigitalFuzed\TextileCore\Http\Controllers\TextileQualityController;
use DigitalFuzed\TextileCore\Http\Controllers\TextileProcessingController;
use DigitalFuzed\TextileCore\Http\Controllers\TextileCostingController;
use DigitalFuzed\TextileCore\Http\Controllers\TextileDashboardController;

Route::middleware(['web', 'auth', 'verified', 'PlanModuleCheck:TextileCore'])->group(function () {
    Route::get('/textile/specifications', [TextileSpecificationController::class, 'index'])->name('textile.specifications.index');
    Route::post('/textile/specifications', [TextileSpecificationController::class, 'store'])->name('textile.specifications.store');
    Route::post('/textile/specifications/update', [TextileSpecificationController::class, 'update'])->name('textile.specifications.update');
    Route::post('/textile/specifications/archive', [TextileSpecificationController::class, 'archive'])->name('textile.specifications.archive');
    Route::get('/textile/quality-profiles', [TextileMasterDataController::class, 'qualityProfiles'])->name('textile.quality-profiles.index');
    Route::post('/textile/quality-profiles', [TextileMasterDataController::class, 'storeQualityProfile'])->name('textile.quality-profiles.store');
    Route::post('/textile/quality-profiles/update', [TextileMasterDataController::class, 'updateQualityProfile'])->name('textile.quality-profiles.update');
    Route::post('/textile/quality-profiles/archive', [TextileMasterDataController::class, 'archiveQualityProfile'])->name('textile.quality-profiles.archive');
    Route::get('/textile/route-recipes', [TextileMasterDataController::class, 'routeRecipes'])->name('textile.route-recipes.index');
    Route::post('/textile/route-recipes', [TextileMasterDataController::class, 'storeRouteRecipe'])->name('textile.route-recipes.store');
    Route::post('/textile/route-recipes/update', [TextileMasterDataController::class, 'updateRouteRecipe'])->name('textile.route-recipes.update');
    Route::post('/textile/route-recipes/archive', [TextileMasterDataController::class, 'archiveRouteRecipe'])->name('textile.route-recipes.archive');
    Route::get('/textile/unit-conversions', [TextileMasterDataController::class, 'unitConversions'])->name('textile.unit-conversions.index');
    Route::post('/textile/unit-conversions', [TextileMasterDataController::class, 'storeUnitConversion'])->name('textile.unit-conversions.store');
    Route::post('/textile/unit-conversions/update', [TextileMasterDataController::class, 'updateUnitConversion'])->name('textile.unit-conversions.update');
    Route::post('/textile/unit-conversions/archive', [TextileMasterDataController::class, 'archiveUnitConversion'])->name('textile.unit-conversions.archive');

    Route::get('/textile/procurement', [TextileProcurementController::class, 'index'])->name('textile.procurement.index');
    Route::post('/textile/procurement/requisitions', [TextileProcurementController::class, 'storeRequisition'])->name('textile.procurement.requisitions.store');
    Route::post('/textile/procurement/requisitions/approve', [TextileProcurementController::class, 'approveRequisition'])->name('textile.procurement.requisitions.approve');
    Route::post('/textile/procurement/purchase-orders', [TextileProcurementController::class, 'storePurchaseOrder'])->name('textile.procurement.purchase-orders.store');
    Route::post('/textile/procurement/purchase-orders/approve', [TextileProcurementController::class, 'approvePurchaseOrder'])->name('textile.procurement.purchase-orders.approve');
    Route::post('/textile/procurement/grns', [TextileProcurementController::class, 'storeGrn'])->name('textile.procurement.grns.store');
    Route::post('/textile/procurement/grns/release', [TextileProcurementController::class, 'releaseGrn'])->name('textile.procurement.grns.release');
    Route::post('/textile/procurement/incoming-qc', [TextileProcurementController::class, 'storeIncomingQc'])->name('textile.procurement.incoming-qc.store');
    Route::post('/textile/procurement/incoming-qc/finalize', [TextileProcurementController::class, 'finalizeIncomingQc'])->name('textile.procurement.incoming-qc.finalize');

    Route::get('/textile/sales', [TextileSalesController::class, 'index'])->name('textile.sales.index');
    Route::post('/textile/sales/orders', [TextileSalesController::class, 'storeSalesOrder'])->name('textile.sales.orders.store');
    Route::post('/textile/sales/orders/approve', [TextileSalesController::class, 'approveSalesOrder'])->name('textile.sales.orders.approve');
    Route::post('/textile/sales/allocations', [TextileSalesController::class, 'storeAllocation'])->name('textile.sales.allocations.store');
    Route::post('/textile/sales/allocations/release', [TextileSalesController::class, 'releaseAllocation'])->name('textile.sales.allocations.release');
    Route::post('/textile/sales/dispatches', [TextileSalesController::class, 'storeDispatch'])->name('textile.sales.dispatches.store');
    Route::post('/textile/sales/dispatches/release', [TextileSalesController::class, 'releaseDispatch'])->name('textile.sales.dispatches.release');
    Route::post('/textile/sales/challans', [TextileSalesController::class, 'storeChallan'])->name('textile.sales.challans.store');
    Route::post('/textile/sales/challans/pod', [TextileSalesController::class, 'markPod'])->name('textile.sales.challans.pod');

    Route::get('/textile/manufacturing', [TextileManufacturingController::class, 'index'])->name('textile.manufacturing.index');
    Route::post('/textile/manufacturing/beams', [TextileManufacturingController::class, 'storeBeam'])->name('textile.manufacturing.beams.store');
    Route::post('/textile/manufacturing/beams/approve', [TextileManufacturingController::class, 'approveBeam'])->name('textile.manufacturing.beams.approve');
    Route::post('/textile/manufacturing/batches', [TextileManufacturingController::class, 'storeProductionBatch'])->name('textile.manufacturing.batches.store');
    Route::post('/textile/manufacturing/batches/release', [TextileManufacturingController::class, 'releaseProductionBatch'])->name('textile.manufacturing.batches.release');
    Route::post('/textile/manufacturing/weaving-output', [TextileManufacturingController::class, 'storeWeavingOutput'])->name('textile.manufacturing.weaving-output.store');
    Route::post('/textile/manufacturing/waste', [TextileManufacturingController::class, 'storeWaste'])->name('textile.manufacturing.waste.store');
    Route::post('/textile/manufacturing/rework', [TextileManufacturingController::class, 'storeRework'])->name('textile.manufacturing.rework.store');

    Route::get('/textile/quality', [TextileQualityController::class, 'index'])->name('textile.quality.index');
    Route::post('/textile/quality/inspections', [TextileQualityController::class, 'storeInspection'])->name('textile.quality.inspections.store');
    Route::post('/textile/quality/inspections/finalize', [TextileQualityController::class, 'finalizeInspection'])->name('textile.quality.inspections.finalize');
    Route::post('/textile/quality/lots/hold', [TextileQualityController::class, 'holdLot'])->name('textile.quality.lots.hold');
    Route::post('/textile/quality/lots/release', [TextileQualityController::class, 'releaseLot'])->name('textile.quality.lots.release');

    Route::get('/textile/processing', [TextileProcessingController::class, 'index'])->name('textile.processing.index');
    Route::post('/textile/processing/outward', [TextileProcessingController::class, 'storeOutward'])->name('textile.processing.outward.store');
    Route::post('/textile/processing/outward/release', [TextileProcessingController::class, 'releaseOutward'])->name('textile.processing.outward.release');
    Route::post('/textile/processing/batches', [TextileProcessingController::class, 'storeBatch'])->name('textile.processing.batches.store');
    Route::post('/textile/processing/batches/release', [TextileProcessingController::class, 'releaseBatch'])->name('textile.processing.batches.release');
    Route::post('/textile/processing/inward', [TextileProcessingController::class, 'storeInward'])->name('textile.processing.inward.store');
    Route::post('/textile/processing/inward/finalize', [TextileProcessingController::class, 'finalizeInward'])->name('textile.processing.inward.finalize');
    Route::post('/textile/processing/reconcile', [TextileProcessingController::class, 'reconcile'])->name('textile.processing.reconcile');

    Route::get('/textile/costing', [TextileCostingController::class, 'index'])->name('textile.costing.index');
    Route::post('/textile/costing/entries', [TextileCostingController::class, 'storeCostingEntry'])->name('textile.costing.entries.store');
    Route::post('/textile/costing/entries/finalize', [TextileCostingController::class, 'finalizeCostingEntry'])->name('textile.costing.entries.finalize');

    Route::get('/textile/dashboard', [TextileDashboardController::class, 'index'])->name('textile.dashboard.index');
});
