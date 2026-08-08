<?php

namespace DigitalFuzed\TextileInventory\Services;

use DigitalFuzed\TextileCore\Models\TextileWorkflowDocument;
use DigitalFuzed\TextileInventory\Models\TextileLot;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Auto-creates TextileLot records when upstream workflow documents are created.
 *
 * Hooks:
 *  - GRN created          → yarn lot (material_type = yarn)
 *  - Beam created         → beam lot (material_type = beam)
 *  - Weaving output       → grey fabric lot (material_type = grey_fabric)
 *  - Job work inward      → finished fabric lot (material_type = finished_fabric)
 */
class TextileLotAutoCreationService
{
    /**
     * Create a lot from a GRN document (yarn receipt).
     */
    public function createFromGrn(TextileWorkflowDocument $grn): ?TextileLot
    {
        return $this->createLotFromDocument($grn, TextileLot::TYPE_YARN, TextileLot::STAGE_PROCUREMENT);
    }

    /**
     * Create a lot from a Beam document.
     */
    public function createFromBeam(TextileWorkflowDocument $beam): ?TextileLot
    {
        return $this->createLotFromDocument($beam, TextileLot::TYPE_BEAM, TextileLot::STAGE_SIZING);
    }

    /**
     * Create a lot from a Weaving Output document (grey fabric).
     */
    public function createFromWeavingOutput(TextileWorkflowDocument $output): ?TextileLot
    {
        return $this->createLotFromDocument($output, TextileLot::TYPE_GREY_FABRIC, TextileLot::STAGE_WEAVING);
    }

    /**
     * Create a lot from a Job Work Inward document (finished fabric).
     */
    public function createFromJobWorkInward(TextileWorkflowDocument $inward): ?TextileLot
    {
        return $this->createLotFromDocument($inward, TextileLot::TYPE_FINISHED_FABRIC, TextileLot::STAGE_PROCESSING);
    }

    /**
     * Shared lot creation logic.
     */
    private function createLotFromDocument(
        TextileWorkflowDocument $document,
        string $materialType,
        string $productionStage,
    ): ?TextileLot {
        $lotReference = $document->lot_reference;

        if (empty($lotReference)) {
            Log::info("TextileLotAutoCreation: skipped — no lot_reference on document #{$document->id} ({$document->document_type})");

            return null;
        }

        $tenantId = $document->created_by;

        // Skip if lot already exists for this tenant + reference
        $existing = TextileLot::query()
            ->where('created_by', $tenantId)
            ->where('lot_reference', $lotReference)
            ->first();

        if ($existing) {
            // Update material_type if not already set
            if (empty($existing->material_type)) {
                $existing->update([
                    'material_type' => $materialType,
                    'production_stage' => $productionStage,
                    'source_document_type' => $document->document_type,
                    'source_document_id' => $document->id,
                ]);
            }

            return $existing;
        }

        $quantity = (float) ($document->quantity ?? 0);

        return TextileLot::create([
            'lot_reference' => $lotReference,
            'batch_number' => $document->metadata['batch_number'] ?? null,
            'barcode' => strtoupper('LOT-'.$lotReference),
            'qr_code' => sprintf(
                'LOT:%s|TYPE:%s|DOC:%s#%s|TENANT:%s',
                $lotReference,
                $materialType,
                $document->document_type,
                $document->id,
                (string) $tenantId,
            ),
            'received_quantity' => $quantity,
            'available_quantity' => $quantity,
            'status' => 'active',
            'material_type' => $materialType,
            'production_stage' => $productionStage,
            'source_document_type' => $document->document_type,
            'source_document_id' => $document->id,
            'is_active' => true,
            'created_by' => $tenantId,
            'creator_id' => Auth::id() ?? $document->creator_id,
        ]);
    }
}
