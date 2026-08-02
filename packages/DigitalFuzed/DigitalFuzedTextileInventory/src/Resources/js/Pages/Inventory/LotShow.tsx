import { Head } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { Boxes, MoveRight } from 'lucide-react';
import AuthenticatedLayout from '@/layouts/authenticated-layout';
import { Card, CardContent } from '@/components/ui/card';
import { DataTable } from '@/components/ui/data-table';
import NoRecordsFound from '@/components/no-records-found';

interface TextileLot {
    id: number;
    lot_reference: string;
    received_quantity: string;
    available_quantity: string;
    status: string;
}

interface TextileMovement {
    id: number;
    movement_type: string;
    location_from?: string | null;
    location_to?: string | null;
    quantity: string;
    status: string;
}

interface TextileReservation {
    id: number;
    reference_type?: string | null;
    reference_id?: number | null;
    reserved_quantity: string;
    status: string;
}

interface Availability {
    lot_reference: string;
    received_quantity: number;
    available_quantity: number;
    reserved_quantity: number;
}

export default function LotShow({ lot, movements, reservations, availability }: { lot: TextileLot; movements: TextileMovement[]; reservations: TextileReservation[]; availability: Availability }) {
    const { t } = useTranslation();

    return (
        <AuthenticatedLayout breadcrumbs={[{ label: t('Textile') }, { label: t('Inventory') }, { label: lot.lot_reference }]} pageTitle={`${t('Lot')} ${lot.lot_reference}`}>
            <Head title={`${t('Lot')} ${lot.lot_reference}`} />

            <div className="grid gap-6 xl:grid-cols-3">
                <Card>
                    <CardContent className="p-5">
                        <div className="mb-3 flex items-center gap-2">
                            <Boxes className="h-5 w-5 text-violet-600" />
                            <h2 className="font-semibold">{t('Lot Summary')}</h2>
                        </div>
                        <div className="space-y-2 text-sm">
                            <p><strong>{t('Lot Reference')}:</strong> {lot.lot_reference}</p>
                            <p><strong>{t('Status')}:</strong> {lot.status}</p>
                            <p><strong>{t('Received')}:</strong> {availability.received_quantity}</p>
                            <p><strong>{t('Available')}:</strong> {availability.available_quantity}</p>
                            <p><strong>{t('Reserved')}:</strong> {availability.reserved_quantity}</p>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <div className="mt-6 grid gap-6 xl:grid-cols-2">
                <Card>
                    <CardContent className="p-0">
                        <DataTable
                            data={movements}
                            columns={[
                                { key: 'movement_type', header: t('Type') },
                                { key: 'location_from', header: t('From'), render: optional },
                                { key: 'location_to', header: t('To'), render: optional },
                                { key: 'quantity', header: t('Qty') },
                                { key: 'status', header: t('Status') },
                            ]}
                            emptyState={<NoRecordsFound icon={MoveRight} title={t('No movements for this lot')} description={t('Movements for this lot will appear here.')} />}
                        />
                    </CardContent>
                </Card>

                <Card>
                    <CardContent className="p-0">
                        <DataTable
                            data={reservations}
                            columns={[
                                { key: 'reserved_quantity', header: t('Reserved Qty') },
                                { key: 'reference_type', header: t('Reference Type'), render: optional },
                                { key: 'reference_id', header: t('Reference ID'), render: optionalNumber },
                                { key: 'status', header: t('Status') },
                            ]}
                            emptyState={<NoRecordsFound icon={Boxes} title={t('No reservations for this lot')} description={t('Reservations for this lot will appear here.')} />}
                        />
                    </CardContent>
                </Card>
            </div>
        </AuthenticatedLayout>
    );
}

function optional(value: string | null) {
    return value || '-';
}

function optionalNumber(value: number | null) {
    return value ? String(value) : '-';
}
