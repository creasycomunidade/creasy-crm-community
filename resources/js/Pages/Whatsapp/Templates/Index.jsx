import { Head, Link, router, usePage } from '@inertiajs/react';
import ClientLayout from '@/Layouts/ClientLayout';
import EmptyState from '@/Components/EmptyState';
import TemplatePreview from '@/Components/TemplatePreview';
import { Plus, RefreshCw, CheckCircle, XCircle, Clock, PauseCircle, FileText, Search, Phone, Pencil, Trash2 } from 'lucide-react';
import { useState, useCallback, useRef } from 'react';
import { useTranslation } from 'react-i18next';

const STATUS_CONFIG = {
    APPROVED: { color: 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300', icon: <CheckCircle className="h-3 w-3" />, labelKey: 'whatsapp.templates_status_approved' },
    REJECTED: { color: 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300',         icon: <XCircle className="h-3 w-3" />, labelKey: 'whatsapp.templates_status_rejected' },
    PENDING:  { color: 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/40 dark:text-yellow-300', icon: <Clock className="h-3 w-3" />, labelKey: 'whatsapp.templates_status_pending' },
    PAUSED:   { color: 'bg-neutral-100 text-neutral-600 dark:bg-neutral-700 dark:text-neutral-300', icon: <PauseCircle className="h-3 w-3" />, labelKey: 'whatsapp.templates_status_paused' },
};

export default function WhatsappTemplatesIndex({ templates, phoneNumbers = [], filters }) {
    const { t } = useTranslation();
    const { props } = usePage();
    const flash = props.flash ?? {};
    const pageErrors = props.errors ?? {};

    const [search, setSearch] = useState(filters.search ?? '');
    const debounceTimer = useRef(null);

    const applyFilters = useCallback((patch) => {
        router.get(
            route('client.whatsapp.templates.index'),
            { ...filters, ...patch },
            { preserveState: true, replace: true },
        );
    }, [filters]);

    const handleSearch = (e) => {
        const value = e.target.value;
        setSearch(value);
        clearTimeout(debounceTimer.current);
        debounceTimer.current = setTimeout(() => {
            applyFilters({ search: value || undefined });
        }, 400);
    };

    const handleSync = () => router.post(route('client.whatsapp.templates.sync'), {}, { preserveScroll: true });
    const handleStatus = (status) => applyFilters({ status: status || undefined });
    const handlePhone = (e) => applyFilters({ phone_number_id: e.target.value || undefined });

    const handleDelete = (tpl) => {
        if (!window.confirm(t('whatsapp.templates_delete_confirm', { name: tpl.name }))) return;
        router.delete(route('client.whatsapp.templates.destroy', tpl.id), { preserveScroll: true });
    };

    return (
        <ClientLayout title={t('whatsapp.templates_title')}>
            <Head title={t('whatsapp.templates_title')} />
            <div className="space-y-5">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 className="text-xl font-semibold text-neutral-900 dark:text-neutral-100">{t('whatsapp.templates_heading')}</h2>
                        <p className="text-sm text-neutral-500 dark:text-neutral-400 mt-0.5">{t('whatsapp.templates_subtitle')}</p>
                    </div>
                    <div className="flex gap-2">
                        <button onClick={handleSync} className="flex items-center gap-1.5 rounded-lg border border-neutral-300 dark:border-neutral-600 px-3 py-2 text-sm text-neutral-700 dark:text-neutral-300 hover:bg-neutral-50 dark:hover:bg-neutral-800 transition">
                            <RefreshCw className="h-4 w-4" /> {t('whatsapp.templates_sync')}
                        </button>
                        {(
                            <Link href={route('client.whatsapp.templates.create')} className="flex items-center gap-1.5 rounded-lg bg-brand-600 px-3 py-2 text-sm font-medium text-white hover:bg-brand-700 transition">
                                <Plus className="h-4 w-4" /> {t('whatsapp.templates_new')}
                            </Link>
                        )}
                    </div>
                </div>

                {flash.success && <div className="rounded-lg bg-green-50 dark:bg-green-900/30 text-green-800 dark:text-green-200 px-4 py-2 text-sm">{flash.success}</div>}
                {flash.error   && <div className="rounded-lg bg-red-50 dark:bg-red-900/30 text-red-800 dark:text-red-200 px-4 py-2 text-sm">{flash.error}</div>}
                {pageErrors.sync && <div className="rounded-lg bg-red-50 dark:bg-red-900/30 text-red-800 dark:text-red-200 px-4 py-2 text-sm">{pageErrors.sync}</div>}

                {/* Search + Phone filter bar */}
                <div className="flex flex-wrap gap-3">
                    <div className="relative flex-1 min-w-48">
                        <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-neutral-400 pointer-events-none" />
                        <input
                            type="text"
                            value={search}
                            onChange={handleSearch}
                            placeholder={t('whatsapp.templates_search_placeholder')}
                            className="w-full rounded-lg border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-900 pl-9 pr-3 py-2 text-sm text-neutral-900 dark:text-neutral-100 placeholder-neutral-400 focus:outline-none focus:ring-2 focus:ring-brand-500"
                        />
                    </div>

                    {phoneNumbers.length > 0 && (
                        <div className="relative">
                            <Phone className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-neutral-400 pointer-events-none" />
                            <select
                                value={filters.phone_number_id ?? ''}
                                onChange={handlePhone}
                                className="rounded-lg border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-900 pl-9 pr-8 py-2 text-sm text-neutral-900 dark:text-neutral-100 focus:outline-none focus:ring-2 focus:ring-brand-500 appearance-none min-w-48"
                            >
                                <option value="">{t('whatsapp.templates_all_phone_numbers')}</option>
                                {phoneNumbers.map(p => (
                                    <option key={p.phone_number_id} value={p.phone_number_id}>
                                        {p.display_phone}{p.verified_name ? ` · ${p.verified_name}` : ''}
                                    </option>
                                ))}
                            </select>
                        </div>
                    )}
                </div>

                {/* Status filter tabs */}
                <div className="flex gap-2 flex-wrap">
                    {[null, 'APPROVED', 'PENDING', 'REJECTED', 'PAUSED'].map(s => (
                        <button
                            key={s ?? 'all'}
                            onClick={() => handleStatus(s)}
                            className={`rounded-full px-3 py-1.5 text-xs font-medium transition ${(filters.status ?? null) === s ? 'bg-brand-600 text-white' : 'bg-neutral-100 text-neutral-600 dark:bg-neutral-800 dark:text-neutral-400 hover:bg-neutral-200 dark:hover:bg-neutral-700'}`}
                        >
                            {s ? t(STATUS_CONFIG[s].labelKey) : t('whatsapp.templates_status_all')}
                        </button>
                    ))}
                </div>

                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    {templates.map(tpl => {
                        const sc = STATUS_CONFIG[tpl.status] ?? STATUS_CONFIG.PENDING;
                        return (
                            <div key={tpl.id} className="flex flex-col rounded-xl border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-900 p-5 space-y-3">
                                <div className="flex items-start justify-between gap-2">
                                    <span className="font-mono font-medium text-sm text-neutral-900 dark:text-neutral-100 break-all">{tpl.name}</span>
                                    <span className={`flex shrink-0 items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium ${sc.color}`}>
                                        {sc.icon} {STATUS_CONFIG[tpl.status] ? t(STATUS_CONFIG[tpl.status].labelKey) : tpl.status}
                                    </span>
                                </div>
                                <div className="flex gap-2 text-xs text-neutral-500 dark:text-neutral-400">
                                    <span>{tpl.category}</span>
                                    <span>·</span>
                                    <span>{tpl.language}</span>
                                </div>

                                <TemplatePreview components={tpl.components ?? []} />

                                {tpl.rejection_reason && (
                                    <p className="text-xs text-red-500 dark:text-red-400">{tpl.rejection_reason}</p>
                                )}

                                <div className="flex gap-2 pt-1 mt-auto border-t border-neutral-100 dark:border-neutral-800">
                                    <Link
                                        href={route('client.whatsapp.templates.edit', tpl.id)}
                                        className="flex flex-1 items-center justify-center gap-1.5 rounded-lg border border-neutral-300 dark:border-neutral-600 px-3 py-1.5 text-xs font-medium text-neutral-700 dark:text-neutral-300 hover:bg-neutral-50 dark:hover:bg-neutral-800 transition mt-2"
                                    >
                                        <Pencil className="h-3.5 w-3.5" /> {t('common.edit')}
                                    </Link>
                                    <button
                                        onClick={() => handleDelete(tpl)}
                                        className="flex items-center justify-center gap-1.5 rounded-lg border border-red-200 dark:border-red-900/50 px-3 py-1.5 text-xs font-medium text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 transition mt-2"
                                    >
                                        <Trash2 className="h-3.5 w-3.5" /> {t('common.delete')}
                                    </button>
                                </div>
                            </div>
                        );
                    })}
                    {templates.length === 0 && (
                        <div className="col-span-3">
                            <EmptyState
                                icon={<FileText className="h-8 w-8" />}
                                title={t('whatsapp.templates_empty_title')}
                                description={t('whatsapp.templates_empty_description')}
                                action={{ label: t('whatsapp.templates_new'), href: route('client.whatsapp.templates.create') }}
                                secondaryAction={{ label: t('whatsapp.templates_sync'), onClick: handleSync }}
                            />
                        </div>
                    )}
                </div>
            </div>
        </ClientLayout>
    );
}
