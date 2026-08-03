<?php

namespace DigitalFuzed\TextileCore\Services;

use Carbon\Carbon;
use DigitalFuzed\TextileCore\Models\TextileOperatingProfile;
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

        $selectedProfiles = $this->resolveRequestedProfiles($payload);
        $primaryModel = $this->resolvePrimaryModel($policy, $selectedProfiles, $payload['operating_model'] ?? null);

        $policy->update([
            'operating_model' => $primaryModel,
            'material_ownership' => $payload['material_ownership'],
            'billing_mode' => $payload['billing_mode'],
            'creator_id' => auth()->id(),
        ]);

        $this->syncActiveProfilesForTenant($tenantId, $selectedProfiles);

        return $policy->refresh();
    }

    public function capabilities(TextileOperatingPolicy $policy): array
    {
        $activeProfiles = $this->resolveActiveProfilesForTenant((int) ($policy->created_by ?? 0));
        if ($activeProfiles === []) {
            $activeProfiles = [$policy->operating_model];
        }

        $capabilities = [
            'procurement' => false,
            'manufacturing' => false,
            'processing' => false,
            'grn_invoice_sync' => false,
        ];

        foreach ($activeProfiles as $profile) {
            $profileFlags = $this->capabilitiesForProfile($profile);
            foreach ($capabilities as $capability => $enabled) {
                $capabilities[$capability] = $enabled || ($profileFlags[$capability] ?? false);
            }
        }

        return $capabilities;
    }

    public function resolveActiveProfilesForTenant(?int $tenantId): array
    {
        if ($tenantId === null || $tenantId <= 0 || !Schema::hasTable('textile_operating_profiles')) {
            return [];
        }

        return TextileOperatingProfile::query()
            ->where('created_by', $tenantId)
            ->where('is_active', true)
            ->whereNull('effective_to')
            ->orderBy('effective_from')
            ->orderBy('id')
            ->pluck('profile_key')
            ->unique()
            ->values()
            ->toArray();
    }

    public function profileHistoryForTenant(?int $tenantId): array
    {
        if ($tenantId === null || $tenantId <= 0 || !Schema::hasTable('textile_operating_profiles')) {
            return [];
        }

        return TextileOperatingProfile::query()
            ->where('created_by', $tenantId)
            ->latest('id')
            ->get(['id', 'profile_key', 'is_active', 'effective_from', 'effective_to'])
            ->map(fn (TextileOperatingProfile $profile) => [
                'id' => $profile->id,
                'profile_key' => $profile->profile_key,
                'is_active' => (bool) $profile->is_active,
                'effective_from' => $profile->effective_from,
                'effective_to' => $profile->effective_to,
            ])
            ->toArray();
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

    private function capabilitiesForProfile(string $profile): array
    {
        return match ($profile) {
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

    private function resolveRequestedProfiles(array $payload): array
    {
        $profiles = $payload['operating_profiles'] ?? [];
        if (!is_array($profiles)) {
            $profiles = [];
        }

        $profiles = array_values(array_unique(array_filter(array_map(
            static fn ($value) => is_string($value) ? trim($value) : '',
            $profiles
        ))));

        if ($profiles === [] && isset($payload['operating_model']) && is_string($payload['operating_model'])) {
            $profiles = [trim($payload['operating_model'])];
        }

        $allowed = $this->options();
        $profiles = array_values(array_filter($profiles, static fn ($profile) => in_array($profile, $allowed, true)));

        if ($profiles === []) {
            throw new RuntimeException('At least one operating profile must be selected.');
        }

        return $profiles;
    }

    private function resolvePrimaryModel(TextileOperatingPolicy $policy, array $profiles, ?string $requestedPrimary): string
    {
        if (is_string($requestedPrimary) && in_array($requestedPrimary, $profiles, true)) {
            return $requestedPrimary;
        }

        if (in_array($policy->operating_model, $profiles, true)) {
            return $policy->operating_model;
        }

        return $profiles[0];
    }

    private function syncActiveProfilesForTenant(?int $tenantId, array $profiles): void
    {
        if ($tenantId === null || $tenantId <= 0 || !Schema::hasTable('textile_operating_profiles')) {
            return;
        }

        $today = Carbon::now()->toDateString();

        $activeProfiles = TextileOperatingProfile::query()
            ->where('created_by', $tenantId)
            ->where('is_active', true)
            ->whereNull('effective_to')
            ->get();

        $activeMap = $activeProfiles->keyBy('profile_key');

        foreach ($activeProfiles as $activeProfile) {
            if (in_array($activeProfile->profile_key, $profiles, true)) {
                continue;
            }

            $activeProfile->update([
                'is_active' => false,
                'effective_to' => $today,
                'creator_id' => auth()->id(),
            ]);
        }

        foreach ($profiles as $profileKey) {
            if ($activeMap->has($profileKey)) {
                continue;
            }

            TextileOperatingProfile::query()->create([
                'created_by' => $tenantId,
                'creator_id' => auth()->id(),
                'profile_key' => $profileKey,
                'is_active' => true,
                'effective_from' => $today,
                'effective_to' => null,
            ]);
        }
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
