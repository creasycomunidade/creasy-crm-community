import { Link, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { ChevronDown, Plus, X } from 'lucide-react';

function NavGroup({ label, items, onClose, open, onToggle }) {
    const groupId = label ? `nav-group-${label.replace(/\s+/g, '-').toLowerCase()}` : undefined;

    return (
        <div className="mb-0.5">
            {label && (
                <button
                    type="button"
                    onClick={onToggle}
                    aria-expanded={open}
                    aria-controls={groupId}
                    className="flex w-full items-center justify-between px-3 py-1.5 mt-3 text-[10px] font-bold uppercase tracking-widest text-white/70 hover:text-white transition-colors duration-150 select-none"
                >
                    <span>{label}</span>
                    <ChevronDown
                        className={[
                            'h-3 w-3 transition-transform duration-200',
                            open ? 'rotate-0' : '-rotate-90',
                        ].join(' ')}
                    />
                </button>
            )}

            {(!label || open) && (
                <div id={groupId} className={`${label ? 'mt-0.5' : 'mt-2'} space-y-0.5`}>
                    {items.map((item, i) => {
                        const isActive =
                            typeof item.active === 'function'
                                ? item.active()
                                : item.route
                                    ? route().current(item.route)
                                    : false;
                        const NavLink = item.hardReload ? 'a' : Link;
                        return (
                            <NavLink
                                key={item.key ?? item.route ?? item.href ?? i}
                                href={item.href ?? (item.route ? route(item.route) : '#')}
                                onClick={onClose}
                                className={[
                                    'group flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm font-medium transition-all duration-150',
                                    isActive
                                        ? 'bg-brand-600 text-white shadow-lg shadow-brand-950/20 ring-1 ring-inset ring-white/10'
                                        : 'text-white/80 hover:bg-white/10 hover:text-white',
                                ].join(' ')}
                                style={!isActive ? undefined : undefined}
                            >
                                {item.icon && (
                                    <span className={[
                                        'shrink-0 transition-colors duration-150',
                                        isActive ? 'text-white' : 'text-white/65 group-hover:text-white',
                                    ].join(' ')}>
                                        {item.icon}
                                    </span>
                                )}
                                <span className="truncate">{item.label}</span>
                                {isActive && (
                                    <span className="ml-auto h-1.5 w-1.5 rounded-full bg-white/70 shrink-0" />
                                )}
                            </NavLink>
                        );
                    })}
                </div>
            )}
        </div>
    );
}

export default function Sidebar({
    navItems = [],
    navGroups = [],
    open = false,
    onClose,
    footer,
    title,
    logo,
    showCreateButton = true,
}) {
    const { t } = useTranslation();
    const appName = usePage().props.branding?.app_name || 'CREASY CRM';
    const page = usePage();
    const { branding } = page.props;
    const logoUrl = branding?.logo_url;
    const activeGroupIndex = useMemo(() => navGroups.findIndex(group =>
        (group.items ?? []).some(item => typeof item.active === 'function' ? item.active() : false)
    ), [navGroups, page.url]);
    const [openGroupIndex, setOpenGroupIndex] = useState(activeGroupIndex >= 0 ? activeGroupIndex : null);

    // Inertia preserves the layout between navigations. Re-evaluate the active
    // section on every URL change so only the group containing the selected
    // link remains expanded; all unrelated sections collapse automatically.
    useEffect(() => {
        setOpenGroupIndex(activeGroupIndex >= 0 ? activeGroupIndex : null);
    }, [activeGroupIndex, page.url]);

    const content = (
        <aside className="flex h-full w-64 flex-col bg-gradient-to-b from-secondary-900 to-secondary-950 dark:from-secondary-950 dark:to-secondary-950">
            {/* Brand header */}
            <div className="flex h-14 shrink-0 items-center gap-2.5 px-4 border-b border-white/8">
                {logoUrl ? (
                    <img src={logoUrl} alt={appName} className="h-7 max-w-[140px] object-contain" />
                ) : logo ? (
                    logo
                ) : (
                    <img src="/creasy-community-logo-light.svg?v=2" alt={appName} className="h-8 w-auto max-w-[200px] object-contain" />
                )}
            </div>

            {showCreateButton && (
                <div className="shrink-0 p-3 pb-2 border-b border-white/8">
                    <button
                        type="button"
                        className="flex w-full items-center justify-center gap-2 rounded-lg px-3 py-2 text-sm font-semibold text-white bg-brand-600 hover:bg-brand-500 shadow-lg shadow-black/15 ring-1 ring-inset ring-white/10 transition duration-150"
                    >
                        <Plus className="h-4 w-4" />
                        {t('common.create')}
                    </button>
                </div>
            )}

            <nav className="flex-1 overflow-y-auto px-2 py-2 scrollbar-thin scrollbar-track-transparent scrollbar-thumb-white/10">
                {navGroups.length > 0 &&
                    navGroups.map((group, gi) => (
                        <NavGroup
                            // Index-prefixed: group labels are not guaranteed unique
                            // (e.g. two "Account" groups), and a duplicate React key
                            // makes React omit/duplicate siblings, corrupting the
                            // sidebar across SPA navigations.
                            key={`${gi}-${group.key ?? group.label ?? ''}`}
                            label={group.label}
                            items={group.items ?? []}
                            onClose={onClose}
                            open={!group.label || openGroupIndex === gi}
                            onToggle={() => setOpenGroupIndex(current => current === gi ? null : gi)}
                        />
                    ))}

                {navGroups.length === 0 &&
                    navItems.map((item, i) => {
                        if (item.type === 'divider') {
                            return <hr key={`div-${i}`} className="my-2 border-white/10" />;
                        }
                        const isActive =
                            typeof item.active === 'function'
                                ? item.active()
                                : item.active ?? (item.route && route().current(item.route));
                        const NavLink = item.hardReload ? 'a' : Link;
                        return (
                            <NavLink
                                key={item.key ?? item.route ?? item.href ?? i}
                                href={item.href ?? (item.route ? route(item.route) : '#')}
                                onClick={onClose}
                                className={[
                                    'group flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm font-medium transition-all duration-150',
                                    isActive
                                        ? 'bg-brand-600 text-white shadow-lg shadow-brand-950/20 ring-1 ring-inset ring-white/10'
                                        : 'text-white/80 hover:bg-white/10 hover:text-white',
                                ].join(' ')}
                            >
                                {item.icon && (
                                    <span className={isActive ? 'text-white' : 'text-white/65 group-hover:text-white'}>
                                        {item.icon}
                                    </span>
                                )}
                                <span className="truncate">{item.label}</span>
                            </NavLink>
                        );
                    })}
            </nav>

            {footer && (
                <div className="shrink-0 border-t border-white/8 p-3">
                    <div className="text-white/55">
                        {footer}
                    </div>
                </div>
            )}
        </aside>
    );

    return (
        <>
            {/* Desktop: always visible */}
            <div className="hidden lg:fixed lg:inset-y-0 lg:z-20 lg:flex lg:w-64 lg:flex-col lg:left-0 rtl:lg:left-auto rtl:lg:right-0">
                {content}
            </div>

            {/* Mobile: overlay + drawer */}
            {open && (
                <div className="fixed inset-0 z-40 lg:hidden">
                    <div className="fixed inset-0 bg-black/60 backdrop-blur-sm" onClick={onClose} aria-hidden="true" />
                    <div className="fixed inset-y-0 left-0 w-64 shadow-2xl rtl:left-auto rtl:right-0">
                        <button
                            type="button"
                            onClick={onClose}
                            className="absolute top-3 right-3 z-10 flex h-7 w-7 items-center justify-center rounded-full bg-white/10 text-white/70 hover:bg-white/20 transition"
                            aria-label={t('ui.close_menu')}
                        >
                            <X className="h-4 w-4" />
                        </button>
                        {content}
                    </div>
                </div>
            )}
        </>
    );
}
