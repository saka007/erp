<?php

namespace DigitalFuzed\TextileCore\Services;

use DigitalFuzed\TextileCore\Models\TextileOperatingPolicy;
use RuntimeException;
use Illuminate\Support\Facades\Schema;

class TextileOperatingPolicyService
{
    public const MODEL_FULL_PACKAGE = 'full_package_buyer';
    public const MODEL_JOBWORK_WEAVING = 'jobwork_weaving_beam_supplied';
    public const MODEL_JOBWORK_PROCESSING = 'jobwork_processing_grey_supplied';
    public const MODEL_TRADER_BULK = 'trader_bulk';
    public const MODEL_EXPORT_COMPLIANCE = 'export_compliance';

    public function options(): array
    {
        return [
            self::MODEL_FULL_PACKAGE,
            self::MODEL_JOBWORK_WEAVING,
            self::MODEL_JOBWORK_PROCESSING,
            self::MODEL_TRADER_BULK,
            self::MODEL_EXPORT_COMPLIANCE,
        ];
    }

    public function materialOwnershipOptions(): array
    {
        return ['company_owned', 'customer_owned', 'mixed'];
    }

    public function billingModeOptions(): array
    {
        return ['sale_value', 'conversion_charge', 'process_charge', 'hybrid'];
    }

    public function resolveForCurrentTenant(): TextileOperatingPolicy
    {
        return $this->resolveForTenant($this->tenantId());
    }

    public function resolveForTenant(?int $tenantId): TextileOperatingPolicy
    {
        if ($tenantId === null) {
            throw new RuntimeException('Tenant context is required to resolve operating policy.');
        }

        if (!Schema::hasTable('textile_operating_policies')) {
            return $this->defaultPolicy($tenantId);
        }

        return TextileOperatingPolicy::query()->firstOrCreate(
            ['created_by' => $tenantId],
            [
                'creator_id' => auth()->id(),
                'operating_model' => self::MODEL_FULL_PACKAGE,
                'material_ownership' => 'company_owned',
                'billing_mode' => 'sale_value',
            ]
        );
    }

    public function updateForCurrentTenant(array $payload): TextileOperatingPolicy
    {
        return $this->updateForTenant($this->tenantId(), $payload);
    }

    public function updateForTenant(?int $tenantId, array $payload): TextileOperatingPolicy
    {
        if (!Schema::hasTable('textile_operating_policies')) {
            throw new RuntimeException('Textile operating policy table is missing. Please run migrations first.');
        }

        $policy = $this->resolveForTenant($tenantId);

        $policy->update([
            'operating_model' => $payload['operating_model'],
            'material_ownership' => $payload['material_ownership'],
            'billing_mode' => $payload['billing_mode'],
            'creator_id' => auth()->id(),
        ]);

        return $policy->refresh();
    }

    public function capabilities(TextileOperatingPolicy $policy): array
    {
        return match ($policy->operating_model) {
            self::MODEL_JOBWORK_WEAVING => [
                'procurement' => false,
                'manufacturing' => true,
                'processing' => false,
                'grn_invoice_sync' => false,
            ],
            self::MODEL_JOBWORK_PROCESSING => [
                'procurement' => false,
                'manufacturing' => false,
                'processing' => true,
                'grn_invoice_sync' => false,
            ],
            self::MODEL_TRADER_BULK => [
                'procurement' => true,
                'manufacturing' => false,
                'processing' => false,
                'grn_invoice_sync' => true,
            ],
            default => [
                'procurement' => true,
                'manufacturing' => true,
                'processing' => true,
                'grn_invoice_sync' => true,
            ],
        };
    }

    public function assertCapability(string $capability): void
    {
        $policy = $this->resolveForCurrentTenant();
        $flags = $this->capabilities($policy);

        if (($flags[$capability] ?? false) !== true) {
            throw new RuntimeException(sprintf(
                'Operation blocked by tenant operating model (%s).',
                $policy->operating_model
            ));
        }
    }

    private function tenantId(): ?int
    {
        return auth()->check() && function_exists('creatorId') ? (int) creatorId() : auth()->id();
    }

    private function defaultPolicy(int $tenantId): TextileOperatingPolicy
    {
        $policy = new TextileOperatingPolicy();
        $policy->created_by = $tenantId;
        $policy->creator_id = auth()->id();
        $policy->operating_model = self::MODEL_FULL_PACKAGE;
        $policy->material_ownership = 'company_owned';
        $policy->billing_mode = 'sale_value';

        return $policy;
    }
}
