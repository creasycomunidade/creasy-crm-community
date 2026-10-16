import AuthLayout from '@/Layouts/AuthLayout';
import { Button, Input } from '@/Components/ui';
import { Head, useForm } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { LockKeyhole } from 'lucide-react';

export default function ConfirmPassword() {
    const { t } = useTranslation();
    const { data, setData, post, processing, errors, reset } = useForm({ password: '' });

    const submit = (e) => {
        e.preventDefault();
        post(route('password.confirm'), {
            onFinish: () => reset('password'),
        });
    };

    return (
        <AuthLayout
            title={t('auth.confirm_password_title') || 'Confirm your password'}
            subtitle={t('auth.confirm_password_intro') || 'This is a secure area. Please confirm your password before continuing.'}
        >
            <Head title={t('auth.confirm_password_title') || 'Confirm password'} />

            <form onSubmit={submit} className="space-y-4">
                <Input
                    id="password"
                    type="password"
                    name="password"
                    label={t('auth.password') || 'Password'}
                    value={data.password}
                    autoComplete="current-password"
                    autoFocus
                    onChange={(e) => setData('password', e.target.value)}
                    error={errors.password}
                />

                <Button type="submit" variant="primary" className="w-full" disabled={processing}>
                    <LockKeyhole className="mr-2 h-4 w-4" />
                    {processing ? (t('auth.confirming') || 'Confirming…') : (t('auth.confirm') || 'Confirm')}
                </Button>
            </form>
        </AuthLayout>
    );
}
