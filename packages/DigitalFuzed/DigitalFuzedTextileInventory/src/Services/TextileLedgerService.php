<?php

namespace DigitalFuzed\TextileInventory\Services;

use DigitalFuzed\TextileCore\Models\TextileWorkflowDocument;
use DigitalFuzed\TextileCore\Support\TextileBranchScope;
use DigitalFuzed\TextileInventory\Models\TextileMovement;
use Illuminate\Support\Facades\Log;

/**
 * Automatic movement ledger — the single source of truth for the textile
 * manufacturing pipeline.
 *
 * Every transition in the flow posts a movement so the ledger tells the full
 * story without joins:
 *
 *   Yarn PO (receipt) → Yarn Issued to Sizing (issue)
 *   → Beam Received (receipt) → Beam Issued to Manufacturing (issue)
 *   → Takha Produced (receipt) → Takha Sold (issue)
 *
 * Design rules:
 *  - Movements inherit the branch of the source workflow document, so the
 *    ledger is branch-scoped end to end.
 *  - All methods are FAIL-OPEN: missing lot/quantity → log + skip, never throw.
 *    Legacy/seeded data must not break the manufacturing workflow.
 *  - reference_type/reference_id point back to the triggering document so the
 *    ledger can be traced to its source.
 */
class TextileLedgerService
{
    public function __construct(protected TextileMovementService $movementService)
    {
    }

    /**
     * Beam received from sizing → post a receipt for the beam lot.
     *
     * Vendor-aware: when the beam's metadata carries a sizing_vendor, sizing is
     * outsourced and the location is labeled 'sizing-vendor'; otherwise it is
     * in-house ('sizing'). Mirrors TextileOperatingPolicyService has_sizing.
     */
    public function postBeamReceipt(TextileWorkflowDocument $beam, string $unit): ?TextileMovement
    {
        $metadata = is_array($beam->metadata) ? $beam->metadata : [];
        $vendor = is_string($metadata['sizing_vendor'] ?? null) ? trim($metadata['sizing_vendor']) : '';
        $outsourced = $vendor !== '';

        return $this->post($beam, [
            'movement_type' => 'receipt',
            'location_from' => $outsourced ? 'sizing-vendor' : 'sizing',
            'location_to' => 'warehouse',
            'unit' => $unit,
            'notes' => $outsourced
                ? "Beam received from sizing vendor ({$vendor})."
                : 'Beam received from in-house sizing.',
        ]);
    }

    /**
     * Takha produced → post a receipt for the takha grey lot.
     *
     * Vendor-aware: when the takha's metadata carries production_mode
     * 'powerloom_vendor', weaving was outsourced and the grey comes back from
     * the powerloom vendor; otherwise it is in-house ('loom-floor').
     */
    public function postTakhaReceipt(TextileWorkflowDocument $takha, string $unit): ?TextileMovement
    {
        $metadata = is_array($takha->metadata) ? $takha->metadata : [];
        $outsourced = ($metadata['production_mode'] ?? null) === 'powerloom_vendor';

        return $this->post($takha, [
            'movement_type' => 'receipt',
            'location_from' => $outsourced ? 'powerloom-vendor' : 'loom-floor',
            'location_to' => 'warehouse',
            'unit' => $unit,
            'notes' => $outsourced
                ? 'Takha received from powerloom vendor (outsourced weaving).'
                : 'Takha received from in-house weaving output.',
        ]);
    }

    /**
     * Weaving output recorded → post a receipt for the grey fabric lot.
     *
     * Vendor-aware: when the batch's weaving is outsourced to a powerloom
     * vendor the grey is received back from the vendor; otherwise it comes
     * from in-house weaving.
     */
    public function postWeavingOutputReceipt(TextileWorkflowDocument $output, string $unit, bool $outsourced = false): ?TextileMovement
    {
        return $this->post($output, [
            'movement_type' => 'receipt',
            'location_from' => $outsourced ? 'powerloom-vendor' : 'weaving',
            'location_to' => 'warehouse',
            'unit' => $unit,
            'notes' => $outsourced
                ? 'Grey fabric received from powerloom vendor weaving.'
                : 'Grey fabric received from in-house weaving.',
        ]);
    }

    /**
     * Fabric inspection passed → post a transfer marking the lot as quality-approved.
     * Final QC is a real gate in the flow: a takha cannot be sold until the
     * inspection document is approved (pass), so the ledger records the QC step.
     */
    public function postInspectionPass(TextileWorkflowDocument $inspection, string $unit): ?TextileMovement
    {
        return $this->post($inspection, [
            'movement_type' => 'transfer',
            'location_from' => 'weaving',
            'location_to' => 'quality-approved',
            'unit' => $unit,
            'notes' => 'Final inspection passed — lot released for sale.',
        ]);
    }

    /**
     * Takha sold / dispatched → post an issue for the sold lot.
     */
    public function postDispatchIssue(TextileWorkflowDocument $dispatch, string $unit): ?TextileMovement
    {
        return $this->post($dispatch, [
            'movement_type' => 'issue',
            'location_from' => 'warehouse',
            'location_to' => 'customer',
            'unit' => $unit,
            'notes' => 'Goods dispatched to customer.',
        ]);
    }

    /**
     * Shared posting logic (fail-open).
     */
    private function post(TextileWorkflowDocument $document, array $attributes): ?TextileMovement
    {
        $lotReference = (string) ($attributes['lot_reference'] ?? $document->lot_reference ?? '');
        $quantity = (float) ($attributes['quantity'] ?? $document->quantity ?? 0);
        $unit = (string) ($attributes['unit'] ?? '');
        $movementType = (string) ($attributes['movement_type'] ?? '');

        if ($lotReference === '' || $quantity <= 0 || $movementType === '') {
            Log::info("TextileLedger: skipped {$document->document_type} #{$document->id} — missing lot/quantity (fail-open).");

            return null;
        }

        return $this->movementService->createMovement([
            'movement_type' => $movementType,
            'reference_type' => $document->document_type,
            'reference_id' => $document->id,
            'lot_reference' => $lotReference,
            'location_from' => $attributes['location_from'] ?? null,
            'location_to' => $attributes['location_to'] ?? null,
            'quantity' => $quantity,
            'unit' => $unit,
            'status' => 'posted',
            'notes' => $attributes['notes'] ?? null,
            'branch_id' => $document->branch_id ?? TextileBranchScope::branchIdForCreate(),
        ]);
    }
}
