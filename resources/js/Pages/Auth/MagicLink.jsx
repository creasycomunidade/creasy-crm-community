import AuthLayout from '@/Layouts/AuthLayout';
import { Button, Input } from '@/Components/ui';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { Wand2 } from 'lucide-react';
import { useTranslation } from 'react-i18next';

export default function MagicLink() {
    const { t } = useTranslation();
    const form = useForm({ email: '' });
    const { status } = usePage().props;

    const handleSubmit = (e) => {
        e.preventDefault();
        form.post(route('auth.magic-link.send'));
    };

    return (
        <AuthLayout
            title={t('auth.magic_link_title')}
            subtitle={t('auth.magic_link_subtitle')}
            icon={
                <div className="flex h-14 w-14 items-center justify-center rounded-full bg-brand-100 dark:bg-brand-900/40">
                    <Wand2 className="h-7 w-7 text-brand-600 dark:text-brand-400" />
                </div>
            }
            status={status}
        >
            <Head title={t('auth.magic_link_login')} />

            {!status && (
                <form onSubmit={handleSubmit} className="space-y-4">
                    <Input
                        label={t('auth.email_address')}
                        type="email"
                        value={form.data.email}
                        onChange={(e) => form.setData('email', e.target.value)}
                        placeholder="you@example.com"
                        autoFocus
                        error={form.errors.email}
                    />
                    <Button type="submit" variant="primary" className="w-full" disabled={form.processing}>
                        <Wand2 className="mr-2 h-4 w-4" />
                        {form.processing ? t('auth.sending') : t('auth.send_magic_link')}
                    </Button>
                </form>
            )}

            <p className="mt-5 text-center text-sm text-neutral-500 dark:text-neutral-400">
                <Link href={route('login')} className="font-medium text-brand-600 dark:text-brand-400 hover:underline">
                    ← {t('auth.back_to_login')}
                </Link>
            </p>
        </AuthLayout>
    );
}
