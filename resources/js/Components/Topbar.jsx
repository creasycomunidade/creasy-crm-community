import { Link, router, usePage } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { useState, useEffect, useRef } from 'react';
import ApplicationLogo from '@/Components/ApplicationLogo';
import { Dropdown } from '@/Components/ui';
import { useTheme } from '@/context/ThemeContext';
import { useLocale } from '@/hooks/useLocale';
import { Bell, X, CheckCheck, ExternalLink, Columns3, ListTodo, BellOff } from 'lucide-react';
import GlobalSearch from '@/Components/GlobalSearch';
import axios from 'axios';
import { clearSiteNotifications } from '@/Utils/browserNotifications';
import { toast } from 'sonner';

/**
 * Topbar with language switcher and account menu.
 * Locale from Inertia shared props; updates via PUT /locale.
 */

export default function Topbar({
    showLogo = true,
    logoHref = route('home'),
    userNavItems = [],
    title,
    showWorkspace = true,
    showGlobalSearch = false,
    unreadCount: externalUnreadCount,
}) {
    const { t } = useTranslation();
    const page = usePage();
    const user = page.props.auth?.user;
    const planModules = page.props.planModules ?? {};
    const crmEnabled = planModules.crm !== false;
    const { theme, setTheme } = useTheme();
    const [notifOpen, setNotifOpen] = useState(false);
    const [recentNotifs, setRecentNotifs] = useState([]);
    const notifRef = useRef(null);
    const unreadCount = externalUnreadCount ?? (page.props.unreadNotificationsCount ?? 0);

    const { locale: currentLocale, isRtl: currentIsRtl, locales: i18nLocales, setLocale: setLocaleCode } = useLocale();
    const localeEntries = i18nLocales.length
        ? i18nLocales.map((l) => [l.code, l.native_name || l.name])
        : Object.entries(page.props.supportedLocales ?? { en: 'English' });

    const handleThemeToggle = () => {
        const next = theme === 'dark' ? 'light' : 'dark';
        setTheme(next);
        if (user) {
            router.post(route('theme.update'), { theme: next }, { preserveScroll: true });
        }
    };

    const handleLocale = (code) => {
        setLocaleCode(code);
    };

    const openNotifDropdown = () => {
        setNotifOpen(true);
        axios.get(route('client.notifications.recent')).then(r => {
            setRecentNotifs(r.data ?? []);
        }).catch(() => {});
    };

    const markAllRead = () => {
        clearSiteNotifications();
        router.post(route('client.notifications.read-all'), {}, { preserveScroll: true, onSuccess: () => setNotifOpen(false) });
    };

    const clearBrowserAlerts = async () => {
        toast.dismiss();
        await clearSiteNotifications();
        setNotifOpen(false);
    };

    // Close on outside click
    useEffect(() => {
        const handler = (e) => {
            if (notifRef.current && !notifRef.current.contains(e.target)) {
                setNotifOpen(false);
            }
        };
        document.addEventListener('mousedown', handler);
        return () => document.removeEventListener('mousedown', handler);
    }, []);

    // On admin routes the authenticated user lives on the `admin` guard, so logout
    // must hit admin.logout (route('logout') only clears the web guard, leaving the
    // admin still signed in). UI is identical — only the logout target differs.
    const isAdmin = !!page.props.auth?.adminUser;
    const defaultUserItems = [
        ...(user
            ? [
                { label: t('nav.profile'), href: route('client.profile.edit'), as: 'link' },
                { type: 'divider' },
                { label: t('nav.logout'), href: isAdmin ? route('admin.logout') : route('logout'), method: 'post', as: 'link' },
            ]
            : [
                { label: t('nav.login'), href: route('login'), as: 'link' },
                { label: t('nav.register'), href: route('register'), as: 'link' },
            ]),
    ];

    const accountItems = userNavItems.length ? userNavItems : defaultUserItems;

    const currentWorkspace = page.props.currentWorkspace;
    const workspaces = page.props.workspaces ?? [];
    const onboardingSummary = page.props.onboardingSummary;
    const showOnboardingProgress =
        user &&
        !page.props.auth?.adminUser &&
        onboardingSummary &&
        !onboardingSummary.is_complete;

    const handleWorkspaceSwitch = (workspaceId) => {
        router.post(route('client.workspaces.switch'), { workspace_id: workspaceId }, { preserveScroll: true });
    };

    return (
        <header className="topbar sticky top-0 z-30 flex h-14 min-w-0 max-w-full items-center justify-between border-b border-brand-100/80 bg-white/80 px-2 shadow-soft backdrop-blur-xl supports-[backdrop-filter]:bg-white/75 dark:border-brand-900/35 dark:bg-[#160b2d]/80 dark:shadow-none dark:supports-[backdrop-filter]:bg-[#160b2d]/75 sm:px-4">
            <div className="flex min-w-0 flex-1 items-center gap-2 sm:gap-3">
                {showLogo && (
                    <Link href={logoHref} className="shrink-0">
                        <ApplicationLogo className="h-8 w-auto fill-current text-gray-900 dark:text-neutral-100" />
                    </Link>
                )}
                {title && (
                    <h1 className="truncate text-sm font-semibold text-gray-900 dark:text-neutral-100">{title}</h1>
                )}
                {showGlobalSearch && (
                    <div className="hidden min-w-0 flex-1 justify-end sm:flex">
                        <GlobalSearch />
                    </div>
                )}
                {showOnboardingProgress && (
                    <Link
                        href={route('client.onboarding.show')}
                        className="hidden min-w-0 max-w-[200px] shrink-0 flex-col justify-center gap-1 rounded-soft border border-neutral-200 bg-neutral-50/80 px-3 py-1.5 dark:border-neutral-700 dark:bg-neutral-800/50 md:flex"
                        title={t('ui.getting_started')}
                    >
                        <div className="flex items-center justify-between gap-2 text-[11px] font-medium text-neutral-600 dark:text-neutral-400">
                            <span className="truncate tabular-nums">
                                {t('ui.onboarding_progress', { done: onboardingSummary.done, total: onboardingSummary.total })}
                            </span>
                            <span className="shrink-0 tabular-nums text-brand-600 dark:text-brand-400">
                                {onboardingSummary.percent}%
                            </span>
                        </div>
                        <div className="h-1.5 overflow-hidden rounded-full bg-neutral-200 dark:bg-neutral-700">
                            <div
                                className="h-full rounded-full bg-brand-500 transition-all duration-500 dark:bg-brand-400"
                                style={{ width: `${onboardingSummary.percent}%` }}
                            />
                        </div>
                    </Link>
                )}
            </div>

            <div className="flex min-w-0 shrink-0 items-center gap-0 sm:gap-1">
                {/* Daily-work shortcuts live in the top bar so they remain available
                    without keeping operational links in the sidebar root. */}
                {user && !page.props.auth?.adminUser && crmEnabled && (
                    <nav className="flex items-center gap-2" aria-label="Atalhos de trabalho">
                        <Link
                            href={route('client.crm.funnel.index')}
                            className={`flex h-11 w-11 items-center justify-center gap-1.5 rounded-soft text-sm transition duration-150 xl:w-auto xl:px-2.5 ${route().current('client.crm.funnel.*') ? 'bg-brand-50 font-medium text-brand-700 dark:bg-brand-900/30 dark:text-brand-300' : 'text-neutral-600 hover:bg-neutral-100 hover:text-neutral-900 dark:text-neutral-400 dark:hover:bg-neutral-800 dark:hover:text-neutral-100'}`}
                            title="Funil de vendas"
                            aria-label="Funil de vendas"
                        >
                            <Columns3 className="h-4 w-4 shrink-0" />
                            <span className="hidden xl:inline">Funil de vendas</span>
                        </Link>
                        <Link
                            href={route('client.crm.tasks.index')}
                            className={`flex h-11 w-11 items-center justify-center gap-1.5 rounded-soft text-sm transition duration-150 xl:w-auto xl:px-2.5 ${route().current('client.crm.tasks.*') ? 'bg-brand-50 font-medium text-brand-700 dark:bg-brand-900/30 dark:text-brand-300' : 'text-neutral-600 hover:bg-neutral-100 hover:text-neutral-900 dark:text-neutral-400 dark:hover:bg-neutral-800 dark:hover:text-neutral-100'}`}
                            title="Tarefas"
                            aria-label="Tarefas"
                        >
                            <ListTodo className="h-4 w-4 shrink-0" />
                            <span className="hidden xl:inline">Tarefas</span>
                        </Link>
                    </nav>
                )}

                {/* Notification bell (client users only) */}
                {user && !page.props.auth?.adminUser && (
                    <button
                        type="button"
                        onClick={clearBrowserAlerts}
                        className="flex h-11 w-11 items-center justify-center rounded-soft text-neutral-600 transition duration-200 hover:bg-neutral-100 hover:text-neutral-900 dark:text-neutral-400 dark:hover:bg-neutral-800 dark:hover:text-neutral-100"
                        title="Remover todas as notificações exibidas"
                        aria-label="Remover todas as notificações exibidas"
                    >
                        <BellOff className="h-5 w-5" />
                    </button>
                )}

                {user && !page.props.auth?.adminUser && (
                    <div className="relative" ref={notifRef}>
                        <button
                            type="button"
                            onClick={notifOpen ? () => setNotifOpen(false) : openNotifDropdown}
                            className="relative flex h-11 w-11 items-center justify-center rounded-soft text-neutral-600 hover:bg-neutral-100 hover:text-neutral-900 dark:text-neutral-400 dark:hover:bg-neutral-800 dark:hover:text-neutral-100 transition duration-200"
                            aria-label={t('ui.notifications')}
                        >
                            <Bell className="h-5 w-5" />
                            {unreadCount > 0 && (
                                <span className="absolute top-1 right-1 flex h-4 w-4 items-center justify-center rounded-full bg-red-500 text-[10px] font-bold text-white">
                                    {unreadCount > 9 ? '9+' : unreadCount}
                                </span>
                            )}
                        </button>

                        {notifOpen && (
                            <div className="fixed inset-x-2 top-14 z-50 mt-1 max-h-[calc(100dvh-4rem)] overflow-hidden rounded-xl border border-neutral-200 bg-white shadow-lg dark:border-neutral-700 dark:bg-neutral-900 sm:absolute sm:inset-x-auto sm:right-0 sm:top-auto sm:w-80">
                                <div className="flex items-center justify-between px-4 py-2.5 border-b border-neutral-100 dark:border-neutral-800">
                                    <span className="text-sm font-semibold text-neutral-800 dark:text-neutral-200">{t('ui.notifications')}</span>
                                    <div className="flex items-center gap-2">
                                        {unreadCount > 0 && (
                                            <button onClick={markAllRead} className="text-xs text-brand-600 hover:underline dark:text-brand-400 flex items-center gap-1">
                                                <CheckCheck className="h-3.5 w-3.5" /> {t('ui.mark_all_read')}
                                            </button>
                                        )}
                                        <button
                                            type="button"
                                            onClick={clearBrowserAlerts}
                                            className="flex items-center gap-1 text-xs text-neutral-500 hover:text-brand-600 dark:text-neutral-400 dark:hover:text-brand-300"
                                            title="Remover alertas exibidos pelo navegador"
                                            aria-label="Remover alertas exibidos pelo navegador"
                                        >
                                            <BellOff className="h-3.5 w-3.5" /> Limpar alertas
                                        </button>
                                        <button onClick={() => setNotifOpen(false)} className="text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-200">
                                            <X className="h-4 w-4" />
                                        </button>
                                    </div>
                                </div>
                                <div className="divide-y divide-neutral-100 dark:divide-neutral-800 max-h-80 overflow-y-auto">
                                    {recentNotifs.length === 0 ? (
                                        <p className="text-sm text-neutral-400 text-center py-6">{t('ui.no_recent_notifications')}</p>
                                    ) : recentNotifs.map(n => (
                                        <div key={n.id} className={`px-4 py-3 text-sm ${n.read_at ? 'opacity-60' : 'bg-brand-50/40 dark:bg-brand-900/10'}`}>
                                            <p className="font-medium text-neutral-800 dark:text-neutral-200">{n.data?.type?.replace('_', ' ')}</p>
                                            <p className="text-xs text-neutral-500 dark:text-neutral-400 truncate">{n.data?.snippet ?? n.data?.name ?? n.data?.automation ?? ''}</p>
                                        </div>
                                    ))}
                                </div>
                                <div className="border-t border-neutral-100 dark:border-neutral-800 px-4 py-2 text-center">
                                    <Link
                                        href={route('client.notifications.index')}
                                        onClick={() => setNotifOpen(false)}
                                        className="text-xs text-brand-600 hover:underline dark:text-brand-400"
                                    >
                                        {t('ui.view_all_notifications')}
                                    </Link>
                                </div>
                            </div>
                        )}
                    </div>
                )}

                {/* Visit website (admins only, when the landing page is publicly visible) */}
                {isAdmin && page.props.landingPageEnabled && (
                    <a
                        href={route('home')}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="flex items-center gap-1.5 rounded-soft px-2.5 py-1.5 text-sm text-neutral-600 hover:bg-neutral-100 hover:text-neutral-900 dark:text-neutral-400 dark:hover:bg-neutral-800 dark:hover:text-neutral-100 transition duration-150"
                        aria-label={t('topbar.visit_website')}
                        title={t('topbar.visit_website')}
                    >
                        <ExternalLink className="h-4 w-4 shrink-0" />
                        <span className="hidden sm:inline">{t('topbar.visit_website')}</span>
                    </a>
                )}

                {/* Theme toggle */}
                <button
                    type="button"
                    onClick={handleThemeToggle}
                    className="flex h-11 w-11 items-center justify-center rounded-soft text-neutral-600 hover:bg-neutral-100 hover:text-neutral-900 dark:text-neutral-400 dark:hover:bg-neutral-800 dark:hover:text-neutral-100 transition duration-200"
                    aria-label={t('topbar.switch_theme')}
                    title={t('topbar.switch_theme')}
                >
                    {theme === 'dark' ? (
                        <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}>
                            <path strokeLinecap="round" strokeLinejoin="round" d="M12 3v2.25m6.364.386l-1.591 1.591M21 12h-2.25m-.386 6.364l-1.591-1.591M12 18.75V21m-4.773-4.227l-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0z" />
                        </svg>
                    ) : (
                        <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}>
                            <path strokeLinecap="round" strokeLinejoin="round" d="M21.752 15.002A9.718 9.718 0 0118 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 003 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 009.002-5.998z" />
                        </svg>
                    )}
                </button>

                {/* Workspace (tenant) switcher */}
                {showWorkspace && user && (
                    <Dropdown>
                        <Dropdown.Trigger>
                            <button
                                type="button"
                                className="flex min-h-11 items-center gap-1 rounded-soft px-1.5 text-sm text-neutral-600 hover:bg-neutral-100 hover:text-neutral-900 dark:text-neutral-400 dark:hover:bg-neutral-800 dark:hover:text-neutral-100 transition duration-150 sm:gap-1.5 sm:px-2.5"
                                aria-label={t('topbar.workspace')}
                            >
                                <span className="text-base" aria-hidden>◫</span>
                                <span className="hidden max-w-[140px] truncate sm:inline">
                                    {currentWorkspace?.name ?? t('topbar.no_workspace')}
                                </span>
                                <svg className="h-4 w-4 text-neutral-400 dark:text-neutral-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 9l-7 7-7-7" />
                                </svg>
                            </button>
                        </Dropdown.Trigger>
                        <Dropdown.Content align="right" width="56">
                            {workspaces.length === 0 && page.props.workspaceCapabilities?.canCreate ? (
                                <Link
                                    href={route('client.workspaces.index')}
                                    className="block px-4 py-2.5 text-sm text-brand-600 hover:bg-brand-50 dark:text-brand-400 dark:hover:bg-brand-900/30"
                                >
                                    {t('topbar.create_workspace')}
                                </Link>
                            ) : workspaces.length > 0 ? (
                                workspaces.map((w) => (
                                    <Dropdown.Item
                                        key={w.id}
                                        as="button"
                                        onClick={() => handleWorkspaceSwitch(w.id)}
                                        className={currentWorkspace?.id === w.id ? 'bg-brand-50 text-brand-700 dark:bg-brand-900/30 dark:text-brand-300 font-medium' : ''}
                                    >
                                        {w.name}
                                    </Dropdown.Item>
                                ))
                            ) : null}
                            <Dropdown.Divider />
                            <Link
                                href={route('client.workspaces.index')}
                                className="block px-4 py-2.5 text-sm text-neutral-600 hover:bg-neutral-50 dark:text-neutral-400 dark:hover:bg-neutral-800"
                            >
                                {t('topbar.manage_workspaces')}
                            </Link>
                        </Dropdown.Content>
                    </Dropdown>
                )}

                {/* Language switcher */}
                <Dropdown>
                    <Dropdown.Trigger>
                        <button
                            type="button"
                            className="flex min-h-11 items-center gap-1 rounded-soft px-1.5 text-sm text-neutral-600 hover:bg-neutral-100 hover:text-neutral-900 dark:text-neutral-400 dark:hover:bg-neutral-800 dark:hover:text-neutral-100 transition duration-150 sm:gap-1.5 sm:px-2.5"
                            aria-label={t('topbar.language')}
                        >
                            <svg className="h-4 w-4 shrink-0" aria-hidden="true" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.75}>
                                <circle cx="12" cy="12" r="9" />
                                <path strokeLinecap="round" d="M3.6 9h16.8M3.6 15h16.8" />
                                <path strokeLinecap="round" d="M12 3c-2.5 3-4 5.7-4 9s1.5 6 4 9M12 3c2.5 3 4 5.7 4 9s-1.5 6-4 9" />
                            </svg>
                            <span className="hidden sm:inline">
                                {i18nLocales.find((l) => l.code === currentLocale)?.native_name ?? currentLocale.toUpperCase()}
                            </span>
                            {currentIsRtl && <span className="rounded bg-neutral-200 dark:bg-neutral-700 px-1 text-[10px] font-medium text-neutral-600 dark:text-neutral-300">RTL</span>}
                            <svg className="h-4 w-4 text-neutral-400 dark:text-neutral-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                    </Dropdown.Trigger>
                    <Dropdown.Content align="right" width="48">
                        {localeEntries.map(([code, label]) => {
                            const isRtl = i18nLocales.find((l) => l.code === code)?.is_rtl ?? false;
                            return (
                                <Dropdown.Item
                                    key={code}
                                    as="button"
                                    onClick={() => handleLocale(code)}
                                    className={currentLocale === code ? 'bg-brand-50 text-brand-700 dark:bg-brand-900/30 dark:text-brand-300 font-medium' : ''}
                                >
                                    <span>{label}</span>
                                    {isRtl && <span className="ms-1.5 rounded bg-neutral-200 dark:bg-neutral-700 px-1 text-[10px]">RTL</span>}
                                </Dropdown.Item>
                            );
                        })}
                    </Dropdown.Content>
                </Dropdown>

                {/* Account menu */}
                {user && (
                    <Dropdown>
                        <Dropdown.Trigger>
                            <button
                                type="button"
                                className="flex min-h-11 items-center gap-1 rounded-soft px-1 text-sm text-neutral-700 hover:bg-neutral-100 dark:text-neutral-300 dark:hover:bg-neutral-800 transition duration-150 sm:gap-2 sm:px-2.5"
                                aria-label={t('topbar.account')}
                            >
                                <span className="flex h-7 w-7 items-center justify-center rounded-full bg-brand-100 text-brand-600 dark:bg-brand-900/40 dark:text-brand-300 text-xs font-medium">
                                    {(user.name || user.email || 'U').charAt(0).toUpperCase()}
                                </span>
                                <span className="hidden max-w-[120px] truncate md:inline">{user.name || user.email}</span>
                                <svg className="h-4 w-4 text-neutral-400 dark:text-neutral-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 9l-7 7-7-7" />
                                </svg>
                            </button>
                        </Dropdown.Trigger>
                        <Dropdown.Content align="right" width="56">
                            <div className="px-4 py-2 border-b border-soft border-gray-200 dark:border-neutral-700">
                                <p className="text-sm font-medium text-gray-900 dark:text-neutral-100 truncate">{user.name}</p>
                                <p className="text-xs text-neutral-500 dark:text-neutral-400 truncate">{user.email}</p>
                            </div>
                            {accountItems.map((item, i) =>
                                item.type === 'divider' ? (
                                    <Dropdown.Divider key={i} />
                                ) : item.as === 'link' ? (
                                    <Link
                                        key={i}
                                        href={item.href}
                                        method={item.method || 'get'}
                                        as="button"
                                        className="block w-full px-4 py-2.5 text-left text-sm text-neutral-700 hover:bg-neutral-50 dark:text-neutral-300 dark:hover:bg-neutral-800 transition duration-150"
                                    >
                                        {item.label}
                                    </Link>
                                ) : (
                                    <Dropdown.Item key={i} as="button" onClick={item.onClick}>
                                        {item.label}
                                    </Dropdown.Item>
                                )
                            )}
                        </Dropdown.Content>
                    </Dropdown>
                )}
            </div>
        </header>
    );
}
