import { useMemo, useState } from 'react';
import { router } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import { Checkbox } from '@/components/ui/checkbox';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Boxes, ShieldCheck } from 'lucide-react';

interface GovernanceModule {
  module: string;
  name: string;
  is_entitled: boolean;
  requires_approval: boolean;
  is_active: boolean;
}

interface GovernanceRequest {
  id: number;
  tenant_id: number;
  module_key: string;
  status: string;
  request_note?: string | null;
  review_note?: string | null;
  requested_at?: string | null;
  reviewed_at?: string | null;
}

interface GovernanceAudit {
  id: number;
  action: string;
  module_key: string;
  change_reason?: string | null;
  changed_at?: string | null;
}

interface GovernanceData {
  selectedTenantId: number | null;
  companies: Array<{ id: number; name: string; email: string }>;
  modules: GovernanceModule[];
  pendingRequests: GovernanceRequest[];
  recentAudits: GovernanceAudit[];
}

interface ModuleGovernanceSettingsProps {
  auth?: any;
  moduleGovernance?: GovernanceData;
}

export default function ModuleGovernanceSettings({ auth, moduleGovernance }: ModuleGovernanceSettingsProps) {
  const { t } = useTranslation();
  const isSuperadmin = auth?.user?.type === 'superadmin';
  const governance = moduleGovernance || {
    selectedTenantId: null,
    companies: [],
    modules: [],
    pendingRequests: [],
    recentAudits: [],
  };

  const [noteByModule, setNoteByModule] = useState<Record<string, string>>({});
  const [tenantId, setTenantId] = useState<string>(governance.selectedTenantId ? String(governance.selectedTenantId) : '');

  const moduleRows = useMemo(() => governance.modules || [], [governance.modules]);

  const reloadForTenant = (nextTenantId: string) => {
    setTenantId(nextTenantId);
    router.get(route('settings.index', { tenant_id: nextTenantId }), {}, { preserveState: true, replace: true });
  };

  const submitActivation = (moduleKey: string) => {
    router.post(route('settings.module-governance.activate'), {
      module_key: moduleKey,
      request_note: noteByModule[moduleKey] || '',
    }, { preserveScroll: true });
  };

  const submitDeactivation = (moduleKey: string) => {
    router.post(route('settings.module-governance.deactivate'), {
      module_key: moduleKey,
      reason: noteByModule[moduleKey] || '',
    }, { preserveScroll: true });
  };

  const updateEntitlement = (moduleKey: string, isEntitled: boolean, requiresApproval: boolean) => {
    if (!governance.selectedTenantId) return;

    router.post(route('settings.module-governance.entitlement.update'), {
      tenant_id: governance.selectedTenantId,
      module_key: moduleKey,
      is_entitled: isEntitled,
      requires_approval: requiresApproval,
    }, { preserveScroll: true });
  };

  const reviewRequest = (requestId: number, decision: 'approved' | 'rejected') => {
    router.post(route('settings.module-governance.requests.review', requestId), {
      decision,
    }, { preserveScroll: true });
  };

  return (
    <Card>
      <CardHeader className="space-y-3">
        <CardTitle className="flex items-center gap-2 text-lg">
          <Boxes className="h-5 w-5" />
          {t('Module Governance')}
        </CardTitle>
        <p className="text-sm text-muted-foreground">
          {t('Manage module entitlement, activation, approvals, and safe deactivation checks.')}
        </p>
      </CardHeader>
      <CardContent className="space-y-6">
        {isSuperadmin ? (
          <div className="space-y-2">
            <Label>{t('Company')}</Label>
            <Select value={tenantId || undefined} onValueChange={reloadForTenant}>
              <SelectTrigger>
                <SelectValue placeholder={t('Select company')} />
              </SelectTrigger>
              <SelectContent>
                {governance.companies.map((company) => (
                  <SelectItem key={company.id} value={String(company.id)}>
                    {company.name} ({company.email})
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>
        ) : null}

        <div className="rounded-md border overflow-x-auto">
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b bg-muted/40 text-left">
                <th className="px-3 py-2">{t('Module')}</th>
                <th className="px-3 py-2">{t('Entitled')}</th>
                <th className="px-3 py-2">{t('Approval')}</th>
                <th className="px-3 py-2">{t('State')}</th>
                <th className="px-3 py-2">{t('Note')}</th>
                <th className="px-3 py-2">{t('Actions')}</th>
              </tr>
            </thead>
            <tbody>
              {moduleRows.map((item) => (
                <tr key={item.module} className="border-b last:border-b-0 align-top">
                  <td className="px-3 py-2">
                    <div className="font-medium">{item.name}</div>
                    <div className="text-xs text-muted-foreground">{item.module}</div>
                  </td>
                  <td className="px-3 py-2">
                    <Badge variant={item.is_entitled ? 'default' : 'secondary'}>
                      {item.is_entitled ? t('Yes') : t('No')}
                    </Badge>
                  </td>
                  <td className="px-3 py-2">
                    <Badge variant={item.requires_approval ? 'outline' : 'secondary'}>
                      {item.requires_approval ? t('Required') : t('Not Required')}
                    </Badge>
                  </td>
                  <td className="px-3 py-2">
                    <Badge variant={item.is_active ? 'default' : 'secondary'}>
                      {item.is_active ? t('Active') : t('Inactive')}
                    </Badge>
                  </td>
                  <td className="px-3 py-2 min-w-[220px]">
                    <Input
                      value={noteByModule[item.module] || ''}
                      onChange={(event) => setNoteByModule((prev) => ({ ...prev, [item.module]: event.target.value }))}
                      placeholder={t('Optional note')}
                    />
                  </td>
                  <td className="px-3 py-2">
                    <div className="flex flex-wrap gap-2">
                      {isSuperadmin ? (
                        <>
                          <div className="flex items-center gap-2 rounded-md border px-2 py-1">
                            <Checkbox
                              checked={item.is_entitled}
                              onCheckedChange={(checked) => updateEntitlement(item.module, Boolean(checked), item.requires_approval)}
                            />
                            <span className="text-xs">{t('Entitle')}</span>
                          </div>
                          <div className="flex items-center gap-2 rounded-md border px-2 py-1">
                            <Checkbox
                              checked={item.requires_approval}
                              onCheckedChange={(checked) => updateEntitlement(item.module, item.is_entitled, Boolean(checked))}
                            />
                            <span className="text-xs">{t('Require Approval')}</span>
                          </div>
                        </>
                      ) : null}

                      {!item.is_active ? (
                        <Button size="sm" onClick={() => submitActivation(item.module)} disabled={!item.is_entitled}>
                          {item.requires_approval ? t('Request Activation') : t('Activate')}
                        </Button>
                      ) : (
                        <Button size="sm" variant="outline" onClick={() => submitDeactivation(item.module)}>
                          {t('Deactivate')}
                        </Button>
                      )}
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>

        {isSuperadmin ? (
          <div className="space-y-3">
            <h3 className="font-semibold flex items-center gap-2"><ShieldCheck className="h-4 w-4" />{t('Pending Requests')}</h3>
            <div className="rounded-md border overflow-x-auto">
              <table className="w-full text-sm">
                <thead>
                  <tr className="border-b bg-muted/40 text-left">
                    <th className="px-3 py-2">{t('Request')}</th>
                    <th className="px-3 py-2">{t('Module')}</th>
                    <th className="px-3 py-2">{t('Note')}</th>
                    <th className="px-3 py-2">{t('Actions')}</th>
                  </tr>
                </thead>
                <tbody>
                  {governance.pendingRequests.length === 0 ? (
                    <tr>
                      <td className="px-3 py-3 text-muted-foreground" colSpan={4}>{t('No pending requests')}</td>
                    </tr>
                  ) : governance.pendingRequests.map((requestItem) => (
                    <tr key={requestItem.id} className="border-b last:border-b-0">
                      <td className="px-3 py-2">#{requestItem.id}</td>
                      <td className="px-3 py-2">{requestItem.module_key}</td>
                      <td className="px-3 py-2">{requestItem.request_note || '-'}</td>
                      <td className="px-3 py-2">
                        <div className="flex gap-2">
                          <Button size="sm" onClick={() => reviewRequest(requestItem.id, 'approved')}>{t('Approve')}</Button>
                          <Button size="sm" variant="outline" onClick={() => reviewRequest(requestItem.id, 'rejected')}>{t('Reject')}</Button>
                        </div>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>
        ) : null}

        <div className="space-y-3">
          <h3 className="font-semibold">{t('Recent Governance Activity')}</h3>
          <div className="rounded-md border overflow-x-auto">
            <table className="w-full text-sm">
              <thead>
                <tr className="border-b bg-muted/40 text-left">
                  <th className="px-3 py-2">{t('Action')}</th>
                  <th className="px-3 py-2">{t('Module')}</th>
                  <th className="px-3 py-2">{t('Reason')}</th>
                  <th className="px-3 py-2">{t('Time')}</th>
                </tr>
              </thead>
              <tbody>
                {governance.recentAudits.length === 0 ? (
                  <tr>
                    <td className="px-3 py-3 text-muted-foreground" colSpan={4}>{t('No governance events yet')}</td>
                  </tr>
                ) : governance.recentAudits.map((audit) => (
                  <tr key={audit.id} className="border-b last:border-b-0">
                    <td className="px-3 py-2">{audit.action}</td>
                    <td className="px-3 py-2">{audit.module_key}</td>
                    <td className="px-3 py-2">{audit.change_reason || '-'}</td>
                    <td className="px-3 py-2">{audit.changed_at || '-'}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      </CardContent>
    </Card>
  );
}
