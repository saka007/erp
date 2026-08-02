<?php

namespace Database\Seeders;

use App\Models\User;
use DigitalFuzed\TextileCore\Models\TextileQualityProfile;
use DigitalFuzed\TextileCore\Models\TextileRouteRecipe;
use DigitalFuzed\TextileCore\Models\TextileSpecification;
use DigitalFuzed\TextileCore\Models\TextileUnitConversion;
use DigitalFuzed\TextileCore\Models\TextileWorkflowDocument;
use DigitalFuzed\TextileInventory\Models\TextileLocation;
use DigitalFuzed\TextileInventory\Models\TextileLot;
use DigitalFuzed\TextileInventory\Models\TextileMovement;
use DigitalFuzed\TextileInventory\Models\TextileReservation;
use Illuminate\Database\Seeder;

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
        $this->seedInventory($companyId);
        $this->seedWorkflow($companyId);

        $this->command?->info("Textile demo data seeded for company user {$companyId}.");
    }

    private function resolveCompanyId($userId): ?int
    {
        if (! empty($userId)) {
            return (int) $userId;
        }

        $company = User::query()->where('type', 'company')->orderBy('id')->first();
        return $company ? (int) $company->id : null;
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

        $lots = [
            ['lot_reference' => 'LOT-2401-A', 'received_quantity' => 1500, 'available_quantity' => 1120, 'status' => 'active'],
            ['lot_reference' => 'LOT-2401-B', 'received_quantity' => 980, 'available_quantity' => 700, 'status' => 'active'],
            ['lot_reference' => 'LOT-2402-C', 'received_quantity' => 760, 'available_quantity' => 760, 'status' => 'hold'],
        ];

        foreach ($lots as $row) {
            TextileLot::updateOrCreate(
                ['created_by' => $companyId, 'lot_reference' => $row['lot_reference']],
                array_merge($row, ['creator_id' => $companyId, 'is_active' => true])
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
                array_merge($row, ['creator_id' => $companyId, 'is_active' => true, 'reference_type' => 'inventory_demo', 'reference_id' => $index + 1])
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
        ];

        foreach ($documents as $row) {
            TextileWorkflowDocument::updateOrCreate(
                ['created_by' => $companyId, 'document_number' => $row['document_number']],
                array_merge($row, ['creator_id' => $companyId])
            );
        }
    }
}
