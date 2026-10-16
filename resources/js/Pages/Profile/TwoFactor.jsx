import ClientLayout from '@/Layouts/ClientLayout';
import { Head, useForm, usePage } from '@inertiajs/react';
import { Button, Card, Input } from '@/Components/ui';
import { useState } from 'react';
import { ShieldCheck, ShieldOff, RefreshCw } from 'lucide-react';
import { useTranslation } from 'react-i18next';

export default function TwoFactor({ enabled, qrCode, secretKey, recoveryCodes = [] }) {
    const { t } = useTranslation();
    const { flash } = usePage().props;
    const [showCodes, setShowCodes] = useState(false);

    const enableForm = useForm({ code: '' });
    const disableForm = useForm({ password: '' });
    const regenForm = useForm({ password: '' });

    const handleEnable = (e) => {
        e.preventDefault();
        enableForm.post(route('client.profile.2fa.enable'), { preserveScroll: true });
    };

    const handleDisable = (e) => {
        e.preventDefault();
        disableForm.delete(route('client.profile.2fa.disable'), { preserveScroll: true });
    };

    const handleRegen = (e) => {
        e.preventDefault();
        regenForm.post(route('client.profile.2fa.recovery-codes'), { preserveScroll: true });
    };

    return (
        <ClientLayout title={t('profile.two_factor')}>
            <Head title={t('profile.two_factor')} />
            <div className="space-y-6 max-w-2xl">
                {flash?.success && (
                    <div className="rounded-lg bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 px-4 py-3 text-sm text-green-800 dark:text-green-300">
                        {flash.success}
                    </div>
                )}

                <Card>
                    <Card.Body>
                        <div className="flex items-start gap-4">
                            <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400">
                                {enabled ? <ShieldCheck className="h-5 w-5" /> : <ShieldOff className="h-5 w-5" />}
                            </div>
                            <div className="flex-1">
                                <h2 className="text-base font-semibold text-neutral-900 dark:text-neutral-100">
                                    {t('profile.two_factor')}
                                </h2>
                                <p className="mt-1 text-sm text-neutral-500 dark:text-neutral-400">
                                    {enabled
                                        ? t('profile.two_factor_enabled_desc')
                                        : t('profile.two_factor_disabled_desc')}
                                </p>
                            </div>
                        </div>

                        {!enabled && qrCode && (
                            <div className="mt-6 space-y-4">
                                <p className="text-sm text-neutral-600 dark:text-neutral-400">
                                    {t('profile.two_factor_scan_instructions')}
                                </p>
                                <div className="flex flex-col items-start gap-4 sm:flex-row">
                                    <div className="rounded-lg bg-white p-2 shadow border border-neutral-200">
                                        <img
                                            src={`https://api.qrserver.com/v1/create-qr-code/?data=${encodeURIComponent(qrCode)}&size=160x160`}
                                            alt={t('profile.two_factor_qr_alt')}
                                            className="h-40 w-40"
                                        />
                                    </div>
                                    <div>
                                        <p className="text-xs text-neutral-500 dark:text-neutral-400 mb-1">{t('profile.two_factor_enter_manually')}</p>
                                        <code className="rounded bg-neutral-100 dark:bg-neutral-800 px-2 py-1 text-sm font-mono text-neutral-800 dark:text-neutral-200">
                                            {secretKey}
                                        </code>
                                    </div>
                                </div>
                                <form onSubmit={handleEnable} className="flex gap-3 items-end">
                                    <Input
                                        label={t('profile.two_factor_verification_code')}
                                        value={enableForm.data.code}
                                        onChange={(e) => enableForm.setData('code', e.target.value)}
                                        placeholder="000000"
                                        className="w-40"
                                        maxLength={6}
                                        error={enableForm.errors.code}
                                    />
                                    <Button type="submit" variant="primary" disabled={enableForm.processing}>
                                        {t('profile.two_factor_enable')}
                                    </Button>
                                </form>
                            </div>
                        )}

                        {enabled && (
                            <div className="mt-6 space-y-4">
                                <div>
                                    <div className="flex items-center justify-between mb-2">
                                        <h3 className="text-sm font-medium text-neutral-700 dark:text-neutral-300">{t('profile.two_factor_recovery_codes')}</h3>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            onClick={() => setShowCodes(!showCodes)}
                                        >
                                            {showCodes ? t('profile.two_factor_hide_codes') : t('profile.two_factor_show_codes')}
                                        </Button>
                                    </div>
                                    {showCodes && recoveryCodes.length > 0 && (
                                        <div className="rounded-lg bg-neutral-50 dark:bg-neutral-800/50 border border-neutral-200 dark:border-neutral-700 p-3 grid grid-cols-2 gap-1">
                                            {recoveryCodes.map((code, i) => (
                                                <code key={i} className="text-xs font-mono text-neutral-700 dark:text-neutral-300">{code}</code>
                                            ))}
                                        </div>
                                    )}
                                    <form onSubmit={handleRegen} className="mt-3 flex gap-3 items-end">
                                        <Input
                                            label={t('profile.two_factor_confirm_regenerate')}
                                            type="password"
                                            value={regenForm.data.password}
                                            onChange={(e) => regenForm.setData('password', e.target.value)}
                                            className="flex-1 max-w-xs"
                                            error={regenForm.errors.password}
                                        />
                                        <Button type="submit" variant="outline" size="sm" disabled={regenForm.processing}>
                                            <RefreshCw className="h-4 w-4 mr-1" />
                                            {t('profile.two_factor_regenerate')}
                                        </Button>
                                    </form>
                                </div>

                                <div className="pt-4 border-t border-neutral-200 dark:border-neutral-700">
                                    <p className="text-sm text-neutral-600 dark:text-neutral-400 mb-3">
                                        {t('profile.two_factor_disable_warning')}
                                    </p>
                                    <form onSubmit={handleDisable} className="flex gap-3 items-end">
                                        <Input
                                            label={t('profile.two_factor_confirm_disable')}
                                            type="password"
                                            value={disableForm.data.password}
                                            onChange={(e) => disableForm.setData('password', e.target.value)}
                                            className="flex-1 max-w-xs"
                                            error={disableForm.errors.password}
                                        />
                                        <Button type="submit" variant="destructive" disabled={disableForm.processing}>
                                            {t('profile.two_factor_disable')}
                                        </Button>
                                    </form>
                                </div>
                            </div>
                        )}
                    </Card.Body>
                </Card>
            </div>
        </ClientLayout>
    );
}
