import ClientLayout from '@/Layouts/ClientLayout';
import { Head, router } from '@inertiajs/react';
import { KpiCard, BarChart, DonutChart } from '@/Components/Charts';
import { DatePicker } from '@/Components/ui';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';

export default function AiReportIndex({ kpis, tokensByDay, tokensByModel, topChatbots, dateRange }) {
    const { t } = useTranslation();
    const [from, setFrom] = useState(dateRange.from);
    const [to, setTo] = useState(dateRange.to);

    const applyRange = () => {
        router.get(route('client.reports.ai.index'), { from, to }, { preserveState: true });
    };

    return (
        <ClientLayout title={t('reports.ai_title')}>
            <Head title={t('reports.ai_title')} />

            <div className="space-y-6">
                {/* Header + date range */}
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <h1 className="text-xl font-bold text-gray-900 dark:text-white">{t('reports.ai_title')}</h1>
                        <p className="text-sm text-gray-500 dark:text-gray-400">{t('reports.ai_subtitle')}</p>
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

                {/* KPI strip */}
                <div className="grid grid-cols-2 gap-4 lg:grid-cols-4">
                    <KpiCard label={t('reports.kpi_total_tokens')} value={kpis.total_tokens} />
                    <KpiCard label={t('reports.kpi_est_cost')} value={`$${(kpis.total_cost_cents / 100).toFixed(2)}`} />
                    <KpiCard label={t('reports.kpi_avg_latency')} value={kpis.avg_latency_ms} unit="ms" trend="down" />
                    <KpiCard label={t('reports.kpi_error_rate')} value={kpis.error_rate} unit="%" trend="down" />
                </div>

                {/* Charts */}
                <div className="grid grid-cols-1 gap-4 lg:grid-cols-3">
                    <div className="lg:col-span-2 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-4">
                        <h3 className="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-3">{t('reports.tokens_per_day')}</h3>
                        <BarChart
                            data={tokensByDay}
                            xKey="date"
                            yKeys={['prompt', 'completion']}
                            labels={{ prompt: t('client.chart_prompt'), completion: t('client.chart_completion') }}
                            stacked
                            height={280}
                        />
                    </div>
                    <div className="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-4">
                        <h3 className="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-3">{t('reports.tokens_by_model')}</h3>
                        {tokensByModel.length > 0 ? (
                            <DonutChart data={tokensByModel} nameKey="name" valueKey="value" height={280} />
                        ) : (
                            <p className="text-sm text-gray-400 py-16 text-center">{t('reports.no_data')}</p>
                        )}
                    </div>
                </div>

                {/* Top chatbots table */}
                <div className="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-4">
                    <h3 className="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-3">{t('reports.top_chatbots')}</h3>
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b border-gray-200 dark:border-gray-700">
                                    <th className="pb-2 text-left text-xs text-gray-500">{t('reports.col_chatbot')}</th>
                                    <th className="pb-2 text-right text-xs text-gray-500">{t('reports.col_runs')}</th>
                                    <th className="pb-2 text-right text-xs text-gray-500">{t('reports.col_tokens')}</th>
                                    <th className="pb-2 text-right text-xs text-gray-500">{t('reports.col_avg_latency')}</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100 dark:divide-gray-700/50">
                                {topChatbots.map((row) => (
                                    <tr key={row.chatbot_id}>
                                        <td className="py-2 font-medium text-gray-800 dark:text-gray-200">{row.name}</td>
                                        <td className="py-2 text-right text-gray-600 dark:text-gray-400">{row.runs.toLocaleString()}</td>
                                        <td className="py-2 text-right text-gray-600 dark:text-gray-400">{row.tokens.toLocaleString()}</td>
                                        <td className="py-2 text-right text-gray-600 dark:text-gray-400">{row.avg_latency_ms}ms</td>
                                    </tr>
                                ))}
                                {topChatbots.length === 0 && (
                                    <tr>
                                        <td colSpan={4} className="py-6 text-center text-gray-400">{t('reports.no_ai_runs')}</td>
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
