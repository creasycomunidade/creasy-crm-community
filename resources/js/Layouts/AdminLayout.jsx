import { Link, usePage } from '@inertiajs/react';
import { useState, useMemo } from 'react';
import { useTranslation } from 'react-i18next';
import { Toaster } from 'sonner';
import Sidebar from '@/Components/Sidebar';
import Topbar from '@/Components/Topbar';
import CommandPalette from '@/Components/CommandPalette';
import {
    LayoutDashboard,
    Users,
    Package,
    CreditCard,
    Globe,
    Banknote,
    Settings,
    FileText,
    ShieldCheck,
    UserCog,
    Tag,
    Receipt,
    Percent,
    Server,
    LifeBuoy,
    Plug,
    Radio,
    Brain,
    Clock,
    LogOut,
} from 'lucide-react';

/** Nav item: { labelKey, route, href, icon, permission } - show only if user has permission (or no permission required). Order follows typical admin usage frequency. */
const ADMIN_NAV_ITEMS = [
    { labelKey: 'Licença CREASY', route: 'admin.creasy-license', href: () => route('admin.creasy-license'), icon: ShieldCheck, permission: 'manage_settings' },
    { labelKey: 'admin.dashboard', route: 'admin.dashboard', href: () => route('admin.dashboard'), icon: LayoutDashboard },
    { labelKey: 'admin.client_management', route: 'admin.clients.index', href: () => route('admin.clients.index'), icon: Users, permission: 'view_clients' },
    { labelKey: 'admin.nav.support', route: 'admin.support.index', href: () => route('admin.support.index'), icon: LifeBuoy, permission: 'view_settings' },
    { labelKey: 'admin.email', route: 'admin.email-system.index', href: () => route('admin.email-system.index'), icon: FileText, permission: 'view_email_settings' },
    { labelKey: 'admin.nav.currencies', route: 'admin.currencies.index', href: () => route('admin.currencies.index'), icon: Banknote, permission: 'view_currencies' },
    { labelKey: 'admin.languages', route: 'admin.locales.index', href: () => route('admin.locales.index'), icon: Globe, permission: 'view_languages' },
    { labelKey: 'admin.roles_permissions', route: 'admin.roles-permissions.index', href: () => route('admin.roles-permissions.index'), icon: ShieldCheck, permission: 'view_admin_roles', permissionAlt: 'manage_admin_roles' },
    { labelKey: 'admin.nav.admins', route: 'admin.admins.index', href: () => route('admin.admins.index'), icon: UserCog, permission: 'view_admins' },
    { labelKey: 'admin.landing_page', route: 'admin.landing-page.index', href: () => route('admin.landing-page.index'), icon: FileText, permission: 'view_settings' },
    { labelKey: 'admin.cms_pages', route: 'admin.cms-pages.index', href: () => route('admin.cms-pages.index'), icon: FileText, permission: 'view_settings' },
    { labelKey: 'admin.nav.queue', route: 'admin.queue.index', href: () => route('admin.queue.index'), icon: Server, permission: 'view_settings' },
    { labelKey: 'admin.cron_setup', route: 'admin.cron-setup.index', href: () => route('admin.cron-setup.index'), icon: Clock, permission: 'view_settings' },
    { labelKey: 'admin.pusher_settings', route: 'admin.pusher-settings.index', href: () => route('admin.pusher-settings.index'), icon: Radio, permission: 'manage_settings' },
    { labelKey: 'admin.nav.settings', route: 'admin.settings.index', href: () => route('admin.settings.index'), icon: Settings, permission: 'view_settings' },
    { labelKey: 'admin.audit_log', route: 'admin.audit-log.index', href: () => route('admin.audit-log.index'), icon: FileText, permission: 'view_settings' },
    { labelKey: 'admin.nav.integrations', route: 'admin.integrations.index', href: () => route('admin.integrations.index'), icon: Plug, permission: 'manage_integrations' },
    { labelKey: 'admin.nav.ai', route: 'admin.ai.index', href: () => route('admin.ai.index'), icon: Brain, permission: 'view_settings' },
];

/** Dedupe and order: Dashboard first, then a single entry per route (first match wins). */
function useAdminNav() {
    const { t } = useTranslation();
    const { auth } = usePage().props;
    const permissions = auth?.permissions ?? [];
    const hasPermission = (key) => permissions.includes(key);

    return useMemo(() => {
        return ADMIN_NAV_ITEMS.filter((item) => {
            // Optional modules may not be installed on this server. Resolve only
            // registered routes so a missing menu entry cannot crash the panel.
            if (!route().has(item.route)) return false;
            if (/^admin\.(creasy-license|plans|subscriptions|payments|payment-gateways|coupons|tax-rates|ai|landing-page)/.test(item.route)) return false;
            const perm = item.permission;
            const alt = item.permissionAlt;
            if (perm && !hasPermission(perm) && (!alt || !hasPermission(alt))) return false;
            return true;
        }).map((item) => ({
            label: t(item.labelKey),
            route: item.route,
            href: typeof item.href === 'function' ? item.href() : item.href,
            icon: item.icon ? <item.icon className="h-5 w-5" /> : null,
        }));
    }, [t, permissions]);
}

function AdminLayoutFooter() {
    const { t } = useTranslation();
    const { auth, app_version: appVersion } = usePage().props;
    const adminUser = auth?.adminUser;
    const initials = (() => {
        if (adminUser?.name) return adminUser.name.split(' ').filter(Boolean).map((n) => n[0]).join('').slice(0, 2).toUpperCase();
        if (adminUser?.email) return adminUser.email[0].toUpperCase();
        return 'A';
    })();
    return (
        <div className="border-t border-neutral-200 dark:border-neutral-800 pt-3 space-y-1">
            {adminUser && (
                <div className="flex items-center gap-3 px-3 py-2 rounded-soft">
                    <div className="flex-shrink-0 h-8 w-8 rounded-full bg-primary-100 dark:bg-primary-900/40 flex items-center justify-center text-xs font-semibold text-primary-700 dark:text-primary-300">
                        {initials}
                    </div>
                    <div className="min-w-0 flex-1">
                        {adminUser.name && (
                            <p className="text-xs font-medium text-neutral-700 dark:text-neutral-200 truncate">{adminUser.name}</p>
                        )}
                        <p className="text-xs text-neutral-400 dark:text-neutral-500 truncate" title={adminUser.email}>
                            {adminUser.email}
                        </p>
                    </div>
                </div>
            )}
            <Link
                href={route('admin.logout')}
                method="post"
                as="button"
                className="flex items-center gap-2 w-full text-left rtl:text-right rounded-soft px-3 py-2 text-sm text-neutral-500 hover:bg-red-50 hover:text-red-600 dark:text-neutral-400 dark:hover:bg-red-950/40 dark:hover:text-red-400 transition duration-150"
            >
                <LogOut className="h-4 w-4 flex-shrink-0" />
                {t('nav.logout')}
            </Link>
            {appVersion && (
                <p className="px-3 pt-1 text-center text-[11px] text-neutral-400 dark:text-neutral-600">
                    v{appVersion}
                </p>
            )}
        </div>
    );
}

export default function AdminLayout({ title = 'Admin', header, children }) {
    const { t } = useTranslation();
    const [sidebarOpen, setSidebarOpen] = useState(false);
    const adminNav = useAdminNav();
    const { demo_mode: demoMode, branding } = usePage().props;
    const logoUrl = branding?.logo_url;

    return (
        <div className="min-h-dvh min-w-0 max-w-full overflow-x-clip bg-surface dark:bg-[#10091d]">
            <Sidebar
                open={sidebarOpen}
                onClose={() => setSidebarOpen(false)}
                title={t('nav.admin')}
                logo={logoUrl ? <img src={logoUrl} alt="Logo" className="h-8 max-w-[160px] object-contain" /> : null}
                showCreateButton={false}
                navItems={adminNav.map((item, i) => ({
                    ...item,
                    key: `${item.route}-${item.label}-${i}`,
                    active: () => route().current(item.route),
                }))}
                footer={<AdminLayoutFooter />}
            />

            <div className="min-w-0 max-w-full lg:pl-64 rtl:lg:pl-0 rtl:lg:pr-64">
                <Topbar
                    showLogo={false}
                    title={title}
                    showWorkspace={false}
                    showLocale={false}
                    showCurrency={false}
                    showAccount={false}
                />

                <main className={`min-w-0 max-w-full overflow-x-clip p-4 sm:p-6 lg:p-8 ${demoMode ? 'pb-16' : ''}`}>
                    {header && typeof header === 'object' && (
                        <div className="mb-6">
                            {header}
                        </div>
                    )}
                    {children}
                </main>
            </div>

            <button
                type="button"
                onClick={() => setSidebarOpen(true)}
                className="fixed bottom-4 right-4 z-30 flex h-12 w-12 items-center justify-center rounded-soft-lg bg-white dark:bg-neutral-900 border border-soft border-gray-200 dark:border-neutral-800 shadow-soft-lg text-neutral-600 hover:bg-neutral-50 dark:text-neutral-400 dark:hover:bg-neutral-800 lg:hidden rtl:right-auto rtl:left-4"
                aria-label={t('open_menu')}
            >
                <svg className="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 6h16M4 12h16M4 18h16" />
                </svg>
            </button>

            <CommandPalette searchRoute={route('admin.search')} />
            <Toaster richColors position="top-right" />
            {/* Demo notice — pinned to the bottom of the viewport */}
            {demoMode && (
                <div className="fixed inset-x-0 bottom-0 z-20 flex items-center justify-center gap-2 bg-amber-500/90 text-amber-950 px-4 py-2 text-sm font-medium pointer-events-none">
                    <span>{t('demo.banner') || 'Demo mode: changes are disabled.'}</span>
                </div>
            )}
        </div>
    );
}
