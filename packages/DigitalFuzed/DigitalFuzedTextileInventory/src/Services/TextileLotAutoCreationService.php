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
     *
     * @param  string|null  $parentLotReference  upstream lot (e.g. source yarn lot)
     * @param  string|null  $parentLotType  material type of the parent lot
     */
    public function createFromBeam(TextileWorkflowDocument $beam, ?string $parentLotReference = null, ?string $parentLotType = null): ?TextileLot
    {
        return $this->createLotFromDocument($beam, TextileLot::TYPE_BEAM, TextileLot::STAGE_SIZING, $parentLotReference, $parentLotType);
    }

    /**
     * Create a lot from a Weaving Output document (grey fabric).
     *
     * Weaving output documents usually reuse the production batch's lot_reference
     * (which is the beam's reference). When a beam lot already occupies that
     * reference we generate a dedicated grey reference so the grey lot is not
     * swallowed by the beam lot (collision fix).
     *
     * @param  string|null  $parentLotReference  upstream lot (e.g. source beam lot)
     * @param  string|null  $parentLotType  material type of the parent lot
     */
    public function createFromWeavingOutput(TextileWorkflowDocument $output, ?string $parentLotReference = null, ?string $parentLotType = null): ?TextileLot
    {
        return $this->createLotFromDocument(
            $output,
            TextileLot::TYPE_GREY_FABRIC,
            TextileLot::STAGE_WEAVING,
            $parentLotReference,
            $parentLotType,
            true // allow re-deriving a unique reference on collision
        );
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
     *
     * @param  string|null  $parentLotReference  upstream lot reference (traceability chain)
     * @param  string|null  $parentLotType  material type of the parent lot
     * @param  bool  $allowRederiveReference  when true and the document's lot_reference
     *                                        is already claimed by a lot of a different
     *                                        material type, generate a unique reference
     *                                        instead of returning the foreign lot
     */
    private function createLotFromDocument(
        TextileWorkflowDocument $document,
        string $materialType,
        string $productionStage,
        ?string $parentLotReference = null,
        ?string $parentLotType = null,
        bool $allowRederiveReference = false,
    ): ?TextileLot {
        $lotReference = $document->lot_reference;

        if (empty($lotReference)) {
            Log::info("TextileLotAutoCreation: skipped — no lot_reference on document #{$document->id} ({$document->document_type})");

            return null;
        }

        $tenantId = $document->created_by;

        // Idempotency by source document: if this exact document already produced
        // a lot of this material type, return it (no duplicates on retry).
        if (! empty($document->id)) {
            $bySource = TextileLot::query()
                ->where('created_by', $tenantId)
                ->where('source_document_type', $document->document_type)
                ->where('source_document_id', $document->id)
                ->where('material_type', $materialType)
                ->first();

            if ($bySource !== null) {
                return $bySource;
            }
        }

        // Skip if lot already exists for this tenant + reference
        $existing = TextileLot::query()
            ->where('created_by', $tenantId)
            ->where('lot_reference', $lotReference)
            ->first();

        if ($existing) {
            $existingType = (string) ($existing->material_type ?? '');

            // The reference is claimed by a lot of a DIFFERENT material type
            // (e.g. beam lot occupies the reference a weaving output wants to use).
            // Re-derive a dedicated reference for this material type.
            if ($allowRederiveReference && $existingType !== '' && $existingType !== $materialType) {
                $lotReference = $this->deriveUniqueReference($lotReference, $materialType, $tenantId);
                $existing = null;
            } elseif ($existingType === '') {
                // Material type not yet set — claim it.
                $existing->update([
                    'material_type' => $materialType,
                    'production_stage' => $productionStage,
                    'source_document_type' => $document->document_type,
                    'source_document_id' => $document->id,
                    'parent_lot_reference' => $parentLotReference ?? $existing->parent_lot_reference,
                    'parent_lot_type' => $parentLotType ?? $existing->parent_lot_type,
                ]);

                return $existing;
            }

            // Same material type already present — idempotent return.
            if ($existing !== null) {
                return $existing;
            }
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
            'parent_lot_reference' => $parentLotReference,
            'parent_lot_type' => $parentLotType,
            'is_active' => true,
            'created_by' => $tenantId,
            'creator_id' => Auth::id() ?? $document->creator_id,
        ]);
    }

    /**
     * Generate a unique lot reference for a material type that collided with an
     * existing foreign lot. Format: {material-prefix}-{original-reference}[-N].
     */
    private function deriveUniqueReference(string $baseReference, string $materialType, ?int $tenantId): string
    {
        $prefix = match ($materialType) {
            TextileLot::TYPE_GREY_FABRIC => 'GREY',
            TextileLot::TYPE_BEAM => 'BEAM',
            TextileLot::TYPE_YARN => 'YARN',
            TextileLot::TYPE_FINISHED_FABRIC => 'FIN',
            TextileLot::TYPE_CHEMICAL => 'CHEM',
            TextileLot::TYPE_PACKING_MATERIAL => 'PACK',
            default => 'LOT',
        };

        $candidate = sprintf('%s-%s', $prefix, $baseReference);
        $suffix = 2;

        while (TextileLot::query()
            ->where('created_by', $tenantId)
            ->where('lot_reference', $candidate)
            ->exists()
        ) {
            $candidate = sprintf('%s-%s-%d', $prefix, $baseReference, $suffix);
            $suffix++;
        }

        return $candidate;
    }
}
