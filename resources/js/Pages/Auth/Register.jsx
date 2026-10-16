import AuthLayout from '@/Layouts/AuthLayout';
import { Button, Input, Checkbox } from '@/Components/ui';
import { Head, Link, useForm } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { UserPlus } from 'lucide-react';
import { browserTz } from '@/Utils/datetime';

export default function Register() {
    const { t } = useTranslation();
    const { data, setData, post, processing, errors, reset } = useForm({
        name:                  '',
        email:                 '',
        password:              '',
        password_confirmation: '',
        agree_terms:           false,
        timezone:              browserTz() || 'America/Sao_Paulo',
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('register'), {
            onFinish: () => reset('password', 'password_confirmation'),
        });
    };

    return (
        <AuthLayout
            title={t('auth.register') || 'Create an account'}
            subtitle={t('auth.register_subtitle') || 'Get started for free today'}
        >
            <Head title={t('auth.register') || 'Register'} />

            <form onSubmit={submit} className="space-y-4">
                <Input
                    id="name"
                    name="name"
                    label={t('auth.name') || 'Full name'}
                    value={data.name}
                    autoComplete="name"
                    autoFocus
                    onChange={(e) => setData('name', e.target.value)}
                    error={errors.name}
                    required
                />

                <Input
                    id="email"
                    type="email"
                    name="email"
                    label={t('auth.email') || 'Email address'}
                    value={data.email}
                    autoComplete="username"
                    onChange={(e) => setData('email', e.target.value)}
                    error={errors.email}
                    required
                />

                <Input
                    id="password"
                    type="password"
                    name="password"
                    label={t('auth.password') || 'Password'}
                    value={data.password}
                    autoComplete="new-password"
                    onChange={(e) => setData('password', e.target.value)}
                    error={errors.password}
                    required
                />

                <Input
                    id="password_confirmation"
                    type="password"
                    name="password_confirmation"
                    label={t('auth.confirm_password') || 'Confirm password'}
                    value={data.password_confirmation}
                    autoComplete="new-password"
                    onChange={(e) => setData('password_confirmation', e.target.value)}
                    error={errors.password_confirmation}
                    required
                />

                <Checkbox
                    id="agree_terms"
                    name="agree_terms"
                    checked={data.agree_terms}
                    onChange={(e) => setData('agree_terms', e.target.checked)}
                    error={errors.agree_terms}
                    label={
                        <span>
                            {t('auth.agree_prefix') || 'I agree to the'}{' '}
                            <a
                                href="/p/terms"
                                target="_blank"
                                rel="noopener noreferrer"
                                className="font-medium text-brand-600 dark:text-brand-400 hover:underline"
                            >
                                {t('auth.terms_of_service') || 'Terms & Conditions'}
                            </a>{' '}
                            {t('auth.and') || 'and'}{' '}
                            <a
                                href="/p/privacy"
                                target="_blank"
                                rel="noopener noreferrer"
                                className="font-medium text-brand-600 dark:text-brand-400 hover:underline"
                            >
                                {t('auth.privacy_policy') || 'Privacy Policy'}
                            </a>
                        </span>
                    }
                />

                <Button type="submit" variant="primary" className="w-full" disabled={processing}>
                    <UserPlus className="mr-2 h-4 w-4" />
                    {processing ? (t('auth.creating_account') || 'Creating account…') : (t('auth.register') || 'Create account')}
                </Button>
                <input type="hidden" name="timezone" value={data.timezone} />
            </form>

            <p className="mt-5 text-center text-sm text-neutral-500 dark:text-neutral-400">
                {t('auth.already_registered') || 'Already have an account?'}{' '}
                <Link href={route('login')} className="font-medium text-brand-600 dark:text-brand-400 hover:underline">
                    {t('auth.log_in') || 'Sign in'}
                </Link>
            </p>
        </AuthLayout>
    );
}
