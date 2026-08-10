<?php

namespace Database\Seeders;

use App\Models\User;
use DigitalFuzed\TextileCore\Models\TextileApprovalRule;
use DigitalFuzed\TextileCore\Models\TextileAuditLog;
use DigitalFuzed\TextileCore\Models\TextileBreakdown;
use DigitalFuzed\TextileCore\Models\TextileChemicalCost;
use DigitalFuzed\TextileCore\Models\TextileCostCenter;
use DigitalFuzed\TextileCore\Models\TextileDispatchDriver;
use DigitalFuzed\TextileCore\Models\TextileDispatchRoute;
use DigitalFuzed\TextileCore\Models\TextileDispatchVehicle;
use DigitalFuzed\TextileCore\Models\TextileFreightCost;
use DigitalFuzed\TextileCore\Models\TextileFuelEntry;
use DigitalFuzed\TextileCore\Models\TextileLabourCost;
use DigitalFuzed\TextileCore\Models\TextileMachineCost;
use DigitalFuzed\TextileCore\Models\TextileMaintenanceCost;
use DigitalFuzed\TextileCore\Models\TextileMaintenanceSparePartUsage;
use DigitalFuzed\TextileCore\Models\TextilePmSchedule;
use DigitalFuzed\TextileCore\Models\TextilePowerCost;
use DigitalFuzed\TextileCore\Models\TextileQualityProfile;
use DigitalFuzed\TextileCore\Models\TextileReferenceMaster;
use DigitalFuzed\TextileCore\Models\TextileRouteRecipe;
use DigitalFuzed\TextileCore\Models\TextileServiceSchedule;
use DigitalFuzed\TextileCore\Models\TextileSpecification;
use DigitalFuzed\TextileCore\Models\TextileUnitConversion;
use DigitalFuzed\TextileCore\Models\TextileVehicleMaintenance;
use DigitalFuzed\TextileCore\Models\TextileWorkflowDocument;
use DigitalFuzed\TextileInventory\Models\TextileLocation;
use DigitalFuzed\TextileInventory\Models\TextileLot;
use DigitalFuzed\TextileInventory\Models\TextileMovement;
use DigitalFuzed\TextileInventory\Models\TextileReservation;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Workdo\Account\Models\Customer;
use Workdo\Account\Models\Vendor;
use Workdo\Hrm\Models\Attendance;
use Workdo\Hrm\Models\Department;
use Workdo\Hrm\Models\Employee;
use Workdo\Hrm\Models\Shift;

class DemoTextileFlowSeeder extends Seeder
{
    public function run($userId = null): void
    {
        $companyId = $this->resolveCompanyId($userId);
        if (! $companyId) {
            $this->command?->warn('No company user found for textile demo seeding.');
            return;
        }

        $this->seedMasters($companyId);
        $this->seedReferenceMasters($companyId);
        $this->seedVendors($companyId);
        $this->seedCustomers($companyId);
        $this->seedInventory($companyId);
        $this->seedWorkflow($companyId);
        $this->seedMaintenance($companyId);
        $this->seedFinance($companyId);
        $this->seedTransport($companyId);
        $this->seedHr($companyId);
        $this->seedApprovals($companyId);

        $this->command?->info("Textile demo data seeded for company user {$companyId}.");
    }

    private function daysAgo(int $days): string
    {
        return now()->subDays($days)->toDateString();
    }

    private function resolveCompanyId($userId): ?int
    {
        if (! empty($userId)) {
            return (int) $userId;
        }

        $company = User::query()->where('type', 'company')->orderBy('id')->first();
        return $company ? (int) $company->id : null;
    }

    /**
     * Resolve the tenant's first branch id (branches table uses branch_name, not name).
     * Falls back to null if the tenant has no branches yet.
     */
    private function defaultBranchId(int $companyId): ?int
    {
        return DB::table('branches')
            ->where('created_by', $companyId)
            ->orderBy('id')
            ->value('id');
    }

    private function seedVendors(int $companyId): void
    {
        $attributes = [
            'contact_person_name' => 'Rajesh Kumar',
            'contact_person_email' => 'rajesh@shreeyarn.com',
            'primary_email' => 'sales@shreeyarn.com',
            'tax_number' => 'GSTIN27AACCS1234F1Z5',
            'payment_terms' => 'net_30',
            'currency_code' => 'INR',
            'credit_limit' => 2500000,
            'billing_address' => [
                'name' => 'Shree Yarn Traders',
                'address_line_1' => 'Plot 12, Ring Road Industrial Estate',
                'address_line_2' => '',
                'city' => 'Surat',
                'state' => 'Gujarat',
                'country' => 'India',
                'zip_code' => '395002',
            ],
            'shipping_address' => [
                'name' => 'Shree Yarn Traders',
                'address_line_1' => 'Plot 12, Ring Road Industrial Estate',
                'address_line_2' => '',
                'city' => 'Surat',
                'state' => 'Gujarat',
                'country' => 'India',
                'zip_code' => '395002',
            ],
            'same_as_billing' => true,
            'creator_id' => $companyId,
            'created_by' => $companyId,
        ];

        foreach (array_keys($attributes) as $column) {
            if (! Schema::hasColumn('vendors', $column)) {
                unset($attributes[$column]);
            }
        }

        Vendor::updateOrCreate(
            ['created_by' => $companyId, 'company_name' => 'Shree Yarn Traders'],
            $attributes
        );
    }

    public function seedCustomers(int $companyId): void
    {
        $customers = [
            [
                'company_name' => 'Metro Fashions Pvt Ltd',
                'contact_person_name' => 'Ananya Mehta',
                'contact_person_email' => 'orders@metrofashions.test',
                'contact_person_mobile' => '+919820001101',
                'tax_number' => 'GSTIN27METRO1101A1Z5',
                'payment_terms' => 'Net 30',
                'credit_limit' => 1500000,
                'currency_code' => 'INR',
                'operating_model' => 'full_package_buyer',
                'material_ownership' => 'company_owned',
                'billing_mode' => 'sale_value',
                'city' => 'Mumbai',
                'state' => 'Maharashtra',
            ],
            [
                'company_name' => 'Surat Textile Distributors',
                'contact_person_name' => 'Dhruv Shah',
                'contact_person_email' => 'purchase@surattextile.test',
                'contact_person_mobile' => '+919825001202',
                'tax_number' => 'GSTIN24SURAT1202B1Z4',
                'payment_terms' => 'Net 45',
                'credit_limit' => 2000000,
                'currency_code' => 'INR',
                'operating_model' => 'full_package_buyer',
                'material_ownership' => 'company_owned',
                'billing_mode' => 'sale_value',
                'city' => 'Surat',
                'state' => 'Gujarat',
            ],
            [
                'company_name' => 'Aster Export House',
                'contact_person_name' => 'Mira Desai',
                'contact_person_email' => 'sourcing@asterexport.test',
                'contact_person_mobile' => '+919810001303',
                'tax_number' => 'GSTIN07ASTER1303C1Z3',
                'payment_terms' => 'Net 30',
                'credit_limit' => 2500000,
                'currency_code' => 'INR',
                'operating_model' => 'full_package_buyer',
                'material_ownership' => 'company_owned',
                'billing_mode' => 'sale_value',
                'city' => 'New Delhi',
                'state' => 'Delhi',
            ],
        ];

        foreach ($customers as $row) {
            $address = [
                'name' => $row['company_name'],
                'address_line_1' => 'Textile Market',
                'address_line_2' => '',
                'city' => $row['city'],
                'state' => $row['state'],
                'country' => 'India',
                'zip_code' => '000000',
            ];

            $attributes = [
                'contact_person_name' => $row['contact_person_name'],
                'contact_person_email' => $row['contact_person_email'],
                'contact_person_mobile' => $row['contact_person_mobile'],
                'tax_number' => $row['tax_number'],
                'payment_terms' => $row['payment_terms'],
                'credit_limit' => $row['credit_limit'],
                'currency_code' => $row['currency_code'],
                'operating_model' => $row['operating_model'],
                'material_ownership' => $row['material_ownership'],
                'billing_mode' => $row['billing_mode'],
                'billing_address' => $address,
                'shipping_address' => $address,
                'same_as_billing' => true,
                'notes' => 'Seeded textile sales customer',
                'creator_id' => $companyId,
            ];

            foreach (array_keys($attributes) as $column) {
                if (! Schema::hasColumn('customers', $column)) {
                    unset($attributes[$column]);
                }
            }

            Customer::updateOrCreate(
                ['created_by' => $companyId, 'company_name' => $row['company_name']],
                $attributes
            );
        }
    }

    private function seedMasters(int $companyId): void
    {
        $specifications = [
            ['name' => 'Cotton Poplin 60x60', 'code' => 'SPEC-POP-001', 'family' => 'Woven', 'composition' => '100% Cotton', 'construction' => '60x60 / 92x88', 'width' => '58 inch', 'gsm' => '110', 'shade' => 'Reactive Navy'],
            ['name' => 'PC Twill 2/1', 'code' => 'SPEC-TWL-002', 'family' => 'Woven', 'composition' => '65% Polyester / 35% Cotton', 'construction' => '30s x 30s', 'width' => '63 inch', 'gsm' => '190', 'shade' => 'Khaki'],
            ['name' => 'Single Jersey Knit', 'code' => 'SPEC-KNT-003', 'family' => 'Knits', 'composition' => '100% Cotton', 'construction' => '30s SJ', 'width' => '72 inch', 'gsm' => '160', 'shade' => 'Optic White'],
        ];

        foreach ($specifications as $row) {
            TextileSpecification::updateOrCreate(
                ['created_by' => $companyId, 'name' => $row['name']],
                array_merge($row, ['creator_id' => $companyId, 'is_active' => true])
            );
        }

        $qualityProfiles = [
            ['name' => 'A-Grade Export', 'code' => 'QPA', 'grade' => 'A', 'parameters' => 'GSM tolerance +/-3%, shade 4-5, shrinkage under 3%'],
            ['name' => 'B-Grade Domestic', 'code' => 'QPB', 'grade' => 'B', 'parameters' => 'GSM tolerance +/-5%, shade 3-4, shrinkage under 5%'],
        ];

        foreach ($qualityProfiles as $row) {
            TextileQualityProfile::updateOrCreate(
                ['created_by' => $companyId, 'name' => $row['name']],
                array_merge($row, ['creator_id' => $companyId, 'is_active' => true])
            );
        }

        $routes = [
            ['name' => 'Woven Reactive Route', 'code' => 'RR-WOV-01', 'steps' => ['Sizing', 'Weaving', 'Desizing', 'Bleaching', 'Reactive Dyeing', 'Finishing']],
            ['name' => 'Knit Compact Route', 'code' => 'RR-KNT-02', 'steps' => ['Knitting', 'Scouring', 'Compacting', 'Softening']],
        ];

        foreach ($routes as $row) {
            TextileRouteRecipe::updateOrCreate(
                ['created_by' => $companyId, 'name' => $row['name']],
                array_merge($row, ['creator_id' => $companyId, 'is_active' => true])
            );
        }

        $conversions = [
            ['from_unit' => 'kg', 'to_unit' => 'mtr', 'factor' => 3.650000],
            ['from_unit' => 'mtr', 'to_unit' => 'yard', 'factor' => 1.093610],
            ['from_unit' => 'roll', 'to_unit' => 'mtr', 'factor' => 92.000000],
        ];

        foreach ($conversions as $row) {
            TextileUnitConversion::updateOrCreate(
                ['created_by' => $companyId, 'from_unit' => $row['from_unit'], 'to_unit' => $row['to_unit']],
                array_merge($row, ['creator_id' => $companyId, 'is_active' => true])
            );
        }
    }

    private function seedInventory(int $companyId): void
    {
        $branchId = $this->defaultBranchId($companyId);

        $locations = [
            ['name' => 'Grey Fabric Warehouse', 'code' => 'LOC-GREY', 'location_type' => 'warehouse'],
            ['name' => 'Dyeing Floor', 'code' => 'LOC-DYE', 'location_type' => 'process-house'],
            ['name' => 'Finishing Area', 'code' => 'LOC-FIN', 'location_type' => 'process-house'],
            ['name' => 'Dispatch Bay 1', 'code' => 'LOC-DSP1', 'location_type' => 'dispatch'],
        ];

        foreach ($locations as $row) {
            TextileLocation::updateOrCreate(
                ['created_by' => $companyId, 'name' => $row['name']],
                array_merge($row, ['creator_id' => $companyId, 'is_active' => true])
            );
        }

        // Opening grey fabric stock.
        $lots = [
            ['lot_reference' => 'LOT-2401-A', 'received_quantity' => 1500, 'available_quantity' => 1120, 'status' => 'active'],
            ['lot_reference' => 'LOT-2401-B', 'received_quantity' => 980, 'available_quantity' => 700, 'status' => 'active'],
            ['lot_reference' => 'LOT-2402-C', 'received_quantity' => 760, 'available_quantity' => 760, 'status' => 'hold'],
        ];

        foreach ($lots as $row) {
            TextileLot::updateOrCreate(
                ['created_by' => $companyId, 'lot_reference' => $row['lot_reference']],
                array_merge($row, [
                    'creator_id' => $companyId,
                    'is_active' => true,
                    'material_type' => TextileLot::TYPE_GREY_FABRIC,
                    'production_stage' => TextileLot::STAGE_WEAVING,
                ])
            );
        }

        $movements = [
            ['movement_type' => 'receipt', 'lot_reference' => 'LOT-2401-A', 'location_to' => 'Grey Fabric Warehouse', 'quantity' => 1500, 'unit' => 'mtr', 'status' => 'posted', 'notes' => 'Opening inward lot receipt'],
            ['movement_type' => 'transfer', 'lot_reference' => 'LOT-2401-A', 'location_from' => 'Grey Fabric Warehouse', 'location_to' => 'Dyeing Floor', 'quantity' => 380, 'unit' => 'mtr', 'status' => 'posted', 'notes' => 'Moved for reactive dyeing'],
            ['movement_type' => 'transfer', 'lot_reference' => 'LOT-2401-A', 'location_from' => 'Dyeing Floor', 'location_to' => 'Finishing Area', 'quantity' => 350, 'unit' => 'mtr', 'status' => 'posted', 'notes' => 'Post-process move to finishing'],
        ];

        foreach ($movements as $index => $row) {
            TextileMovement::updateOrCreate(
                ['created_by' => $companyId, 'movement_type' => $row['movement_type'], 'lot_reference' => $row['lot_reference'], 'notes' => $row['notes']],
                array_merge($row, [
                    'creator_id' => $companyId,
                    'is_active' => true,
                    'reference_type' => 'inventory_demo',
                    'reference_id' => $index + 1,
                    'branch_id' => $branchId,
                ])
            );
        }

        $reservations = [
            ['lot_reference' => 'LOT-2401-A', 'reference_type' => 'sales_order', 'reference_id' => 101, 'reserved_quantity' => 220, 'status' => 'reserved'],
            ['lot_reference' => 'LOT-2401-B', 'reference_type' => 'sales_order', 'reference_id' => 102, 'reserved_quantity' => 140, 'status' => 'reserved'],
        ];

        foreach ($reservations as $row) {
            TextileReservation::updateOrCreate(
                ['created_by' => $companyId, 'lot_reference' => $row['lot_reference'], 'reference_type' => $row['reference_type'], 'reference_id' => $row['reference_id']],
                array_merge($row, ['creator_id' => $companyId, 'is_active' => true])
            );
        }
    }

    private function seedWorkflow(int $companyId): void
    {
        $branchId = $this->defaultBranchId($companyId);

        $documents = [
            ['document_type' => 'purchase_requisition', 'document_number' => 'TRQ-0001', 'party_name' => 'Shree Yarn Traders', 'lot_reference' => 'LOT-2403-RM', 'quantity' => 1200, 'unit' => 'kg', 'status' => 'approved'],
            ['document_type' => 'purchase_order', 'document_number' => 'TPO-0001', 'party_name' => 'Shree Yarn Traders', 'lot_reference' => 'LOT-2403-RM', 'quantity' => 1200, 'unit' => 'kg', 'status' => 'approved'],
            ['document_type' => 'grn', 'document_number' => 'TGRN-0001', 'party_name' => 'Shree Yarn Traders', 'lot_reference' => 'LOT-2403-RM', 'quantity' => 1200, 'unit' => 'kg', 'status' => 'released'],
            ['document_type' => 'incoming_qc', 'document_number' => 'TIQC-0001', 'party_name' => 'Shree Yarn Traders', 'lot_reference' => 'LOT-2403-RM', 'quantity' => 1200, 'unit' => 'kg', 'status' => 'approved', 'metadata' => ['decision' => 'pass']],

            ['document_type' => 'sales_order', 'document_number' => 'TSO-0001', 'party_name' => 'Metro Fashions Pvt Ltd', 'lot_reference' => 'LOT-2401-A', 'quantity' => 320, 'unit' => 'mtr', 'status' => 'approved'],
            ['document_type' => 'allocation', 'document_number' => 'TAL-0001', 'party_name' => 'Metro Fashions Pvt Ltd', 'lot_reference' => 'LOT-2401-A', 'quantity' => 320, 'unit' => 'mtr', 'status' => 'released'],
            ['document_type' => 'dispatch', 'document_number' => 'TDSP-0001', 'party_name' => 'Metro Fashions Pvt Ltd', 'lot_reference' => 'LOT-2401-A', 'quantity' => 300, 'unit' => 'mtr', 'status' => 'released'],
            ['document_type' => 'challan', 'document_number' => 'TCH-0001', 'party_name' => 'Metro Fashions Pvt Ltd', 'lot_reference' => 'LOT-2401-A', 'quantity' => 300, 'unit' => 'mtr', 'status' => 'released'],
            ['document_type' => 'pod', 'document_number' => 'TPOD-0001', 'party_name' => 'Metro Fashions Pvt Ltd', 'lot_reference' => 'LOT-2401-A', 'quantity' => 300, 'unit' => 'mtr', 'status' => 'approved'],

            ['document_type' => 'production_batch', 'document_number' => 'TPB-0001', 'party_name' => 'Unit-1 Weaving', 'lot_reference' => 'LOT-2401-A', 'quantity' => 500, 'unit' => 'mtr', 'status' => 'released'],
            ['document_type' => 'job_work_outward', 'document_number' => 'TJWO-0001', 'party_name' => 'Apex Processing House', 'lot_reference' => 'LOT-2401-B', 'quantity' => 280, 'unit' => 'mtr', 'status' => 'released'],
            ['document_type' => 'job_work_inward', 'document_number' => 'TJWI-0001', 'party_name' => 'Apex Processing House', 'lot_reference' => 'LOT-2401-B', 'quantity' => 272, 'unit' => 'mtr', 'status' => 'approved'],

            ['document_type' => 'costing_entry', 'document_number' => 'TCST-0001', 'party_name' => 'Metro Fashions Pvt Ltd', 'lot_reference' => 'LOT-2401-A', 'quantity' => 300, 'unit' => 'mtr', 'status' => 'approved', 'metadata' => ['material_cost' => 98000, 'conversion_cost' => 32000, 'overhead_cost' => 14000, 'variance_value' => 1200, 'revenue_value' => 178000, 'total_cost' => 145200, 'margin_value' => 32800, 'margin_percent' => 18.43, 'unit_cost' => 484]],
            ['document_type' => 'margin_snapshot', 'document_number' => 'TMS-0001', 'party_name' => 'Metro Fashions Pvt Ltd', 'lot_reference' => 'LOT-2401-A', 'quantity' => 300, 'unit' => 'mtr', 'status' => 'approved', 'metadata' => ['revenue_value' => 178000, 'total_cost' => 145200, 'margin_value' => 32800, 'margin_percent' => 18.43]],

            // Manufacturing masters + flow
            ['document_type' => 'loom_master', 'document_number' => 'LM-0001', 'party_name' => 'Unit-1 Weaving', 'quantity' => 0, 'unit' => 'pcs', 'status' => 'approved', 'metadata' => ['machine_name' => 'Rapier-1', 'machine_type' => 'Rapier', 'rpm' => 420, 'width' => '72 inch', 'shed_type' => 'dobby', 'loom_status' => 'running']],
            ['document_type' => 'loom_master', 'document_number' => 'LM-0002', 'party_name' => 'Unit-1 Weaving', 'quantity' => 0, 'unit' => 'pcs', 'status' => 'approved', 'metadata' => ['machine_name' => 'Airjet-1', 'machine_type' => 'Airjet', 'rpm' => 600, 'width' => '75 inch', 'shed_type' => 'jacquard', 'loom_status' => 'running']],
            ['document_type' => 'loom_master', 'document_number' => 'LM-0003', 'party_name' => 'Unit-1 Weaving', 'quantity' => 0, 'unit' => 'pcs', 'status' => 'approved', 'metadata' => ['machine_name' => 'Waterjet-1', 'machine_type' => 'Waterjet', 'rpm' => 520, 'width' => '70 inch', 'shed_type' => 'plain', 'loom_status' => 'idle']],
            ['document_type' => 'warp_plan', 'document_number' => 'TWP-0001', 'party_name' => 'Unit-1 Weaving', 'lot_reference' => 'LOT-2403-RM', 'quantity' => 500, 'unit' => 'kg', 'status' => 'approved', 'metadata' => ['yarn_count' => '30s', 'ends_total' => 4800, 'plan_date' => $this->daysAgo(9)]],
            ['document_type' => 'warp_sheet', 'document_number' => 'TWS-0001', 'party_name' => 'Unit-1 Weaving', 'lot_reference' => 'LOT-2403-RM', 'quantity' => 500, 'unit' => 'kg', 'status' => 'approved', 'metadata' => ['warp_plan' => 'TWP-0001', 'sheet_length' => 12000, 'created_on' => $this->daysAgo(8)]],
            ['document_type' => 'beam', 'document_number' => 'TBM-0001', 'party_name' => 'Unit-1 Weaving', 'lot_reference' => 'LOT-2403-RM', 'quantity' => 500, 'unit' => 'kg', 'status' => 'approved', 'metadata' => ['warp_sheet' => 'TWS-0001', 'beam_number' => 'BM-1001', 'width' => '72 inch', 'created_on' => $this->daysAgo(7)]],
            ['document_type' => 'weaving_output', 'document_number' => 'TWO-0001', 'party_name' => 'Unit-1 Weaving', 'lot_reference' => 'LOT-2401-A', 'quantity' => 420, 'unit' => 'mtr', 'status' => 'approved', 'metadata' => ['batch' => 'TPB-0001', 'loom_master' => 'LM-0001', 'machine_name' => 'Rapier-1', 'planned_shift' => 'day', 'operator_name' => 'Ramesh Kumar', 'runtime_hours' => 7.5, 'efficiency_percent' => 88.5, 'output_date' => $this->daysAgo(6)]],
            ['document_type' => 'shift_production', 'document_number' => 'TSP-0001', 'party_name' => 'Unit-1 Weaving', 'lot_reference' => 'LOT-2401-A', 'quantity' => 210, 'unit' => 'mtr', 'status' => 'approved', 'metadata' => ['batch' => 'TPB-0001', 'loom_master' => 'LM-0001', 'planned_shift' => 'day', 'operator_name' => 'Ramesh Kumar', 'output_date' => $this->daysAgo(6)]],
            ['document_type' => 'loom_efficiency', 'document_number' => 'TLE-0001', 'party_name' => 'Unit-1 Weaving', 'lot_reference' => 'LOT-2401-A', 'quantity' => 420, 'unit' => 'mtr', 'status' => 'approved', 'metadata' => ['loom_master' => 'LM-0001', 'planned_shift' => 'day', 'planned_quantity' => 475, 'actual_quantity' => 420, 'efficiency_percent' => 88.5, 'runtime_hours' => 7.5, 'downtime_hours' => 0.5]],
            ['document_type' => 'operator_efficiency', 'document_number' => 'TOE-0001', 'party_name' => 'Ramesh Kumar', 'quantity' => 420, 'unit' => 'mtr', 'status' => 'approved', 'metadata' => ['loom_master' => 'LM-0001', 'planned_shift' => 'day', 'planned_quantity' => 475, 'actual_quantity' => 420, 'efficiency_percent' => 88.5]],
            ['document_type' => 'machine_downtime', 'document_number' => 'TMD-0001', 'party_name' => 'Unit-1 Weaving', 'quantity' => 0, 'unit' => 'hour', 'status' => 'approved', 'metadata' => ['loom_master' => 'LM-0001', 'planned_shift' => 'day', 'downtime_reason' => 'warp_breakage', 'downtime_hours' => 1.5, 'downtime_date' => $this->daysAgo(4)]],
            ['document_type' => 'grey_fabric_roll', 'document_number' => 'TGR-0001', 'party_name' => 'Unit-1 Weaving', 'lot_reference' => 'LOT-2401-A', 'quantity' => 96, 'unit' => 'mtr', 'status' => 'approved', 'metadata' => ['roll_number' => 'GR-2406-001', 'roll_weight' => 38.4, 'roll_length' => 96, 'gsm' => 112, 'grade' => 'A', 'width' => '58 inch', 'warehouse' => 'Grey Fabric Warehouse', 'roll_barcode' => '8901234567890', 'roll_qr_code' => 'QR-GR-2406-001']],
            ['document_type' => 'waste', 'document_number' => 'TWS-0001', 'party_name' => 'Unit-1 Weaving', 'lot_reference' => 'LOT-2401-A', 'quantity' => 12, 'unit' => 'kg', 'status' => 'approved', 'metadata' => ['waste_type' => 'selvedge_cut', 'waste_date' => $this->daysAgo(5), 'disposal' => 'recycle']],
            ['document_type' => 'rework', 'document_number' => 'TRW-0001', 'party_name' => 'Unit-1 Weaving', 'lot_reference' => 'LOT-2401-B', 'quantity' => 40, 'unit' => 'mtr', 'status' => 'approved', 'metadata' => ['rework_reason' => 'weft_streak', 'rework_date' => $this->daysAgo(3), 'completed' => true]],

            // Takha entries cut from the weaving output grey fabric (saleable lots).
            ['document_type' => 'takha_entry', 'document_number' => 'TAK-0001', 'party_name' => 'Unit-1 Weaving', 'lot_reference' => 'LOT-2401-A', 'quantity' => 120, 'unit' => 'mtr', 'status' => 'approved', 'metadata' => ['source_type' => 'weaving_output', 'source_reference' => 'TWO-0001', 'takha_number' => 'TK-0001', 'fabric_type' => 'grey_fabric', 'cut_on' => $this->daysAgo(5)]],
            ['document_type' => 'takha_entry', 'document_number' => 'TAK-0002', 'party_name' => 'Unit-1 Weaving', 'lot_reference' => 'LOT-2401-A', 'quantity' => 100, 'unit' => 'mtr', 'status' => 'approved', 'metadata' => ['source_type' => 'weaving_output', 'source_reference' => 'TWO-0001', 'takha_number' => 'TK-0002', 'fabric_type' => 'grey_fabric', 'cut_on' => $this->daysAgo(5)]],
            ['document_type' => 'takha_entry', 'document_number' => 'TAK-0003', 'party_name' => 'Unit-1 Weaving', 'lot_reference' => 'LOT-2401-A', 'quantity' => 200, 'unit' => 'mtr', 'status' => 'approved', 'metadata' => ['source_type' => 'weaving_output', 'source_reference' => 'TWO-0001', 'takha_number' => 'TK-0003', 'fabric_type' => 'grey_fabric', 'cut_on' => $this->daysAgo(4)]],

            // Processing + quality + packing + dispatch planning
            ['document_type' => 'processing_batch', 'document_number' => 'TPB-9001', 'party_name' => 'Apex Processing House', 'lot_reference' => 'LOT-2401-B', 'quantity' => 280, 'unit' => 'mtr', 'status' => 'released', 'metadata' => ['process_stage' => 'dyeing', 'batch_ref' => 'PB-DYE-07', 'issued_on' => $this->daysAgo(5)]],
            ['document_type' => 'dyeing', 'document_number' => 'TDYE-0001', 'party_name' => 'Apex Processing House', 'lot_reference' => 'LOT-2401-B', 'quantity' => 275, 'unit' => 'mtr', 'status' => 'approved', 'metadata' => ['process_stage' => 'dyeing', 'shade' => 'Reactive Navy', 'recipe' => 'RR-WOV-01', 'completed_on' => $this->daysAgo(4)]],
            ['document_type' => 'inspection', 'document_number' => 'TQC-0001', 'party_name' => 'Metro Fashions Pvt Ltd', 'lot_reference' => 'LOT-2401-A', 'quantity' => 300, 'unit' => 'mtr', 'status' => 'approved', 'metadata' => ['qc_stage' => 'final_qc', 'inspection_result' => 'pass', 'fabric_defects' => [], 'remarks' => 'Shade OK, GSM within tolerance', 'inspected_on' => $this->daysAgo(2)]],
            ['document_type' => 'quality_certificate', 'document_number' => 'TCERT-0001', 'party_name' => 'Metro Fashions Pvt Ltd', 'lot_reference' => 'LOT-2401-A', 'quantity' => 300, 'unit' => 'mtr', 'status' => 'issued', 'metadata' => ['certificate_number' => 'QCERT-2026-001', 'certified_on' => $this->daysAgo(2), 'grade' => 'A']],
            ['document_type' => 'roll_packing', 'document_number' => 'TRP-0001', 'party_name' => 'Metro Fashions Pvt Ltd', 'lot_reference' => 'LOT-2401-A', 'quantity' => 96, 'unit' => 'roll', 'status' => 'released', 'metadata' => ['packing_material' => 'poly_bag', 'roll_count' => 3, 'packed_on' => $this->daysAgo(1)]],
            ['document_type' => 'bale_packing', 'document_number' => 'TBP-0001', 'party_name' => 'Metro Fashions Pvt Ltd', 'lot_reference' => 'LOT-2401-A', 'quantity' => 4, 'unit' => 'bale', 'status' => 'released', 'metadata' => ['packing_material' => 'jute_bale', 'bale_weight' => 210, 'packed_on' => $this->daysAgo(1)]],
            ['document_type' => 'label', 'document_number' => 'TLBL-0001', 'party_name' => 'Metro Fashions Pvt Ltd', 'lot_reference' => 'LOT-2401-A', 'quantity' => 6, 'unit' => 'pcs', 'status' => 'issued', 'metadata' => ['label_type' => 'barcode', 'barcode' => '8901234567890', 'issued_on' => $this->daysAgo(1)]],
            ['document_type' => 'dispatch_plan', 'document_number' => 'TDPL-0001', 'party_name' => 'Metro Fashions Pvt Ltd', 'lot_reference' => 'LOT-2401-A', 'quantity' => 300, 'unit' => 'mtr', 'status' => 'released', 'metadata' => ['mode' => 'truck', 'vehicle_name' => 'MH-12-AB-1234', 'driver_name' => 'Suresh Patil', 'route_name' => 'Surat-Mumbai', 'lr_number' => 'LR-77881', 'eway_bill' => 'EWB-331001234567', 'challan_number' => 'TCH-0001', 'tracking_status' => 'in_transit', 'planned_date' => $this->daysAgo(1)]],
        ];

        foreach ($documents as $row) {
            TextileWorkflowDocument::updateOrCreate(
                ['created_by' => $companyId, 'document_number' => $row['document_number']],
                array_merge($row, ['creator_id' => $companyId, 'branch_id' => $branchId])
            );
        }

        // Yarn lot that the procurement docs (TRQ/TPO/TGRN/TIQC) reference, material-typed so
        // it shows up correctly in the Inventory table. Created after the docs so the GRN
        // document id is available for traceability.
        $grnDoc = TextileWorkflowDocument::query()
            ->where('created_by', $companyId)
            ->where('document_type', 'grn')
            ->where('document_number', 'TGRN-0001')
            ->value('id');

        TextileLot::updateOrCreate(
            ['created_by' => $companyId, 'lot_reference' => 'LOT-2403-RM'],
            [
                'received_quantity' => 1200,
                'available_quantity' => 1200,
                'status' => 'active',
                'material_type' => TextileLot::TYPE_YARN,
                'production_stage' => TextileLot::STAGE_PROCUREMENT,
                'source_document_type' => 'grn',
                'source_document_id' => $grnDoc,
                'is_active' => true,
                'creator_id' => $companyId,
            ]
        );

        // Takha lots (grey fabric) linked to the takha_entry docs so the sales gate
        // (source_document_type = takha_entry) accepts them for new sales orders.
        $takhaLots = [
            ['lot_reference' => 'TAK-LOT-0001', 'quantity' => 120, 'doc' => 'TAK-0001'],
            ['lot_reference' => 'TAK-LOT-0002', 'quantity' => 100, 'doc' => 'TAK-0002'],
            ['lot_reference' => 'TAK-LOT-0003', 'quantity' => 200, 'doc' => 'TAK-0003'],
        ];

        foreach ($takhaLots as $row) {
            $takhaDocId = TextileWorkflowDocument::query()
                ->where('created_by', $companyId)
                ->where('document_type', 'takha_entry')
                ->where('document_number', $row['doc'])
                ->value('id');

            TextileLot::updateOrCreate(
                ['created_by' => $companyId, 'lot_reference' => $row['lot_reference']],
                [
                    'received_quantity' => $row['quantity'],
                    'available_quantity' => $row['quantity'],
                    'status' => 'active',
                    'material_type' => TextileLot::TYPE_GREY_FABRIC,
                    'production_stage' => TextileLot::STAGE_QUALITY_APPROVED,
                    'source_document_type' => 'takha_entry',
                    'source_document_id' => $takhaDocId,
                    'is_active' => true,
                    'creator_id' => $companyId,
                ]
            );
        }
    }

    private function seedReferenceMasters(int $companyId): void
    {
        $masters = [
            'machine_type' => ['Rapier', 'Airjet', 'Waterjet', 'Projectile'],
            'source_type' => ['inventory_lot', 'sales_order', 'purchase_order', 'production_batch', 'processing_batch'],
            'source_action' => ['convert', 'release', 'allocate', 'finalize'],
            'inspection_result' => ['pass', 'fail', 'hold'],
            'fabric_defect' => ['Slub', 'Hole', 'Staining', 'Weft Streak', 'Broken Pick'],
            'fabric_grade' => ['A', 'B', 'C'],
            'maintenance_type' => ['Preventive', 'Corrective', 'Predictive'],
            'breakdown_reason' => ['Shuttle Jam', 'Bearing Failure', 'Warp Breakage', 'Electrical Fault'],
            'fuel_type' => ['Diesel', 'Petrol', 'CNG'],
            'freight_type' => ['Full Truck Load', 'Part Load', 'Container'],
            'cost_type' => ['Material', 'Conversion', 'Overhead', 'Transport'],
            'process_stage' => ['Sizing', 'Weaving', 'Dyeing', 'Printing', 'Finishing', 'Compacting'],
            'shift' => ['Day', 'Night', 'Evening'],
            'unit' => ['kg', 'mtr', 'pcs', 'roll', 'cone', 'set', 'bale'],
        ];

        foreach ($masters as $masterType => $names) {
            foreach ($names as $name) {
                TextileReferenceMaster::updateOrCreate(
                    ['master_type' => $masterType, 'created_by' => $companyId, 'name' => $name],
                    ['code' => null, 'description' => null, 'is_active' => true, 'creator_id' => $companyId]
                );
            }
        }

        $costCenters = [
            ['name' => 'Weaving Unit-1', 'code' => 'CC-WV1', 'notes' => 'Rapier and Airjet weaving'],
            ['name' => 'Dyeing House', 'code' => 'CC-DYE', 'notes' => 'Reactive and disperse dyeing'],
            ['name' => 'Finishing Section', 'code' => 'CC-FIN', 'notes' => 'Stentering, compacting, softening'],
            ['name' => 'Utilities', 'code' => 'CC-UTL', 'notes' => 'Power, steam, compressed air'],
        ];

        foreach ($costCenters as $row) {
            TextileCostCenter::updateOrCreate(
                ['created_by' => $companyId, 'name' => $row['name']],
                array_merge($row, ['creator_id' => $companyId, 'is_active' => true])
            );
        }
    }

    private function seedMaintenance(int $companyId): void
    {
        TextilePmSchedule::updateOrCreate(
            ['created_by' => $companyId, 'pm_code' => 'PM-0001'],
            ['scheduled_date' => $this->daysAgo(5), 'next_due_date' => now()->addDays(25)->toDateString(), 'machine_name' => 'LM-0001', 'machine_type' => 'Rapier', 'maintenance_type' => 'Preventive', 'frequency_type' => 'days', 'frequency_value' => 30, 'task_description' => 'Lubricate cam box, check reed alignment, tighten selvedge motors', 'status' => 'scheduled', 'creator_id' => $companyId, 'is_active' => true, 'created_by' => $companyId]
        );
        TextilePmSchedule::updateOrCreate(
            ['created_by' => $companyId, 'pm_code' => 'PM-0002'],
            ['scheduled_date' => $this->daysAgo(2), 'next_due_date' => now()->addDays(12)->toDateString(), 'machine_name' => 'LM-0002', 'machine_type' => 'Airjet', 'maintenance_type' => 'Preventive', 'frequency_type' => 'days', 'frequency_value' => 15, 'task_description' => 'Check air nozzles, clean filters, verify pick timing', 'status' => 'scheduled', 'creator_id' => $companyId, 'is_active' => true, 'created_by' => $companyId]
        );

        TextileBreakdown::updateOrCreate(
            ['created_by' => $companyId, 'breakdown_code' => 'BD-0001'],
            ['breakdown_date' => $this->daysAgo(4), 'machine_name' => 'LM-0001', 'machine_type' => 'Rapier', 'fault_description' => 'Shuttle jam on left selvedge with warp breakage', 'symptom' => 'Shuttle Jam', 'impact' => 'Stoppage 90 minutes', 'status' => 'resolved', 'resolved_date' => $this->daysAgo(4), 'downtime_minutes' => 90, 'notes' => 'Replaced shuttle guide and re-threaded warp', 'creator_id' => $companyId, 'is_active' => true, 'created_by' => $companyId]
        );
        TextileBreakdown::updateOrCreate(
            ['created_by' => $companyId, 'breakdown_code' => 'BD-0002'],
            ['breakdown_date' => $this->daysAgo(1), 'machine_name' => 'LM-0003', 'machine_type' => 'Waterjet', 'fault_description' => 'High pressure pump losing pressure', 'symptom' => 'Bearing Failure', 'impact' => 'Machine stopped', 'status' => 'reported', 'resolved_date' => null, 'downtime_minutes' => 45, 'notes' => 'Awaiting spare pump seal', 'creator_id' => $companyId, 'is_active' => true, 'created_by' => $companyId]
        );

        TextileServiceSchedule::updateOrCreate(
            ['created_by' => $companyId, 'schedule_code' => 'SS-0001'],
            ['scheduled_date' => now()->addDays(3)->toDateString(), 'machine_name' => 'LM-0002', 'machine_type' => 'Airjet', 'technician_name' => 'Vijay Sawant', 'status' => 'scheduled', 'completion_notes' => null, 'creator_id' => $companyId, 'is_active' => true, 'created_by' => $companyId]
        );

        $breakdownId = TextileBreakdown::where('created_by', $companyId)->where('breakdown_code', 'BD-0001')->value('id');
        $pmScheduleId = TextilePmSchedule::where('created_by', $companyId)->where('pm_code', 'PM-0002')->value('id');

        TextileMaintenanceSparePartUsage::updateOrCreate(
            ['created_by' => $companyId, 'usage_code' => 'SP-0001'],
            ['usage_date' => $this->daysAgo(4), 'maintenance_ref_type' => 'breakdown', 'maintenance_ref_id' => $breakdownId, 'machine_name' => 'LM-0001', 'part_name' => 'Shuttle Guide', 'part_code' => 'SP-SG-101', 'quantity' => 2, 'unit_cost' => 450, 'total_cost' => 900, 'creator_id' => $companyId, 'is_active' => true, 'created_by' => $companyId]
        );
        TextileMaintenanceSparePartUsage::updateOrCreate(
            ['created_by' => $companyId, 'usage_code' => 'SP-0002'],
            ['usage_date' => $this->daysAgo(2), 'maintenance_ref_type' => 'pm', 'maintenance_ref_id' => $pmScheduleId, 'machine_name' => 'LM-0002', 'part_name' => 'Air Nozzle Kit', 'part_code' => 'SP-AN-220', 'quantity' => 1, 'unit_cost' => 1250, 'total_cost' => 1250, 'creator_id' => $companyId, 'is_active' => true, 'created_by' => $companyId]
        );

        TextileMaintenanceCost::updateOrCreate(
            ['created_by' => $companyId, 'cost_code' => 'MC-0001'],
            ['cost_date' => $this->daysAgo(4), 'machine_name' => 'LM-0001', 'machine_type' => 'Rapier', 'labor_cost' => 1200, 'parts_cost' => 900, 'external_cost' => 0, 'total_cost' => 2100, 'notes' => 'Breakdown BD-0001 repair', 'creator_id' => $companyId, 'is_active' => true, 'created_by' => $companyId]
        );
        TextileMaintenanceCost::updateOrCreate(
            ['created_by' => $companyId, 'cost_code' => 'MC-0002'],
            ['cost_date' => $this->daysAgo(2), 'machine_name' => 'LM-0002', 'machine_type' => 'Airjet', 'labor_cost' => 800, 'parts_cost' => 1250, 'external_cost' => 500, 'total_cost' => 2550, 'notes' => 'PM-0002 service + nozzle kit', 'creator_id' => $companyId, 'is_active' => true, 'created_by' => $companyId]
        );
    }

    private function seedFinance(int $companyId): void
    {
        TextileMachineCost::updateOrCreate(
            ['created_by' => $companyId, 'machine_name' => 'LM-0001', 'period_start' => now()->startOfMonth()->toDateString()],
            ['period_end' => now()->endOfMonth()->toDateString(), 'machine_type' => 'Rapier', 'depreciation_cost' => 8000, 'maintenance_cost' => 2100, 'power_cost' => 4200, 'labor_cost' => 9600, 'other_cost' => 800, 'total_cost' => 24700, 'creator_id' => $companyId, 'is_active' => true]
        );
        TextileMachineCost::updateOrCreate(
            ['created_by' => $companyId, 'machine_name' => 'LM-0002', 'period_start' => now()->startOfMonth()->toDateString()],
            ['period_end' => now()->endOfMonth()->toDateString(), 'machine_type' => 'Airjet', 'depreciation_cost' => 10000, 'maintenance_cost' => 2550, 'power_cost' => 5600, 'labor_cost' => 9600, 'other_cost' => 900, 'total_cost' => 28650, 'creator_id' => $companyId, 'is_active' => true]
        );

        TextilePowerCost::updateOrCreate(
            ['created_by' => $companyId, 'period_start' => now()->startOfMonth()->toDateString()],
            ['period_end' => now()->endOfMonth()->toDateString(), 'meter_reading_start' => 12400, 'meter_reading_end' => 13150, 'units_consumed' => 750, 'rate_per_unit' => 8, 'total_cost' => 6000, 'creator_id' => $companyId, 'is_active' => true]
        );
        TextilePowerCost::updateOrCreate(
            ['created_by' => $companyId, 'period_start' => now()->subMonth()->startOfMonth()->toDateString()],
            ['period_end' => now()->subMonth()->endOfMonth()->toDateString(), 'meter_reading_start' => 11600, 'meter_reading_end' => 12400, 'units_consumed' => 800, 'rate_per_unit' => 7.8, 'total_cost' => 6240, 'creator_id' => $companyId, 'is_active' => true]
        );

        TextileChemicalCost::updateOrCreate(
            ['created_by' => $companyId, 'chemical_name' => 'Reactive Navy Dye', 'chemical_date' => $this->daysAgo(6)],
            ['process_stage' => 'Dyeing', 'quantity' => 120, 'unit' => 'kg', 'unit_cost' => 480, 'total_cost' => 57600, 'batch_reference' => 'PB-DYE-07', 'creator_id' => $companyId, 'is_active' => true]
        );
        TextileChemicalCost::updateOrCreate(
            ['created_by' => $companyId, 'chemical_name' => 'Soda Ash', 'chemical_date' => $this->daysAgo(6)],
            ['process_stage' => 'Dyeing', 'quantity' => 300, 'unit' => 'kg', 'unit_cost' => 22, 'total_cost' => 6600, 'batch_reference' => 'PB-DYE-07', 'creator_id' => $companyId, 'is_active' => true]
        );
        TextileChemicalCost::updateOrCreate(
            ['created_by' => $companyId, 'chemical_name' => 'Softener', 'chemical_date' => $this->daysAgo(3)],
            ['process_stage' => 'Finishing', 'quantity' => 80, 'unit' => 'kg', 'unit_cost' => 150, 'total_cost' => 12000, 'batch_reference' => 'PB-FIN-02', 'creator_id' => $companyId, 'is_active' => true]
        );

        TextileLabourCost::updateOrCreate(
            ['created_by' => $companyId, 'labour_date' => $this->daysAgo(2)],
            ['worker_count' => 24, 'cost_center_name' => 'Weaving Unit-1', 'shift_name' => 'Day', 'hours_worked' => 8, 'rate_per_hour' => 90, 'total_cost' => 17280, 'creator_id' => $companyId, 'is_active' => true]
        );
        TextileLabourCost::updateOrCreate(
            ['created_by' => $companyId, 'labour_date' => $this->daysAgo(1)],
            ['worker_count' => 18, 'cost_center_name' => 'Dyeing House', 'shift_name' => 'Night', 'hours_worked' => 8, 'rate_per_hour' => 105, 'total_cost' => 15120, 'creator_id' => $companyId, 'is_active' => true]
        );
    }

    private function seedTransport(int $companyId): void
    {
        TextileDispatchDriver::updateOrCreate(
            ['created_by' => $companyId, 'name' => 'Suresh Patil'],
            ['code' => 'DRV-001', 'driver_source' => 'own', 'phone' => '98200 12345', 'license_number' => 'MH-2020-44112', 'license_expiry_date' => '2027-05-30', 'transporter_name' => null, 'creator_id' => $companyId, 'is_active' => true]
        );
        TextileDispatchDriver::updateOrCreate(
            ['created_by' => $companyId, 'name' => 'Mahesh Yadav'],
            ['code' => 'DRV-002', 'driver_source' => 'vendor', 'phone' => '98765 43210', 'license_number' => 'GJ-2019-88231', 'license_expiry_date' => '2026-11-15', 'transporter_name' => 'Shree Transport Co', 'creator_id' => $companyId, 'is_active' => true]
        );

        TextileDispatchVehicle::updateOrCreate(
            ['created_by' => $companyId, 'vehicle_number' => 'MH-12-AB-1234'],
            ['code' => 'VHL-001', 'vehicle_type' => 'Truck 12T', 'capacity' => 12, 'capacity_unit' => 'ton', 'ownership_type' => 'owned', 'transporter_name' => null, 'creator_id' => $companyId, 'is_active' => true]
        );
        TextileDispatchVehicle::updateOrCreate(
            ['created_by' => $companyId, 'vehicle_number' => 'GJ-05-CD-7788'],
            ['code' => 'VHL-002', 'vehicle_type' => 'Container 20ft', 'capacity' => 20, 'capacity_unit' => 'ton', 'ownership_type' => 'vendor', 'container_number' => 'CLU-440112', 'transporter_name' => 'Shree Transport Co', 'creator_id' => $companyId, 'is_active' => true]
        );

        TextileDispatchRoute::updateOrCreate(
            ['created_by' => $companyId, 'route_name' => 'Surat-Mumbai'],
            ['route_code' => 'RT-001', 'origin_location' => 'Surat, Gujarat', 'destination_location' => 'Mumbai, Maharashtra', 'distance_km' => 290, 'transit_hours' => 8, 'transporter_name' => null, 'creator_id' => $companyId, 'is_active' => true]
        );
        TextileDispatchRoute::updateOrCreate(
            ['created_by' => $companyId, 'route_name' => 'Surat-Delhi'],
            ['route_code' => 'RT-002', 'origin_location' => 'Surat, Gujarat', 'destination_location' => 'Delhi NCR', 'distance_km' => 1200, 'transit_hours' => 30, 'transporter_name' => null, 'creator_id' => $companyId, 'is_active' => true]
        );

        TextileFuelEntry::updateOrCreate(
            ['created_by' => $companyId, 'entry_code' => 'FL-0001'],
            ['fuel_date' => $this->daysAgo(3), 'vehicle_name' => 'MH-12-AB-1234', 'driver_name' => 'Suresh Patil', 'route_name' => 'Surat-Mumbai', 'fuel_quantity_liters' => 80, 'fuel_rate_per_liter' => 92, 'fuel_total_cost' => 7360, 'odometer_km' => 45820, 'fuel_type' => 'Diesel', 'creator_id' => $companyId, 'is_active' => true]
        );
        TextileFuelEntry::updateOrCreate(
            ['created_by' => $companyId, 'entry_code' => 'FL-0002'],
            ['fuel_date' => $this->daysAgo(1), 'vehicle_name' => 'MH-12-AB-1234', 'driver_name' => 'Suresh Patil', 'route_name' => 'Surat-Delhi', 'fuel_quantity_liters' => 160, 'fuel_rate_per_liter' => 92.5, 'fuel_total_cost' => 14800, 'odometer_km' => 47210, 'fuel_type' => 'Diesel', 'creator_id' => $companyId, 'is_active' => true]
        );

        TextileFreightCost::updateOrCreate(
            ['created_by' => $companyId, 'cost_code' => 'FR-0001'],
            ['freight_date' => $this->daysAgo(2), 'vehicle_name' => 'MH-12-AB-1234', 'driver_name' => 'Suresh Patil', 'route_name' => 'Surat-Mumbai', 'freight_type' => 'Full Truck Load', 'amount' => 18500, 'transport_vendor_name' => null, 'creator_id' => $companyId, 'is_active' => true]
        );
        TextileFreightCost::updateOrCreate(
            ['created_by' => $companyId, 'cost_code' => 'FR-0002'],
            ['freight_date' => $this->daysAgo(1), 'vehicle_name' => 'GJ-05-CD-7788', 'driver_name' => 'Mahesh Yadav', 'route_name' => 'Surat-Delhi', 'freight_type' => 'Container', 'amount' => 42000, 'transport_vendor_name' => 'Shree Transport Co', 'creator_id' => $companyId, 'is_active' => true]
        );

        TextileVehicleMaintenance::updateOrCreate(
            ['created_by' => $companyId, 'maintenance_code' => 'VM-0001'],
            ['maintenance_date' => $this->daysAgo(7), 'next_due_date' => now()->addMonths(2)->toDateString(), 'vehicle_name' => 'MH-12-AB-1234', 'maintenance_type' => 'Preventive', 'description' => 'Engine oil change and brake inspection', 'cost' => 6500, 'service_provider' => 'Auto Care Garage', 'creator_id' => $companyId, 'is_active' => true]
        );
    }

    private function seedHr(int $companyId): void
    {
        $departments = ['Weaving', 'Dyeing', 'Finishing', 'Maintenance'];
        foreach ($departments as $name) {
            Department::updateOrCreate(
                ['created_by' => $companyId, 'department_name' => $name],
                ['creator_id' => $companyId]
            );
        }
        $weavingDept = Department::where('created_by', $companyId)->where('department_name', 'Weaving')->value('id');

        $dayShift = Shift::updateOrCreate(
            ['created_by' => $companyId, 'shift_name' => 'Day'],
            ['start_time' => '08:00:00', 'end_time' => '17:00:00', 'creator_id' => $companyId]
        );
        Shift::updateOrCreate(
            ['created_by' => $companyId, 'shift_name' => 'Night'],
            ['start_time' => '20:00:00', 'end_time' => '05:00:00', 'creator_id' => $companyId]
        );

        $employees = [
            ['employee_id' => 'EMP-2026-0001', 'name' => 'Ramesh Kumar', 'department_id' => $weavingDept, 'shift' => $dayShift->id, 'gender' => 'Male', 'city' => 'Surat', 'employment_type' => '1'],
            ['employee_id' => 'EMP-2026-0002', 'name' => 'Asha Devi', 'department_id' => $weavingDept, 'shift' => $dayShift->id, 'gender' => 'Female', 'city' => 'Surat', 'employment_type' => '1'],
            ['employee_id' => 'EMP-2026-0003', 'name' => 'Vijay Sawant', 'department_id' => $weavingDept, 'shift' => $dayShift->id, 'gender' => 'Male', 'city' => 'Navsari', 'employment_type' => '1'],
            ['employee_id' => 'EMP-2026-0004', 'name' => 'Sunita Pawar', 'department_id' => $weavingDept, 'shift' => $dayShift->id, 'gender' => 'Female', 'city' => 'Bardoli', 'employment_type' => '0'],
        ];
        foreach ($employees as $row) {
            Employee::updateOrCreate(
                ['created_by' => $companyId, 'employee_id' => $row['employee_id']],
                array_merge($row, ['creator_id' => $companyId])
            );
        }

        // Attendance records reference the company user id (FK targets users table).
        $today = now()->toDateString();
        if (! Attendance::where('created_by', $companyId)->where('employee_id', $companyId)->where('date', $today)->exists()) {
            Attendance::create([
                'employee_id' => $companyId, 'shift_id' => $dayShift->id, 'date' => $today,
                'clock_in' => $today . ' 08:02:00', 'clock_out' => $today . ' 17:05:00', 'break_hour' => 0.5, 'total_hour' => 8.5,
                'overtime_hours' => 0.5, 'overtime_amount' => 45, 'status' => 'present', 'notes' => 'On time',
                'creator_id' => $companyId, 'created_by' => $companyId,
            ]);
            Attendance::create([
                'employee_id' => $companyId, 'shift_id' => $dayShift->id, 'date' => $today,
                'clock_in' => $today . ' 08:20:00', 'clock_out' => $today . ' 16:40:00', 'break_hour' => 0.5, 'total_hour' => 7.5,
                'overtime_hours' => 0, 'overtime_amount' => 0, 'status' => 'present', 'notes' => 'Late by 20 min',
                'creator_id' => $companyId, 'created_by' => $companyId,
            ]);
            Attendance::create([
                'employee_id' => $companyId, 'shift_id' => $dayShift->id, 'date' => $today,
                'clock_in' => $today . ' 08:10:00', 'clock_out' => $today . ' 17:10:00', 'break_hour' => 0.5, 'total_hour' => 8,
                'overtime_hours' => 1, 'overtime_amount' => 90, 'status' => 'present', 'notes' => 'Extra hour for machine setup',
                'creator_id' => $companyId, 'created_by' => $companyId,
            ]);
            Attendance::create([
                'employee_id' => $companyId, 'shift_id' => $dayShift->id, 'date' => $today,
                'clock_in' => $today . ' 09:00:00', 'clock_out' => $today . ' 10:00:00', 'break_hour' => 0, 'total_hour' => 1,
                'overtime_hours' => 0, 'overtime_amount' => 0, 'status' => 'absent', 'notes' => 'Medical leave',
                'creator_id' => $companyId, 'created_by' => $companyId,
            ]);
        }
    }

    private function seedApprovals(int $companyId): void
    {
        TextileApprovalRule::updateOrCreate(
            ['created_by' => $companyId, 'document_type' => 'purchase_order', 'from_status' => 'draft', 'to_status' => 'approved'],
            ['min_quantity' => null, 'max_quantity' => null, 'required_approvals' => 1, 'is_active' => true, 'conditions' => [], 'creator_id' => $companyId]
        );
        TextileApprovalRule::updateOrCreate(
            ['created_by' => $companyId, 'document_type' => 'sales_order', 'from_status' => 'draft', 'to_status' => 'approved'],
            ['min_quantity' => 100, 'max_quantity' => null, 'required_approvals' => 1, 'is_active' => true, 'conditions' => [], 'creator_id' => $companyId]
        );
        TextileApprovalRule::updateOrCreate(
            ['created_by' => $companyId, 'document_type' => 'purchase_order', 'from_status' => 'approved', 'to_status' => 'released'],
            ['min_quantity' => 50000, 'max_quantity' => null, 'required_approvals' => 2, 'is_active' => true, 'conditions' => ['rule' => 'high_value'], 'creator_id' => $companyId]
        );

        TextileAuditLog::updateOrCreate(
            ['created_by' => $companyId, 'event_type' => 'document.created', 'payload' => ['document_type' => 'purchase_order', 'document_number' => 'TPO-0001']],
            ['creator_id' => $companyId]
        );
        TextileAuditLog::updateOrCreate(
            ['created_by' => $companyId, 'event_type' => 'document.approved', 'payload' => ['document_type' => 'sales_order', 'document_number' => 'TSO-0001']],
            ['creator_id' => $companyId]
        );
        TextileAuditLog::updateOrCreate(
            ['created_by' => $companyId, 'event_type' => 'costing.finalized', 'payload' => ['document_type' => 'costing_entry', 'document_number' => 'TCST-0001', 'margin_percent' => 18.43]],
            ['creator_id' => $companyId]
        );
    }
}
