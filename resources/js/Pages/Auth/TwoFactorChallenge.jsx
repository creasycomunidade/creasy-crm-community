import AuthLayout from '@/Layouts/AuthLayout';
import { Button, Input } from '@/Components/ui';
import { Head, useForm } from '@inertiajs/react';
import { ShieldCheck } from 'lucide-react';
import { useTranslation } from 'react-i18next';

export default function TwoFactorChallenge() {
    const { t } = useTranslation();
    const form = useForm({ code: '' });

    const handleSubmit = (e) => {
        e.preventDefault();
        form.post(route('auth.two-factor.verify'));
    };

    return (
        <AuthLayout
            title={t('auth.two_factor_title')}
            subtitle={t('auth.two_factor_subtitle')}
            icon={
                <div className="flex h-14 w-14 items-center justify-center rounded-full bg-brand-100 dark:bg-brand-900/40">
                    <ShieldCheck className="h-7 w-7 text-brand-600 dark:text-brand-400" />
                </div>
            }
        >
            <Head title={t('auth.two_factor_challenge')} />

            <form onSubmit={handleSubmit} className="space-y-4">
                <Input
                    label={t('auth.verification_code')}
                    value={form.data.code}
                    onChange={(e) => form.setData('code', e.target.value)}
                    placeholder={t('auth.verification_code_placeholder')}
                    autoFocus
                    error={form.errors.code}
                />
                <Button type="submit" variant="primary" className="w-full" disabled={form.processing}>
                    <ShieldCheck className="mr-2 h-4 w-4" />
                    {form.processing ? t('auth.verifying') : t('auth.verify')}
                </Button>
            </form>
        </AuthLayout>
    );
}
