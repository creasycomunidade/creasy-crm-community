import { Link, usePage } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { useTheme } from '@/context/ThemeContext';
import { useLocale } from '@/hooks/useLocale';
import ApplicationLogo from '@/Components/ApplicationLogo';
import { Sun, Moon, ShieldCheck, Zap, Users, Bot, TrendingUp, Clock } from 'lucide-react';

/**
 * Split-pane auth layout.
 * - Left pane (desktop only): brand gradient with logo + feature highlights
 * - Right pane: scrollable area containing the form card
 *
 * Props:
 *   variant  – 'client' (default) | 'admin'
 *   title    – heading shown above the form card
 *   subtitle – subheading shown below the title
 *   icon     – React node shown above the title
 *   status   – success message string (green alert)
 *   error    – error message string (red alert)
 *   children – the form content
 */

const CLIENT_FEATURES = [
    { icon: Zap,        label: 'Crie automações visuais para tarefas e mensagens' },
    { icon: TrendingUp, label: 'Organize contatos e oportunidades em pipelines' },
    { icon: Bot,        label: 'Use agentes de IA com o contexto da sua empresa' },
];

const ADMIN_FEATURES = [
    { icon: ShieldCheck, key: 'auth.admin_feature1', fallback: 'Granular role-based access for every team member' },
    { icon: Users,       key: 'auth.admin_feature2', fallback: 'Manage clients, plans, and billing from one dashboard' },
    { icon: Zap,         key: 'auth.admin_feature3', fallback: 'Real-time audit logs and live queue monitoring' },
];

function LeftPane({ variant }) {
    const { t } = useTranslation();
    const features = variant === 'admin' ? ADMIN_FEATURES : CLIENT_FEATURES;
    const appName = usePage().props.branding?.app_name || 'CREASY CRM';

    return (
        <div className="relative hidden lg:flex lg:w-[45%] flex-col justify-between overflow-hidden p-10 text-white" style={{ background: '#1c1917' }}>
            {/* Brand radial glow */}
            <div
                aria-hidden
                className="pointer-events-none absolute inset-0"
                style={{
                    background: 'radial-gradient(ellipse 80% 65% at 65% 50%, rgba(255,122,0,0.34) 0%, rgba(253,186,116,0.12) 45%, transparent 70%)',
                }}
            />
            {/* Subtle grid overlay */}
            <div
                aria-hidden
                className="pointer-events-none absolute inset-0 opacity-[0.04]"
                style={{
                    backgroundImage:
                        'linear-gradient(to right, white 1px, transparent 1px), linear-gradient(to bottom, white 1px, transparent 1px)',
                    backgroundSize: '48px 48px',
                }}
            />

            {/* Logo + brand name */}
            <div className="relative flex items-center gap-3">
                <ApplicationLogo className="h-9 w-9 fill-current text-white/90" />
                <span className="text-xl font-bold tracking-tight text-white">
                    {appName}
                </span>
                {variant === 'admin' && (
                    <span className="inline-flex items-center gap-1 rounded-full bg-white/20 px-2 py-0.5 text-xs font-medium text-white">
                        <ShieldCheck className="h-3 w-3" />
                        {t('nav.admin')}
                    </span>
                )}
            </div>

            {/* Middle copy */}
            <div className="relative space-y-6">
                <div>
                    <p className="mb-2 text-xs font-semibold uppercase tracking-widest text-brand-400">
                        {variant === 'admin'
                            ? (t('auth.admin_panel_label') || 'Admin Panel')
                            : (t('auth.welcome_to') || 'Welcome to')}
                    </p>
                    <h2 className="text-3xl font-bold leading-tight text-white">
                        {variant === 'admin'
                            ? (t('auth.admin_tagline') || 'Manage your platform with confidence')
                            : (t('auth.tagline') || 'Turn conversations into revenue — on autopilot')}
                    </h2>
                    <p className="mt-3 text-sm text-neutral-300 leading-relaxed">
                        {variant === 'admin'
                            ? ('Administre usuários, canais e configurações.' || 'Full control over clients, subscriptions, integrations, and settings.')
                            : 'Centralize atendimento, vendas, automações e inteligência artificial em um único espaço de trabalho.'}</p>
                </div>

                <ul className="space-y-3">
                    {features.map(({ icon: Icon, key, fallback, label }) => (
                        <li key={key || label} className="flex items-start gap-3">
                            <span className="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-md bg-brand-500/20 border border-brand-500/30">
                                <Icon className="h-3.5 w-3.5 text-brand-400" />
                            </span>
                            <span className="text-sm text-neutral-300">{label || t(key) || fallback}</span>
                        </li>
                    ))}
                </ul>

            </div>

            {/* Footer */}
            <p className="relative text-xs text-neutral-500">
                &copy; {new Date().getFullYear()} {appName}. {t('nav.all_rights_reserved')}
            </p>
        </div>
    );
}

function ThemeToggle() {
    const { t } = useTranslation();
    const { theme, setTheme } = useTheme();
    return (
        <button
            type="button"
            onClick={() => setTheme(theme === 'dark' ? 'light' : 'dark')}
            className="inline-flex h-8 w-8 items-center justify-center rounded-soft text-neutral-500 hover:bg-neutral-100 dark:text-neutral-400 dark:hover:bg-neutral-800 transition"
            aria-label={t('topbar.switch_theme')}
        >
            {theme === 'dark'
                ? <Sun className="h-4 w-4" />
                : <Moon className="h-4 w-4" />}
        </button>
    );
}

function LocaleToggle() {
    const { locale, locales, setLocale } = useLocale();
    if (locales.length <= 1) return null;

    return (
        <div className="relative">
            <select
                value={locale}
                onChange={(e) => setLocale(e.target.value)}
                className="h-8 rounded-soft border border-neutral-200 dark:border-neutral-700 bg-transparent px-2 text-xs text-neutral-500 dark:text-neutral-400 focus:outline-none focus:ring-2 focus:ring-brand-500/20"
            >
                {locales.map((l) => (
                    <option key={l.code} value={l.code}>
                        {l.native_name || l.name || l.code.toUpperCase()}
                    </option>
                ))}
            </select>
        </div>
    );
}

export default function AuthLayout({
    variant = 'client',
    title,
    subtitle,
    icon,
    status,
    error,
    children,
}) {
    return (
        <div className="flex min-h-screen">
            <LeftPane variant={variant} />

            {/* Right pane */}
            <div className="flex flex-1 flex-col bg-neutral-50 dark:bg-neutral-950">
                {/* Top bar */}
                <div className="flex items-center justify-between px-6 py-4">
                    {/* Mobile logo */}
                    <Link href={route('home')} className="flex items-center gap-2 lg:hidden">
                        <ApplicationLogo className="h-7 w-7 fill-current text-brand-600 dark:text-brand-400" />
                        <span className="text-sm font-semibold text-neutral-900 dark:text-white">
                            <BrandName />
                        </span>
                    </Link>
                    <span className="hidden lg:block" />
                    <div className="flex items-center gap-2">
                        <LocaleToggle />
                        <ThemeToggle />
                    </div>
                </div>

                {/* Centered card */}
                <div className="flex flex-1 items-center justify-center px-4 pb-12 pt-4">
                    <div className="w-full max-w-md">
                        {/* Icon */}
                        {icon && (
                            <div className="mb-5 flex justify-center">{icon}</div>
                        )}

                        {/* Title block */}
                        {title && (
                            <div className="mb-6 text-center">
                                <h1 className="text-2xl font-bold text-neutral-900 dark:text-white">
                                    {title}
                                </h1>
                                {subtitle && (
                                    <p className="mt-1.5 text-sm text-neutral-500 dark:text-neutral-400">
                                        {subtitle}
                                    </p>
                                )}
                            </div>
                        )}

                        {/* Status / error banners */}
                        {status && (
                            <div className="mb-4 rounded-lg bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 px-4 py-3 text-sm text-green-800 dark:text-green-300">
                                {status}
                            </div>
                        )}
                        {error && (
                            <div className="mb-4 rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 px-4 py-3 text-sm text-red-800 dark:text-red-300">
                                {error}
                            </div>
                        )}

                        {/* Card */}
                        <div className="rounded-xl border border-neutral-200 dark:border-neutral-800 bg-white dark:bg-neutral-900 p-6 sm:p-8 shadow-soft-lg">
                            {children}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
}

function BrandName() { return usePage().props.branding?.app_name || 'CREASY CRM'; }
