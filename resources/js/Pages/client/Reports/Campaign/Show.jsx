import ClientLayout from '@/Layouts/ClientLayout';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { KpiCard, FunnelChart, LineChart, DonutChart } from '@/Components/Charts';
import { Download, ArrowLeft, Filter, Clock } from 'lucide-react';
import { useState } from 'react';
import { formatInTz } from '@/Utils/datetime';
import { useTranslation } from 'react-i18next';

const STATUS_COLORS = {
    queued: 'bg-gray-100 text-gray-700',
    sent: 'bg-blue-100 text-blue-700',
    delivered: 'bg-emerald-100 text-emerald-700',
    read: 'bg-violet-100 text-violet-700',
    failed: 'bg-red-100 text-red-700',
};

function humanSeconds(seconds) {
    if (!seconds || seconds <= 0) return '—';
    if (seconds < 60) return `${seconds}s`;
    if (seconds < 3600) return `${Math.round(seconds / 60)}m ${seconds % 60}s`;
    const h = Math.floor(seconds / 3600);
    const m = Math.round((seconds % 3600) / 60);
    return `${h}h ${m}m`;
}

export default function CampaignReportShow({
    campaign,
    kpis,
    funnel,
    deliveryOverTime,
    failedReasons,
    lag,
    recipients,
    filters,
}) {
    const { t } = useTranslation();
    const userTz = usePage().props.timezone || 'America/Sao_Paulo';
    const [statusFilter, setStatusFilter] = useState(filters.status ?? '');

    const applyFilter = (status) => {
        setStatusFilter(status);
        router.get(
            route('client.reports.campaigns.show', campaign.uuid),
            { status: status || undefined },
            { preserveState: true },
        );
    };

    const exportUrl = route('reports.exports.campaign-recipients', campaign.uuid);

    return (
        <ClientLayout title={t('reports.campaign_report_title', { name: campaign.name })}>
            <Head title={t('reports.campaign_report_head', { name: campaign.name })} />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <div className="flex items-center gap-3">
                        <Link
                            href={route('client.campaigns.show', campaign.uuid)}
                            className="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
                        >
                            <ArrowLeft className="h-5 w-5" />
                        </Link>
                        <div>
                            <h1 className="text-xl font-bold text-gray-900 dark:text-white">{campaign.name}</h1>
                            <p className="text-sm text-gray-500 dark:text-gray-400 capitalize">
                                {t('reports.campaign_channel_subtitle', { channel: campaign.channel })}
                            </p>
                        </div>
                    </div>
                    <a
                        href={exportUrl}
                        className="inline-flex items-center gap-2 rounded-lg bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 px-3 py-2 text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700"
                    >
                        <Download className="h-4 w-4" />
                        {t('contacts_page.export_csv')}
                    </a>
                </div>

                {/* KPI strip */}
                <div className="grid grid-cols-2 gap-4 lg:grid-cols-6">
                    <KpiCard label={t('reports.kpi_total_recipients')} value={kpis.total} />
                    <KpiCard label={t('reports.kpi_delivered')} value={kpis.delivered_pct} unit="%" trend="up" />
                    <KpiCard label={t('reports.kpi_read')} value={kpis.read_pct} unit="%" trend="up" />
                    <KpiCard label={t('reports.kpi_failed')} value={kpis.failed_pct} unit="%" trend="down" />
                    <KpiCard label={t('reports.kpi_clicked')} value={kpis.clicked_pct ?? 0} unit="%" />
                    <KpiCard label={t('reports.kpi_opted_out')} value={kpis.opted_out ?? 0} />
                </div>

                {/* Status lag */}
                {(lag?.sent_to_delivered || lag?.delivered_to_read || lag?.sent_to_read) ? (
                    <div className="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-4">
                        <h3 className="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-3 flex items-center gap-2">
                            <Clock className="h-4 w-4" /> {t('reports.status_timeline')}
                        </h3>
                        <div className="grid grid-cols-1 gap-3 sm:grid-cols-3 text-sm">
                            <LagCard label={t('reports.lag_sent_to_delivered')} value={lag.sent_to_delivered} color="text-blue-600" />
                            <LagCard label={t('reports.lag_delivered_to_read')} value={lag.delivered_to_read} color="text-violet-600" />
                            <LagCard label={t('reports.lag_sent_to_read')} value={lag.sent_to_read} color="text-emerald-600" />
                        </div>
                    </div>
                ) : null}

                {/* Charts row */}
                <div className="grid grid-cols-1 gap-4 lg:grid-cols-3">
                    <div className="lg:col-span-1 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-4">
                        <h3 className="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-3">
                            {t('reports.delivery_funnel')}
                        </h3>
                        {funnel.length > 0 ? (
                            <FunnelChart data={funnel} nameKey="name" valueKey="value" height={250} />
                        ) : (
                            <p className="text-sm text-gray-400 py-8 text-center">{t('reports.no_delivery_data')}</p>
                        )}
                    </div>
                    <div className="lg:col-span-2 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-4">
                        <h3 className="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-3">
                            {t('reports.delivery_over_time')}
                        </h3>
                        {deliveryOverTime.length > 0 ? (
                            <LineChart
                                data={deliveryOverTime}
                                xKey="hour"
                                yKeys={['sent', 'delivered', 'read']}
                                labels={{ sent: t('reports.series_sent'), delivered: t('reports.series_delivered'), read: t('reports.series_read') }}
                                height={250}
                            />
                        ) : (
                            <p className="text-sm text-gray-400 py-8 text-center">{t('reports.no_timeseries')}</p>
                        )}
                    </div>
                </div>

                {/* Failed reasons + recipients */}
                <div className="grid grid-cols-1 gap-4 lg:grid-cols-3">
                    <div className="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-4">
                        <h3 className="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-3">
                            {t('reports.failed_reasons')}
                        </h3>
                        {failedReasons.length > 0 ? (
                            <DonutChart data={failedReasons} nameKey="name" valueKey="value" height={220} />
                        ) : (
                            <p className="text-sm text-gray-400 py-8 text-center">{t('reports.no_failures')}</p>
                        )}
                    </div>
                    <div className="lg:col-span-2 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-4">
                        <div className="flex items-center justify-between mb-3">
                            <h3 className="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">
                                {t('reports.recipients')}
                            </h3>
                            <div className="flex items-center gap-2">
                                <Filter className="h-4 w-4 text-gray-400" />
                                <select
                                    value={statusFilter}
                                    onChange={(e) => applyFilter(e.target.value)}
                                    className="text-xs rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200"
                                >
                                    <option value="">{t('reports.all_statuses')}</option>
                                    {['queued', 'sent', 'delivered', 'read', 'failed'].map((s) => (
                                        <option key={s} value={s}>
                                            {s}
                                        </option>
                                    ))}
                                </select>
                            </div>
                        </div>
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b border-gray-200 dark:border-gray-700">
                                        <th className="pb-2 text-left text-xs text-gray-500 dark:text-gray-400">
                                            {t('contacts_page.contact_alt')}
                                        </th>
                                        <th className="pb-2 text-left text-xs text-gray-500 dark:text-gray-400">
                                            {t('reports.col_status')}
                                        </th>
                                        <th className="pb-2 text-left text-xs text-gray-500 dark:text-gray-400">
                                            {t('reports.col_sent_at')}
                                        </th>
                                        <th className="pb-2 text-left text-xs text-gray-500 dark:text-gray-400">
                                            {t('reports.col_delivered_at')}
                                        </th>
                                        <th className="pb-2 text-left text-xs text-gray-500 dark:text-gray-400">
                                            {t('reports.col_read_at')}
                                        </th>
                                        <th className="pb-2 text-left text-xs text-gray-500 dark:text-gray-400">
                                            {t('reports.col_reason')}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100 dark:divide-gray-700/50">
                                    {recipients.data.map((r) => {
                                        const c = r.contact ?? {};
                                        const name =
                                            `${c.first_name ?? ''} ${c.last_name ?? ''}`.trim() ||
                                            c.phone_e164 ||
                                            c.email ||
                                            `#${r.contact_id}`;
                                        return (
                                            <tr key={r.id}>
                                                <td className="py-2 text-gray-800 dark:text-gray-200">
                                                    <div>{name}</div>
                                                    <div className="text-xs text-gray-400">
                                                        {c.phone_e164 || c.email}
                                                    </div>
                                                </td>
                                                <td className="py-2">
                                                    <span
                                                        className={`inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ${
                                                            STATUS_COLORS[r.status] ?? ''
                                                        }`}
                                                    >
                                                        {r.status}
                                                    </span>
                                                </td>
                                                <td className="py-2 text-gray-500">
                                                    {r.sent_at ? formatInTz(r.sent_at, userTz) : '—'}
                                                </td>
                                                <td className="py-2 text-gray-500">
                                                    {r.delivered_at
                                                        ? formatInTz(r.delivered_at, userTz)
                                                        : '—'}
                                                </td>
                                                <td className="py-2 text-gray-500">
                                                    {r.read_at ? formatInTz(r.read_at, userTz) : '—'}
                                                </td>
                                                <td className="py-2 text-gray-500 max-w-xs truncate" title={r.failed_reason ?? ''}>
                                                    {r.failed_reason ?? '—'}
                                                </td>
                                            </tr>
                                        );
                                    })}
                                    {recipients.data.length === 0 && (
                                        <tr>
                                            <td colSpan={6} className="py-6 text-center text-gray-400">
                                                {t('reports.no_recipients')}
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                        {/* Pagination */}
                        {recipients.last_page > 1 && (
                            <div className="mt-4 flex items-center justify-between text-xs text-gray-500">
                                <span>
                                    {t('reports.page_of', { current: recipients.current_page, total: recipients.last_page })}
                                </span>
                                <div className="flex gap-2">
                                    {recipients.prev_page_url && (
                                        <Link href={recipients.prev_page_url} className="hover:underline">
                                            {t('common.previous')}
                                        </Link>
                                    )}
                                    {recipients.next_page_url && (
                                        <Link href={recipients.next_page_url} className="hover:underline">
                                            {t('common.next')}
                                        </Link>
                                    )}
                                </div>
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </ClientLayout>
    );
}

function LagCard({ label, value, color }) {
    return (
        <div className="rounded-lg border border-gray-200 dark:border-gray-700 p-3">
            <div className="text-xs text-gray-500 dark:text-gray-400">{label}</div>
            <div className={`mt-1 text-lg font-semibold ${color}`}>{humanSeconds(value)}</div>
        </div>
    );
}
