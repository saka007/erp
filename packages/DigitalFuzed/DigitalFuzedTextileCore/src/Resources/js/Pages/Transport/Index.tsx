import { Head, router, useForm, usePage } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { Fuel, Truck, Wrench, Plus, IndianRupee } from 'lucide-react';
import AuthenticatedLayout from '@/layouts/authenticated-layout';
import { Button } from '@/components/ui/button';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import NoRecordsFound from '@/components/no-records-found';
import { TextileField as Field } from '@/components/textile/textile-field';
import { TextileFormCard } from '@/components/textile/textile-form-card';
import { TextileSelectField as SelectField } from '@/components/textile/textile-select-field';
import { TextileDataTableSection } from '@/components/textile/textile-data-table-section';
import { TextileKpiOverview } from '@/components/textile/textile-kpi-overview';
import { formatTextileOptionLabel } from '@/components/textile/textile-form-options';
import { PageProps } from '@/types';

interface FuelEntry {
    id: number;
    entry_code?: string | null;
    fuel_date: string;
    vehicle_name?: string | null;
    driver_name?: string | null;
    route_name?: string | null;
    fuel_quantity_liters: string;
    fuel_rate_per_liter: string;
    fuel_total_cost: string;
    odometer_km?: string | null;
    fuel_type?: string | null;
    notes?: string | null;
}

interface FreightCost {
    id: number;
    cost_code?: string | null;
    freight_date: string;
    vehicle_name?: string | null;
    driver_name?: string | null;
    route_name?: string | null;
    transport_vendor_name?: string | null;
    freight_type?: string | null;
    amount: string;
    notes?: string | null;
}

interface VehicleMaintenance {
    id: number;
    maintenance_code?: string | null;
    maintenance_date: string;
    next_due_date?: string | null;
    vehicle_name?: string | null;
    maintenance_type?: string | null;
    description?: string | null;
    cost: string;
    service_provider?: string | null;
    notes?: string | null;
}

interface EntityOption {
    id: number;
    label: string;
}

const TRANSPORT_SECTIONS = ['fuel', 'freight-cost', 'vehicle-maintenance'] as const;
type TransportSection = typeof TRANSPORT_SECTIONS[number];

function formatLabel(value?: string | null) {
    return value ? formatTextileOptionLabel(value) : '-';
}

function formatDate(value?: string | null) {
    return value ? value.slice(0, 10) : '-';
}

export default function Index({
    fuelEntries,
    freightCosts,
    vehicleMaintenances,
    vehicleOptions,
    driverOptions,
    routeOptions,
    transportVendorOptions,
    fuelTypeOptions,
    freightTypeOptions,
    maintenanceTypeOptions,
}: {
    fuelEntries: FuelEntry[];
    freightCosts: FreightCost[];
    vehicleMaintenances: VehicleMaintenance[];
    vehicleOptions: EntityOption[];
    driverOptions: EntityOption[];
    routeOptions: EntityOption[];
    transportVendorOptions: EntityOption[];
    fuelTypeOptions: string[];
    freightTypeOptions: string[];
    maintenanceTypeOptions: string[];
}) {
    const { t } = useTranslation();
    const { auth } = usePage<PageProps>().props;
    const textileCapabilities = auth.user?.textile_capabilities || {};
    const hasFineGrainedCapabilities = Object.keys(textileCapabilities).some((key) => key.startsWith('transport_'));
    const canTransport = !hasFineGrainedCapabilities || textileCapabilities.transport_operations;

    const sectionParam = new URLSearchParams(window.location.search).get('section');
    const visibleSections: TransportSection[] = canTransport ? [...TRANSPORT_SECTIONS] : [];
    const activeSection = sectionParam && visibleSections.includes(sectionParam as TransportSection)
        ? sectionParam as TransportSection
        : (visibleSections[0] ?? 'fuel');

    const resolvedVehicleOptions = vehicleOptions.map((value) => ({ value: String(value.id), label: value.label }));
    const resolvedDriverOptions = driverOptions.map((value) => ({ value: String(value.id), label: value.label }));
    const resolvedRouteOptions = routeOptions.map((value) => ({ value: String(value.id), label: value.label }));
    const resolvedTransportVendorOptions = transportVendorOptions.map((value) => ({ value: String(value.id), label: value.label }));
    const resolvedFuelTypeOptions = fuelTypeOptions.map((value) => ({ value, label: formatTextileOptionLabel(value) }));
    const resolvedFreightTypeOptions = freightTypeOptions.map((value) => ({ value, label: formatTextileOptionLabel(value) }));
    const resolvedMaintenanceTypeOptions = maintenanceTypeOptions.map((value) => ({ value, label: formatTextileOptionLabel(value) }));

    const fuelForm = useForm({
        entry_code: '',
        fuel_date: '',
        vehicle_id: '',
        driver_id: '',
        route_id: '',
        fuel_quantity_liters: '',
        fuel_rate_per_liter: '',
        odometer_km: '',
        fuel_type: resolvedFuelTypeOptions[0]?.value ?? 'diesel',
        notes: '',
    });

    const freightForm = useForm({
        cost_code: '',
        freight_date: '',
        vehicle_id: '',
        driver_id: '',
        route_id: '',
        transport_vendor_id: '',
        freight_type: resolvedFreightTypeOptions[0]?.value ?? 'per_trip',
        amount: '',
        notes: '',
    });

    const maintenanceForm = useForm({
        maintenance_code: '',
        maintenance_date: '',
        next_due_date: '',
        vehicle_id: '',
        maintenance_type: resolvedMaintenanceTypeOptions[0]?.value ?? 'general_service',
        description: '',
        cost: '',
        service_provider: '',
        notes: '',
    });

    const totalFuelCost = fuelEntries.reduce((sum, row) => sum + Number(row.fuel_total_cost || 0), 0);
    const totalFreightCost = freightCosts.reduce((sum, row) => sum + Number(row.amount || 0), 0);
    const dueMaintenances = vehicleMaintenances.filter((row) => {
        if (!row.next_due_date) {
            return false;
        }
        return row.next_due_date.slice(0, 10) <= new Date().toISOString().slice(0, 10);
    }).length;

    return (
        <AuthenticatedLayout breadcrumbs={[{ label: t('Textile') }, { label: t('Transport') }]} pageTitle={t('Textile Transport')}>
            <Head title={t('Textile Transport')} />

            <TextileKpiOverview
                title={t('Transport Overview')}
                className="mb-6"
                items={[
                    { label: t('Fuel Entries'), value: fuelEntries.length, hint: t('Fuel logged against vehicles and routes') },
                    { label: t('Total Fuel Cost'), value: totalFuelCost.toFixed(2), hint: t('Sum of fuel entry costs') },
                    { label: t('Freight Costs'), value: freightCosts.length, hint: t('Freight records across trips') },
                    { label: t('Maintenance Due'), value: dueMaintenances, hint: t('Records with next due date reached') },
                ]}
            />

            {canTransport ? (
                <Tabs
                    value={activeSection}
                    onValueChange={(value: string) => router.get(route('textile.transport.index', { section: value }), {}, { preserveState: true, replace: true })}
                    className="space-y-6"
                >
                    <TabsList className="grid w-full grid-cols-3 gap-2 h-auto p-1 md:grid-cols-3">
                        <TabsTrigger value="fuel">{t('Fuel')}</TabsTrigger>
                        <TabsTrigger value="freight-cost">{t('Freight Cost')}</TabsTrigger>
                        <TabsTrigger value="vehicle-maintenance">{t('Vehicle Maintenance')}</TabsTrigger>
                    </TabsList>

                    <TabsContent value="fuel">
                        <div className="grid gap-6 xl:grid-cols-2">
                            <TextileFormCard title={t('Log Fuel Entry')} icon={Fuel}>
                                <form
                                    className="space-y-3"
                                    onSubmit={(event) => {
                                        event.preventDefault();
                                        fuelForm.post(route('textile.transport.fuel-entries.store'), {
                                            onSuccess: () => fuelForm.reset('entry_code', 'fuel_date', 'vehicle_id', 'driver_id', 'route_id', 'fuel_quantity_liters', 'fuel_rate_per_liter', 'odometer_km', 'notes'),
                                        });
                                    }}
                                >
                                    <div className="grid grid-cols-2 gap-3">
                                        <Field label={t('Entry Code')} value={fuelForm.data.entry_code} onChange={(value: string) => fuelForm.setData('entry_code', value)} placeholder={t('Optional code')} />
                                        <Field label={t('Fuel Date')} type="date" value={fuelForm.data.fuel_date} onChange={(value: string) => fuelForm.setData('fuel_date', value)} required />
                                    </div>
                                    <div className="grid grid-cols-2 gap-3">
                                        <SelectField label={t('Vehicle')} value={fuelForm.data.vehicle_id} onChange={(value: string) => fuelForm.setData('vehicle_id', value)} options={resolvedVehicleOptions} includeEmpty emptyLabel={t('Select vehicle')} helperText={t('Managed from Master Setup > Dispatch Setup > Vehicles.')} disabled={resolvedVehicleOptions.length === 0} disabledReason={t('No vehicle record found. Create dispatch vehicles first.')} />
                                        <SelectField label={t('Driver')} value={fuelForm.data.driver_id} onChange={(value: string) => fuelForm.setData('driver_id', value)} options={resolvedDriverOptions} includeEmpty emptyLabel={t('Select driver')} helperText={t('Managed from Master Setup > Dispatch Setup > Drivers.')} disabled={resolvedDriverOptions.length === 0} disabledReason={t('No driver record found. Create dispatch drivers first.')} />
                                    </div>
                                    <SelectField label={t('Route')} value={fuelForm.data.route_id} onChange={(value: string) => fuelForm.setData('route_id', value)} options={resolvedRouteOptions} includeEmpty emptyLabel={t('Select route')} helperText={t('Managed from Master Setup > Dispatch Setup > Routes.')} disabled={resolvedRouteOptions.length === 0} disabledReason={t('No route record found. Create dispatch routes first.')} />
                                    <div className="grid grid-cols-2 gap-3">
                                        <Field label={t('Quantity (Liters)')} type="number" step="0.01" min="0" value={fuelForm.data.fuel_quantity_liters} onChange={(value: string) => fuelForm.setData('fuel_quantity_liters', value)} required />
                                        <Field label={t('Rate per Liter')} type="number" step="0.01" min="0" value={fuelForm.data.fuel_rate_per_liter} onChange={(value: string) => fuelForm.setData('fuel_rate_per_liter', value)} required />
                                    </div>
                                    <div className="grid grid-cols-2 gap-3">
                                        <SelectField label={t('Fuel Type')} value={fuelForm.data.fuel_type} onChange={(value: string) => fuelForm.setData('fuel_type', value)} options={resolvedFuelTypeOptions} includeEmpty emptyLabel={t('Select fuel type')} helperText={t('Managed from Master Setup > Dispatch & Transport > Fuel Types.')} />
                                        <Field label={t('Odometer (km)')} type="number" step="0.01" min="0" value={fuelForm.data.odometer_km} onChange={(value: string) => fuelForm.setData('odometer_km', value)} />
                                    </div>
                                    <Field label={t('Notes')} value={fuelForm.data.notes} onChange={(value: string) => fuelForm.setData('notes', value)} />
                                    <Button type="submit" disabled={fuelForm.processing} className="w-full"><Plus className="mr-2 h-4 w-4" />{t('Save Fuel Entry')}</Button>
                                </form>
                            </TextileFormCard>

                            <TextileDataTableSection
                                title={t('Fuel Records')}
                                data={fuelEntries}
                                columns={[
                                    { key: 'entry_code', header: t('Code'), render: (_value: unknown, row: FuelEntry) => row.entry_code || '-' },
                                    { key: 'fuel_date', header: t('Date'), render: (_value: unknown, row: FuelEntry) => formatDate(row.fuel_date) },
                                    { key: 'vehicle_name', header: t('Vehicle'), render: (_value: unknown, row: FuelEntry) => row.vehicle_name || '-' },
                                    { key: 'driver_name', header: t('Driver'), render: (_value: unknown, row: FuelEntry) => row.driver_name || '-' },
                                    { key: 'route_name', header: t('Route'), render: (_value: unknown, row: FuelEntry) => row.route_name || '-' },
                                    { key: 'fuel_type', header: t('Type'), render: (_value: unknown, row: FuelEntry) => formatLabel(row.fuel_type) },
                                    { key: 'fuel_quantity_liters', header: t('Liters'), render: (_value: unknown, row: FuelEntry) => row.fuel_quantity_liters },
                                    { key: 'fuel_rate_per_liter', header: t('Rate'), render: (_value: unknown, row: FuelEntry) => row.fuel_rate_per_liter },
                                    { key: 'fuel_total_cost', header: t('Total Cost'), render: (_value: unknown, row: FuelEntry) => row.fuel_total_cost },
                                ]}
                                emptyState={<NoRecordsFound icon={Fuel} title={t('No fuel entries found')} description={t('Log fuel entries against vehicles, drivers and routes.')} />}
                            />
                        </div>
                    </TabsContent>

                    <TabsContent value="freight-cost">
                        <div className="grid gap-6 xl:grid-cols-2">
                            <TextileFormCard title={t('Record Freight Cost')} icon={IndianRupee}>
                                <form
                                    className="space-y-3"
                                    onSubmit={(event) => {
                                        event.preventDefault();
                                        freightForm.post(route('textile.transport.freight-costs.store'), {
                                            onSuccess: () => freightForm.reset('cost_code', 'freight_date', 'vehicle_id', 'driver_id', 'route_id', 'transport_vendor_id', 'amount', 'notes'),
                                        });
                                    }}
                                >
                                    <div className="grid grid-cols-2 gap-3">
                                        <Field label={t('Cost Code')} value={freightForm.data.cost_code} onChange={(value: string) => freightForm.setData('cost_code', value)} placeholder={t('Optional code')} />
                                        <Field label={t('Freight Date')} type="date" value={freightForm.data.freight_date} onChange={(value: string) => freightForm.setData('freight_date', value)} required />
                                    </div>
                                    <div className="grid grid-cols-2 gap-3">
                                        <SelectField label={t('Vehicle')} value={freightForm.data.vehicle_id} onChange={(value: string) => freightForm.setData('vehicle_id', value)} options={resolvedVehicleOptions} includeEmpty emptyLabel={t('Select vehicle')} helperText={t('Managed from Master Setup > Dispatch Setup > Vehicles.')} disabled={resolvedVehicleOptions.length === 0} disabledReason={t('No vehicle record found. Create dispatch vehicles first.')} />
                                        <SelectField label={t('Driver')} value={freightForm.data.driver_id} onChange={(value: string) => freightForm.setData('driver_id', value)} options={resolvedDriverOptions} includeEmpty emptyLabel={t('Select driver')} helperText={t('Managed from Master Setup > Dispatch Setup > Drivers.')} disabled={resolvedDriverOptions.length === 0} disabledReason={t('No driver record found. Create dispatch drivers first.')} />
                                    </div>
                                    <SelectField label={t('Route')} value={freightForm.data.route_id} onChange={(value: string) => freightForm.setData('route_id', value)} options={resolvedRouteOptions} includeEmpty emptyLabel={t('Select route')} helperText={t('Managed from Master Setup > Dispatch Setup > Routes.')} disabled={resolvedRouteOptions.length === 0} disabledReason={t('No route record found. Create dispatch routes first.')} />
                                    <div className="grid grid-cols-2 gap-3">
                                        <SelectField label={t('Transport Vendor')} value={freightForm.data.transport_vendor_id} onChange={(value: string) => freightForm.setData('transport_vendor_id', value)} options={resolvedTransportVendorOptions} includeEmpty emptyLabel={t('Select transport vendor')} helperText={t('Use Account > Vendors with supplier type Transport Vendor.')} disabled={resolvedTransportVendorOptions.length === 0} disabledReason={t('No transport vendor found. Create one under CRM & Suppliers first.')} />
                                        <SelectField label={t('Freight Type')} value={freightForm.data.freight_type} onChange={(value: string) => freightForm.setData('freight_type', value)} options={resolvedFreightTypeOptions} includeEmpty emptyLabel={t('Select freight type')} helperText={t('Managed from Master Setup > Dispatch & Transport > Freight Types.')} />
                                    </div>
                                    <Field label={t('Amount')} type="number" step="0.01" min="0" value={freightForm.data.amount} onChange={(value: string) => freightForm.setData('amount', value)} required />
                                    <Field label={t('Notes')} value={freightForm.data.notes} onChange={(value: string) => freightForm.setData('notes', value)} />
                                    <Button type="submit" disabled={freightForm.processing} className="w-full"><Plus className="mr-2 h-4 w-4" />{t('Save Freight Cost')}</Button>
                                </form>
                            </TextileFormCard>

                            <TextileDataTableSection
                                title={t('Freight Cost Records')}
                                data={freightCosts}
                                columns={[
                                    { key: 'cost_code', header: t('Code'), render: (_value: unknown, row: FreightCost) => row.cost_code || '-' },
                                    { key: 'freight_date', header: t('Date'), render: (_value: unknown, row: FreightCost) => formatDate(row.freight_date) },
                                    { key: 'vehicle_name', header: t('Vehicle'), render: (_value: unknown, row: FreightCost) => row.vehicle_name || '-' },
                                    { key: 'driver_name', header: t('Driver'), render: (_value: unknown, row: FreightCost) => row.driver_name || '-' },
                                    { key: 'route_name', header: t('Route'), render: (_value: unknown, row: FreightCost) => row.route_name || '-' },
                                    { key: 'transport_vendor_name', header: t('Transport Vendor'), render: (_value: unknown, row: FreightCost) => row.transport_vendor_name || '-' },
                                    { key: 'freight_type', header: t('Type'), render: (_value: unknown, row: FreightCost) => formatLabel(row.freight_type) },
                                    { key: 'amount', header: t('Amount'), render: (_value: unknown, row: FreightCost) => row.amount },
                                ]}
                                emptyState={<NoRecordsFound icon={IndianRupee} title={t('No freight cost records found')} description={t('Record freight costs against trips, routes and transport vendors.')} />}
                            />
                        </div>
                    </TabsContent>

                    <TabsContent value="vehicle-maintenance">
                        <div className="grid gap-6 xl:grid-cols-2">
                            <TextileFormCard title={t('Schedule Vehicle Maintenance')} icon={Wrench}>
                                <form
                                    className="space-y-3"
                                    onSubmit={(event) => {
                                        event.preventDefault();
                                        maintenanceForm.post(route('textile.transport.vehicle-maintenances.store'), {
                                            onSuccess: () => maintenanceForm.reset('maintenance_code', 'maintenance_date', 'next_due_date', 'vehicle_id', 'description', 'cost', 'service_provider', 'notes'),
                                        });
                                    }}
                                >
                                    <div className="grid grid-cols-2 gap-3">
                                        <Field label={t('Maintenance Code')} value={maintenanceForm.data.maintenance_code} onChange={(value: string) => maintenanceForm.setData('maintenance_code', value)} placeholder={t('Optional code')} />
                                        <SelectField label={t('Vehicle')} value={maintenanceForm.data.vehicle_id} onChange={(value: string) => maintenanceForm.setData('vehicle_id', value)} options={resolvedVehicleOptions} includeEmpty emptyLabel={t('Select vehicle')} helperText={t('Managed from Master Setup > Dispatch Setup > Vehicles.')} disabled={resolvedVehicleOptions.length === 0} disabledReason={t('No vehicle record found. Create dispatch vehicles first.')} required />
                                    </div>
                                    <div className="grid grid-cols-2 gap-3">
                                        <Field label={t('Maintenance Date')} type="date" value={maintenanceForm.data.maintenance_date} onChange={(value: string) => maintenanceForm.setData('maintenance_date', value)} required />
                                        <Field label={t('Next Due Date')} type="date" value={maintenanceForm.data.next_due_date} onChange={(value: string) => maintenanceForm.setData('next_due_date', value)} />
                                    </div>
                                    <div className="grid grid-cols-2 gap-3">
                                        <SelectField label={t('Maintenance Type')} value={maintenanceForm.data.maintenance_type} onChange={(value: string) => maintenanceForm.setData('maintenance_type', value)} options={resolvedMaintenanceTypeOptions} includeEmpty emptyLabel={t('Select maintenance type')} helperText={t('Managed from Master Setup > Dispatch & Transport > Transport Maintenance Types.')} />
                                        <Field label={t('Cost')} type="number" step="0.01" min="0" value={maintenanceForm.data.cost} onChange={(value: string) => maintenanceForm.setData('cost', value)} required />
                                    </div>
                                    <Field label={t('Service Provider')} value={maintenanceForm.data.service_provider} onChange={(value: string) => maintenanceForm.setData('service_provider', value)} />
                                    <Field label={t('Description')} value={maintenanceForm.data.description} onChange={(value: string) => maintenanceForm.setData('description', value)} />
                                    <Field label={t('Notes')} value={maintenanceForm.data.notes} onChange={(value: string) => maintenanceForm.setData('notes', value)} />
                                    <Button type="submit" disabled={maintenanceForm.processing} className="w-full"><Plus className="mr-2 h-4 w-4" />{t('Save Maintenance Record')}</Button>
                                </form>
                            </TextileFormCard>

                            <TextileDataTableSection
                                title={t('Maintenance Records')}
                                data={vehicleMaintenances}
                                columns={[
                                    { key: 'maintenance_code', header: t('Code'), render: (_value: unknown, row: VehicleMaintenance) => row.maintenance_code || '-' },
                                    { key: 'maintenance_date', header: t('Date'), render: (_value: unknown, row: VehicleMaintenance) => formatDate(row.maintenance_date) },
                                    { key: 'next_due_date', header: t('Next Due'), render: (_value: unknown, row: VehicleMaintenance) => formatDate(row.next_due_date) },
                                    { key: 'vehicle_name', header: t('Vehicle'), render: (_value: unknown, row: VehicleMaintenance) => row.vehicle_name || '-' },
                                    { key: 'maintenance_type', header: t('Type'), render: (_value: unknown, row: VehicleMaintenance) => formatLabel(row.maintenance_type) },
                                    { key: 'service_provider', header: t('Provider'), render: (_value: unknown, row: VehicleMaintenance) => row.service_provider || '-' },
                                    { key: 'cost', header: t('Cost'), render: (_value: unknown, row: VehicleMaintenance) => row.cost },
                                ]}
                                emptyState={<NoRecordsFound icon={Wrench} title={t('No maintenance records found')} description={t('Schedule maintenance against vehicles to track costs and next due dates.')} />}
                            />
                        </div>
                    </TabsContent>
                </Tabs>
            ) : (
                <NoRecordsFound icon={Truck} title={t('Transport is not enabled')} description={t('Enable Own Transport or Vendor Transport in Textile Operating Model to use transport workflows.')} />
            )}
        </AuthenticatedLayout>
    );
}
