import ClientLayout from '@/Layouts/ClientLayout';
import { Head, router } from '@inertiajs/react';
import { BarChart, DonutChart } from '@/Components/Charts';
import { DatePicker } from '@/Components/ui';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';

export default function AutomationReportIndex({ runsByStatus, runsPerAutomation, topErrors, dateRange }) {
    const { t } = useTranslation();
    const [from, setFrom] = useState(dateRange.from);
    const [to, setTo] = useState(dateRange.to);

    const applyRange = () => {
        router.get(route('client.reports.automations.index'), { from, to }, { preserveState: true });
    };

    // Collect unique status keys for stacked bar
    const statusKeys = [...new Set(runsByStatus.flatMap(d => Object.keys(d).filter(k => k !== 'date')))];

    const statusDonutData = runsPerAutomation.reduce((acc, row) => {
        acc[0] = (acc[0] ?? 0) + row.completed;
        acc[1] = (acc[1] ?? 0) + row.failed;
        acc[2] = (acc[2] ?? 0) + Math.max(0, row.runs - row.completed - row.failed);
        return acc;
    }, {});

    const donutData = [
        { name: t('reports.col_completed'), value: statusDonutData[0] ?? 0 },
        { name: t('reports.col_failed'), value: statusDonutData[1] ?? 0 },
        { name: t('reports.status_running_other'), value: statusDonutData[2] ?? 0 },
    ].filter(d => d.value > 0);

    return (
        <ClientLayout title={t('reports.automation_title')}>
            <Head title={t('reports.automation_title')} />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <h1 className="text-xl font-bold text-gray-900 dark:text-white">{t('reports.automation_title')}</h1>
                        <p className="text-sm text-gray-500 dark:text-gray-400">{t('reports.automation_subtitle')}</p>
                    </div>
                    <div className="flex items-center gap-2">
                        <DatePicker value={from} onChange={setFrom} className="w-40" />
                        <span className="text-gray-500">—</span>
                        <DatePicker value={to} onChange={setTo} className="w-40" />
                        <button onClick={applyRange} className="rounded-lg bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-500">{t('reports.apply')}</button>
                    </div>
                </div>

                {/* Charts */}
                <div className="grid grid-cols-1 gap-4 lg:grid-cols-3">
                    <div className="lg:col-span-2 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-4">
                        <h3 className="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-3">{t('reports.runs_per_day')}</h3>
                        <BarChart data={runsByStatus} xKey="date" yKeys={statusKeys} stacked height={260} />
                    </div>
                    <div className="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-4">
                        <h3 className="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-3">{t('reports.overall_status')}</h3>
                        {donutData.length > 0 ? (
                            <DonutChart data={donutData} nameKey="name" valueKey="value" height={260} />
                        ) : (
                            <p className="text-sm text-gray-400 py-16 text-center">{t('reports.no_data')}</p>
                        )}
                    </div>
                </div>

                {/* Per-automation table */}
                <div className="overflow-x-auto bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-4">
                    <h3 className="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-3">{t('reports.runs_per_automation')}</h3>
                    <table className="min-w-[560px] w-full text-sm">
                        <thead>
                            <tr className="border-b border-gray-200 dark:border-gray-700">
                                <th className="pb-2 text-left text-xs text-gray-500">{t('reports.col_automation')}</th>
                                <th className="pb-2 text-right text-xs text-gray-500">{t('reports.col_total_runs')}</th>
                                <th className="pb-2 text-right text-xs text-gray-500">{t('reports.col_completed')}</th>
                                <th className="pb-2 text-right text-xs text-gray-500">{t('reports.col_failed')}</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-100 dark:divide-gray-700/50">
                            {runsPerAutomation.map(r => (
                                <tr key={r.automation_id}>
                                    <td className="py-2 font-medium text-gray-800 dark:text-gray-200">{r.name}</td>
                                    <td className="py-2 text-right text-gray-600 dark:text-gray-400">{r.runs}</td>
                                    <td className="py-2 text-right text-emerald-600">{r.completed}</td>
                                    <td className="py-2 text-right text-red-500">{r.failed}</td>
                                </tr>
                            ))}
                            {runsPerAutomation.length === 0 && (
                                <tr><td colSpan={4} className="py-6 text-center text-gray-400">{t('reports.no_runs')}</td></tr>
                            )}
                        </tbody>
                    </table>
                </div>

                {/* Top errors */}
                {topErrors.length > 0 && (
                    <div className="overflow-x-auto bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-4">
                        <h3 className="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-3">{t('reports.top_errors')}</h3>
                        <table className="min-w-[480px] w-full text-sm">
                            <thead>
                                <tr className="border-b border-gray-200 dark:border-gray-700">
                                    <th className="pb-2 text-left text-xs text-gray-500">{t('reports.col_error')}</th>
                                    <th className="pb-2 text-right text-xs text-gray-500">{t('reports.col_count')}</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100 dark:divide-gray-700/50">
                                {topErrors.map((e, i) => (
                                    <tr key={i}>
                                        <td className="py-2 text-gray-700 dark:text-gray-300 font-mono text-xs">{e.message}</td>
                                        <td className="py-2 text-right text-red-500">{e.count}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>
        </ClientLayout>
    );
}
