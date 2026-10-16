import AdminLayout from '@/Layouts/AdminLayout';
import { Card } from '@/Components/ui';
import { Head } from '@inertiajs/react';
import { useForm } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';

export default function AdminCurrenciesIndex({ currencies = [], flash = {} }) {
    const { t } = useTranslation();
    return (
        <AdminLayout title={t('admin.nav.currencies')}>
            <Head title={`${t('admin.nav.currencies')} · ${t('head.admin')}`} />
            <div className="space-y-6">
                <h2 className="text-xl font-semibold text-neutral-900 dark:text-neutral-100">{t('admin.nav.currencies')}</h2>
                {flash?.success && <div className="rounded-soft-lg bg-green-50 dark:bg-green-900/30 text-green-800 dark:text-green-200 px-4 py-2 text-sm">{flash.success}</div>}
                <div className="rounded-soft-lg border border-brand-200/80 bg-brand-50/80 px-4 py-3 text-sm text-neutral-700 dark:border-brand-800/60 dark:bg-brand-950/30 dark:text-neutral-200">
                    <p>
                        <strong>{t('admin.exchange_rate_title')}</strong>{' '}
                        {t('admin.exchange_rate_help')}
                    </p>
                    <p className="mt-1 text-xs text-neutral-500 dark:text-neutral-400">
                        {t('admin.exchange_rate_source')}
                    </p>
                </div>
                <Card>
                    <div className="overflow-x-auto">
                        <table className="min-w-full text-sm">
                            <thead>
                                <tr className="border-b border-neutral-200 dark:border-neutral-700 text-left text-neutral-500 dark:text-neutral-400">
                                    <th className="pb-2 pr-4 font-medium">{t('admin.col_code')}</th>
                                    <th className="pb-2 pr-4 font-medium">{t('admin.col_symbol')}</th>
                                    <th className="pb-2 pr-4 font-medium">{t('admin.col_decimals')}</th>
                                    <th className="pb-2 pr-4 font-medium">{t('admin.col_exchange_rate')}</th>
                                    <th className="pb-2 pr-4 font-medium">{t('admin.col_default')}</th>
                                    <th className="pb-2 pr-4 font-medium">{t('common.enabled')}</th>
                                    <th className="pb-2"></th>
                                </tr>
                            </thead>
                            <tbody>
                                {currencies.map((c) => (
                                    <CurrencyRow key={c.code} currency={c} />
                                ))}
                                <tr className="border-t-2 border-neutral-200 dark:border-neutral-700">
                                    <td colSpan={7} className="pt-4 pb-1">
                                        <span className="text-sm font-semibold text-neutral-900 dark:text-neutral-100">
                                            {t('admin.add_currency')}
                                        </span>
                                    </td>
                                </tr>
                                <AddCurrencyRow />
                            </tbody>
                        </table>
                    </div>
                </Card>
            </div>
        </AdminLayout>
    );
}

function CurrencyRow({ currency }) {
    const { t } = useTranslation();
    const { data, setData, put, processing } = useForm({
        symbol: currency.symbol ?? '',
        decimals: currency.decimals ?? 2,
        exchange_rate: currency.exchange_rate ?? '',
        is_default: currency.is_default ?? false,
        enabled: currency.enabled ?? true,
    });
    return (
        <tr className="border-b border-neutral-100 dark:border-neutral-800">
            <td className="py-3 pr-4 font-medium text-neutral-900 dark:text-neutral-100">{currency.code}</td>
            <td className="py-3 pr-4"><input value={data.symbol} onChange={(e) => setData('symbol', e.target.value)} className="rounded-soft border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 px-2 py-1 w-16 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500/20" /></td>
            <td className="py-3 pr-4"><input type="number" value={data.decimals} onChange={(e) => setData('decimals', parseInt(e.target.value, 10) || 0)} className="rounded-soft border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 px-2 py-1 w-14 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500/20" /></td>
            <td className="py-3 pr-4"><input type="number" step="any" value={data.exchange_rate} onChange={(e) => setData('exchange_rate', e.target.value)} className="rounded-soft border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 px-2 py-1 w-20 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500/20" /></td>
            <td className="py-3 pr-4"><input type="checkbox" checked={data.is_default} onChange={(e) => setData('is_default', e.target.checked)} className="rounded border-neutral-300 dark:border-neutral-600 text-brand-500" /></td>
            <td className="py-3 pr-4"><input type="checkbox" checked={data.enabled} onChange={(e) => setData('enabled', e.target.checked)} className="rounded border-neutral-300 dark:border-neutral-600 text-brand-500" /></td>
            <td className="py-3"><button type="button" onClick={() => put(route('admin.currencies.update', currency.code))} className="text-brand-600 dark:text-brand-400 text-sm hover:underline font-medium" disabled={processing}>{t('common.save')}</button></td>
        </tr>
    );
}

function AddCurrencyRow() {
    const { t } = useTranslation();
    const { data, setData, post, processing, errors, reset } = useForm({
        code: '',
        symbol: '',
        decimals: 2,
        exchange_rate: '',
        is_default: false,
        enabled: true,
    });

    const submit = () => {
        post(route('admin.currencies.store'), {
            preserveScroll: true,
            onSuccess: () => reset(),
        });
    };

    const inputCls = 'rounded-soft border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 px-2 py-1 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500/20';

    return (
        <tr className="align-top bg-neutral-50/60 dark:bg-neutral-800/30">
            <td className="py-3 pr-4">
                <input value={data.code} onChange={(e) => setData('code', e.target.value.toUpperCase())} placeholder="BRL" maxLength={10} className={`${inputCls} w-20 uppercase`} />
                {errors.code && <div className="mt-1 text-xs text-red-600 dark:text-red-400">{errors.code}</div>}
            </td>
            <td className="py-3 pr-4">
                <input value={data.symbol} onChange={(e) => setData('symbol', e.target.value)} placeholder="$" className={`${inputCls} w-16`} />
                {errors.symbol && <div className="mt-1 text-xs text-red-600 dark:text-red-400">{errors.symbol}</div>}
            </td>
            <td className="py-3 pr-4"><input type="number" value={data.decimals} onChange={(e) => setData('decimals', parseInt(e.target.value, 10) || 0)} className={`${inputCls} w-14`} /></td>
            <td className="py-3 pr-4"><input type="number" step="any" value={data.exchange_rate} onChange={(e) => setData('exchange_rate', e.target.value)} placeholder="1" className={`${inputCls} w-20`} /></td>
            <td className="py-3 pr-4"><input type="checkbox" checked={data.is_default} onChange={(e) => setData('is_default', e.target.checked)} className="rounded border-neutral-300 dark:border-neutral-600 text-brand-500" /></td>
            <td className="py-3 pr-4"><input type="checkbox" checked={data.enabled} onChange={(e) => setData('enabled', e.target.checked)} className="rounded border-neutral-300 dark:border-neutral-600 text-brand-500" /></td>
            <td className="py-3"><button type="button" onClick={submit} className="inline-flex items-center rounded-soft bg-brand-600 hover:bg-brand-700 text-white text-sm font-medium px-4 py-1.5 disabled:opacity-50" disabled={processing || !data.code || !data.symbol}>{t('common.add', 'Add')}</button></td>
        </tr>
    );
}
