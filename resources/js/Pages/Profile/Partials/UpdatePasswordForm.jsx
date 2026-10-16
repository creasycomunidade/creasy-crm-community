import { Button, Input } from '@/Components/ui';
import { useForm } from '@inertiajs/react';
import { useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';

export default function UpdatePasswordForm({ className = '' }) {
    const { t } = useTranslation();
    const passwordInput = useRef();
    const currentPasswordInput = useRef();
    const [saved, setSaved] = useState(false);

    const { data, setData, errors, put, reset, processing } = useForm({
        current_password: '',
        password: '',
        password_confirmation: '',
    });

    const updatePassword = (e) => {
        e.preventDefault();
        put(route('password.update'), {
            preserveScroll: true,
            onSuccess: () => {
                reset();
                setSaved(true);
                setTimeout(() => setSaved(false), 2000);
            },
            onError: (errs) => {
                if (errs.password) {
                    reset('password', 'password_confirmation');
                    passwordInput.current?.focus();
                }
                if (errs.current_password) {
                    reset('current_password');
                    currentPasswordInput.current?.focus();
                }
            },
        });
    };

    return (
        <section className={className}>
            <header className="mb-6">
                <h2 className="text-base font-semibold text-neutral-900 dark:text-white">
                    {t('profile.update_password')}
                </h2>
                <p className="mt-1 text-sm text-neutral-500 dark:text-neutral-400">
                    {t('profile.update_password_intro')}
                </p>
            </header>

            <form onSubmit={updatePassword} className="space-y-4">
                <Input
                    id="current_password"
                    ref={currentPasswordInput}
                    type="password"
                    name="current_password"
                    label={t('profile.current_password')}
                    value={data.current_password}
                    autoComplete="current-password"
                    onChange={(e) => setData('current_password', e.target.value)}
                    error={errors.current_password}
                />

                <Input
                    id="password"
                    ref={passwordInput}
                    type="password"
                    name="password"
                    label={t('profile.new_password')}
                    value={data.password}
                    autoComplete="new-password"
                    onChange={(e) => setData('password', e.target.value)}
                    error={errors.password}
                />

                <Input
                    id="password_confirmation"
                    type="password"
                    name="password_confirmation"
                    label={t('profile.confirm_new_password')}
                    value={data.password_confirmation}
                    autoComplete="new-password"
                    onChange={(e) => setData('password_confirmation', e.target.value)}
                    error={errors.password_confirmation}
                />

                <div className="flex items-center gap-4">
                    <Button type="submit" variant="primary" disabled={processing}>
                        {processing ? t('common.saving') : t('profile.update_password')}
                    </Button>
                    {saved && (
                        <p className="text-sm text-green-600 dark:text-green-400">{t('profile.saved')}</p>
                    )}
                </div>
            </form>
        </section>
    );
}
