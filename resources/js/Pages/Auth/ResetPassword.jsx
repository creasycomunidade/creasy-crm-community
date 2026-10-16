import AuthLayout from '@/Layouts/AuthLayout';
import { Button, Input } from '@/Components/ui';
import { Head, useForm } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { KeyRound } from 'lucide-react';

export default function ResetPassword({ token, email }) {
    const { t } = useTranslation();
    const { data, setData, post, processing, errors, reset } = useForm({
        token,
        email,
        password: '',
        password_confirmation: '',
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('password.store'), {
            onFinish: () => reset('password', 'password_confirmation'),
        });
    };

    return (
        <AuthLayout
            title={t('auth.reset_password') || 'Reset your password'}
            subtitle={t('auth.reset_password_subtitle') || 'Choose a new password for your account'}
        >
            <Head title={t('auth.reset_password') || 'Reset password'} />

            <form onSubmit={submit} className="space-y-4">
                <Input
                    id="email"
                    type="email"
                    name="email"
                    label={t('auth.email') || 'Email address'}
                    value={data.email}
                    autoComplete="username"
                    onChange={(e) => setData('email', e.target.value)}
                    error={errors.email}
                />

                <Input
                    id="password"
                    type="password"
                    name="password"
                    label={t('auth.password') || 'New password'}
                    value={data.password}
                    autoComplete="new-password"
                    autoFocus
                    onChange={(e) => setData('password', e.target.value)}
                    error={errors.password}
                />

                <Input
                    id="password_confirmation"
                    type="password"
                    name="password_confirmation"
                    label={t('auth.confirm_password') || 'Confirm new password'}
                    value={data.password_confirmation}
                    autoComplete="new-password"
                    onChange={(e) => setData('password_confirmation', e.target.value)}
                    error={errors.password_confirmation}
                />

                <Button type="submit" variant="primary" className="w-full" disabled={processing}>
                    <KeyRound className="mr-2 h-4 w-4" />
                    {processing ? (t('auth.resetting') || 'Resetting…') : (t('auth.reset_password') || 'Reset password')}
                </Button>
            </form>
        </AuthLayout>
    );
}
