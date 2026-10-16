import AuthLayout from '@/Layouts/AuthLayout';
import { Button, Input } from '@/Components/ui';
import { Head, useForm } from '@inertiajs/react';
import { UserPlus } from 'lucide-react';
import { useTranslation } from 'react-i18next';

export default function AcceptInvitation({ token, email, client }) {
    const { t } = useTranslation();
    const form = useForm({ name: '', password: '', password_confirmation: '' });

    const handleSubmit = (e) => {
        e.preventDefault();
        form.post(route('auth.invitations.accept', { token }));
    };

    return (
        <AuthLayout
            title={t('auth.invited_title')}
            subtitle={
                client
                    ? t('auth.invited_subtitle_client', { name: client.name, email })
                    : t('auth.invited_subtitle', { email })
            }
            icon={
                <div className="flex h-14 w-14 items-center justify-center rounded-full bg-green-100 dark:bg-green-900/40">
                    <UserPlus className="h-7 w-7 text-green-600 dark:text-green-400" />
                </div>
            }
        >
            <Head title={t('auth.accept_invitation')} />

            <form onSubmit={handleSubmit} className="space-y-4">
                <Input
                    label={t('auth.your_name')}
                    value={form.data.name}
                    onChange={(e) => form.setData('name', e.target.value)}
                    placeholder={t('auth.full_name_placeholder')}
                    autoFocus
                    error={form.errors.name}
                />
                <Input
                    label={t('auth.password')}
                    type="password"
                    value={form.data.password}
                    onChange={(e) => form.setData('password', e.target.value)}
                    error={form.errors.password}
                />
                <Input
                    label={t('auth.confirm_password')}
                    type="password"
                    value={form.data.password_confirmation}
                    onChange={(e) => form.setData('password_confirmation', e.target.value)}
                    error={form.errors.password_confirmation}
                />
                <Button type="submit" variant="primary" className="w-full" disabled={form.processing}>
                    <UserPlus className="mr-2 h-4 w-4" />
                    {form.processing ? t('auth.joining') : t('auth.accept_invitation')}
                </Button>
            </form>
        </AuthLayout>
    );
}
