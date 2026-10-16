import { Link, usePage, router } from '@inertiajs/react';
import { useOneSignal } from '@/hooks/useOneSignal';
import { useGlobalMessageNotifications, usePushNotificationSetup } from '@/hooks/useGlobalMessageNotifications';
import { useState, useEffect, useCallback } from 'react';
import { useTranslation } from 'react-i18next';
import { Toaster, toast } from 'sonner';
import Topbar from '@/Components/Topbar';
import Sidebar from '@/Components/Sidebar';
import useClientNav from '@/Layouts/useClientNav';
import { ChannelBrandIcon } from '@/Components/BrandIcons';
import {
    LayoutDashboard,
    CreditCard,
    Package,
    FileText,
    Users,
    Settings,
    Layers,
    Webhook,
    Key,
    BookOpen,
    Image,
    Radio,
    Inbox,
    Bot,
    Database,
    Zap,
    Share2,
    MapPin,
    Tag,
    LifeBuoy,
    ExternalLink,
    Mail,
    MessageSquare,
} from 'lucide-react';

const iconClass = 'h-4 w-4';
const whatsappNavIcon = <ChannelBrandIcon channel="whatsapp" className={iconClass} />;

function safeRoute(name, ...args) {
    try { return route(name, ...args); } catch { return '#'; }
}


function ClientLayoutFooter() {
    const { app_version: appVersion } = usePage().props;
    const v = typeof appVersion === 'string' && appVersion.trim() !== '' ? appVersion.trim() : '1.0.0';
    return (
        <div className="rounded-soft px-3 py-2 text-xs text-neutral-400 dark:text-neutral-500 tabular-nums">
            v{v}
        </div>
    );
}

export default function ClientLayout({ header, children, title, mainClassName, fullscreenContent = false }) {
    const { t } = useTranslation();
    const [sidebarOpen, setSidebarOpen] = useState(false);
    const { auth, impersonation, current_workspace_usage, unreadNotificationsCount, branding, onesignal, currentWorkspace, allowedChannelAccountIds = [] } = usePage().props;
    const logoUrl = branding?.logo_url;
    const [unreadCount, setUnreadCount] = useState(unreadNotificationsCount ?? 0);
    const clientNavGroups = useClientNav();

    // Initialise OneSignal SDK (registers SW, requests push permission)
    useOneSignal({ appId: onesignal?.app_id, enabled: onesignal?.enabled });
    usePushNotificationSetup({ enabled: !!auth?.user });
    useGlobalMessageNotifications({
        workspaceId: currentWorkspace?.id ?? auth?.user?.workspace_id,
        channelAccountIds: allowedChannelAccountIds,
        enabled: !!auth?.user,
    });

    // Sync unread count from server on page changes
    useEffect(() => {
        setUnreadCount(unreadNotificationsCount ?? 0);
    }, [unreadNotificationsCount]);

    // Subscribe to broadcast notifications for this user
    useEffect(() => {
        if (! window.Echo || ! auth?.user?.id) return;

        window.Echo.private(`App.Models.User.${auth.user.id}`)
            .notification((notification) => {
                // New inbound messages are handled by the global workspace monitor,
                // which also provides the shared-hosting polling fallback.
                if (notification.type === 'new_message') return;
                setUnreadCount(prev => prev + 1);
                const msg = notification.snippet ?? notification.name ?? notification.automation ?? notification.error ?? t('ui.notif_new');
                const title = {
                    new_message:          t('ui.notif_new_message'),
                    mention:              t('ui.notif_mention'),
                    conversation_assigned:t('ui.notif_conversation_assigned'),
                    campaign_completed:   t('ui.notif_campaign_completed'),
                    automation_failed:    t('ui.notif_automation_failed'),
                    billing_failed:       t('ui.notif_billing_failed'),
                }[notification.type] ?? t('ui.notif_default');

                toast(title, {
                    description: msg,
                    action: notification.url ? { label: t('common.view'), onClick: () => router.visit(notification.url) } : undefined,
                });

            });

        return () => {
            window.Echo.leave(`App.Models.User.${auth.user.id}`);
        };
    }, [auth?.user?.id]);

    const returnToAdmin = () => {
        router.post(impersonation?.returnUrl ?? route('admin.impersonation.stop'));
    };

    const demoMode = usePage().props.demo_mode === true;

    const userNavItems = [
        { label: t('nav.profile') || 'Profile',           href: safeRoute('client.profile.edit'),    as: 'link' },
        { label: t('nav.twoFactor') || 'Two-Factor Auth', href: safeRoute('client.profile.2fa'),     as: 'link' },
        { label: t('nav.sessions') || 'Sessions',         href: safeRoute('client.profile.sessions'),as: 'link' },
        { type: 'divider' },
        { label: t('nav.logout') || 'Log Out',            href: route('logout'), method: 'post', as: 'link' },
    ];

    return (
        <div className={`${fullscreenContent ? 'flex h-dvh min-w-0 max-w-full flex-col overflow-hidden' : 'min-h-dvh min-w-0 max-w-full overflow-x-clip'} bg-surface dark:bg-[#10091d]`}>
            {impersonation?.active && (
                <div className="flex min-h-14 items-center justify-between gap-3 bg-amber-500/90 text-white px-3 py-2 text-sm font-medium sm:px-4">
                    <span className="min-w-0 flex-1 break-words leading-tight">{t('impersonation.impersonating', { name: impersonation.clientName })}</span>
                    <button
                        type="button"
                        onClick={returnToAdmin}
                        className="min-h-11 shrink-0 rounded-soft bg-white/20 px-3 py-2 text-center font-medium hover:bg-white/30 transition"
                    >
                        {t('impersonation.return_to_admin')}
                    </button>
                </div>
            )}

            <Sidebar
                open={sidebarOpen}
                onClose={() => setSidebarOpen(false)}
                showCreateButton={false}
                navGroups={clientNavGroups.map(group => ({
                    ...group,
                    items: group.items.map(item => ({
                        ...item,
                        key: item.activePattern || item.label,
                        active: () => item.activePattern ? route().current(item.activePattern) : false,
                    }))
                }))}
footer={<ClientLayoutFooter />}
            />

            <div className={`${fullscreenContent ? 'flex min-h-0 min-w-0 flex-1 flex-col overflow-hidden' : 'min-w-0 max-w-full'} lg:pl-64 rtl:lg:pl-0 rtl:lg:pr-64`}>
                <Topbar
                    showLogo={false}
                    title={title}
                    userNavItems={userNavItems}
                    unreadCount={unreadCount}
                    showGlobalSearch
                />

                <main className={`min-w-0 max-w-full ${fullscreenContent ? 'min-h-0 flex-1 overflow-hidden' : 'overflow-x-clip'} ${mainClassName ?? 'p-4 sm:p-6 lg:p-8'} ${demoMode && !fullscreenContent ? 'pb-16' : ''}`}>
                    {header && typeof header === 'object' && (
                        <div className="mb-6">
                            {header}
                        </div>
                    )}
                    {children}
                </main>
            </div>

            {/* Mobile menu button */}
            <button
                type="button"
                onClick={() => setSidebarOpen(true)}
                className="fixed bottom-[calc(env(safe-area-inset-bottom)+6rem)] right-4 z-30 flex h-12 w-12 items-center justify-center rounded-soft-lg bg-white dark:bg-neutral-900 border border-soft border-gray-200 dark:border-neutral-800 shadow-soft-lg text-neutral-600 hover:bg-neutral-50 dark:text-neutral-400 dark:hover:bg-neutral-800 lg:hidden rtl:right-auto rtl:left-4"
                aria-label={t('open_menu')}
            >
                <svg className="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 6h16M4 12h16M4 18h16" />
                </svg>
            </button>

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
