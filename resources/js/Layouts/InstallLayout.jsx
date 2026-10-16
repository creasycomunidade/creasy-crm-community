import { usePage } from '@inertiajs/react';
import ApplicationLogo from '@/Components/ApplicationLogo';
import { useTheme } from '@/context/ThemeContext';
import { Sun, Moon, Check } from 'lucide-react';

/**
 * Branded split-pane layout for the setup wizard, echoing AuthLayout:
 *   - Left pane (desktop): warm graphite background with logo + a vertical stepper
 *     that doubles as progress and context.
 *   - Right pane: the active step's content in a clean card.
 *
 * Deliberately self-contained — it runs before the database
 * (and translations) exist.
 *
 * Props:
 *   steps    – array of { label, desc, icon } describing each step
 *   current  – zero-based index of the active step
 *   title    – heading above the card
 *   subtitle – subheading below the title
 *   children – the active step's content
 */

const BRAND_BG = '#1c1917';

function ThemeToggle() {
    const { theme, setTheme } = useTheme();
    return (
        <button
            type="button"
            onClick={() => setTheme(theme === 'dark' ? 'light' : 'dark')}
            className="inline-flex h-8 w-8 items-center justify-center rounded-soft text-neutral-500 transition hover:bg-neutral-100 dark:text-neutral-400 dark:hover:bg-neutral-800"
            aria-label="Alternar tema"
        >
            {theme === 'dark' ? <Sun className="h-4 w-4" /> : <Moon className="h-4 w-4" />}
        </button>
    );
}

function LeftPane({ steps, current, appName }) {
    return (
        <div
            className="relative hidden w-[42%] shrink-0 flex-col justify-between overflow-hidden p-10 text-white lg:flex"
            style={{ background: BRAND_BG }}
        >
            {/* Brand radial glow */}
            <div
                aria-hidden
                className="pointer-events-none absolute inset-0"
                style={{
                    background:
                        'radial-gradient(ellipse 80% 65% at 65% 50%, rgba(255,122,0,0.34) 0%, rgba(253,186,116,0.12) 45%, transparent 70%)',
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

            {/* Logo + brand */}
            <div className="relative flex items-center gap-3">
                <ApplicationLogo className="h-9 w-9 fill-current text-white/90" />
                <span className="text-xl font-bold tracking-tight">{appName}</span>
                <span className="inline-flex items-center rounded-full bg-white/15 px-2 py-0.5 text-xs font-medium text-white/90">
                    Comunidade
                </span>
            </div>

            {/* Intro + vertical stepper */}
            <div className="relative space-y-7">
                <div>
                    <p className="mb-2 text-xs font-semibold uppercase tracking-widest text-brand-400">
                        Primeiros passos
                    </p>
                    <h2 className="text-2xl font-bold leading-tight">Vamos configurar {appName}</h2>
                    <p className="mt-2 text-sm leading-relaxed text-neutral-300">
                        Configure o servidor, a marca e sua conta de administrador.
                    </p>
                </div>

                <ol className="relative space-y-1">
                    {steps.map((step, i) => {
                        const done = i < current;
                        const active = i === current;
                        const Icon = step.icon;
                        const last = i === steps.length - 1;

                        return (
                            <li key={step.label} className="flex gap-4">
                                <div className="flex flex-col items-center">
                                    <span
                                        className={[
                                            'flex h-9 w-9 items-center justify-center rounded-full border text-sm font-semibold transition',
                                            done
                                                ? 'border-brand-500 bg-brand-500 text-white'
                                                : active
                                                    ? 'border-brand-400 bg-white/5 text-white ring-4 ring-brand-500/20'
                                                    : 'border-white/20 bg-transparent text-white/40',
                                        ].join(' ')}
                                    >
                                        {done ? (
                                            <Check className="h-4 w-4" />
                                        ) : Icon ? (
                                            <Icon className="h-4 w-4" />
                                        ) : (
                                            i + 1
                                        )}
                                    </span>
                                    {!last && (
                                        <span
                                            className={[
                                                'my-1 w-px flex-1 transition',
                                                done ? 'bg-brand-500/70' : 'bg-white/12',
                                            ].join(' ')}
                                        />
                                    )}
                                </div>
                                <div className={last ? '' : 'pb-5'}>
                                    <p
                                        className={[
                                            'text-sm font-medium transition',
                                            active ? 'text-white' : done ? 'text-white/80' : 'text-white/40',
                                        ].join(' ')}
                                    >
                                        {step.label}
                                    </p>
                                    {step.desc && (
                                        <p
                                            className={[
                                                'mt-0.5 text-xs transition',
                                                active ? 'text-neutral-300' : 'text-white/30',
                                            ].join(' ')}
                                        >
                                            {step.desc}
                                        </p>
                                    )}
                                </div>
                            </li>
                        );
                    })}
                </ol>
            </div>

            {/* Footer */}
            <p className="relative text-xs text-neutral-500">
                &copy; {new Date().getFullYear()} {appName}. Assistente de instalação.
            </p>
        </div>
    );
}

function MobileProgress({ steps, current }) {
    const pct = Math.round(((current + 1) / steps.length) * 100);
    return (
        <div className="lg:hidden">
            <div className="mb-1.5 flex items-center justify-between text-xs">
                <span className="font-medium text-neutral-700 dark:text-neutral-300">
                    {steps[current]?.label}
                </span>
                <span className="text-neutral-400 dark:text-neutral-500">
                    Etapa {current + 1} de {steps.length}
                </span>
            </div>
            <div className="h-1.5 w-full overflow-hidden rounded-full bg-neutral-200 dark:bg-neutral-800">
                <div
                    className="h-full rounded-full bg-brand-600 transition-all duration-300"
                    style={{ width: `${pct}%` }}
                />
            </div>
        </div>
    );
}

export default function InstallLayout({ steps = [], current = 0, title, subtitle, children }) {
    const appName = usePage().props.branding?.app_name || 'CREASY CRM';
    const Icon = steps[current]?.icon;

    return (
        <div className="flex min-h-screen">
            <LeftPane steps={steps} current={current} appName={appName} />

            {/* Right pane */}
            <div className="flex flex-1 flex-col bg-neutral-50 dark:bg-neutral-950">
                {/* Top bar */}
                <div className="flex items-center justify-between px-6 py-4">
                    <div className="flex items-center gap-2 lg:invisible">
                        <ApplicationLogo className="h-7 w-7 fill-current text-brand-600 dark:text-brand-400" />
                        <span className="text-sm font-semibold text-neutral-900 dark:text-white">
                            {appName}
                        </span>
                    </div>
                    <ThemeToggle />
                </div>

                {/* Centered content */}
                <div className="flex flex-1 items-start justify-center px-4 pb-16 pt-2 sm:pt-6">
                    <div className="w-full max-w-xl">
                        <div className="mb-6 lg:hidden">
                            <MobileProgress steps={steps} current={current} />
                        </div>

                        {/* Header */}
                        <div className="mb-6 text-center">
                            {Icon && (
                                <div className="mb-4 flex justify-center">
                                    <span className="flex h-12 w-12 items-center justify-center rounded-soft-lg bg-brand-50 text-brand-600 ring-1 ring-brand-500/15 dark:bg-brand-900/20 dark:text-brand-400">
                                        <Icon className="h-6 w-6" />
                                    </span>
                                </div>
                            )}
                            {title && (
                                <h1 className="text-2xl font-bold text-neutral-900 dark:text-white">{title}</h1>
                            )}
                            {subtitle && (
                                <p className="mt-1.5 text-sm text-neutral-500 dark:text-neutral-400">{subtitle}</p>
                            )}
                        </div>

                        {/* Card */}
                        <div className="rounded-xl border border-neutral-200 bg-white p-6 shadow-soft-lg dark:border-neutral-800 dark:bg-neutral-900 sm:p-8">
                            {children}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
}
