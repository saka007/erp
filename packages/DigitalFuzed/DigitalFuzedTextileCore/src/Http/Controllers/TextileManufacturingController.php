<?php

namespace DigitalFuzed\TextileCore\Http\Controllers;

use DigitalFuzed\TextileCore\Models\TextileWorkflowDocument;
use DigitalFuzed\TextileCore\Models\TextileCostCenter;
use DigitalFuzed\TextileCore\Models\TextileReferenceMaster;
use DigitalFuzed\TextileCore\Models\TextileUnitConversion;
use DigitalFuzed\TextileInventory\Models\TextileLot;
use DigitalFuzed\TextileCore\Services\TextileManufacturingService;
use DigitalFuzed\TextileCore\Services\TextileOperatingPolicyService;
use DigitalFuzed\TextileCore\Support\TextileBranchScope;
use DigitalFuzed\TextileCore\Traits\ProvidesRecentActivity;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use RuntimeException;
use Workdo\Account\Models\Customer;
use Workdo\Account\Models\Vendor;
use Workdo\ProductService\Models\ProductServiceItem;
use App\Models\User;

class TextileManufacturingController extends Controller
{
    use ProvidesRecentActivity;

    public function __construct(protected TextileOperatingPolicyService $policyService)
    {
    }

    public function index()
    {
        $this->authorizeTextileAccess();
        $this->authorizeCapabilityOrAbort('manufacturing');

        return Inertia::render('DigitalFuzedTextileCore/Manufacturing/Index', [
            'warpPlans' => $this->documents('warp_plan'),
            'yarnAllocations' => $this->documents('yarn_allocation'),
            'warpSheets' => $this->documents('warp_sheet'),
            'warpProductions' => $this->documents('warp_production'),
            'sizingRecipes' => $this->documents('sizing_recipe'),
            'chemicalConsumptions' => $this->documents('chemical_consumption'),
            'loomMasters' => $this->documents('loom_master'),
            'loomBreakdowns' => $this->documents('loom_breakdown'),
            'loomMaintenances' => $this->documents('loom_maintenance'),
            'productionCalendars' => $this->documents('production_calendar'),
            'capacityPlans' => $this->documents('capacity_plan'),
            'shiftPlans' => $this->documents('shift_plan'),
            'machinePlans' => $this->documents('machine_plan'),
            'materialPlans' => $this->documents('material_plan'),
            'productionSchedules' => $this->documents('production_schedule'),
            'productionAssignments' => $this->documents('production_assignment'),
            'beams' => $this->documents('beam'),
            'beamIssues' => $this->documents('beam_issue'),
            'beamReturns' => $this->documents('beam_return'),
            'beamInspections' => $this->documents('beam_inspection'),
            'beamCosts' => $this->documents('beam_cost'),
            'productionBatches' => $this->documents('production_batch'),
            'weavingOutputs' => $this->documents('weaving_output'),
            'shiftProductions' => $this->documents('shift_production'),
            'takhaEntries' => $this->documents('takha_entry'),
            'loomEfficiencies' => $this->documents('loom_efficiency'),
            'operatorEfficiencies' => $this->documents('operator_efficiency'),
            'machineDowntimes' => $this->documents('machine_downtime'),
            'productionCosts' => $this->documents('production_cost'),
            'greyFabricRolls' => $this->documents('grey_fabric_roll'),
            'greyRollHistories' => $this->documents('grey_roll_history'),
            'wastes' => $this->documents('waste'),
            'reworks' => $this->documents('rework'),
            'inspections' => $this->documents('inspection'),
            'sourceTypeOptions' => $this->sourceTypeOptions(),
            'sourceActionOptions' => $this->sourceActionOptions(),
            'machineTypeOptions' => $this->machineTypeOptions(),
            'shedTypeOptions' => $this->shedTypeOptions(),
            'loomStatusOptions' => $this->loomStatusOptions(),
            'breakdownReasonOptions' => $this->breakdownReasonOptions(),
            'maintenanceTypeOptions' => $this->maintenanceTypeOptions(),
            'dayTypeOptions' => $this->dayTypeOptions(),
            'shiftOptions' => $this->shiftOptions(),
            'unitOptions' => $this->unitOptions(),
            'costCenterOptions' => $this->costCenterOptions(),
            'costTypeOptions' => $this->costTypeOptions(),
            'inspectionResultOptions' => $this->inspectionResultOptions(),
            'fabricDefectOptions' => $this->fabricDefectOptions(),
            'fabricGradeOptions' => $this->fabricGradeOptions(),
            'warehouseOptions' => $this->warehouseOptions(),
            'chemicalOptions' => $this->chemicalOptions(),
            'operatorOptions' => $this->operatorOptions(),
            'partyOptions' => $this->partyOptions(),
            'lotReferenceOptions' => $this->lotReferenceOptions(),
            'sizingVendorOptions' => $this->sizingVendorOptions(),
            'powerloomVendorOptions' => $this->powerloomVendorOptions(),
            'yarnLotOptions' => $this->yarnLotOptions(),
            'recentActivity' => $this->recentActivity(),
        ]);
    }

    public function storeWarpPlan(Request $request, TextileManufacturingService $service)
    {
        $this->authorizeTextileAccess();
        $this->authorizeCapability('manufacturing_warping', 'source_reference_id');

        $validated = $request->validate([
            'source_reference_type' => ['required', 'string', 'max:100'],
            'source_reference_id' => ['required', 'integer', 'min:1'],
            'source_action' => ['nullable', 'string', 'max:100'],
            'party_name' => ['nullable', 'string', 'max:100'],
            'lot_reference' => ['required', 'string', 'max:100'],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'unit' => ['nullable', 'string', 'max:50'],
        ]);

        try {
            $service->createWarpPlan($validated);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['source_reference_id' => __($exception->getMessage())]);
        }

        return back()->with('success', __('Warp plan created successfully.'));
    }

    public function approveWarpPlan(Request $request, TextileManufacturingService $service)
    {
        $this->authorizeTextileAccess();
        $this->authorizeCapability('manufacturing_warping', 'warp_plan_id');

        $validated = $request->validate([
            'warp_plan_id' => ['required', 'integer', 'min:1'],
        ]);

        try {
            $service->approveWarpPlan((int) $validated['warp_plan_id']);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['warp_plan_id' => __($exception->getMessage())]);
        }

        return back()->with('success', __('Warp plan approved successfully.'));
    }

    public function storeYarnAllocation(Request $request, TextileManufacturingService $service)
    {
        $this->authorizeTextileAccess();
        $this->authorizeCapability('manufacturing_warping', 'warp_plan_id');

        $validated = $request->validate([
            'warp_plan_id' => ['required', 'integer', 'min:1'],
        ]);

        try {
            $service->createYarnAllocation((int) $validated['warp_plan_id']);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['warp_plan_id' => __($exception->getMessage())]);
        }

        return back()->with('success', __('Yarn allocation recorded successfully.'));
    }

    public function storeWarpSheet(Request $request, TextileManufacturingService $service)
    {
        $this->authorizeTextileAccess();
        $this->authorizeCapability('manufacturing_warping', 'yarn_allocation_id');

        $validated = $request->validate([
            'yarn_allocation_id' => ['required', 'integer', 'min:1'],
        ]);

        try {
            $service->createWarpSheet((int) $validated['yarn_allocation_id']);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['yarn_allocation_id' => __($exception->getMessage())]);
        }

        return back()->with('success', __('Warp sheet created successfully.'));
    }

    public function storeWarpProduction(Request $request, TextileManufacturingService $service)
    {
        $this->authorizeTextileAccess();
        $this->authorizeCapability('manufacturing_warping', 'warp_sheet_id');

        $validated = $request->validate([
            'warp_sheet_id' => ['required', 'integer', 'min:1'],
        ]);

        try {
            $service->createWarpProduction((int) $validated['warp_sheet_id']);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['warp_sheet_id' => __($exception->getMessage())]);
        }

        return back()->with('success', __('Warp production created successfully.'));
    }

    public function storeSizingRecipe(Request $request, TextileManufacturingService $service)
    {
        $this->authorizeTextileAccess();
        $this->authorizeCapability('manufacturing_sizing', 'warp_production_id');

        $validated = $request->validate([
            'warp_production_id' => ['required', 'integer', 'min:1'],
        ]);

        try {
            $service->createSizingRecipe((int) $validated['warp_production_id']);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['warp_production_id' => __($exception->getMessage())]);
        }

        return back()->with('success', __('Sizing recipe created successfully.'));
    }

    public function storeChemicalConsumption(Request $request, TextileManufacturingService $service)
    {
        $this->authorizeTextileAccess();
        $this->authorizeCapability('manufacturing_sizing', 'sizing_recipe_id');

        $validated = $request->validate([
            'sizing_recipe_id' => ['required', 'integer', 'min:1'],
            'chemical_type' => ['required', 'string', 'max:100'],
            'composition_percent' => ['required', 'numeric', 'gt:0', 'lte:100'],
            'consumption_quantity' => ['required', 'numeric', 'gt:0'],
            'unit' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $service->createChemicalConsumption((int) $validated['sizing_recipe_id'], $validated);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['sizing_recipe_id' => __($exception->getMessage())]);
        }

        return back()->with('success', __('Chemical consumption recorded successfully.'));
    }

    public function storeBeamIssue(Request $request, TextileManufacturingService $service)
    {
        $this->authorizeTextileAccess();
        $this->authorizeCapability('manufacturing_beam', 'beam_id');

        $validated = $request->validate([
            'beam_id' => ['required', 'integer', 'min:1'],
        ]);

        try {
            $service->createBeamIssue((int) $validated['beam_id']);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['beam_id' => __($exception->getMessage())]);
        }

        return back()->with('success', __('Beam issue created successfully.'));
    }

    public function storeBeamReturn(Request $request, TextileManufacturingService $service)
    {
        $this->authorizeTextileAccess();
        $this->authorizeCapability('manufacturing_beam', 'beam_issue_id');

        $validated = $request->validate([
            'beam_issue_id' => ['required', 'integer', 'min:1'],
        ]);

        try {
            $service->createBeamReturn((int) $validated['beam_issue_id']);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['beam_issue_id' => __($exception->getMessage())]);
        }

        return back()->with('success', __('Beam return created successfully.'));
    }

    public function storeBeamInspection(Request $request, TextileManufacturingService $service)
    {
        $this->authorizeTextileAccess();
        $this->authorizeCapability('manufacturing_beam', 'beam_id');

        $validated = $request->validate([
            'beam_id' => ['required', 'integer', 'min:1'],
            'inspection_result' => ['required', 'string', 'max:100', Rule::in($this->inspectionResultOptions())],
            'remarks' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $service->createBeamInspection((int) $validated['beam_id'], $validated);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['beam_id' => __($exception->getMessage())]);
        }

        return back()->with('success', __('Beam inspection recorded successfully.'));
    }

    public function storeBeamCost(Request $request, TextileManufacturingService $service)
    {
        $this->authorizeTextileAccess();
        $this->authorizeCapability('manufacturing_beam', 'beam_id');

        $validated = $request->validate([
            'beam_id' => ['required', 'integer', 'min:1'],
            'cost_type' => ['required', 'string', 'max:100', Rule::in($this->costTypeOptions())],
            'cost_amount' => ['required', 'numeric', 'gt:0'],
            'quantity' => ['nullable', 'numeric', 'gt:0'],
            'unit' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $service->createBeamCost((int) $validated['beam_id'], $validated);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['beam_id' => __($exception->getMessage())]);
        }

        return back()->with('success', __('Beam cost recorded successfully.'));
    }

    public function storeBeam(Request $request, TextileManufacturingService $service)
    {
        $this->authorizeTextileAccess();
        $this->authorizeCapability('manufacturing_beam', 'source_reference_id');

        $validated = $request->validate([
            'source_reference_type' => ['required', 'string', 'max:100'],
            'source_reference_id' => ['required', 'integer', 'min:1'],
            'source_action' => ['nullable', 'string', 'max:100'],
            'party_name' => ['nullable', 'string', 'max:100'],
            'lot_reference' => ['required', 'string', 'max:100'],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'unit' => ['nullable', 'string', 'max:50'],
        ]);

        try {
            $service->createBeam($validated);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['source_reference_id' => __($exception->getMessage())]);
        }

        return back()->with('success', __('Beam created successfully.'));
    }

    public function storeBeamFromYarnAllocation(Request $request, TextileManufacturingService $service)
    {
        $this->authorizeTextileAccess();
        $this->authorizeCapability('manufacturing_beam', 'yarn_allocation_id');

        $validated = $request->validate([
            'yarn_allocation_id' => ['required', 'integer', 'min:1'],
            'quantity' => ['required', 'numeric', 'gt:0'],
        ]);

        try {
            $service->createBeamFromYarnAllocation((int) $validated['yarn_allocation_id'], $validated);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['yarn_allocation_id' => __($exception->getMessage())]);
        }

        return back()->with('success', __('Beam received from sizing vendor successfully.'));
    }

    public function storeBeamFromSizingRecipe(Request $request, TextileManufacturingService $service)
    {
        $this->authorizeTextileAccess();
        $this->authorizeCapability('manufacturing_beam', 'sizing_recipe_id');

        $validated = $request->validate([
            'sizing_recipe_id' => ['required', 'integer', 'min:1'],
        ]);

        try {
            $service->createBeamFromSizingRecipe((int) $validated['sizing_recipe_id']);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['sizing_recipe_id' => __($exception->getMessage())]);
        }

        return back()->with('success', __('Beam created from sizing recipe successfully.'));
    }

    public function storeLoomMaster(Request $request, TextileManufacturingService $service)
    {
        $this->authorizeTextileAccess();
        $this->authorizeCapability('manufacturing_loom', 'source_reference_id');

        $validated = $request->validate([
            'source_reference_type' => ['required', 'string', 'max:100'],
            'source_reference_id' => ['required', 'integer', 'min:1'],
            'source_action' => ['nullable', 'string', 'max:100'],
            'party_name' => ['required', 'string', 'max:100'],
            'lot_reference' => ['required', 'string', 'max:100'],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'unit' => ['nullable', 'string', 'max:50'],
            'shed_type' => ['required', 'string', 'max:100', Rule::in($this->shedTypeOptions())],
            'width' => ['nullable', 'numeric', 'gt:0'],
            'loom_status' => ['required', 'string', 'max:100', Rule::in($this->loomStatusOptions())],
            'running_hours' => ['nullable', 'numeric', 'gte:0'],
            'idle_hours' => ['nullable', 'numeric', 'gte:0'],
            'operator_name' => ['nullable', 'string', 'max:100'],
        ]);

        try {
            $service->createLoomMaster($validated);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['source_reference_id' => __($exception->getMessage())]);
        }

        return back()->with('success', __('Loom master created successfully.'));
    }

    public function storeLoomBreakdown(Request $request, TextileManufacturingService $service)
    {
        $this->authorizeTextileAccess();
        $this->authorizeCapability('manufacturing_maintenance', 'loom_master_id');

        $validated = $request->validate([
            'loom_master_id' => ['required', 'integer', 'min:1'],
            'breakdown_reason' => ['required', 'string', 'max:100', Rule::in($this->breakdownReasonOptions())],
            'downtime_hours' => ['required', 'numeric', 'gt:0'],
            'unit' => ['nullable', 'string', 'max:50'],
            'operator_name' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $service->createLoomBreakdown((int) $validated['loom_master_id'], $validated);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['loom_master_id' => __($exception->getMessage())]);
        }

        return back()->with('success', __('Loom breakdown recorded successfully.'));
    }

    public function storeLoomMaintenance(Request $request, TextileManufacturingService $service)
    {
        $this->authorizeTextileAccess();
        $this->authorizeCapability('manufacturing_maintenance', 'loom_master_id');

        $validated = $request->validate([
            'loom_master_id' => ['required', 'integer', 'min:1'],
            'maintenance_type' => ['required', 'string', 'max:100', Rule::in($this->maintenanceTypeOptions())],
            'maintenance_hours' => ['required', 'numeric', 'gt:0'],
            'unit' => ['nullable', 'string', 'max:50'],
            'operator_name' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $service->createLoomMaintenance((int) $validated['loom_master_id'], $validated);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['loom_master_id' => __($exception->getMessage())]);
        }

        return back()->with('success', __('Loom maintenance recorded successfully.'));
    }

    public function storeMachinePlan(Request $request, TextileManufacturingService $service)
    {
        $this->authorizeTextileAccess();
        $this->authorizeCapability('manufacturing_planning', 'loom_master_id');

        $validated = $request->validate([
            'loom_master_id' => ['required', 'integer', 'min:1'],
            'beam_id' => ['required', 'integer', 'min:1'],
            'planned_date' => ['required', 'date'],
            'planned_shift' => ['required', 'string', 'max:100', Rule::in($this->shiftOptions())],
            'planned_quantity' => ['required', 'numeric', 'gt:0'],
            'unit' => ['nullable', 'string', 'max:50'],
            'operator_name' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $service->createMachinePlan((int) $validated['loom_master_id'], (int) $validated['beam_id'], $validated);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['loom_master_id' => __($exception->getMessage())]);
        }

        return back()->with('success', __('Machine plan recorded successfully.'));
    }

    public function storeProductionCalendar(Request $request, TextileManufacturingService $service)
    {
        $this->authorizeTextileAccess();
        $this->authorizeCapability('manufacturing_planning', 'plan_date');

        $validated = $request->validate([
            'plan_date' => ['required', 'date'],
            'day_type' => ['required', 'string', 'max:100', Rule::in($this->dayTypeOptions())],
            'planned_shift' => ['nullable', 'string', 'max:100', Rule::in($this->shiftOptions())],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $service->createProductionCalendar($validated);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['plan_date' => __($exception->getMessage())]);
        }

        return back()->with('success', __('Production calendar recorded successfully.'));
    }

    public function storeCapacityPlan(Request $request, TextileManufacturingService $service)
    {
        $this->authorizeTextileAccess();
        $this->authorizeCapability('manufacturing_planning', 'loom_master_id');

        $validated = $request->validate([
            'loom_master_id' => ['required', 'integer', 'min:1'],
            'plan_date' => ['required', 'date'],
            'available_hours' => ['required', 'numeric', 'gt:0'],
            'capacity_quantity' => ['required', 'numeric', 'gt:0'],
            'unit' => ['nullable', 'string', 'max:50'],
            'efficiency_target' => ['nullable', 'numeric', 'gt:0', 'lte:100'],
            'operator_name' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $service->createCapacityPlan((int) $validated['loom_master_id'], $validated);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['loom_master_id' => __($exception->getMessage())]);
        }

        return back()->with('success', __('Capacity plan recorded successfully.'));
    }

    public function storeShiftPlan(Request $request, TextileManufacturingService $service)
    {
        $this->authorizeTextileAccess();
        $this->authorizeCapability('manufacturing_planning', 'loom_master_id');

        $validated = $request->validate([
            'loom_master_id' => ['required', 'integer', 'min:1'],
            'plan_date' => ['required', 'date'],
            'planned_shift' => ['required', 'string', 'max:100', Rule::in($this->shiftOptions())],
            'expected_hours' => ['required', 'numeric', 'gt:0'],
            'unit' => ['nullable', 'string', 'max:50'],
            'operator_name' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $service->createShiftPlan((int) $validated['loom_master_id'], $validated);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['loom_master_id' => __($exception->getMessage())]);
        }

        return back()->with('success', __('Shift plan recorded successfully.'));
    }

    public function storeMaterialPlan(Request $request, TextileManufacturingService $service)
    {
        $this->authorizeTextileAccess();
        $this->authorizeCapability('manufacturing_planning', 'beam_id');

        $validated = $request->validate([
            'beam_id' => ['required', 'integer', 'min:1'],
            'plan_date' => ['required', 'date'],
            'required_quantity' => ['required', 'numeric', 'gt:0'],
            'unit' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $service->createMaterialPlan((int) $validated['beam_id'], $validated);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['beam_id' => __($exception->getMessage())]);
        }

        return back()->with('success', __('Material plan recorded successfully.'));
    }

    public function storeProductionSchedule(Request $request, TextileManufacturingService $service)
    {
        $this->authorizeTextileAccess();
        $this->authorizeCapability('manufacturing_planning', 'loom_master_id');

        $validated = $request->validate([
            'loom_master_id' => ['required', 'integer', 'min:1'],
            'beam_id' => ['required', 'integer', 'min:1'],
            'scheduled_date' => ['required', 'date'],
            'scheduled_shift' => ['required', 'string', 'max:100', Rule::in($this->shiftOptions())],
            'scheduled_quantity' => ['required', 'numeric', 'gt:0'],
            'unit' => ['nullable', 'string', 'max:50'],
            'operator_name' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $service->createProductionSchedule((int) $validated['loom_master_id'], (int) $validated['beam_id'], $validated);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['loom_master_id' => __($exception->getMessage())]);
        }

        return back()->with('success', __('Production schedule recorded successfully.'));
    }

    public function approveBeam(Request $request, TextileManufacturingService $service)
    {
        $this->authorizeTextileAccess();
        $this->authorizeCapability('manufacturing_beam', 'beam_id');

        $validated = $request->validate([
            'beam_id' => ['required', 'integer', 'min:1'],
        ]);

        try {
            $service->approveBeam((int) $validated['beam_id']);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['beam_id' => __($exception->getMessage())]);
        }

        return back()->with('success', __('Beam approved successfully.'));
    }

    public function storeProductionBatch(Request $request, TextileManufacturingService $service)
    {
        $this->authorizeTextileAccess();
        $this->authorizeCapability('manufacturing_beam', 'beam_id');

        $validated = $request->validate([
            'beam_id' => ['required', 'integer', 'min:1'],
        ]);

        try {
            $service->createProductionBatch((int) $validated['beam_id']);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['beam_id' => __($exception->getMessage())]);
        }

        return back()->with('success', __('Production batch created successfully.'));
    }

    public function releaseProductionBatch(Request $request, TextileManufacturingService $service)
    {
        $this->authorizeTextileAccess();
        $this->authorizeCapability('manufacturing_weaving', 'batch_id');

        $validated = $request->validate([
            'batch_id' => ['required', 'integer', 'min:1'],
        ]);

        try {
            $service->releaseProductionBatch((int) $validated['batch_id']);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['batch_id' => __($exception->getMessage())]);
        }

        return back()->with('success', __('Production batch released successfully.'));
    }

    public function storeProductionAssignment(Request $request, TextileManufacturingService $service)
    {
        $this->authorizeTextileAccess();
        $this->authorizeCapability('manufacturing_weaving', 'batch_id');

        $validated = $request->validate([
            'batch_id' => ['required', 'integer', 'min:1'],
            'production_mode' => ['required', Rule::in(['own_unit', 'powerloom_vendor'])],
            'assigned_quantity' => ['required', 'numeric', 'gt:0'],
            'assignment_date' => ['required', 'date'],
            'expected_completion_date' => ['nullable', 'date', 'after_or_equal:assignment_date'],
            'loom_allocations' => ['nullable', 'required_if:production_mode,own_unit', 'array', 'min:1'],
            'loom_allocations.*.loom_master_id' => ['required', 'integer', 'min:1', 'distinct'],
            'loom_allocations.*.quantity' => ['required', 'numeric', 'gt:0'],
            'powerloom_vendor_id' => ['nullable', 'required_if:production_mode,powerloom_vendor', 'integer', 'min:1'],
            'planned_shift' => ['nullable', 'string', Rule::in($this->shiftOptions())],
            'operator_name' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        if ($validated['production_mode'] === 'powerloom_vendor') {
            $vendor = Vendor::query()
                ->where('created_by', creatorId())
                ->where('supplier_type', 'powerloom')
                ->findOrFail((int) $validated['powerloom_vendor_id']);
            $validated['powerloom_vendor_name'] = $vendor->company_name;
        }

        try {
            $service->createProductionAssignment((int) $validated['batch_id'], $validated);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['batch_id' => __($exception->getMessage())]);
        }

        return back()->with('success', __('Beam assigned for production successfully.'));
    }

    public function storeWeavingOutput(Request $request, TextileManufacturingService $service)
    {
        $this->authorizeTextileAccess();
        $this->authorizeCapability('manufacturing_weaving', 'batch_id');

        $validated = $request->validate([
            'batch_id' => ['required', 'integer', 'min:1'],
            'quantity' => ['nullable', 'numeric', 'gt:0'],
            'unit' => ['nullable', 'string', 'max:50'],
        ]);

        try {
            $service->createWeavingOutput((int) $validated['batch_id'], $validated);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['batch_id' => __($exception->getMessage())]);
        }

        return back()->with('success', __('Weaving output recorded successfully.'));
    }

    public function storeShiftProduction(Request $request, TextileManufacturingService $service)
    {
        $this->authorizeTextileAccess();
        $this->authorizeCapability('manufacturing_weaving', 'batch_id');

        $validated = $request->validate([
            'batch_id' => ['required', 'integer', 'min:1'],
            'loom_master_id' => ['nullable', 'integer', 'min:1'],
            'planned_shift' => ['required', 'string', 'max:100', Rule::in($this->shiftOptions())],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'unit' => ['nullable', 'string', 'max:50'],
            'operator_name' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $service->createShiftProduction((int) $validated['batch_id'], $validated);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['batch_id' => __($exception->getMessage())]);
        }

        return back()->with('success', __('Shift production recorded successfully.'));
    }

    public function storeTakhaEntry(Request $request, TextileManufacturingService $service)
    {
        $this->authorizeTextileAccess();
        $this->authorizeCapability('manufacturing_weaving', 'production_assignment_id');

        $validated = $request->validate([
            'production_assignment_id' => ['nullable', 'required_without:weaving_output_id', 'integer', 'min:1'],
            'weaving_output_id' => ['nullable', 'required_without:production_assignment_id', 'integer', 'min:1'],
            'takha_number' => ['nullable', 'required_without:takhas', 'string', 'max:100'],
            'quantity' => ['nullable', 'required_without:takhas', 'numeric', 'gt:0'],
            'takhas' => ['nullable', 'required_without:takha_number', 'array', 'min:1'],
            'takhas.*.takha_number' => ['required', 'string', 'max:100', 'distinct'],
            'takhas.*.quantity' => ['required', 'numeric', 'gt:0'],
            'unit' => ['nullable', 'string', 'max:50'],
            'production_date' => ['nullable', 'date'],
            'loom_master_id' => ['nullable', 'integer', 'min:1'],
            'operator_name' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            if (! empty($validated['production_assignment_id'])) {
                if (! empty($validated['takhas'])) {
                    $service->createTakhasFromAssignment((int) $validated['production_assignment_id'], $validated);
                } else {
                    $service->createTakhaFromAssignment((int) $validated['production_assignment_id'], $validated);
                }
            } else {
                $service->createTakhaEntry((int) $validated['weaving_output_id'], $validated);
            }
        } catch (RuntimeException $exception) {
            return back()->withErrors(['production_assignment_id' => __($exception->getMessage())]);
        }

        return back()->with('success', __('Takha entry recorded successfully.'));
    }

    public function storeLoomEfficiency(Request $request, TextileManufacturingService $service)
    {
        $this->authorizeTextileAccess();
        $this->authorizeCapability('manufacturing_weaving', 'loom_master_id');

        $validated = $request->validate([
            'loom_master_id' => ['required', 'integer', 'min:1'],
            'planned_shift' => ['required', 'string', 'max:100', Rule::in($this->shiftOptions())],
            'planned_quantity' => ['required', 'numeric', 'gt:0'],
            'actual_quantity' => ['required', 'numeric', 'gte:0'],
            'runtime_hours' => ['nullable', 'numeric', 'gte:0'],
            'downtime_hours' => ['nullable', 'numeric', 'gte:0'],
            'unit' => ['nullable', 'string', 'max:50'],
            'operator_name' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $service->createLoomEfficiency((int) $validated['loom_master_id'], $validated);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['loom_master_id' => __($exception->getMessage())]);
        }

        return back()->with('success', __('Loom efficiency recorded successfully.'));
    }

    public function storeOperatorEfficiency(Request $request, TextileManufacturingService $service)
    {
        $this->authorizeTextileAccess();
        $this->authorizeCapability('manufacturing_weaving', 'operator_name');

        $validated = $request->validate([
            'planned_shift' => ['required', 'string', 'max:100', Rule::in($this->shiftOptions())],
            'planned_quantity' => ['required', 'numeric', 'gt:0'],
            'actual_quantity' => ['required', 'numeric', 'gte:0'],
            'unit' => ['nullable', 'string', 'max:50'],
            'operator_name' => ['required', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $service->createOperatorEfficiency($validated);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['operator_name' => __($exception->getMessage())]);
        }

        return back()->with('success', __('Operator efficiency recorded successfully.'));
    }

    public function storeMachineDowntime(Request $request, TextileManufacturingService $service)
    {
        $this->authorizeTextileAccess();
        $this->authorizeCapability('manufacturing_weaving', 'loom_master_id');

        $validated = $request->validate([
            'loom_master_id' => ['required', 'integer', 'min:1'],
            'planned_shift' => ['required', 'string', 'max:100', Rule::in($this->shiftOptions())],
            'downtime_reason' => ['required', 'string', 'max:100', Rule::in($this->breakdownReasonOptions())],
            'downtime_hours' => ['required', 'numeric', 'gt:0'],
            'unit' => ['nullable', 'string', 'max:50'],
            'operator_name' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $service->createMachineDowntime((int) $validated['loom_master_id'], $validated);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['loom_master_id' => __($exception->getMessage())]);
        }

        return back()->with('success', __('Machine downtime recorded successfully.'));
    }

    public function storeProductionCost(Request $request, TextileManufacturingService $service)
    {
        $this->authorizeTextileAccess();
        $this->authorizeCapability('manufacturing_weaving', 'weaving_output_id');

        $validated = $request->validate([
            'weaving_output_id' => ['required', 'integer', 'min:1'],
            'cost_center_id' => ['nullable', 'integer', 'min:1'],
            'cost_amount' => ['required', 'numeric', 'gt:0'],
            'quantity' => ['nullable', 'numeric', 'gt:0'],
            'unit' => ['nullable', 'string', 'max:50'],
            'operator_name' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $service->createProductionCost((int) $validated['weaving_output_id'], $validated);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['weaving_output_id' => __($exception->getMessage())]);
        }

        return back()->with('success', __('Production cost recorded successfully.'));
    }

    public function storeGreyFabricRoll(Request $request, TextileManufacturingService $service)
    {
        $this->authorizeTextileAccess();
        $this->authorizeCapability('manufacturing_weaving', 'weaving_output_id');

        $validated = $request->validate([
            'weaving_output_id' => ['required', 'integer', 'min:1'],
            'roll_number' => ['nullable', 'string', 'max:100'],
            'roll_barcode' => ['nullable', 'string', 'max:150'],
            'roll_qr_code' => ['nullable', 'string', 'max:500'],
            'roll_weight' => ['nullable', 'numeric', 'gt:0'],
            'roll_length' => ['nullable', 'numeric', 'gt:0'],
            'gsm' => ['nullable', 'numeric', 'gt:0'],
            'width' => ['nullable', 'numeric', 'gt:0'],
            'defects' => ['nullable', 'array'],
            'defects.*' => ['required', 'string', Rule::in($this->fabricDefectOptions())],
            'grade' => ['nullable', 'string', Rule::in($this->fabricGradeOptions())],
            'warehouse' => ['nullable', 'string', Rule::in($this->warehouseOptions())],
            'unit' => ['nullable', 'string', 'max:50'],
            'operator_name' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $service->createGreyFabricRoll((int) $validated['weaving_output_id'], $validated);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['weaving_output_id' => __($exception->getMessage())]);
        }

        return back()->with('success', __('Grey fabric roll generated successfully.'));
    }

    public function updateGreyFabricRoll(Request $request, TextileManufacturingService $service)
    {
        $this->authorizeTextileAccess();
        $this->authorizeCapability('manufacturing_weaving', 'grey_roll_id');

        $validated = $request->validate([
            'grey_roll_id' => ['required', 'integer', 'min:1'],
            'roll_weight' => ['nullable', 'numeric', 'gt:0'],
            'roll_length' => ['nullable', 'numeric', 'gt:0'],
            'gsm' => ['nullable', 'numeric', 'gt:0'],
            'width' => ['nullable', 'numeric', 'gt:0'],
            'defects' => ['nullable', 'array'],
            'defects.*' => ['required', 'string', Rule::in($this->fabricDefectOptions())],
            'grade' => ['nullable', 'string', Rule::in($this->fabricGradeOptions())],
            'warehouse' => ['nullable', 'string', Rule::in($this->warehouseOptions())],
            'operator_name' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $service->updateGreyFabricRoll((int) $validated['grey_roll_id'], $validated);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['grey_roll_id' => __($exception->getMessage())]);
        }

        return back()->with('success', __('Grey fabric roll updated successfully.'));
    }

    public function storeWaste(Request $request, TextileManufacturingService $service)
    {
        $this->authorizeTextileAccess();
        $this->authorizeCapability('manufacturing_waste', 'batch_id');

        $validated = $request->validate([
            'batch_id' => ['required', 'integer', 'min:1'],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'unit' => ['nullable', 'string', 'max:50'],
        ]);

        try {
            $service->createWaste((int) $validated['batch_id'], $validated);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['batch_id' => __($exception->getMessage())]);
        }

        return back()->with('success', __('Waste recorded successfully.'));
    }

    public function storeRework(Request $request, TextileManufacturingService $service)
    {
        $this->authorizeTextileAccess();
        $this->authorizeCapability('manufacturing_rework', 'weaving_output_id');

        $validated = $request->validate([
            'weaving_output_id' => ['required', 'integer', 'min:1'],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'unit' => ['nullable', 'string', 'max:50'],
        ]);

        try {
            $service->createRework((int) $validated['weaving_output_id'], $validated);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['weaving_output_id' => __($exception->getMessage())]);
        }

        return back()->with('success', __('Rework recorded successfully.'));
    }

    private function documents(string $type)
    {
        $query = TextileWorkflowDocument::query()
            ->where('created_by', creatorId())
            ->where('document_type', $type);

        TextileBranchScope::applyWorkflowScope($query);

        return $query->latest()->get();
    }

    private function unitOptions(): array
    {
        return TextileUnitConversion::query()
            ->where('created_by', creatorId())
            ->where('is_active', true)
            ->get(['from_unit', 'to_unit'])
            ->flatMap(fn ($row) => [$row->from_unit, $row->to_unit])
            ->filter(fn ($unit) => is_string($unit) && trim($unit) !== '')
            ->map(fn ($unit) => trim((string) $unit))
            ->unique()
            ->values()
            ->all();
    }

    private function costCenterOptions(): array
    {
        if (!Schema::hasTable('textile_cost_centers')) {
            return [];
        }

        return TextileCostCenter::query()
            ->where('created_by', creatorId())
            ->where('is_active', true)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->map(fn ($name, $id) => ['value' => (string) $id, 'label' => (string) $name])
            ->values()
            ->all();
    }

    private function chemicalOptions(): array
    {
        if (!Schema::hasTable('product_service_items')) {
            return [];
        }

        return ProductServiceItem::query()
            ->where('created_by', creatorId())
            ->where('type', 'chemical')
            ->where('is_active', true)
            ->orderBy('name')
            ->pluck('name')
            ->map(fn ($value) => trim((string) $value))
            ->filter(fn ($value) => $value !== '')
            ->values()
            ->all();
    }

    private function sourceTypeOptions(): array
    {
        if (!Schema::hasTable('textile_reference_masters')) {
            return $this->defaultSourceTypeOptions();
        }

        $query = TextileReferenceMaster::query()
            ->type('source_type')
            ->where('created_by', creatorId())
            ->where('is_active', true);

        if (Schema::hasColumn('textile_reference_masters', 'master_domain')) {
            $query->domain('manufacturing');
        }

        $options = $query->orderBy('name')->pluck('name')->values()->all();

        return count($options) > 0 ? $options : $this->defaultSourceTypeOptions();
    }

    private function machineTypeOptions(): array
    {
        if (!Schema::hasTable('textile_reference_masters')) {
            return $this->defaultMachineTypeOptions();
        }

        $query = TextileReferenceMaster::query()
            ->type('machine_type')
            ->where('created_by', creatorId())
            ->where('is_active', true);

        if (Schema::hasColumn('textile_reference_masters', 'master_domain')) {
            $query->domain('manufacturing');
        }

        $options = $query->orderBy('name')->pluck('name')->values()->all();

        return count($options) > 0 ? $options : $this->defaultMachineTypeOptions();
    }

    private function shedTypeOptions(): array
    {
        if (!Schema::hasTable('textile_reference_masters')) {
            return $this->defaultShedTypeOptions();
        }

        $query = TextileReferenceMaster::query()
            ->type('shed_type')
            ->where('created_by', creatorId())
            ->where('is_active', true);

        if (Schema::hasColumn('textile_reference_masters', 'master_domain')) {
            $query->domain('manufacturing');
        }

        $options = $query->orderBy('name')->pluck('name')->values()->all();

        return count($options) > 0 ? $options : $this->defaultShedTypeOptions();
    }

    private function loomStatusOptions(): array
    {
        if (!Schema::hasTable('textile_reference_masters')) {
            return $this->defaultLoomStatusOptions();
        }

        $query = TextileReferenceMaster::query()
            ->type('loom_status')
            ->where('created_by', creatorId())
            ->where('is_active', true);

        if (Schema::hasColumn('textile_reference_masters', 'master_domain')) {
            $query->domain('manufacturing');
        }

        $options = $query->orderBy('name')->pluck('name')->values()->all();

        return count($options) > 0 ? $options : $this->defaultLoomStatusOptions();
    }

    private function breakdownReasonOptions(): array
    {
        if (!Schema::hasTable('textile_reference_masters')) {
            return $this->defaultBreakdownReasonOptions();
        }

        $query = TextileReferenceMaster::query()
            ->type('breakdown_reason')
            ->where('created_by', creatorId())
            ->where('is_active', true);

        if (Schema::hasColumn('textile_reference_masters', 'master_domain')) {
            $query->domain('manufacturing');
        }

        $options = $query->orderBy('name')->pluck('name')->values()->all();

        return count($options) > 0 ? $options : $this->defaultBreakdownReasonOptions();
    }

    private function maintenanceTypeOptions(): array
    {
        if (!Schema::hasTable('textile_reference_masters')) {
            return $this->defaultMaintenanceTypeOptions();
        }

        $query = TextileReferenceMaster::query()
            ->type('maintenance_type')
            ->where('created_by', creatorId())
            ->where('is_active', true);

        if (Schema::hasColumn('textile_reference_masters', 'master_domain')) {
            $query->domain('manufacturing');
        }

        $options = $query->orderBy('name')->pluck('name')->values()->all();

        return count($options) > 0 ? $options : $this->defaultMaintenanceTypeOptions();
    }

    private function shiftOptions(): array
    {
        return [
            'day',
            'night',
            'general',
        ];
    }

    private function dayTypeOptions(): array
    {
        return [
            'working',
            'holiday',
            'shutdown',
            'maintenance',
        ];
    }

    private function sourceActionOptions(): array
    {
        if (!Schema::hasTable('textile_reference_masters')) {
            return $this->defaultSourceActionOptions();
        }

        $query = TextileReferenceMaster::query()
            ->type('source_action')
            ->where('created_by', creatorId())
            ->where('is_active', true);

        if (Schema::hasColumn('textile_reference_masters', 'master_domain')) {
            $query->domain('manufacturing');
        }

        $options = $query->orderBy('name')->pluck('name')->values()->all();

        return count($options) > 0 ? $options : $this->defaultSourceActionOptions();
    }

    private function costTypeOptions(): array
    {
        if (!Schema::hasTable('textile_reference_masters')) {
            return $this->defaultCostTypeOptions();
        }

        $query = TextileReferenceMaster::query()
            ->type('cost_type')
            ->where('created_by', creatorId())
            ->where('is_active', true);

        if (Schema::hasColumn('textile_reference_masters', 'master_domain')) {
            $query->domain('manufacturing');
        }

        $options = $query->orderBy('name')->pluck('name')->values()->all();

        return count($options) > 0 ? $options : $this->defaultCostTypeOptions();
    }

    private function inspectionResultOptions(): array
    {
        if (!Schema::hasTable('textile_reference_masters')) {
            return $this->defaultInspectionResultOptions();
        }

        $query = TextileReferenceMaster::query()
            ->type('inspection_result')
            ->where('created_by', creatorId())
            ->where('is_active', true);

        if (Schema::hasColumn('textile_reference_masters', 'master_domain')) {
            $query->domain('manufacturing');
        }

        $options = $query->orderBy('name')->pluck('name')->values()->all();

        return count($options) > 0 ? $options : $this->defaultInspectionResultOptions();
    }

    private function fabricDefectOptions(): array
    {
        if (!Schema::hasTable('textile_reference_masters')) {
            return $this->defaultFabricDefectOptions();
        }

        $query = TextileReferenceMaster::query()
            ->type('fabric_defect')
            ->where('created_by', creatorId())
            ->where('is_active', true);

        if (Schema::hasColumn('textile_reference_masters', 'master_domain')) {
            $query->domain('manufacturing');
        }

        $options = $query->orderBy('name')->pluck('name')->values()->all();

        return count($options) > 0 ? $options : $this->defaultFabricDefectOptions();
    }

    private function fabricGradeOptions(): array
    {
        if (!Schema::hasTable('textile_reference_masters')) {
            return $this->defaultFabricGradeOptions();
        }

        $query = TextileReferenceMaster::query()
            ->type('fabric_grade')
            ->where('created_by', creatorId())
            ->where('is_active', true);

        if (Schema::hasColumn('textile_reference_masters', 'master_domain')) {
            $query->domain('manufacturing');
        }

        $options = $query->orderBy('name')->pluck('name')->values()->all();

        return count($options) > 0 ? $options : $this->defaultFabricGradeOptions();
    }

    private function warehouseOptions(): array
    {
        return [
            'main_warehouse',
            'grey_store',
            'dispatch_store',
            'quarantine_store',
        ];
    }

    private function defaultSourceTypeOptions(): array
    {
        return [
            'warp_plan',
            'beam_register',
            'sizing_recipe',
            'loom_allocation',
            'factory',
        ];
    }

    private function defaultMachineTypeOptions(): array
    {
        return [
            'rapier',
            'airjet',
            'waterjet',
            'shuttle',
        ];
    }

    private function defaultShedTypeOptions(): array
    {
        return [
            'open',
            'closed',
            'dobby',
            'jacquard',
        ];
    }

    private function defaultLoomStatusOptions(): array
    {
        return [
            'running',
            'idle',
        ];
    }

    private function defaultBreakdownReasonOptions(): array
    {
        return [
            'mechanical',
            'electrical',
            'yarn_break',
            'power_failure',
            'other',
        ];
    }

    private function defaultMaintenanceTypeOptions(): array
    {
        return [
            'preventive',
            'corrective',
            'lubrication',
            'cleaning',
            'other',
        ];
    }

    private function defaultSourceActionOptions(): array
    {
        return [
            'warp_plan',
            'beam_prepare',
            'loom_register',
        ];
    }

    private function defaultCostTypeOptions(): array
    {
        return [
            'sizing_overhead',
            'beam_preparation',
            'labor',
            'energy',
            'other',
        ];
    }

    private function defaultInspectionResultOptions(): array
    {
        return [
            'pass',
            'hold',
            'rework',
        ];
    }

    private function defaultFabricDefectOptions(): array
    {
        return [
            'slub',
            'missing_end',
            'broken_pick',
            'stain',
            'hole',
            'shade_variation',
        ];
    }

    private function defaultFabricGradeOptions(): array
    {
        return [
            'A',
            'B',
            'C',
            'reject',
        ];
    }

    private function partyOptions(): array
    {
        $customers = collect();
        if (Schema::hasTable('customers')) {
            $customers = Customer::query()
                ->where('created_by', creatorId())
                ->whereNotNull('company_name')
                ->pluck('company_name');
        }

        $workflowPartiesQuery = TextileWorkflowDocument::query()
            ->where('created_by', creatorId())
            ->whereNotNull('party_name');

        TextileBranchScope::applyWorkflowScope($workflowPartiesQuery);

        $workflowParties = $workflowPartiesQuery->pluck('party_name');

        return $customers
            ->merge($workflowParties)
            ->map(fn ($value) => trim((string) $value))
            ->filter(fn ($value) => $value !== '')
            ->unique()
            ->values()
            ->all();
    }

    private function lotReferenceOptions(): array
    {
        $lots = collect();
        if (Schema::hasTable('textile_lots')) {
            $lots = TextileLot::query()
                ->where('created_by', creatorId())
                ->where('is_active', true)
                ->pluck('lot_reference');
        }

        $workflowLotsQuery = TextileWorkflowDocument::query()
            ->where('created_by', creatorId())
            ->whereNotNull('lot_reference');

        TextileBranchScope::applyWorkflowScope($workflowLotsQuery);

        $workflowLots = $workflowLotsQuery->pluck('lot_reference');

        return $lots
            ->merge($workflowLots)
            ->map(fn ($value) => trim((string) $value))
            ->filter(fn ($value) => $value !== '')
            ->unique()
            ->values()
            ->all();
    }

    private function sizingVendorOptions(): array
    {
        if (! Schema::hasTable('vendors')) {
            return [];
        }

        return Vendor::query()
            ->where('created_by', creatorId())
            ->where('supplier_type', 'sizing')
            ->whereNotNull('company_name')
            ->orderBy('company_name')
            ->get(['vendor_code', 'company_name'])
            ->map(fn (Vendor $vendor) => [
                'value' => $vendor->company_name,
                'label' => trim(($vendor->vendor_code ? $vendor->vendor_code . ' | ' : '') . $vendor->company_name),
            ])
            ->values()
            ->all();
    }

    private function powerloomVendorOptions(): array
    {
        if (! Schema::hasTable('vendors')) {
            return [];
        }

        return Vendor::query()
            ->where('created_by', creatorId())
            ->where('supplier_type', 'powerloom')
            ->whereNotNull('company_name')
            ->orderBy('company_name')
            ->get(['id', 'vendor_code', 'company_name'])
            ->map(fn (Vendor $vendor) => [
                'value' => (string) $vendor->id,
                'label' => trim(($vendor->vendor_code ? $vendor->vendor_code . ' | ' : '') . $vendor->company_name),
            ])
            ->values()
            ->all();
    }

    private function yarnLotOptions(): array
    {
        if (! Schema::hasTable('textile_lots')) {
            return [];
        }

        return TextileLot::query()
            ->where('created_by', creatorId())
            ->where('is_active', true)
            ->where('material_type', TextileLot::TYPE_YARN)
            ->where('available_quantity', '>', 0)
            ->latest('created_at')
            ->get()
            ->map(function (TextileLot $lot) {
                $source = null;
                if ($lot->source_document_id && $lot->source_document_type) {
                    $source = TextileWorkflowDocument::query()
                        ->where('id', $lot->source_document_id)
                        ->where('created_by', creatorId())
                        ->where('document_type', $lot->source_document_type)
                        ->first(['party_name', 'unit']);
                }

                $quantity = rtrim(rtrim(number_format((float) $lot->available_quantity, 2, '.', ''), '0'), '.');
                $unit = $source?->unit ?: 'kg';
                $receivedDate = $lot->created_at?->format('d M Y');
                $details = array_filter([
                    $quantity . ' ' . $unit . ' available',
                    $receivedDate,
                    $source?->party_name,
                ]);

                return [
                    'id' => (int) $lot->id,
                    'value' => $lot->lot_reference,
                    'label' => $lot->lot_reference . ' (' . implode(' | ', $details) . ')',
                    'available_quantity' => $quantity,
                    'unit' => $unit,
                ];
            })
            ->values()
            ->all();
    }

    private function operatorOptions(): array
    {
        if (!Schema::hasTable('users')) {
            return [];
        }

        return User::query()
            ->where('created_by', creatorId())
            ->whereNotNull('name')
            ->orderBy('name')
            ->pluck('name')
            ->map(fn ($value) => trim((string) $value))
            ->filter(fn ($value) => $value !== '')
            ->values()
            ->all();
    }

    private function authorizeTextileAccess(): void
    {
        $user = Auth::user();

        abort_unless($user && in_array($user->type, ['company', 'superadmin', 'staff'], true), 403);
    }

    private function authorizeCapability(string $capability, string $errorKey): void
    {
        try {
            $this->policyService->assertCapability($capability);
        } catch (RuntimeException $exception) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                $errorKey => __($exception->getMessage()),
            ]);
        }
    }

    private function authorizeCapabilityOrAbort(string $capability): void
    {
        try {
            $this->policyService->assertCapability($capability);
        } catch (RuntimeException $exception) {
            abort(403, __($exception->getMessage()));
        }
    }
}
