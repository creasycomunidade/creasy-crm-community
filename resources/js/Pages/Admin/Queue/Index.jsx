import AdminLayout from '@/Layouts/AdminLayout';
import { router, Link, usePage } from '@inertiajs/react';
import { Server, RefreshCw, Trash2, AlertTriangle } from 'lucide-react';
import { formatUnixTz } from '@/Utils/datetime';
import { useTranslation } from 'react-i18next';

export default function QueueIndex({ tab, failedJobs, batches }) {
    const { t } = useTranslation();
    const adminTz = usePage().props.timezone || 'America/Sao_Paulo';
    const tabs = [
        { id: 'failed', label: t('queue.failed_jobs') },
        { id: 'batches', label: t('queue.job_batches') },
    ];

    const retry = (id) => router.post(route('admin.queue.retry', id));
    const del   = (id) => { if (confirm(t('queue.delete_job_confirm'))) router.delete(route('admin.queue.delete-failed', id)); };

    return (
        <AdminLayout title={t('queue.title')}>
            <div className="max-w-6xl space-y-6">
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-3">
                        <Server className="h-6 w-6 text-indigo-600" />
                        <h1 className="text-xl font-bold text-gray-900 dark:text-white">{t('queue.title')}</h1>
                    </div>

                    {tab === 'failed' && (
                        <div className="flex items-center gap-2">
                            <button onClick={() => router.post(route('admin.queue.retry-all'))} className="flex items-center gap-1.5 px-3 py-1.5 text-sm border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700">
                                <RefreshCw className="h-3.5 w-3.5" /> {t('queue.retry_all')}
                            </button>
                            <button onClick={() => { if (confirm(t('queue.flush_confirm'))) router.post(route('admin.queue.flush')); }} className="flex items-center gap-1.5 px-3 py-1.5 text-sm border border-red-200 text-red-600 rounded-lg hover:bg-red-50 dark:hover:bg-red-900/10">
                                <Trash2 className="h-3.5 w-3.5" /> {t('queue.flush_all')}
                            </button>
                        </div>
                    )}
                </div>

                {/* Tabs */}
                <div className="flex gap-1 border-b border-gray-200 dark:border-gray-700">
                    {tabs.map(tb => (
                        <button key={tb.id} onClick={() => router.get(route('admin.queue.index'), { tab: tb.id })}
                            className={`px-4 py-2 text-sm font-medium border-b-2 -mb-px transition-colors ${tab === tb.id ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 dark:hover:text-gray-300'}`}>
                            {tb.label}
                            {tb.id === 'failed' && failedJobs?.total > 0 && (
                                <span className="ml-1.5 px-1.5 py-0.5 text-xs bg-red-100 text-red-600 rounded-full">{failedJobs.total}</span>
                            )}
                        </button>
                    ))}
                </div>

                {/* Failed Jobs */}
                {tab === 'failed' && (
                    <div className="overflow-x-auto bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700">
                        <table className="w-full text-sm">
                            <thead className="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th className="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-300">{t('queue.col_id')}</th>
                                    <th className="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-300">{t('queue.col_queue')}</th>
                                    <th className="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-300">{t('queue.col_job_class')}</th>
                                    <th className="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-300">{t('queue.col_exception')}</th>
                                    <th className="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-300">{t('queue.col_failed_at')}</th>
                                    <th className="px-4 py-3"></th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100 dark:divide-gray-700">
                                {failedJobs?.data?.map(job => (
                                    <tr key={job.id}>
                                        <td className="px-4 py-3 text-gray-400 text-xs font-mono">{job.id}</td>
                                        <td className="px-4 py-3 text-gray-600 dark:text-gray-300 text-xs">{job.queue}</td>
                                        <td className="px-4 py-3 font-medium text-gray-900 dark:text-white text-xs">{job.class}</td>
                                        <td className="px-4 py-3 text-gray-500 text-xs max-w-xs truncate" title={job.exception}>{job.exception}</td>
                                        <td className="px-4 py-3 text-gray-400 text-xs">{job.failed_at}</td>
                                        <td className="px-4 py-3">
                                            <div className="flex items-center gap-1 justify-end">
                                                <button onClick={() => retry(job.id)} className="p-1 text-gray-400 hover:text-indigo-600" title={t('queue.retry_title')}><RefreshCw className="h-3.5 w-3.5" /></button>
                                                <button onClick={() => del(job.id)} className="p-1 text-gray-400 hover:text-red-500" title={t('queue.delete_title')}><Trash2 className="h-3.5 w-3.5" /></button>
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                                {!failedJobs?.data?.length && (
                                    <tr><td colSpan={6} className="px-4 py-12 text-center text-gray-400">
                                        <AlertTriangle className="h-8 w-8 mx-auto mb-2 opacity-30" />
                                        {t('queue.no_failed_jobs')}
                                    </td></tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                )}

                {/* Job Batches */}
                {tab === 'batches' && (
                    <div className="overflow-x-auto bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700">
                        <table className="w-full text-sm">
                            <thead className="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th className="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-300">{t('queue.col_name')}</th>
                                    <th className="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-300">{t('queue.col_total')}</th>
                                    <th className="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-300">{t('queue.col_pending')}</th>
                                    <th className="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-300">{t('queue.col_failed')}</th>
                                    <th className="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-300">{t('queue.col_status')}</th>
                                    <th className="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-300">{t('queue.col_created')}</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100 dark:divide-gray-700">
                                {batches?.data?.map(b => (
                                    <tr key={b.id}>
                                        <td className="px-4 py-3 font-medium text-gray-900 dark:text-white text-xs">{b.name || <span className="text-gray-400 italic">{t('queue.unnamed')}</span>}</td>
                                        <td className="px-4 py-3 text-gray-600 dark:text-gray-300">{b.total_jobs}</td>
                                        <td className="px-4 py-3 text-gray-600 dark:text-gray-300">{b.pending_jobs}</td>
                                        <td className="px-4 py-3 text-gray-600 dark:text-gray-300">{b.failed_jobs}</td>
                                        <td className="px-4 py-3">
                                            <span className={`px-2 py-0.5 rounded-full text-xs font-medium ${b.finished_at ? 'bg-green-100 text-green-700' : b.cancelled_at ? 'bg-red-100 text-red-700' : 'bg-blue-100 text-blue-700'}`}>
                                                {b.finished_at ? t('queue.status_done') : b.cancelled_at ? t('queue.status_cancelled') : t('queue.status_running')}
                                            </span>
                                        </td>
                                        <td className="px-4 py-3 text-gray-400 text-xs">{formatUnixTz(b.created_at, adminTz)}</td>
                                    </tr>
                                ))}
                                {!batches?.data?.length && (
                                    <tr><td colSpan={6} className="px-4 py-8 text-center text-gray-400 dark:text-gray-500">{t('queue.no_batches')}</td></tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>
        </AdminLayout>
    );
}
