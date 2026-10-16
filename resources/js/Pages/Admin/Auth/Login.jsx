import AuthLayout from '@/Layouts/AuthLayout';
import { Button, Input, Checkbox } from '@/Components/ui';
import { Head, Link, useForm } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { ShieldCheck } from 'lucide-react';

export default function AdminLogin({ status, error }) {
    const { t } = useTranslation();
    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
        password: '',
        remember: false,
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('admin.login'), { onFinish: () => reset('password') });
    };

    return (
        <AuthLayout
            variant="admin"
            title={t('admin.admin_log_in')}
            subtitle={t('admin.admin_login_subtitle')}
            status={status}
            error={error}
        >
            <Head title={t('admin.admin_log_in')} />

            <form onSubmit={submit} className="space-y-4">
                <Input
                    id="email"
                    type="email"
                    name="email"
                    label={t('common.email')}
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
                    label={t('auth.password')}
                    value={data.password}
                    autoComplete="current-password"
                    onChange={(e) => setData('password', e.target.value)}
                    error={errors.password}
                />

                <Checkbox
                    id="remember"
                    name="remember"
                    label={t('auth.remember_me')}
                    checked={data.remember}
                    onChange={(e) => setData('remember', e.target.checked)}
                />

                <Button type="submit" variant="primary" className="w-full" disabled={processing}>
                    <ShieldCheck className="mr-2 h-4 w-4" />
                    {processing ? t('auth.signing_in') : t('auth.log_in')}
                </Button>
            </form>

            <p className="mt-5 text-center text-sm text-neutral-500 dark:text-neutral-400">
                <Link
                    href={route('login')}
                    className="font-medium text-brand-600 dark:text-brand-400 hover:underline"
                >
                    {t('admin.client_login_link')}
                </Link>
            </p>
        </AuthLayout>
    );
}
