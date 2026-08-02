import { Head, useForm } from '@inertiajs/react';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';
import { CheckCircle2, Plus, ShieldCheck } from 'lucide-react';
import AuthenticatedLayout from '@/layouts/authenticated-layout';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import NoRecordsFound from '@/components/no-records-found';
import { TextileField as Field } from '@/components/textile/textile-field';
import { TextileFormCard } from '@/components/textile/textile-form-card';
import { TextileSelectField as SelectField } from '@/components/textile/textile-select-field';
import { TextileDataTableCard } from '@/components/textile/textile-data-table-card';

interface ApprovalRule {
    id: number;
    document_type: string | null;
    from_status: string;
    to_status: string;
    min_quantity: string | null;
    max_quantity: string | null;
    required_approvals: number;
    is_active: boolean;
}

interface PendingApproval {
    document_id: number;
    document_type: string;
    document_number: string;
    current_status: string;
    next_status: string;
    required_approvals: number;
    approved_count: number;
}

interface WorkflowDocument {
    id: number;
    document_type: string;
    document_number: string;
    status: string;
}

interface Props {
    rules: ApprovalRule[];
    pendingApprovals: PendingApproval[];
    documents: WorkflowDocument[];
}

export default function Index({ rules, pendingApprovals, documents }: Props) {
    const { t } = useTranslation();

    const ruleForm = useForm({
        document_type: '',
        from_status: 'draft',
        to_status: 'approved',
        min_quantity: '',
        max_quantity: '',
        required_approvals: '1',
    });

    const decisionForm = useForm({
        document_id: '',
        to_status: 'approved',
        decision: 'approved',
        comment: '',
    });

    const documentOptions = useMemo(
        () => documents.map((doc) => ({ value: String(doc.id), label: `${doc.document_number} (${doc.document_type})` })),
        [documents]
    );

    return (
        <AuthenticatedLayout breadcrumbs={[{ label: t('Textile') }, { label: t('Approvals') }]} pageTitle={t('Textile Approvals')}>
            <Head title={t('Textile Approvals')} />

            <div className="grid gap-6 xl:grid-cols-2">
                <TextileFormCard title={t('Create Approval Rule')} icon={ShieldCheck}>
                        <form
                            className="space-y-3"
                            onSubmit={(e) => {
                                e.preventDefault();
                                ruleForm.post(route('textile.approvals.rules.store'), {
                                    onSuccess: () => ruleForm.reset('document_type', 'min_quantity', 'max_quantity'),
                                });
                            }}
                        >
                            <Field label={t('Document Type (optional)')} value={ruleForm.data.document_type} onChange={(v) => ruleForm.setData('document_type', v)} placeholder={t('purchase_requisition')} />
                            <div className="grid grid-cols-2 gap-3">
                                <Field label={t('From Status')} value={ruleForm.data.from_status} onChange={(v) => ruleForm.setData('from_status', v)} required />
                                <Field label={t('To Status')} value={ruleForm.data.to_status} onChange={(v) => ruleForm.setData('to_status', v)} required />
                            </div>
                            <div className="grid grid-cols-2 gap-3">
                                <Field label={t('Min Quantity (optional)')} type="number" value={ruleForm.data.min_quantity} onChange={(v) => ruleForm.setData('min_quantity', v)} />
                                <Field label={t('Max Quantity (optional)')} type="number" value={ruleForm.data.max_quantity} onChange={(v) => ruleForm.setData('max_quantity', v)} />
                            </div>
                            <Field label={t('Required Approvals')} type="number" value={ruleForm.data.required_approvals} onChange={(v) => ruleForm.setData('required_approvals', v)} required />
                            <Button type="submit" disabled={ruleForm.processing} className="w-full">
                                <Plus className="mr-2 h-4 w-4" />
                                {t('Save Rule')}
                            </Button>
                        </form>
                </TextileFormCard>

                <TextileFormCard title={t('Record Approval Decision')} icon={CheckCircle2}>
                        <form
                            className="space-y-3"
                            onSubmit={(e) => {
                                e.preventDefault();
                                decisionForm.post(route('textile.approvals.decisions.store'), {
                                    onSuccess: () => decisionForm.reset('comment'),
                                });
                            }}
                        >
                            <SelectField label={t('Document')} value={decisionForm.data.document_id} onChange={(v) => decisionForm.setData('document_id', v)} options={documentOptions} includeEmpty required />
                            <div className="grid grid-cols-2 gap-3">
                                <Field label={t('Transition To Status')} value={decisionForm.data.to_status} onChange={(v) => decisionForm.setData('to_status', v)} required />
                                <SelectField
                                    label={t('Decision')}
                                    value={decisionForm.data.decision}
                                    onChange={(v) => decisionForm.setData('decision', v)}
                                    options={[
                                        { value: 'approved', label: t('Approved') },
                                        { value: 'rejected', label: t('Rejected') },
                                    ]}
                                    includeEmpty
                                    required
                                />
                            </div>
                            <Field label={t('Comment (optional)')} value={decisionForm.data.comment} onChange={(v) => decisionForm.setData('comment', v)} />
                            <Button type="submit" disabled={decisionForm.processing} className="w-full" variant="outline">
                                <CheckCircle2 className="mr-2 h-4 w-4" />
                                {t('Record Decision')}
                            </Button>
                        </form>
                </TextileFormCard>
            </div>

            <div className="mt-6 grid gap-6 xl:grid-cols-2">
                <TextileDataTableCard
                    data={rules}
                    columns={[
                        { key: 'document_type', header: t('Document Type'), render: (value: string | null) => value || t('Any') },
                        { key: 'from_status', header: t('From') },
                        { key: 'to_status', header: t('To') },
                        { key: 'required_approvals', header: t('Approvals') },
                    ]}
                    emptyState={<NoRecordsFound icon={ShieldCheck} title={t('No approval rules found')} description={t('Create rules to enforce document approvals before transitions.')} />}
                />

                <TextileDataTableCard
                    data={pendingApprovals}
                    columns={[
                        { key: 'document_number', header: t('Document') },
                        { key: 'current_status', header: t('Current') },
                        { key: 'next_status', header: t('Next') },
                        {
                            key: 'approved_count',
                            header: t('Progress'),
                            render: (value: number, row: PendingApproval) => `${value}/${row.required_approvals}`,
                        },
                    ]}
                    emptyState={<NoRecordsFound icon={CheckCircle2} title={t('No pending approvals')} description={t('All matched approval transitions are satisfied.')} />}
                />
            </div>
        </AuthenticatedLayout>
    );
}

