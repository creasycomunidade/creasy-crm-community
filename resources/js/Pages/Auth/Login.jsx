import AuthLayout from '@/Layouts/AuthLayout';
import { Button, Input, Checkbox } from '@/Components/ui';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { LogIn, Sparkles } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

const PROVIDERS = [
    { id: 'google',    label: 'Google',    color: 'text-red-500' },
    { id: 'github',    label: 'GitHub',    color: 'text-neutral-800 dark:text-neutral-200' },
    { id: 'microsoft', label: 'Microsoft', color: 'text-blue-500' },
];

function GoogleIcon() {
    return (
        <svg className="h-4 w-4" viewBox="0 0 24 24" aria-hidden="true">
            <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
            <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
            <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
            <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
        </svg>
    );
}

function FirebaseGoogleButton() {
    const { t } = useTranslation();
    const { props } = usePage();
    const firebase = props.firebase ?? {};
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState(null);
    const initRef = useRef(false);

    useEffect(() => {
        if (initRef.current || !firebase.enabled || !firebase.apiKey) return;
        initRef.current = true;
        import('@/lib/firebase').then(({ initFirebase }) => {
            initFirebase({
                apiKey:     firebase.apiKey,
                authDomain: firebase.authDomain,
                projectId:  firebase.projectId,
                appId:      firebase.appId,
            });
        });
    }, [firebase]);

    if (!firebase.enabled || !firebase.apiKey) return null;

    const handleClick = async () => {
        setError(null);
        setLoading(true);
        try {
            const { signInWithGoogle } = await import('@/lib/firebase');
            const idToken = await signInWithGoogle();
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
            const res = await fetch(route('auth.firebase'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ id_token: idToken }),
            });
            const data = await res.json();
            if (data.redirect) {
                window.location.href = data.redirect;
            } else {
                setError(data.message ?? t('auth.login_failed'));
            }
        } catch (err) {
            if (err?.code === 'auth/popup-closed-by-user') {
                // user dismissed — no error shown
            } else {
                setError(t('auth.google_signin_failed'));
            }
        } finally {
            setLoading(false);
        }
    };

    return (
        <div className="space-y-1">
            <button
                type="button"
                onClick={handleClick}
                disabled={loading}
                className="flex w-full items-center justify-center gap-3 rounded-soft border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800 px-4 py-2.5 text-sm font-medium text-neutral-700 dark:text-neutral-200 hover:bg-neutral-100 dark:hover:bg-neutral-700 transition disabled:opacity-60"
            >
                <GoogleIcon />
                {loading ? t('auth.signing_in') : t('auth.continue_with_google')}
            </button>
            {error && <p className="text-xs text-red-500 text-center">{error}</p>}
        </div>
    );
}

const DEMO_ROLE_META = {
    admin:  { labelKey: 'auth.demo_role_admin',  fallback: 'Admin' },
    client: { labelKey: 'auth.demo_role_client', fallback: 'Client' },
};

function DemoAccountRow({ account }) {
    const { t } = useTranslation();
    const [loading, setLoading] = useState(false);
    const meta = DEMO_ROLE_META[account.role] ?? { labelKey: null, fallback: account.role };

    const login = () => {
        router.post(route('login'), {
            email: account.email,
            password: account.password,
            remember: true,
        }, {
            onStart: () => setLoading(true),
            onFinish: () => setLoading(false),
        });
    };

    return (
        <div className="flex items-center justify-between gap-3 px-4 py-3">
            <div className="min-w-0">
                <p className="text-sm font-semibold text-neutral-800 dark:text-neutral-100">
                    {meta.labelKey ? (t(meta.labelKey) || meta.fallback) : meta.fallback}
                </p>
                <p className="truncate text-xs text-neutral-500 dark:text-neutral-400">{account.email}</p>
                <p className="text-xs text-neutral-500 dark:text-neutral-400">
                    {(t('auth.demo_password_label') || 'Password')}: {account.password}
                </p>
            </div>
            <button
                type="button"
                onClick={login}
                disabled={loading}
                className="shrink-0 rounded-soft bg-accent-200 px-3.5 py-2 text-sm font-semibold text-accent-900 transition hover:bg-accent-300 disabled:opacity-60 dark:bg-accent-400 dark:text-accent-950 dark:hover:bg-accent-300"
            >
                {loading
                    ? (t('auth.signing_in') || 'Signing in…')
                    : (t('auth.demo_one_click_login') || 'One-click login')}
            </button>
        </div>
    );
}

function DemoLoginPanel({ accounts }) {
    const { t } = useTranslation();

    return (
        <div className="mb-5 rounded-soft border border-dashed border-neutral-300 dark:border-neutral-700 p-4">
            <div className="mb-3 flex items-center gap-2">
                <Sparkles className="h-4 w-4 text-accent-500" />
                <div>
                    <p className="text-sm font-semibold text-neutral-800 dark:text-neutral-100">
                        {t('auth.demo_mode_title') || 'Demo mode'}
                    </p>
                    <p className="text-xs text-neutral-500 dark:text-neutral-400">
                        {t('auth.demo_mode_subtitle') || 'Sign in instantly with a demo account.'}
                    </p>
                </div>
            </div>
            <div className="divide-y divide-neutral-200 dark:divide-neutral-700 rounded-soft border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-800/40">
                {accounts.map((account) => (
                    <DemoAccountRow key={`${account.role}:${account.email}`} account={account} />
                ))}
            </div>
        </div>
    );
}

export default function Login({ status, canResetPassword, socialProviders = [], demo = null }) {
    const { t } = useTranslation();
    const { props } = usePage();
    const firebase = props.firebase ?? {};
    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
        password: '',
        remember: false,
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('login'), { onFinish: () => reset('password') });
    };

    const enabledProviders = PROVIDERS.filter(p => socialProviders.includes(p.id));
    const hasSocialButtons = enabledProviders.length > 0 || firebase.enabled;

    return (
        <AuthLayout
            title={t('auth.log_in') || 'Sign in to your account'}
            subtitle={t('auth.login_subtitle') || 'Enter your credentials to continue'}
            status={status}
        >
            <Head title={t('auth.log_in') || 'Log in'} />

            {/* Demo mode: one-click sign-in with the seeded demo accounts */}
            {demo && demo.length > 0 && (
                <>
                    <DemoLoginPanel accounts={demo} />
                    <div className="relative mb-5">
                        <div className="absolute inset-0 flex items-center">
                            <div className="w-full border-t border-neutral-200 dark:border-neutral-700" />
                        </div>
                        <div className="relative flex justify-center">
                            <span className="bg-white dark:bg-neutral-900 px-3 text-xs text-neutral-400">
                                {t('auth.demo_or_sign_in_manually') || 'or sign in manually'}
                            </span>
                        </div>
                    </div>
                </>
            )}

            {/* Social / Firebase providers */}
            {hasSocialButtons && (
                <>
                    <div className="space-y-2 mb-4">
                        <FirebaseGoogleButton />
                        {enabledProviders.map((provider) => (
                            <a
                                key={provider.id}
                                href={route('auth.social.redirect', { provider: provider.id })}
                                className="flex w-full items-center justify-center gap-3 rounded-soft border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800 px-4 py-2.5 text-sm font-medium text-neutral-700 dark:text-neutral-200 hover:bg-neutral-100 dark:hover:bg-neutral-700 transition"
                            >
                                {t('auth.continue_with', { provider: provider.label })}
                            </a>
                        ))}
                    </div>
                    <div className="relative mb-5">
                        <div className="absolute inset-0 flex items-center">
                            <div className="w-full border-t border-neutral-200 dark:border-neutral-700" />
                        </div>
                        <div className="relative flex justify-center">
                            <span className="bg-white dark:bg-neutral-900 px-3 text-xs text-neutral-400">
                                {t('auth.or_continue_with_email')}
                            </span>
                        </div>
                    </div>
                </>
            )}

            <form onSubmit={submit} className="space-y-4">
                <Input
                    id="email"
                    type="email"
                    name="email"
                    label={t('auth.email') || 'Email address'}
                    value={data.email}
                    autoComplete="username"
                    autoFocus
                    onChange={(e) => setData('email', e.target.value)}
                    error={errors.email}
                />

                <Input
                    id="password"
                    type="password"
                    name="password"
                    label={t('auth.password') || 'Password'}
                    value={data.password}
                    autoComplete="current-password"
                    onChange={(e) => setData('password', e.target.value)}
                    error={errors.password}
                />

                <div className="flex items-center justify-between">
                    <Checkbox
                        id="remember"
                        name="remember"
                        label={t('auth.remember_me') || 'Remember me'}
                        checked={data.remember}
                        onChange={(e) => setData('remember', e.target.checked)}
                    />
                    <div className="flex items-center gap-3 text-sm">
                        {canResetPassword && (
                            <Link
                                href={route('password.request')}
                                className="text-brand-600 dark:text-brand-400 hover:underline"
                            >
                                {t('auth.forgot_password') || 'Forgot password?'}
                            </Link>
                        )}
                        <Link
                            href={route('auth.magic-link')}
                            className="text-neutral-500 dark:text-neutral-400 hover:text-neutral-700 dark:hover:text-neutral-200 hover:underline"
                        >
                            {t('auth.magic_link')}
                        </Link>
                    </div>
                </div>

                <Button type="submit" variant="primary" className="w-full" disabled={processing}>
                    <LogIn className="mr-2 h-4 w-4" />
                    {processing ? (t('auth.signing_in') || 'Signing in…') : (t('auth.log_in') || 'Sign in')}
                </Button>
            </form>

            <p className="mt-5 text-center text-sm text-neutral-500 dark:text-neutral-400">
                {t('auth.no_account') || "Don't have an account?"}{' '}
                <Link href={route('register')} className="font-medium text-brand-600 dark:text-brand-400 hover:underline">
                    {t('auth.register') || 'Sign up'}
                </Link>
            </p>
        </AuthLayout>
    );
}
