import AuthLayout from '@/Layouts/AuthLayout';
import { Button, Input } from '@/Components/ui';
import { Head, Link, useForm } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { Mail } from 'lucide-react';

export default function ForgotPassword({ status }) {
    const { t } = useTranslation();
    const { data, setData, post, processing, errors } = useForm({ email: '' });

    const submit = (e) => {
        e.preventDefault();
        post(route('password.email'));
    };

    return (
        <AuthLayout
            title={t('auth.forgot_password_title') || 'Forgot your password?'}
            subtitle={t('auth.forgot_password_intro') || 'Enter your email and we\'ll send you a reset link.'}
            status={status}
        >
            <Head title={t('auth.forgot_password_title') || 'Forgot password'} />

            <form onSubmit={submit} className="space-y-4">
                <Input
                    id="email"
                    type="email"
                    name="email"
                    label={t('auth.email') || 'Email address'}
                    value={data.email}
                    autoFocus
                    onChange={(e) => setData('email', e.target.value)}
                    error={errors.email}
                />

                <Button type="submit" variant="primary" className="w-full" disabled={processing}>
                    <Mail className="mr-2 h-4 w-4" />
                    {processing ? (t('auth.sending') || 'Sending…') : (t('auth.send_reset_link') || 'Send reset link')}
                </Button>
            </form>

            <p className="mt-5 text-center text-sm text-neutral-500 dark:text-neutral-400">
                <Link href={route('login')} className="font-medium text-brand-600 dark:text-brand-400 hover:underline">
                    ← {t('auth.back_to_login') || 'Back to login'}
                </Link>
            </p>
        </AuthLayout>
    );
}
