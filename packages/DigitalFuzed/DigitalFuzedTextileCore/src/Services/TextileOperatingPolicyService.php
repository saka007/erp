<?php

namespace DigitalFuzed\TextileCore\Services;

use Carbon\Carbon;
use App\Models\User;
use DigitalFuzed\TextileCore\Models\TextileOperatingProfile;
use DigitalFuzed\TextileCore\Models\TextileRoleCapability;
use DigitalFuzed\TextileCore\Models\TextileOperatingPolicy;
use RuntimeException;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;

class TextileOperatingPolicyService
{
    public const MODEL_FULL_PACKAGE = 'full_package_buyer';
    public const MODEL_JOBWORK_WEAVING = 'jobwork_weaving_beam_supplied';
    public const MODEL_JOBWORK_PROCESSING = 'jobwork_processing_grey_supplied';
    public const MODEL_TRADER_BULK = 'trader_bulk';
    public const MODEL_EXPORT_COMPLIANCE = 'export_compliance';

    public const SETTING_HAS_WARPING = 'has_warping';
    public const SETTING_HAS_SIZING = 'has_sizing';
    public const SETTING_HAS_OWN_LOOMS = 'has_own_looms';
    public const SETTING_HAS_WEAVING_PRODUCTION = 'has_weaving_production';
    public const SETTING_HAS_SHIFT_PLANNING = 'has_shift_planning';
    public const SETTING_HAS_MAINTENANCE = 'has_maintenance';
    public const SETTING_HAS_JOBWORK_WEAVING = 'has_jobwork_weaving';
    public const SETTING_HAS_JOBWORK_PROCESSING = 'has_jobwork_processing';
    public const SETTING_HAS_PROCUREMENT = 'has_procurement';
    public const SETTING_HAS_INCOMING_QC = 'has_incoming_qc';
    public const SETTING_HAS_SUPPLIER_CLAIMS = 'has_supplier_claims';
    public const SETTING_HAS_PROCESSING_HOUSE = 'has_processing_house';
    public const SETTING_HAS_QUALITY_INSPECTION = 'has_quality_inspection';
    public const SETTING_HAS_HOLD_RELEASE = 'has_hold_release';
    public const SETTING_HAS_SALES = 'has_sales';
    public const SETTING_HAS_SALES_ALLOCATION = 'has_sales_allocation';
    public const SETTING_HAS_SALES_DISPATCH = 'has_sales_dispatch';
    public const SETTING_HAS_CHALLAN_POD = 'has_challan_pod';
    public const SETTING_HAS_INVENTORY = 'has_inventory';
    public const SETTING_HAS_INVENTORY_LOCATIONS = 'has_inventory_locations';
    public const SETTING_HAS_INVENTORY_MOVEMENTS = 'has_inventory_movements';
    public const SETTING_HAS_INVENTORY_RESERVATIONS = 'has_inventory_reservations';
    public const SETTING_HAS_INVENTORY_FREEZE = 'has_inventory_freeze';
    public const SETTING_HAS_INVENTORY_VERIFICATION = 'has_inventory_verification';
    public const SETTING_HAS_INVENTORY_CYCLE_COUNT = 'has_inventory_cycle_count';
    public const SETTING_HAS_TRANSPORT_OWN = 'has_transport_own';
    public const SETTING_HAS_TRANSPORT_VENDOR = 'has_transport_vendor';
    public const SETTING_HAS_PAYMENT_REMINDERS = 'has_payment_reminders';

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

    public function settingOptions(): array
    {
        return [
            self::SETTING_HAS_WARPING,
            self::SETTING_HAS_SIZING,
            self::SETTING_HAS_OWN_LOOMS,
            self::SETTING_HAS_WEAVING_PRODUCTION,
            self::SETTING_HAS_SHIFT_PLANNING,
            self::SETTING_HAS_MAINTENANCE,
            self::SETTING_HAS_JOBWORK_WEAVING,
            self::SETTING_HAS_JOBWORK_PROCESSING,
            self::SETTING_HAS_PROCUREMENT,
            self::SETTING_HAS_INCOMING_QC,
            self::SETTING_HAS_SUPPLIER_CLAIMS,
            self::SETTING_HAS_PROCESSING_HOUSE,
            self::SETTING_HAS_QUALITY_INSPECTION,
            self::SETTING_HAS_HOLD_RELEASE,
            self::SETTING_HAS_SALES,
            self::SETTING_HAS_SALES_ALLOCATION,
            self::SETTING_HAS_SALES_DISPATCH,
            self::SETTING_HAS_CHALLAN_POD,
            self::SETTING_HAS_INVENTORY,
            self::SETTING_HAS_INVENTORY_LOCATIONS,
            self::SETTING_HAS_INVENTORY_MOVEMENTS,
            self::SETTING_HAS_INVENTORY_RESERVATIONS,
            self::SETTING_HAS_INVENTORY_FREEZE,
            self::SETTING_HAS_INVENTORY_VERIFICATION,
            self::SETTING_HAS_INVENTORY_CYCLE_COUNT,
            self::SETTING_HAS_TRANSPORT_OWN,
            self::SETTING_HAS_TRANSPORT_VENDOR,
            self::SETTING_HAS_PAYMENT_REMINDERS,
        ];
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
                'settings' => $this->defaultSettingsForProfiles([self::MODEL_FULL_PACKAGE]),
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
            'settings' => $this->resolveRequestedSettings($payload, $selectedProfiles),
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
            'quality' => false,
            'sales' => false,
            'inventory' => false,
            'grn_invoice_sync' => false,
            'sales_order' => false,
            'sales_allocation_dispatch' => false,
            'sales_challan_pod' => false,
            'sales_dispatch_tracking' => false,
            'inventory_transactions' => false,
            'inventory_controls' => false,
            'inventory_records' => false,
            'inventory_locations' => false,
            'inventory_movements' => false,
            'inventory_reservations' => false,
            'inventory_freeze' => false,
            'inventory_verification' => false,
            'inventory_cycle_count' => false,
            'procurement_requisition' => false,
            'procurement_rfq' => false,
            'procurement_purchase_order' => false,
            'procurement_grn' => false,
            'procurement_incoming_qc' => false,
            'procurement_supplier_claims' => false,
            'processing_outward' => false,
            'processing_batch' => false,
            'processing_inward' => false,
            'processing_reconciliation' => false,
            'quality_inspection' => false,
            'quality_hold_release' => false,
            'manufacturing_warping' => false,
            'manufacturing_sizing' => false,
            'manufacturing_beam' => false,
            'manufacturing_loom' => false,
            'manufacturing_planning' => false,
            'manufacturing_weaving' => false,
            'manufacturing_waste' => false,
            'manufacturing_rework' => false,
            'manufacturing_maintenance' => false,
            'transport_operations' => false,
            'maintenance_operations' => false,
        ];

        foreach ($activeProfiles as $profile) {
            $profileFlags = $this->capabilitiesForProfile($profile);
            foreach ($capabilities as $capability => $enabled) {
                $capabilities[$capability] = $enabled || ($profileFlags[$capability] ?? false);
            }
        }

        $settings = $this->normalizedSettings($policy, $activeProfiles);

        $capabilities['manufacturing_warping'] = $capabilities['manufacturing'] && ($settings[self::SETTING_HAS_WARPING] ?? false);
        $capabilities['manufacturing_sizing'] = $capabilities['manufacturing'] && ($settings[self::SETTING_HAS_SIZING] ?? false);
        $capabilities['manufacturing_beam'] = $capabilities['manufacturing'] && (($settings[self::SETTING_HAS_WARPING] ?? false) || ($settings[self::SETTING_HAS_SIZING] ?? false) || ($settings[self::SETTING_HAS_OWN_LOOMS] ?? false));
        $capabilities['manufacturing_loom'] = $capabilities['manufacturing'] && ($settings[self::SETTING_HAS_OWN_LOOMS] ?? false);
        $capabilities['manufacturing_planning'] = $capabilities['manufacturing'] && ($settings[self::SETTING_HAS_OWN_LOOMS] ?? false) && ($settings[self::SETTING_HAS_SHIFT_PLANNING] ?? false);
        $capabilities['manufacturing_weaving'] = $capabilities['manufacturing'] && ($settings[self::SETTING_HAS_WEAVING_PRODUCTION] ?? false);
        $capabilities['manufacturing_waste'] = $capabilities['manufacturing_weaving'];
        $capabilities['manufacturing_rework'] = $capabilities['manufacturing_weaving'];
        $capabilities['manufacturing_maintenance'] = $capabilities['manufacturing_loom'] && ($settings[self::SETTING_HAS_MAINTENANCE] ?? false);
        $capabilities['procurement'] = $capabilities['procurement'] && ($settings[self::SETTING_HAS_PROCUREMENT] ?? false);
        $capabilities['procurement_requisition'] = $capabilities['procurement'];
        $capabilities['procurement_rfq'] = $capabilities['procurement'];
        $capabilities['procurement_purchase_order'] = $capabilities['procurement'];
        $capabilities['procurement_grn'] = $capabilities['procurement'];
        $capabilities['procurement_incoming_qc'] = $capabilities['procurement'] && ($settings[self::SETTING_HAS_INCOMING_QC] ?? false);
        $capabilities['procurement_supplier_claims'] = $capabilities['procurement'] && ($settings[self::SETTING_HAS_SUPPLIER_CLAIMS] ?? false);
        $capabilities['processing'] = $capabilities['processing'] && ($settings[self::SETTING_HAS_PROCESSING_HOUSE] ?? false || $settings[self::SETTING_HAS_JOBWORK_PROCESSING] ?? false);
        $capabilities['processing_outward'] = $capabilities['processing'];
        $capabilities['processing_batch'] = $capabilities['processing'];
        $capabilities['processing_inward'] = $capabilities['processing'];
        $capabilities['processing_reconciliation'] = $capabilities['processing'];
        $capabilities['quality'] = ($settings[self::SETTING_HAS_QUALITY_INSPECTION] ?? false) || ($settings[self::SETTING_HAS_HOLD_RELEASE] ?? false);
        $capabilities['quality_inspection'] = $capabilities['quality'] && ($settings[self::SETTING_HAS_QUALITY_INSPECTION] ?? false);
        $capabilities['quality_hold_release'] = $capabilities['quality'] && ($settings[self::SETTING_HAS_HOLD_RELEASE] ?? false);
        $capabilities['sales'] = $settings[self::SETTING_HAS_SALES] ?? false;
        $capabilities['sales_order'] = $capabilities['sales'];
        $capabilities['sales_allocation_dispatch'] = $capabilities['sales'] && (($settings[self::SETTING_HAS_SALES_ALLOCATION] ?? false) || ($settings[self::SETTING_HAS_SALES_DISPATCH] ?? false));
        $capabilities['sales_challan_pod'] = $capabilities['sales'] && ($settings[self::SETTING_HAS_CHALLAN_POD] ?? false);
        $capabilities['sales_dispatch_tracking'] = $capabilities['sales'] && ($settings[self::SETTING_HAS_SALES_DISPATCH] ?? false);
        $capabilities['inventory'] = $settings[self::SETTING_HAS_INVENTORY] ?? false;
        $capabilities['inventory_transactions'] = $capabilities['inventory'];
        $capabilities['inventory_controls'] = $capabilities['inventory'];
        $capabilities['inventory_records'] = $capabilities['inventory'];
        $capabilities['inventory_locations'] = $capabilities['inventory'] && ($settings[self::SETTING_HAS_INVENTORY_LOCATIONS] ?? false);
        $capabilities['inventory_movements'] = $capabilities['inventory'] && ($settings[self::SETTING_HAS_INVENTORY_MOVEMENTS] ?? false);
        $capabilities['inventory_reservations'] = $capabilities['inventory'] && ($settings[self::SETTING_HAS_INVENTORY_RESERVATIONS] ?? false);
        $capabilities['inventory_freeze'] = $capabilities['inventory'] && ($settings[self::SETTING_HAS_INVENTORY_FREEZE] ?? false);
        $capabilities['inventory_verification'] = $capabilities['inventory'] && ($settings[self::SETTING_HAS_INVENTORY_VERIFICATION] ?? false);
        $capabilities['inventory_cycle_count'] = $capabilities['inventory'] && ($settings[self::SETTING_HAS_INVENTORY_CYCLE_COUNT] ?? false);
        $capabilities['transport_operations'] = (($settings[self::SETTING_HAS_TRANSPORT_OWN] ?? false) || ($settings[self::SETTING_HAS_TRANSPORT_VENDOR] ?? false));
        $capabilities['maintenance_operations'] = $settings[self::SETTING_HAS_MAINTENANCE] ?? false;
        $capabilities['payments'] = $settings[self::SETTING_HAS_PAYMENT_REMINDERS] ?? false;

        return $capabilities;
    }

    public function capabilitiesForUser($user): array
    {
        if (! $user) {
            return [];
        }

        $companyContextUser = $this->resolveCompanyContextUser($user);
        if (! $companyContextUser) {
            return [];
        }

        $policy = $this->resolveForTenant($companyContextUser->id);
        $capabilities = $this->capabilities($policy);

        if (! Schema::hasTable('textile_role_capabilities')) {
            return $capabilities;
        }

        try {
            $roleNames = method_exists($user, 'getRoleNames') ? $user->getRoleNames()->toArray() : [];
            if ($roleNames === []) {
                return $capabilities;
            }

            $roleIds = Role::query()
                ->where('created_by', $companyContextUser->id)
                ->whereIn('name', $roleNames)
                ->pluck('id')
                ->all();

            if ($roleIds === []) {
                return $capabilities;
            }

            $roleCapabilities = TextileRoleCapability::query()
                ->where('created_by', $companyContextUser->id)
                ->whereIn('role_id', $roleIds)
                ->get();

            foreach ($roleCapabilities as $roleCapability) {
                $capabilities = $this->applyCapabilityOverride($capabilities, $roleCapability->capabilities ?? []);
            }
        } catch (\Throwable $exception) {
            report($exception);
        }

        return $capabilities;
    }

    public function syncDefaultRoleCapabilitiesForTenant(int $tenantId): void
    {
        if ($tenantId <= 0 || ! Schema::hasTable('textile_role_capabilities')) {
            return;
        }

        $roles = Role::query()
            ->where('created_by', $tenantId)
            ->whereIn('name', ['company', 'staff'])
            ->get()
            ->keyBy('name');

        if ($roles->isEmpty()) {
            return;
        }

        $companyCapabilities = $this->fullAccessCapabilities();
        $staffCapabilities = $this->staffOperationalCapabilities();

        if ($roles->has('company')) {
            TextileRoleCapability::updateOrCreate(
                ['created_by' => $tenantId, 'role_id' => $roles->get('company')->id],
                ['capabilities' => $companyCapabilities]
            );
        }

        if ($roles->has('staff')) {
            TextileRoleCapability::updateOrCreate(
                ['created_by' => $tenantId, 'role_id' => $roles->get('staff')->id],
                ['capabilities' => $staffCapabilities]
            );
        }
    }

    public function settings(TextileOperatingPolicy $policy): array
    {
        $activeProfiles = $this->resolveActiveProfilesForTenant((int) ($policy->created_by ?? 0));
        if ($activeProfiles === []) {
            $activeProfiles = [$policy->operating_model];
        }

        return $this->normalizedSettings($policy, $activeProfiles);
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
                'quality' => true,
                'sales' => false,
                'inventory' => true,
                'grn_invoice_sync' => false,
            ],
            self::MODEL_JOBWORK_PROCESSING => [
                'procurement' => false,
                'manufacturing' => false,
                'processing' => true,
                'quality' => true,
                'sales' => false,
                'inventory' => true,
                'grn_invoice_sync' => false,
            ],
            self::MODEL_TRADER_BULK => [
                'procurement' => true,
                'manufacturing' => false,
                'processing' => false,
                'quality' => false,
                'sales' => true,
                'inventory' => true,
                'grn_invoice_sync' => true,
            ],
            default => [
                'procurement' => true,
                'manufacturing' => true,
                'processing' => true,
                'quality' => true,
                'sales' => true,
                'inventory' => true,
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

    private function resolveRequestedSettings(array $payload, array $profiles): array
    {
        $requested = $payload['settings'] ?? [];
        if (!is_array($requested)) {
            $requested = [];
        }

        $defaults = $this->defaultSettingsForProfiles($profiles);

        foreach ($this->settingOptions() as $settingKey) {
            $defaults[$settingKey] = in_array($settingKey, $requested, true);
        }

        return $defaults;
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
        $policy->settings = $this->defaultSettingsForProfiles([self::MODEL_FULL_PACKAGE]);

        return $policy;
    }

    private function normalizedSettings(TextileOperatingPolicy $policy, array $profiles): array
    {
        $defaults = $this->defaultSettingsForProfiles($profiles);
        $stored = is_array($policy->settings ?? null) ? $policy->settings : [];

        foreach ($this->settingOptions() as $settingKey) {
            if (array_key_exists($settingKey, $stored)) {
                $defaults[$settingKey] = (bool) $stored[$settingKey];
            }
        }

        return $defaults;
    }

    private function defaultSettingsForProfiles(array $profiles): array
    {
        $defaults = array_fill_keys($this->settingOptions(), false);

        foreach ($profiles as $profile) {
            switch ($profile) {
                case self::MODEL_JOBWORK_WEAVING:
                    $defaults[self::SETTING_HAS_OWN_LOOMS] = true;
                    $defaults[self::SETTING_HAS_WEAVING_PRODUCTION] = true;
                    $defaults[self::SETTING_HAS_SHIFT_PLANNING] = true;
                    $defaults[self::SETTING_HAS_MAINTENANCE] = true;
                    $defaults[self::SETTING_HAS_JOBWORK_WEAVING] = true;
                    $defaults[self::SETTING_HAS_QUALITY_INSPECTION] = true;
                    $defaults[self::SETTING_HAS_HOLD_RELEASE] = true;
                    $defaults[self::SETTING_HAS_INVENTORY] = true;
                    $defaults[self::SETTING_HAS_INVENTORY_LOCATIONS] = true;
                    $defaults[self::SETTING_HAS_INVENTORY_MOVEMENTS] = true;
                    $defaults[self::SETTING_HAS_INVENTORY_RESERVATIONS] = true;
                    $defaults[self::SETTING_HAS_INVENTORY_FREEZE] = true;
                    $defaults[self::SETTING_HAS_INVENTORY_VERIFICATION] = true;
                    $defaults[self::SETTING_HAS_INVENTORY_CYCLE_COUNT] = true;
                    $defaults[self::SETTING_HAS_TRANSPORT_OWN] = true;
                    $defaults[self::SETTING_HAS_TRANSPORT_VENDOR] = true;
                    break;
                case self::MODEL_JOBWORK_PROCESSING:
                    $defaults[self::SETTING_HAS_JOBWORK_PROCESSING] = true;
                    $defaults[self::SETTING_HAS_PROCESSING_HOUSE] = true;
                    $defaults[self::SETTING_HAS_QUALITY_INSPECTION] = true;
                    $defaults[self::SETTING_HAS_HOLD_RELEASE] = true;
                    $defaults[self::SETTING_HAS_INVENTORY] = true;
                    $defaults[self::SETTING_HAS_INVENTORY_LOCATIONS] = true;
                    $defaults[self::SETTING_HAS_INVENTORY_MOVEMENTS] = true;
                    $defaults[self::SETTING_HAS_INVENTORY_RESERVATIONS] = true;
                    $defaults[self::SETTING_HAS_INVENTORY_VERIFICATION] = true;
                    $defaults[self::SETTING_HAS_INVENTORY_CYCLE_COUNT] = true;
                    $defaults[self::SETTING_HAS_TRANSPORT_OWN] = true;
                    $defaults[self::SETTING_HAS_TRANSPORT_VENDOR] = true;
                    break;
                case self::MODEL_TRADER_BULK:
                    $defaults[self::SETTING_HAS_PROCUREMENT] = true;
                    $defaults[self::SETTING_HAS_INCOMING_QC] = true;
                    $defaults[self::SETTING_HAS_SALES] = true;
                    $defaults[self::SETTING_HAS_SALES_ALLOCATION] = true;
                    $defaults[self::SETTING_HAS_SALES_DISPATCH] = true;
                    $defaults[self::SETTING_HAS_CHALLAN_POD] = true;
                    $defaults[self::SETTING_HAS_INVENTORY] = true;
                    $defaults[self::SETTING_HAS_INVENTORY_LOCATIONS] = true;
                    $defaults[self::SETTING_HAS_INVENTORY_MOVEMENTS] = true;
                    $defaults[self::SETTING_HAS_INVENTORY_RESERVATIONS] = true;
                    $defaults[self::SETTING_HAS_TRANSPORT_OWN] = true;
                    $defaults[self::SETTING_HAS_TRANSPORT_VENDOR] = true;
                    break;
                default:
                    $defaults[self::SETTING_HAS_PROCUREMENT] = true;
                    $defaults[self::SETTING_HAS_INCOMING_QC] = true;
                    $defaults[self::SETTING_HAS_SUPPLIER_CLAIMS] = true;
                    $defaults[self::SETTING_HAS_PROCESSING_HOUSE] = true;
                    $defaults[self::SETTING_HAS_QUALITY_INSPECTION] = true;
                    $defaults[self::SETTING_HAS_HOLD_RELEASE] = true;
                    $defaults[self::SETTING_HAS_SALES] = true;
                    $defaults[self::SETTING_HAS_SALES_ALLOCATION] = true;
                    $defaults[self::SETTING_HAS_SALES_DISPATCH] = true;
                    $defaults[self::SETTING_HAS_CHALLAN_POD] = true;
                    $defaults[self::SETTING_HAS_INVENTORY] = true;
                    $defaults[self::SETTING_HAS_INVENTORY_LOCATIONS] = true;
                    $defaults[self::SETTING_HAS_INVENTORY_MOVEMENTS] = true;
                    $defaults[self::SETTING_HAS_INVENTORY_RESERVATIONS] = true;
                    $defaults[self::SETTING_HAS_INVENTORY_FREEZE] = true;
                    $defaults[self::SETTING_HAS_INVENTORY_VERIFICATION] = true;
                    $defaults[self::SETTING_HAS_INVENTORY_CYCLE_COUNT] = true;
                    $defaults[self::SETTING_HAS_WARPING] = true;
                    $defaults[self::SETTING_HAS_SIZING] = true;
                    $defaults[self::SETTING_HAS_OWN_LOOMS] = true;
                    $defaults[self::SETTING_HAS_WEAVING_PRODUCTION] = true;
                    $defaults[self::SETTING_HAS_SHIFT_PLANNING] = true;
                    $defaults[self::SETTING_HAS_MAINTENANCE] = true;
                    $defaults[self::SETTING_HAS_TRANSPORT_OWN] = true;
                    $defaults[self::SETTING_HAS_TRANSPORT_VENDOR] = true;
                    $defaults[self::SETTING_HAS_PAYMENT_REMINDERS] = true;
                    break;
            }
        }

        return $defaults;
    }

    private function applyCapabilityOverride(array $capabilities, array $override): array
    {
        foreach ($override as $key => $value) {
            if (! array_key_exists($key, $capabilities)) {
                continue;
            }

            $capabilities[$key] = (bool) $value;
        }

        return $capabilities;
    }

    private function fullAccessCapabilities(): array
    {
        $policy = $this->defaultPolicy((int) auth()->id());

        return array_fill_keys(array_keys($this->capabilities($policy)), true);
    }

    private function staffOperationalCapabilities(): array
    {
        return [
            'manufacturing_planning' => false,
            'manufacturing_maintenance' => false,
            'quality_hold_release' => false,
            'sales_challan_pod' => false,
            'inventory_freeze' => false,
            'inventory_verification' => false,
            'inventory_cycle_count' => false,
            'transport_operations' => false,
            'maintenance_operations' => false,
        ];
    }
}
