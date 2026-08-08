import { PropsWithChildren, ReactNode, Fragment } from "react";
import {AppSidebar} from "@/components/app-sidebar";
import {SidebarInset, SidebarProvider, SidebarTrigger} from "@/components/ui/sidebar";
import {Separator} from "@/components/ui/separator";
import {
    Breadcrumb,
    BreadcrumbItem,
    BreadcrumbList,
    BreadcrumbPage,
    BreadcrumbLink,
    BreadcrumbSeparator,
} from "@/components/ui/breadcrumb";
import { NavUser } from "@/components/nav-user";
import { usePage, Head, Link, router } from "@inertiajs/react";
import { PageProps } from "@/types";
import { BrandProvider, useBrand } from "@/contexts/brand-context";
import CookieConsent from "@/components/cookie-consent";
import { useFavicon } from "@/hooks/use-favicon";
import { useTranslation } from 'react-i18next';
import { Button } from "@/components/ui/button";
import { UserX, ArrowLeft } from "lucide-react";
import { useFlashMessages } from "@/hooks/useFlashMessages";

function AuthenticatedLayoutContent({
    header,
    children,
    breadcrumbs,
    pageTitle,
    pageActions,
    backUrl,
    className,
    ...props
}: PropsWithChildren<{
    header?: ReactNode;
    breadcrumbs?: Array<{label: string, url?: string}>;
    pageTitle?: string;
    pageActions?: ReactNode;
    backUrl?: string;
    className?: string;
}>) {
    const { t } = useTranslation();
    const { auth, companyAllSetting, adminAllSetting, branchContext } = usePage<PageProps>().props as any;
    const { settings } = useBrand();
    const isTextileCompany = auth?.user?.type === 'company' && (
        auth?.user?.industry_type === 'textile' ||
        (auth?.user?.activatedPackages || []).some((module: string) => module.toLowerCase().includes('textile'))
    );
    const dashboardLabel = isTextileCompany ? t('Textile Dashboard') : t('Dashboard');
    const dashboardRoute = isTextileCompany ? route('textile.dashboard.index') : route('dashboard');
    const branchOptions = branchContext?.branches || [];
    const canManageAllBranches = Boolean(branchContext?.can_manage_all_branches);
    const activeBranchId = branchContext?.active_branch_id;
    const isSuperAdmin = auth?.user?.type === 'superadmin';
    useFavicon();
    useFlashMessages();

    const updateBranchContext = (value: string) => {
        router.post(
            route('branch-context.update'),
            { branch_id: value === '' ? null : Number(value) },
            { preserveScroll: true, preserveState: true }
        );
    };

    return (
        <>
        <Head>
            {companyAllSetting?.metaKeywords && (
                <meta name="keywords" content={companyAllSetting.metaKeywords} />
            )}
            {companyAllSetting?.metaDescription && (
                <meta name="description" content={companyAllSetting.metaDescription} />
            )}
            {companyAllSetting?.metaImage && (
                <meta property="og:image" content={companyAllSetting.metaImage} />
            )}
        </Head>
        <div
            className={settings.layoutDirection === 'rtl' ? 'rtl' : 'ltr'}
            data-theme={settings.themeMode}
            dir={settings.layoutDirection === 'rtl' ? 'rtl' : 'ltr'}
            style={{ direction: settings.layoutDirection === 'rtl' ? 'rtl' : 'ltr' }}
        >
        <SidebarProvider defaultOpen={true}>
            <AppSidebar />

            <SidebarInset className="overflow-visible"
                style={{ direction: settings.layoutDirection === 'rtl' ? 'rtl' : 'ltr' }}
                dir={settings.layoutDirection === 'rtl' ? 'rtl' : 'ltr'}
            >
                <header
                    className={`bg-background flex h-12 shrink-0 items-center gap-2 px-4 py-1 border-b mb-2 justify-between`}
                    >
                    {/* Sidebar + Breadcrumb */}
                    <div className={`flex items-center gap-2 ${ settings.layoutDirection === "rtl" ? "order-2 flex-row-reverse" : "order-1" }`} >
                        {/* SidebarTrigger */}
                        <SidebarTrigger className={`-ml-1 ${ settings.layoutDirection === "rtl" ? "order-3" : "order-1" }`} />

                        {/* Separator */}
                        <Separator orientation="vertical" className="mr-2 h-4 order-2" />

                        {/* Breadcrumb */}
                        <Breadcrumb className={`${ settings.layoutDirection === "rtl" ? "order-1" : "order-3" }`} >
                            <BreadcrumbList className={`flex ${ settings.layoutDirection === "rtl" ? "justify-end" : "justify-start" }`} >
                            <BreadcrumbItem>
                                <BreadcrumbLink asChild>
                                    <Link href={dashboardRoute}>{dashboardLabel}</Link>
                                </BreadcrumbLink>
                            </BreadcrumbItem>
                            {breadcrumbs?.map((crumb, index) => (
                                <Fragment key={index}>
                                <BreadcrumbSeparator className={settings.layoutDirection === 'rtl' ? 'rotate-180' : ''} />
                                <BreadcrumbItem>
                                    {crumb.url ? (
                                    <BreadcrumbLink asChild>
                                        <Link href={crumb.url}>{crumb.label}</Link>
                                    </BreadcrumbLink>
                                    ) : (
                                    <BreadcrumbPage>{crumb.label}</BreadcrumbPage>
                                    )}
                                </BreadcrumbItem>
                                </Fragment>
                            ))}
                            </BreadcrumbList>
                        </Breadcrumb>
                    </div>

                    {/* NavUser */}
                    <div
                        className={`flex items-center gap-2 ${
                        settings.layoutDirection === "rtl" ? "order-1 flex-row-reverse" : "order-2"
                        }`}
                    >
                        {canManageAllBranches && (
                            <select
                                className="h-8 rounded-md border border-input bg-background px-2 text-sm"
                                value={activeBranchId ?? branchOptions[0]?.id ?? ''}
                                onChange={(event) => updateBranchContext(event.target.value)}
                                aria-label={t('Active Branch')}
                                disabled={branchOptions.length === 0}
                            >
                                {isSuperAdmin ? (
                                    <option value="">{t('All Branches')}</option>
                                ) : null}
                                {branchOptions.length === 0 ? (
                                    <option value="" disabled>
                                        {t('No branches available')}
                                    </option>
                                ) : null}
                                {branchOptions.map((branch: { id: number; name: string }) => (
                                    <option key={branch.id} value={branch.id}>
                                        {branch.name}
                                    </option>
                                ))}
                            </select>
                        )}

                        {/* Leave Impersonation Button */}
                        {auth.impersonating && (
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() => router.post(route('users.leave-impersonation'))}
                                className="text-orange-600 border-orange-600 hover:bg-orange-50"
                            >
                                <UserX className="h-4 w-4 mr-2" />
                                {t('Leave Login As User')}
                            </Button>
                        )}
                        <NavUser user={auth.user} inHeader={true} />
                    </div>
                </header>

                <main className="p-4 md:pt-0 h-full">
                    {pageTitle && (
                        <div className="flex items-center mb-4 gap-3" dir={settings.layoutDirection}>
                            <h1 className="text-xl font-semibold flex-1">{pageTitle}</h1>
                            <div className="flex items-center gap-2 flex-shrink-0">
                                {backUrl && (
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        className="flex items-center gap-2 h-8 px-3"
                                        onClick={() => router.visit(backUrl)}
                                    >
                                        <ArrowLeft className="h-4 w-4" />
                                        {t('Back')}
                                    </Button>
                                )}
                                {pageActions}
                            </div>
                        </div>
                    )}
                    {children}
                </main>
            </SidebarInset>
        </SidebarProvider>
        <CookieConsent settings={adminAllSetting || {}} />
        </div>
        </>
    );
}

export default function AuthenticatedLayout({
    children,
    header,
    breadcrumbs,
    pageTitle,
    pageActions,
    backUrl,
    className,
    ...props
}: PropsWithChildren<{
    header?: ReactNode;
    breadcrumbs?: Array<{label: string, url?: string}>;
    pageTitle?: string;
    pageActions?: ReactNode;
    backUrl?: string;
    className?: string;
}>) {
    return (
        <BrandProvider>
            <AuthenticatedLayoutContent
                header={header}
                breadcrumbs={breadcrumbs}
                pageTitle={pageTitle}
                pageActions={pageActions}
                backUrl={backUrl}
                className={className}
                {...props}
            >
                {children}
            </AuthenticatedLayoutContent>
        </BrandProvider>
    );
}
