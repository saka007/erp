<?php

namespace DigitalFuzed\TextileCore\Services;

use App\Models\EmailTemplate;
use App\Models\PurchaseInvoice;
use App\Models\SalesInvoice;
use Carbon\Carbon;
use DigitalFuzed\TextileCore\Models\TextilePaymentReminder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Workdo\Account\Models\Customer;
use Workdo\Account\Models\Vendor;

class TextilePaymentReminderService
{
    public const PARTY_SUPPLIER = 'supplier';

    public const PARTY_BUYER = 'buyer';

    public const INVOICE_PURCHASE = 'purchase';

    public const INVOICE_SALES = 'sales';

    public const TEMPLATE_SUPPLIER = 'Payment Reminder - Supplier';

    public const TEMPLATE_BUYER = 'Payment Reminder - Buyer';

    /** Do not re-remind the same invoice within this many days. */
    public const REMINDER_COOLDOWN_DAYS = 3;

    /**
     * Branch-wise and vendor-wise outstanding summary for the tenant.
     */
    public function summary(?int $tenantId = null, ?int $branchId = null): array
    {
        $tenantId = $tenantId ?? $this->resolveTenantId();

        $payables = $this->unpaidPurchaseInvoices($tenantId);
        $receivables = $this->unpaidSalesInvoices($tenantId);

        $vendorRows = collect();
        foreach ($payables as $invoice) {
            $vendorRows->push($this->partyRow(PurchaseInvoice::class, $invoice, self::PARTY_SUPPLIER, $tenantId));
        }
        foreach ($receivables as $invoice) {
            $vendorRows->push($this->partyRow(SalesInvoice::class, $invoice, self::PARTY_BUYER, $tenantId));
        }

        $vendors = $vendorRows
            ->filter()
            ->when($branchId !== null, fn ($rows) => $rows->where('branch_id', (int) $branchId))
            ->groupBy('party_key')
            ->map(function ($rows) {
                $first = $rows->first();

                return array_merge($first, [
                    'outstanding' => round((float) $rows->sum('outstanding'), 2),
                    'due_invoices' => $rows->sum('due_invoices'),
                    'oldest_due_date' => $rows->min('due_date'),
                    'max_overdue_days' => $rows->max('overdue_days'),
                    'overdue_days' => $rows->max('overdue_days'),
                ]);
            })
            ->values()
            ->map(fn (array $row) => $this->shapeVendorRow($row))
            ->values();

        $branches = $vendors
            ->groupBy('branch_key')
            ->map(function ($rows) {
                $first = $rows->first();

                return [
                    'id' => $first['branch_id'],
                    'name' => $first['branch_name'],
                    'payable' => round((float) $rows->where('direction', 'pay')->sum('outstanding'), 2),
                    'receivable' => round((float) $rows->where('direction', 'receive')->sum('outstanding'), 2),
                    'net' => round((float) $rows->where('direction', 'receive')->sum('outstanding') - (float) $rows->where('direction', 'pay')->sum('outstanding'), 2),
                    'overdue_payable' => round((float) $rows->where('direction', 'pay')->where('overdue_days', '>', 0)->sum('outstanding'), 2),
                    'overdue_receivable' => round((float) $rows->where('direction', 'receive')->where('overdue_days', '>', 0)->sum('outstanding'), 2),
                    'vendor_count' => $rows->where('direction', 'pay')->count(),
                    'buyer_count' => $rows->where('direction', 'receive')->count(),
                ];
            })
            ->sortByDesc('net')
            ->values();

        $totals = [
            'payable' => round((float) $vendors->where('direction', 'pay')->sum('outstanding'), 2),
            'receivable' => round((float) $vendors->where('direction', 'receive')->sum('outstanding'), 2),
            'net' => round((float) $vendors->where('direction', 'receive')->sum('outstanding') - (float) $vendors->where('direction', 'pay')->sum('outstanding'), 2),
            'overdue_payable' => round((float) $vendors->where('direction', 'pay')->where('overdue_days', '>', 0)->sum('outstanding'), 2),
            'overdue_receivable' => round((float) $vendors->where('direction', 'receive')->where('overdue_days', '>', 0)->sum('outstanding'), 2),
            'parties' => $vendors->count(),
        ];

        return [
            'totals' => $totals,
            'branches' => $branches,
            'vendors' => $vendors,
            'reminders' => $this->recentReminders($tenantId, 15, $branchId),
        ];
    }

    /**
     * Send payment reminders for every due, unpaid invoice.
     *
     * @return array{sent: int, skipped: int, failed: int}
     */
    public function sendDueReminders(bool $force = false, ?int $tenantId = null, ?string $partyType = null, ?int $partyId = null, ?int $branchId = null): array
    {
        $tenantId = $tenantId ?? $this->resolveTenantId();
        $today = Carbon::today();

        $dueInvoices = collect()
            ->merge($this->unpaidPurchaseInvoices($tenantId))
            ->merge($this->unpaidSalesInvoices($tenantId))
            ->filter(function ($invoice) use ($today, $partyType, $partyId) {
                $invoiceType = $invoice instanceof PurchaseInvoice ? self::INVOICE_PURCHASE : self::INVOICE_SALES;
                if ($partyType !== null && $partyType !== $this->partyTypeForInvoice($invoiceType)) {
                    return false;
                }
                if ($partyId !== null && (int) $invoice->vendor_id !== $partyId && (int) $invoice->customer_id !== $partyId) {
                    return false;
                }

                return $this->dueDate($invoice) !== null && $this->dueDate($invoice)->lt($today);
            });

        $sent = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($dueInvoices as $invoice) {
            $invoiceType = $invoice instanceof PurchaseInvoice ? self::INVOICE_PURCHASE : self::INVOICE_SALES;
            $party = $this->resolveParty($invoice, $invoiceType, $tenantId);

            if ($party === null) {
                $skipped++;

                continue;
            }

            if ($branchId !== null && (int) ($party['branch_id'] ?? 0) !== (int) $branchId) {
                $skipped++;

                continue;
            }

            if (! (bool) ($party['reminder_enabled'] ?? true)) {
                $skipped++;

                continue;
            }

            if (! $force && $this->wasRecentlyReminded($invoiceType, (int) $invoice->id, $tenantId)) {
                $skipped++;

                continue;
            }

            $template = $invoiceType === self::INVOICE_PURCHASE ? self::TEMPLATE_SUPPLIER : self::TEMPLATE_BUYER;
            $result = $this->dispatchEmail($template, $party, $invoice, $tenantId);

            $this->logReminder($invoiceType, $invoice, $party, $template, $tenantId);

            if (($result['is_success'] ?? false) === true) {
                $sent++;
            } else {
                $failed++;
            }
        }

        return ['sent' => $sent, 'skipped' => $skipped, 'failed' => $failed];
    }

    /**
     * Update credit/reminder configuration for a vendor or customer master.
     */
    public function updateCredit(string $partyType, int $partyId, array $payload): array
    {
        $model = $partyType === self::PARTY_SUPPLIER ? Vendor::query() : Customer::query();
        $party = $model->find($partyId);

        if ($party === null) {
            throw new \RuntimeException('Party not found.');
        }

        $party->fill([
            'credit_enabled' => (bool) ($payload['credit_enabled'] ?? false),
            'credit_days' => $payload['credit_days'] !== null && $payload['credit_days'] !== '' ? (int) $payload['credit_days'] : null,
            'credit_limit' => $payload['credit_limit'] !== null && $payload['credit_limit'] !== '' ? (float) $payload['credit_limit'] : null,
            'reminder_enabled' => (bool) $payload['reminder_enabled'],
            'branch_id' => $payload['branch_id'] !== null && $payload['branch_id'] !== '' ? (int) $payload['branch_id'] : null,
        ])->save();

        return $this->partyMaster($party, $partyType);
    }

    public function partyMasters(?int $tenantId = null): array
    {
        $tenantId = $tenantId ?? $this->resolveTenantId();

        $vendors = Vendor::query()
            ->when($tenantId, fn ($query) => $query->where('created_by', $tenantId))
            ->orderBy('company_name')
            ->get()
            ->map(fn (Vendor $vendor) => $this->partyMaster($vendor, self::PARTY_SUPPLIER));

        $customers = Customer::query()
            ->when($tenantId, fn ($query) => $query->where('created_by', $tenantId))
            ->orderBy('company_name')
            ->get()
            ->map(fn (Customer $customer) => $this->partyMaster($customer, self::PARTY_BUYER));

        return $vendors->concat($customers)->values()->all();
    }

    public function branchOptions(?int $tenantId = null): array
    {
        $tenantId = $tenantId ?? $this->resolveTenantId();

        if ($tenantId === null || ! Schema::hasTable('branches')) {
            return [];
        }

        return DB::table('branches')
            ->where('created_by', $tenantId)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($branch) => ['id' => (int) $branch->id, 'name' => $branch->name])
            ->values()
            ->all();
    }

    private function unpaidPurchaseInvoices(?int $tenantId)
    {
        return PurchaseInvoice::query()
            ->when($tenantId, fn ($query) => $query->where('created_by', $tenantId))
            ->where(function ($query) {
                $query->where('balance_amount', '>', 0)
                    ->orWhere(function ($query) {
                        $query->whereNull('balance_amount')->where('total_amount', '>', 0)->where('status', '!=', 'paid');
                    });
            })
            ->where(function ($query) {
                $query->whereNull('status')->orWhere('status', '!=', 'cancelled');
            })
            ->orderBy('due_date')
            ->get();
    }

    private function unpaidSalesInvoices(?int $tenantId)
    {
        return SalesInvoice::query()
            ->when($tenantId, fn ($query) => $query->where('created_by', $tenantId))
            ->where(function ($query) {
                $query->where('balance_amount', '>', 0)
                    ->orWhere(function ($query) {
                        $query->whereNull('balance_amount')->where('total_amount', '>', 0)->where('status', '!=', 'paid');
                    });
            })
            ->where(function ($query) {
                $query->whereNull('status')->orWhere('status', '!=', 'cancelled');
            })
            ->orderBy('due_date')
            ->get();
    }

    private function partyRow(string $invoiceClass, $invoice, string $partyType, ?int $tenantId): ?array
    {
        $party = $this->resolveParty($invoice, $partyType, $tenantId);

        if ($party === null) {
            return null;
        }

        $invoiceType = $invoiceClass === PurchaseInvoice::class ? self::INVOICE_PURCHASE : self::INVOICE_SALES;
        $dueDate = $this->dueDate($invoice);
        $overdueDays = $dueDate !== null ? max(0, (int) $dueDate->diffInDays(Carbon::today(), false)) : 0;

        return array_merge($party, [
            'party_key' => $partyType.':'.$party['party_id'],
            'invoice_type' => $invoiceType,
            'invoice_number' => $invoice->invoice_number,
            'invoice_id' => (int) $invoice->id,
            'invoice_date' => optional($invoice->invoice_date)->toDateString(),
            'due_date' => $dueDate?->toDateString(),
            'overdue_days' => $overdueDays,
            'outstanding' => round((float) ($invoice->balance_amount ?? $invoice->total_amount), 2),
            'due_invoices' => 1,
        ]);
    }

    private function resolveParty($invoice, string $partyType, ?int $tenantId): ?array
    {
        if ($partyType === self::PARTY_SUPPLIER) {
            $vendor = Vendor::query()
                ->where(function ($query) use ($invoice) {
                    $query->where('user_id', $invoice->vendor_id)->orWhere('id', $invoice->vendor_id);
                })
                ->first();

            if ($vendor === null) {
                return null;
            }

            return [
                'party_id' => (int) $vendor->id,
                'party_type' => self::PARTY_SUPPLIER,
                'party_name' => $vendor->company_name,
                'party_email' => $vendor->contact_person_email ?: $vendor->primary_email,
                'credit_enabled' => (bool) ($vendor->credit_enabled ?? false),
                'credit_days' => $vendor->credit_days,
                'credit_limit' => $vendor->credit_limit !== null ? (float) $vendor->credit_limit : null,
                'reminder_enabled' => (bool) ($vendor->reminder_enabled ?? true),
                'branch_id' => $vendor->branch_id !== null ? (int) $vendor->branch_id : null,
                'branch_name' => $this->branchName($vendor->branch_id, $tenantId),
                'branch_key' => (string) ($vendor->branch_id ?: 'unassigned'),
            ];
        }

        $customer = Customer::query()
            ->where(function ($query) use ($invoice) {
                $query->where('user_id', $invoice->customer_id)->orWhere('id', $invoice->customer_id);
            })
            ->first();

        if ($customer === null) {
            return null;
        }

        return [
            'party_id' => (int) $customer->id,
            'party_type' => self::PARTY_BUYER,
            'party_name' => $customer->company_name,
            'party_email' => $customer->contact_person_email,
            'credit_enabled' => (bool) ($customer->credit_enabled ?? false),
            'credit_days' => $customer->credit_days,
            'credit_limit' => $customer->credit_limit !== null ? (float) $customer->credit_limit : null,
            'reminder_enabled' => (bool) ($customer->reminder_enabled ?? true),
            'branch_id' => $customer->branch_id !== null ? (int) $customer->branch_id : null,
            'branch_name' => $this->branchName($customer->branch_id, $tenantId),
            'branch_key' => (string) ($customer->branch_id ?: 'unassigned'),
        ];
    }

    private function dueDate($invoice): ?Carbon
    {
        if (! empty($invoice->due_date)) {
            return Carbon::parse($invoice->due_date);
        }

        $invoiceDate = $invoice->invoice_date ? Carbon::parse($invoice->invoice_date) : $invoice->created_at;
        if ($invoiceDate === null) {
            return null;
        }

        return $invoiceDate->copy()->addDays((int) ($this->creditDaysFor($invoice) ?? 0));
    }

    private function creditDaysFor($invoice): ?int
    {
        $party = null;

        if ($invoice instanceof PurchaseInvoice) {
            $party = Vendor::query()
                ->where(function ($query) use ($invoice) {
                    $query->where('user_id', $invoice->vendor_id)->orWhere('id', $invoice->vendor_id);
                })
                ->first();
        } else {
            $party = Customer::query()
                ->where(function ($query) use ($invoice) {
                    $query->where('user_id', $invoice->customer_id)->orWhere('id', $invoice->customer_id);
                })
                ->first();
        }

        // Credit only applies when the party has explicitly opted into it.
        if ($party === null || ! (bool) ($party->credit_enabled ?? false)) {
            return 0;
        }

        return $party->credit_days;
    }

    private function branchName(?int $branchId, ?int $tenantId): ?string
    {
        if ($branchId === null || ! Schema::hasTable('branches')) {
            return null;
        }

        return DB::table('branches')->where('id', $branchId)->value('name');
    }

    private function wasRecentlyReminded(string $invoiceType, int $invoiceId, ?int $tenantId): bool
    {
        $tenantId = $tenantId ?? $this->resolveTenantId();

        return TextilePaymentReminder::query()
            ->where('created_by', $tenantId)
            ->where('invoice_type', $invoiceType)
            ->where('invoice_id', $invoiceId)
            ->where('reminded_at', '>=', Carbon::now()->subDays(self::REMINDER_COOLDOWN_DAYS))
            ->exists();
    }

    private function dispatchEmail(string $template, array $party, $invoice, ?int $tenantId): array
    {
        $tenantId = $tenantId ?? $this->resolveTenantId();
        if (empty($party['party_email'])) {
            return ['is_success' => false];
        }

        $obj = [
            'party_name' => $party['party_name'],
            'invoice_number' => $invoice->invoice_number,
            'amount_due' => number_format((float) ($invoice->balance_amount ?? $invoice->total_amount), 2),
            'due_date' => optional($this->dueDate($invoice))->toDateString(),
            'overdue_days' => (string) max(0, (int) $this->dueDate($invoice)?->diffInDays(Carbon::today(), false) ?? 0),
            'credit_days' => (string) ($party['credit_days'] ?? ''),
            'name' => $party['party_name'],
            'email' => $party['party_email'],
            'item_name' => $invoice->invoice_number,
        ];

        return EmailTemplate::sendEmailTemplate($template, [$party['party_email']], $obj, $tenantId, $tenantId);
    }

    private function logReminder(string $invoiceType, $invoice, array $party, string $template, ?int $tenantId): void
    {
        $tenantId = $tenantId ?? $this->resolveTenantId();

        TextilePaymentReminder::create([
            'party_type' => $party['party_type'],
            'party_id' => $party['party_id'],
            'party_name' => $party['party_name'],
            'invoice_type' => $invoiceType,
            'invoice_id' => (int) $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'amount_due' => (float) ($invoice->balance_amount ?? $invoice->total_amount),
            'due_date' => $this->dueDate($invoice)?->toDateString(),
            'template_name' => $template,
            'reminded_at' => Carbon::now(),
            'branch_id' => $party['branch_id'],
            'creator_id' => $tenantId,
            'created_by' => $tenantId,
        ]);
    }

    private function recentReminders(?int $tenantId = null, int $limit = 15, ?int $branchId = null): array
    {
        $tenantId = $tenantId ?? $this->resolveTenantId();

        return TextilePaymentReminder::query()
            ->when($tenantId, fn ($query) => $query->where('created_by', $tenantId))
            ->when($branchId !== null, fn ($query) => $query->where('branch_id', (int) $branchId))
            ->latest('reminded_at')
            ->limit($limit)
            ->get()
            ->map(fn (TextilePaymentReminder $reminder) => [
                'id' => (int) $reminder->id,
                'party_type' => $reminder->party_type,
                'party_name' => $reminder->party_name,
                'invoice_number' => $reminder->invoice_number,
                'amount_due' => (float) $reminder->amount_due,
                'due_date' => $reminder->due_date?->toDateString(),
                'template_name' => $reminder->template_name,
                'reminded_at' => $reminder->reminded_at?->toDateTimeString(),
                'branch_id' => $reminder->branch_id,
            ])
            ->values()
            ->all();
    }

    private function partyMaster($party, string $partyType): array
    {
        return [
            'party_id' => (int) $party->id,
            'party_type' => $partyType,
            'party_name' => $party->company_name,
            'party_email' => $partyType === self::PARTY_SUPPLIER
                ? ($party->contact_person_email ?: $party->primary_email)
                : $party->contact_person_email,
            'credit_enabled' => (bool) ($party->credit_enabled ?? false),
            'credit_days' => $party->credit_days !== null ? (int) $party->credit_days : null,
            'credit_limit' => $party->credit_limit !== null ? (float) $party->credit_limit : null,
            'reminder_enabled' => (bool) ($party->reminder_enabled ?? true),
            'branch_id' => $party->branch_id !== null ? (int) $party->branch_id : null,
        ];
    }

    private function shapeVendorRow(array $row): array
    {
        $direction = $row['party_type'] === self::PARTY_SUPPLIER ? 'pay' : 'receive';

        return [
            'party_id' => $row['party_id'],
            'party_type' => $row['party_type'],
            'party_name' => $row['party_name'],
            'party_email' => $row['party_email'],
            'direction' => $direction,
            'credit_enabled' => (bool) ($row['credit_enabled'] ?? false),
            'credit_days' => $row['credit_days'] !== null ? (int) $row['credit_days'] : null,
            'credit_limit' => $row['credit_limit'] !== null ? (float) $row['credit_limit'] : null,
            'reminder_enabled' => (bool) $row['reminder_enabled'],
            'branch_id' => $row['branch_id'],
            'branch_name' => $row['branch_name'],
            'branch_key' => $row['branch_key'],
            'outstanding' => (float) $row['outstanding'],
            'due_invoices' => $row['due_invoices'],
            'oldest_due_date' => $row['oldest_due_date'],
            'overdue_days' => $row['overdue_days'],
            'last_reminded_at' => $this->lastRemindedAt($row['party_id'], $row['party_type']),
        ];
    }

    private function lastRemindedAt(int $partyId, string $partyType): ?string
    {
        return TextilePaymentReminder::query()
            ->where('party_id', $partyId)
            ->where('party_type', $partyType)
            ->latest('reminded_at')
            ->value('reminded_at');
    }

    private function partyTypeForInvoice(string $invoiceType): string
    {
        return $invoiceType === self::INVOICE_PURCHASE ? self::PARTY_SUPPLIER : self::PARTY_BUYER;
    }

    private function resolveTenantId(): ?int
    {
        if (! function_exists('creatorId')) {
            return null;
        }

        try {
            return (int) creatorId();
        } catch (\Throwable) {
            return null;
        }
    }
}
