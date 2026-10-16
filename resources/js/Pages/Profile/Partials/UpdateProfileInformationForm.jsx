import { Button, Input } from '@/Components/ui';
import { Link, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { useTranslation, Trans } from 'react-i18next';

export default function UpdateProfileInformation({ mustVerifyEmail, status, className = '' }) {
    const { t } = useTranslation();
    const user = usePage().props.auth.user;
    const [saved, setSaved] = useState(false);

    const { data, setData, patch, errors, processing } = useForm({
        name:  user.name,
        email: user.email,
        phone_e164: user.phone_e164 || '',
    });

    const submit = (e) => {
        e.preventDefault();
        patch(route('client.profile.update'), {
            preserveScroll: true,
            onSuccess: () => {
                setSaved(true);
                setTimeout(() => setSaved(false), 2000);
            },
        });
    };

    return (
        <section className={className}>
            <header className="mb-6">
                <h2 className="text-base font-semibold text-neutral-900 dark:text-white">
                    {t('profile.profile_information')}
                </h2>
                <p className="mt-1 text-sm text-neutral-500 dark:text-neutral-400">
                    {t('profile.profile_information_intro')}
                </p>
            </header>

            <form onSubmit={submit} className="space-y-4">
                <Input
                    id="name"
                    name="name"
                    label={t('common.name')}
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
                    label={t('profile.email_address')}
                    value={data.email}
                    autoComplete="username"
                    onChange={(e) => setData('email', e.target.value)}
                    error={errors.email}
                    required
                />

                <Input
                    id="phone_e164"
                    type="tel"
                    name="phone_e164"
                    label="Meu telefone / WhatsApp"
                    value={data.phone_e164}
                    autoComplete="tel"
                    placeholder="+5511999999999"
                    onChange={(e) => setData('phone_e164', e.target.value.replace(/[\\s().-]/g, ''))}
                    error={errors.phone_e164}
                />
                <p className="-mt-2 text-xs text-neutral-500 dark:text-neutral-400">
                    Usado somente quando você pedir à IA para enviar algo para você. O destino sempre será mostrado para confirmação antes do envio.
                </p>

                {mustVerifyEmail && user.email_verified_at === null && (
                    <div className="rounded-lg bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 px-4 py-3 text-sm text-amber-800 dark:text-amber-300">
                        <Trans
                            i18nKey="profile.email_unverified"
                            components={{
                                resend: (
                                    <Link
                                        href={route('verification.send')}
                                        method="post"
                                        as="button"
                                        className="font-medium underline hover:no-underline"
                                    />
                                ),
                            }}
                        />
                        {status === 'verification-link-sent' && (
                            <p className="mt-1 text-green-700 dark:text-green-400">
                                {t('profile.verification_link_sent')}
                            </p>
                        )}
                    </div>
                )}

                <div className="flex items-center gap-4">
                    <Button type="submit" variant="primary" disabled={processing}>
                        {processing ? t('common.saving') : t('profile.save_changes')}
                    </Button>
                    {saved && (
                        <p className="text-sm text-green-600 dark:text-green-400">{t('profile.saved')}</p>
                    )}
                </div>
            </form>
        </section>
    );
}
