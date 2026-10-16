import ClientLayout from '@/Layouts/ClientLayout';
import { Head, router } from '@inertiajs/react';
import { LineChart, DonutChart } from '@/Components/Charts';
import { DatePicker } from '@/Components/ui';
import { Download, Clock, CheckCircle } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';

function formatSeconds(seconds) {
    if (seconds == null) return '—';
    if (seconds < 60)   return `${seconds}s`;
    if (seconds < 3600) return `${Math.round(seconds / 60)}m`;
    return `${(seconds / 3600).toFixed(1)}h`;
}

export default function InboxReportIndex({ conversationsOverTime, channelMix, agentLeaderboard, firstResponseTimes, resolutionTimes, dateRange, isDashboard = false }) {
    const { t } = useTranslation();
    const pageTitle = isDashboard ? t('nav.dashboard') : t('reports.inbox_title');
    const [from, setFrom] = useState(dateRange.from);
    const [to, setTo] = useState(dateRange.to);

    // Compute overall medians for KPI cards
    const validFirstResponse = (firstResponseTimes ?? []).filter(r => r.median_seconds != null);
    const validResolution     = (resolutionTimes    ?? []).filter(r => r.median_seconds != null);
    const overallMedianFirstResponse = validFirstResponse.length
        ? validFirstResponse.reduce((s, r) => s + r.median_seconds, 0) / validFirstResponse.length
        : null;
    const overallMedianResolution = validResolution.length
        ? validResolution.reduce((s, r) => s + r.median_seconds, 0) / validResolution.length
        : null;

    const applyRange = () => {
        router.get(route(isDashboard ? 'client.dashboard' : 'client.reports.inbox.index'), { from, to }, { preserveState: true });
    };

    const exportLeaderboardCsv = () => {
        const headers = ['Agent', 'Conversations Handled', 'Avg First Response (min)'];
        const rows = agentLeaderboard.map(r => [r.name, r.handled, r.avg_first_response_min ?? '']);
        const csv = [headers, ...rows].map(r => r.join(',')).join('\n');
        const blob = new Blob([csv], { type: 'text/csv' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url; a.download = 'agent-leaderboard.csv'; a.click();
        URL.revokeObjectURL(url);
    };

    return (
        <ClientLayout title={pageTitle}>
            <Head title={pageTitle} />

            <div className="space-y-6">
                {/* Header + date range */}
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <h1 className="text-xl font-bold text-gray-900 dark:text-white">{pageTitle}</h1>
                        <p className="text-sm text-gray-500 dark:text-gray-400">{t('reports.inbox_subtitle')}</p>
                    </div>
                    <div className="flex items-center gap-2">
                        <DatePicker
                            value={from}
                            onChange={setFrom}
                            className="w-40"
                        />
                        <span className="text-gray-500">—</span>
                        <DatePicker
                            value={to}
                            onChange={setTo}
                            className="w-40"
                        />
                        <button
                            onClick={applyRange}
                            className="rounded-lg bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-500"
                        >
                            {t('reports.apply')}
                        </button>
                    </div>
                </div>

                {/* SLA KPI cards */}
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div className="flex items-center gap-4 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-4">
                        <div className="h-10 w-10 rounded-full bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center">
                            <Clock className="h-5 w-5 text-blue-600 dark:text-blue-400" />
                        </div>
                        <div>
                            <p className="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">{t('reports.median_first_response')}</p>
                            <p className="text-2xl font-bold text-gray-900 dark:text-white">{formatSeconds(overallMedianFirstResponse != null ? Math.round(overallMedianFirstResponse) : null)}</p>
                        </div>
                    </div>
                    <div className="flex items-center gap-4 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-4">
                        <div className="h-10 w-10 rounded-full bg-green-100 dark:bg-green-900/30 flex items-center justify-center">
                            <CheckCircle className="h-5 w-5 text-green-600 dark:text-green-400" />
                        </div>
                        <div>
                            <p className="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">{t('reports.median_resolution')}</p>
                            <p className="text-2xl font-bold text-gray-900 dark:text-white">{formatSeconds(overallMedianResolution != null ? Math.round(overallMedianResolution) : null)}</p>
                        </div>
                    </div>
                </div>

                {/* Charts row */}
                <div className="grid grid-cols-1 gap-4 lg:grid-cols-3">
                    <div className="lg:col-span-2 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-4">
                        <h3 className="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-3">{t('reports.opened_vs_resolved')}</h3>
                        <LineChart
                            data={conversationsOverTime}
                            xKey="date"
                            yKeys={['opened', 'resolved']}
                            labels={{ opened: t('reports.series_opened'), resolved: t('reports.series_resolved') }}
                            height={280}
                        />
                    </div>
                    <div className="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-4">
                        <h3 className="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-3">{t('reports.channel_mix')}</h3>
                        {channelMix.length > 0 ? (
                            <DonutChart data={channelMix} nameKey="name" valueKey="value" height={280} />
                        ) : (
                            <p className="text-sm text-gray-400 py-16 text-center">{t('reports.no_data')}</p>
                        )}
                    </div>
                </div>

                {/* SLA trend chart */}
                {(firstResponseTimes?.length > 0) && (
                    <div className="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-4">
                        <h3 className="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-3">{t('reports.sla_trend')}</h3>
                        <LineChart
                            data={firstResponseTimes.map((r, i) => ({
                                date: r.date,
                                'First Response': r.median_seconds ?? 0,
                                'Resolution': (resolutionTimes?.[i]?.median_seconds ?? 0),
                            }))}
                            xKey="date"
                            yKeys={['First Response', 'Resolution']}
                            labels={{ 'First Response': t('reports.series_first_response'), 'Resolution': t('reports.series_resolution') }}
                            height={240}
                        />
                    </div>
                )}

                {/* Agent leaderboard */}
                <div className="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-4">
                    <div className="flex items-center justify-between mb-3">
                        <h3 className="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">{t('reports.agent_leaderboard')}</h3>
                        <button
                            onClick={exportLeaderboardCsv}
                            className="inline-flex items-center gap-1.5 text-xs text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200"
                        >
                            <Download className="h-3.5 w-3.5" />
                            {t('contacts_page.export_csv')}
                        </button>
                    </div>
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b border-gray-200 dark:border-gray-700">
                                    <th className="pb-2 text-left text-xs text-gray-500">{t('reports.col_agent')}</th>
                                    <th className="pb-2 text-right text-xs text-gray-500">{t('reports.col_conversations_handled')}</th>
                                    <th className="pb-2 text-right text-xs text-gray-500">{t('reports.col_avg_first_response')}</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100 dark:divide-gray-700/50">
                                {agentLeaderboard.map((row) => (
                                    <tr key={row.user_id}>
                                        <td className="py-2 font-medium text-gray-800 dark:text-gray-200">{row.name}</td>
                                        <td className="py-2 text-right text-gray-600 dark:text-gray-400">{row.handled}</td>
                                        <td className="py-2 text-right text-gray-600 dark:text-gray-400">
                                            {row.avg_first_response_min != null ? `${row.avg_first_response_min} min` : '—'}
                                        </td>
                                    </tr>
                                ))}
                                {agentLeaderboard.length === 0 && (
                                    <tr>
                                        <td colSpan={3} className="py-6 text-center text-gray-400">{t('reports.no_assigned_conversations')}</td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </ClientLayout>
    );
}
