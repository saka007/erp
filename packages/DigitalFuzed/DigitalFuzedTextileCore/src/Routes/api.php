<?php

use DigitalFuzed\TextileCore\Http\Controllers\Api\TextileWorkflowApiController;
use DigitalFuzed\TextileCore\Http\Controllers\Api\TextileProcurementApiController;
use DigitalFuzed\TextileCore\Http\Controllers\Api\TextileSalesApiController;
use DigitalFuzed\TextileCore\Http\Controllers\Api\TextileManufacturingApiController;
use DigitalFuzed\TextileCore\Http\Controllers\Api\TextileProcessingApiController;
use DigitalFuzed\TextileCore\Http\Controllers\Api\TextileCostingApiController;
use DigitalFuzed\TextileCore\Http\Controllers\Api\TextileApprovalApiController;
use Illuminate\Support\Facades\Route;

Route::prefix('api')->middleware(['api.json'])->group(function () {
    Route::group(['middleware' => ['auth:sanctum'], 'prefix' => 'textile'], function () {
        Route::post('workflow/store', [TextileWorkflowApiController::class, 'store']);
        Route::get('workflow/{type}', [TextileWorkflowApiController::class, 'index']);
        Route::post('workflow/{documentId}/transition', [TextileWorkflowApiController::class, 'transition']);
        Route::get('dashboard/summary', [TextileWorkflowApiController::class, 'summary']);

        Route::get('approvals/rules', [TextileApprovalApiController::class, 'indexRules']);
        Route::post('approvals/rules', [TextileApprovalApiController::class, 'storeRule']);
        Route::get('approvals/pending', [TextileApprovalApiController::class, 'pending']);
        Route::post('approvals/documents/{documentId}/decision', [TextileApprovalApiController::class, 'recordDecision']);

        Route::post('procurement/requisitions/store', [TextileProcurementApiController::class, 'storeRequisition']);
        Route::post('procurement/requisitions/{id}/approve', [TextileProcurementApiController::class, 'approveRequisition']);

        Route::post('procurement/purchase-orders/store', [TextileProcurementApiController::class, 'storePurchaseOrder']);
        Route::post('procurement/purchase-orders/{id}/approve', [TextileProcurementApiController::class, 'approvePurchaseOrder']);

        Route::post('procurement/grns/store', [TextileProcurementApiController::class, 'storeGrn']);
        Route::post('procurement/grns/{id}/release', [TextileProcurementApiController::class, 'releaseGrn']);

        Route::post('procurement/incoming-qc/store', [TextileProcurementApiController::class, 'storeIncomingQc']);
        Route::post('procurement/incoming-qc/{id}/finalize', [TextileProcurementApiController::class, 'finalizeIncomingQc']);

        Route::post('sales/orders/store', [TextileSalesApiController::class, 'storeSalesOrder']);
        Route::post('sales/orders/{id}/approve', [TextileSalesApiController::class, 'approveSalesOrder']);

        Route::post('sales/allocations/store', [TextileSalesApiController::class, 'storeAllocation']);
        Route::post('sales/allocations/{id}/release', [TextileSalesApiController::class, 'releaseAllocation']);

        Route::post('sales/dispatches/store', [TextileSalesApiController::class, 'storeDispatch']);
        Route::post('sales/dispatches/{id}/release', [TextileSalesApiController::class, 'releaseDispatch']);

        Route::post('sales/challans/store', [TextileSalesApiController::class, 'storeChallan']);
        Route::post('sales/challans/{id}/pod', [TextileSalesApiController::class, 'markPod']);

        Route::post('manufacturing/beams/store', [TextileManufacturingApiController::class, 'storeBeam']);
        Route::post('manufacturing/beams/{id}/approve', [TextileManufacturingApiController::class, 'approveBeam']);
        Route::post('manufacturing/warp-plans/store', [TextileManufacturingApiController::class, 'storeWarpPlan']);
        Route::post('manufacturing/warp-plans/{id}/approve', [TextileManufacturingApiController::class, 'approveWarpPlan']);
        Route::post('manufacturing/yarn-allocations/store', [TextileManufacturingApiController::class, 'storeYarnAllocation']);
        Route::post('manufacturing/warp-sheets/store', [TextileManufacturingApiController::class, 'storeWarpSheet']);
        Route::post('manufacturing/warp-productions/store', [TextileManufacturingApiController::class, 'storeWarpProduction']);
        Route::post('manufacturing/sizing-recipes/store', [TextileManufacturingApiController::class, 'storeSizingRecipe']);
        Route::post('manufacturing/chemical-consumptions/store', [TextileManufacturingApiController::class, 'storeChemicalConsumption']);
        Route::post('manufacturing/beams/from-sizing-recipe/store', [TextileManufacturingApiController::class, 'storeBeamFromSizingRecipe']);
        Route::post('manufacturing/beam-issues/store', [TextileManufacturingApiController::class, 'storeBeamIssue']);
        Route::post('manufacturing/beam-returns/store', [TextileManufacturingApiController::class, 'storeBeamReturn']);
        Route::post('manufacturing/beam-inspections/store', [TextileManufacturingApiController::class, 'storeBeamInspection']);
        Route::post('manufacturing/beam-costs/store', [TextileManufacturingApiController::class, 'storeBeamCost']);
        Route::post('manufacturing/loom-masters/store', [TextileManufacturingApiController::class, 'storeLoomMaster']);
        Route::post('manufacturing/loom-breakdowns/store', [TextileManufacturingApiController::class, 'storeLoomBreakdown']);
        Route::post('manufacturing/loom-maintenances/store', [TextileManufacturingApiController::class, 'storeLoomMaintenance']);
        Route::post('manufacturing/machine-plans/store', [TextileManufacturingApiController::class, 'storeMachinePlan']);
        Route::post('manufacturing/production-calendars/store', [TextileManufacturingApiController::class, 'storeProductionCalendar']);
        Route::post('manufacturing/capacity-plans/store', [TextileManufacturingApiController::class, 'storeCapacityPlan']);
        Route::post('manufacturing/shift-plans/store', [TextileManufacturingApiController::class, 'storeShiftPlan']);
        Route::post('manufacturing/material-plans/store', [TextileManufacturingApiController::class, 'storeMaterialPlan']);
        Route::post('manufacturing/production-schedules/store', [TextileManufacturingApiController::class, 'storeProductionSchedule']);

        Route::post('manufacturing/batches/store', [TextileManufacturingApiController::class, 'storeProductionBatch']);
        Route::post('manufacturing/batches/{id}/release', [TextileManufacturingApiController::class, 'releaseProductionBatch']);

        Route::post('manufacturing/weaving-output/store', [TextileManufacturingApiController::class, 'storeWeavingOutput']);
        Route::post('manufacturing/shift-productions/store', [TextileManufacturingApiController::class, 'storeShiftProduction']);
        Route::post('manufacturing/takha-entries/store', [TextileManufacturingApiController::class, 'storeTakhaEntry']);
        Route::post('manufacturing/loom-efficiencies/store', [TextileManufacturingApiController::class, 'storeLoomEfficiency']);
        Route::post('manufacturing/operator-efficiencies/store', [TextileManufacturingApiController::class, 'storeOperatorEfficiency']);
        Route::post('manufacturing/machine-downtimes/store', [TextileManufacturingApiController::class, 'storeMachineDowntime']);
        Route::post('manufacturing/production-costs/store', [TextileManufacturingApiController::class, 'storeProductionCost']);
        Route::post('manufacturing/grey-fabric-rolls/store', [TextileManufacturingApiController::class, 'storeGreyFabricRoll']);
        Route::post('manufacturing/grey-fabric-rolls/update', [TextileManufacturingApiController::class, 'updateGreyFabricRoll']);
        Route::post('manufacturing/waste/store', [TextileManufacturingApiController::class, 'storeWaste']);
        Route::post('manufacturing/rework/store', [TextileManufacturingApiController::class, 'storeRework']);

        Route::post('processing/outward/store', [TextileProcessingApiController::class, 'storeOutward']);
        Route::post('processing/outward/{id}/release', [TextileProcessingApiController::class, 'releaseOutward']);
        Route::post('processing/batches/store', [TextileProcessingApiController::class, 'storeBatch']);
        Route::post('processing/batches/{id}/release', [TextileProcessingApiController::class, 'releaseBatch']);
        Route::post('processing/inward/store', [TextileProcessingApiController::class, 'storeInward']);
        Route::post('processing/inward/{id}/finalize', [TextileProcessingApiController::class, 'finalizeInward']);
        Route::post('processing/reconcile', [TextileProcessingApiController::class, 'reconcile']);

        Route::post('costing/entries/store', [TextileCostingApiController::class, 'storeCostingEntry']);
        Route::post('costing/entries/{id}/finalize', [TextileCostingApiController::class, 'finalizeCostingEntry']);
        Route::get('costing/summary', [TextileCostingApiController::class, 'summary']);
    });
});
