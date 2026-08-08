import { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import { formatTextileLabel } from '@/components/textile/textile-form-options';

export interface TextileWorkflowRow {
    id: number;
    document_number: string;
    party_name?: string | null;
    lot_reference?: string | null;
    quantity: string;
    unit?: string | null;
    status: string;
    purchase_invoice_id?: number | null;
    source_reference_id?: number | null;
    source_action?: string | null;
}

export interface TextileSelectOption {
    value: string;
    label: string;
    group?: string;
    disabled?: boolean;
    disabledReason?: string;
}

interface TextileWorkflowSelectOptionsConfig {
    recentCount?: number;
    recentLabel?: string;
    olderLabel?: string;
}

type TextileActionIcon = (props: { className?: string }) => ReactNode;

export interface TextileWorkflowAction {
    label: string;
    onClick: (row: TextileWorkflowRow) => void;
    icon?: TextileActionIcon;
    when?: (row: TextileWorkflowRow) => boolean;
}

export interface TextileWorkflowActionRule {
    statuses: readonly string[];
    actions: TextileWorkflowAction[];
    noVisibleActionContent?: string | ((row: TextileWorkflowRow) => ReactNode);
}

export const textileActionableStatuses = {
    draft: ['draft'],
    draftOrApproved: ['draft', 'approved'],
    draftApprovedOrReleased: ['draft', 'approved', 'released'],
} as const;

interface TextileWorkflowColumnsOptions {
    includeInvoiceId?: boolean;
    actions?: (row: TextileWorkflowRow) => ReactNode | null;
    noActionLabel?: string;
}

export function createTextileWorkflowActions(rules: TextileWorkflowActionRule[]) {
    return (row: TextileWorkflowRow): ReactNode | null => {
        const matchedRule = rules.find((rule) => rule.statuses.includes(row.status));

        const visibleActions = (matchedRule?.actions || []).filter((action) => (action.when ? action.when(row) : true));

        if (!matchedRule) {
            return null;
        }

        if (visibleActions.length === 0) {
            if (!matchedRule.noVisibleActionContent) {
                return null;
            }

            if (typeof matchedRule.noVisibleActionContent === 'function') {
                return matchedRule.noVisibleActionContent(row);
            }

            return <span className="text-muted-foreground">{matchedRule.noVisibleActionContent}</span>;
        }

        if (visibleActions.length === 1) {
            const action = visibleActions[0];
            const Icon = action.icon;

            return (
                <Button type="button" size="sm" variant="outline" onClick={() => action.onClick(row)}>
                    {Icon ? <Icon className="mr-1 h-3.5 w-3.5" /> : null}
                    {action.label}
                </Button>
            );
        }

        return (
            <div className="flex flex-wrap gap-2">
                {visibleActions.map((action) => {
                    const Icon = action.icon;

                    return (
                        <Button key={action.label} type="button" size="sm" variant="outline" onClick={() => action.onClick(row)}>
                            {Icon ? <Icon className="mr-1 h-3.5 w-3.5" /> : null}
                            {action.label}
                        </Button>
                    );
                })}
            </div>
        );
    };
}

export function createTextileWorkflowColumns(
    t: (key: string) => string,
    { includeInvoiceId = false, actions, noActionLabel = 'No action' }: TextileWorkflowColumnsOptions = {}
) {
    const columns: Array<{ key: string; header: string; render?: (...args: any[]) => any }> = [
        { key: 'id', header: t('ID') },
        { key: 'document_number', header: t('Number') },
        { key: 'party_name', header: t('Party'), render: optional },
        { key: 'lot_reference', header: t('Lot'), render: optional },
        { key: 'quantity', header: t('Qty') },
        { key: 'unit', header: t('Unit'), render: optional },
    ];

    if (includeInvoiceId) {
        columns.push({ key: 'purchase_invoice_id', header: t('Invoice ID'), render: optionalNumber });
    }

    columns.push({ key: 'status', header: t('Status'), render: formatTextileLabel });

    if (actions) {
        columns.push({
            key: 'actions',
            header: t('Actions'),
            render: (_value: unknown, row: TextileWorkflowRow) => actions(row) ?? <span className="text-muted-foreground">{t(noActionLabel)}</span>,
        });
    }

    return columns;
}

export function createTextileWorkflowSelectOptions(
    rows: TextileWorkflowRow[],
    { recentCount = 5, recentLabel = 'Recent', olderLabel = 'Older' }: TextileWorkflowSelectOptionsConfig = {}
): TextileSelectOption[] {
    const orderedRows = [...rows].sort((left, right) => right.id - left.id);
    const shouldGroupByRecency = orderedRows.length > recentCount;

    return orderedRows.map((row, index) => {
        const segments = [row.document_number];

        if (row.party_name) {
            segments.push(row.party_name);
        }

        if (row.lot_reference) {
            segments.push(`Lot ${row.lot_reference}`);
        }

        segments.push(`${row.quantity} ${row.unit || '-'}`);

        return {
            value: String(row.id),
            label: segments.join(' | '),
            group: shouldGroupByRecency ? (index < recentCount ? recentLabel : olderLabel) : undefined,
        };
    });
}

function optional(value: string | null) {
    return value || '-';
}

function optionalNumber(value: number | null) {
    return value ? String(value) : '-';
}
