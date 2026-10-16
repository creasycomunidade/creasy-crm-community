import AuthLayout from '@/Layouts/AuthLayout';
import { Button } from '@/Components/ui';
import { Head, Link, useForm } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { MailCheck } from 'lucide-react';

export default function VerifyEmail({ status }) {
    const { t } = useTranslation();
    const { post, processing } = useForm({});

    const submit = (e) => {
        e.preventDefault();
        post(route('verification.send'));
    };

    return (
        <AuthLayout
            title={t('auth.verify_email_title') || 'Verify your email'}
            subtitle={t('auth.verify_email_intro') || 'Thanks for signing up! Before getting started, please verify your email address by clicking the link we just sent you.'}
            status={status === 'verification-link-sent' ? (t('auth.verification_sent') || 'A new verification link has been sent to your email address.') : undefined}
            icon={
                <div className="flex h-14 w-14 items-center justify-center rounded-full bg-brand-100 dark:bg-brand-900/40">
                    <MailCheck className="h-7 w-7 text-brand-600 dark:text-brand-400" />
                </div>
            }
        >
            <Head title={t('auth.verify_email_title') || 'Verify email'} />

            <form onSubmit={submit} className="space-y-4">
                <Button type="submit" variant="primary" className="w-full" disabled={processing}>
                    {processing ? (t('auth.sending') || 'Sending…') : (t('auth.resend_verification') || 'Resend verification email')}
                </Button>

                <Link
                    href={route('logout')}
                    method="post"
                    as="button"
                    className="block w-full text-center text-sm text-neutral-500 dark:text-neutral-400 hover:text-neutral-700 dark:hover:text-neutral-200 transition"
                >
                    {t('auth.log_out') || 'Log out'}
                </Link>
            </form>
        </AuthLayout>
    );
}
