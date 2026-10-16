import ClientLayout from '@/Layouts/ClientLayout';
import { Head, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { FileText } from 'lucide-react';
import { formatInTz } from '@/Utils/datetime';

export default function ClientAuditLogIndex({ logs, filters = {} }) {
    const { t } = useTranslation();
    const userTz = usePage().props.timezone || 'America/Sao_Paulo';
    const formatDate = (iso) => formatInTz(iso, userTz, { dateStyle: undefined, timeStyle: undefined, year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit', timeZoneName: undefined });
    const [actionFilter, setActionFilter] = useState(filters.action ?? '');

    const submitFilters = (e) => {
        e.preventDefault();
        router.get(route('client.audit-log.index'), { action: actionFilter || undefined }, { preserveState: true });
    };

    const data = logs?.data ?? [];
    const links = logs?.links ?? [];

    return (
        <ClientLayout title={t('client.audit_log') || 'Audit log'}>
            <Head title={t('client.audit_log') || 'Audit log'} />
            <div className="space-y-6">
                <div>
                    <h1 className="text-2xl font-bold text-neutral-900 dark:text-white">
                        {t('client.audit_log') || 'Audit log'}
                    </h1>
                    <p className="mt-1 text-sm text-neutral-500 dark:text-neutral-400">
                        {t('client.audit_log_subtitle') || 'Activity for your organization'}
                    </p>
                </div>

                <form onSubmit={submitFilters} className="flex flex-wrap gap-2 items-center">
                    <input
                        type="text"
                        value={actionFilter}
                        onChange={(e) => setActionFilter(e.target.value)}
                        placeholder={t('client.filter_by_action') || 'Filter by action'}
                        className="rounded-lg border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 px-3 py-2 text-sm w-48"
                    />
                    <button type="submit" className="rounded-lg bg-neutral-200 dark:bg-neutral-700 px-3 py-2 text-sm font-medium text-neutral-800 dark:text-neutral-200 hover:bg-neutral-300 dark:hover:bg-neutral-600">
                        {t('client.filter') || 'Filter'}
                    </button>
                </form>

                <div className="rounded-xl border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-800/50 overflow-hidden">
                    {data.length > 0 ? (
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-neutral-200 dark:divide-neutral-700">
                                <thead className="bg-neutral-50 dark:bg-neutral-800">
                                    <tr>
                                        <th className="px-4 py-3 text-left text-xs font-medium text-neutral-500 uppercase">
                                            {t('client.date') || 'Date'}
                                        </th>
                                        <th className="px-4 py-3 text-left text-xs font-medium text-neutral-500 uppercase">
                                            {t('client.user') || 'User'}
                                        </th>
                                        <th className="px-4 py-3 text-left text-xs font-medium text-neutral-500 uppercase">
                                            {t('client.action') || 'Action'}
                                        </th>
                                        <th className="px-4 py-3 text-left text-xs font-medium text-neutral-500 uppercase">
                                            {t('client.details') || 'Details'}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-neutral-200 dark:divide-neutral-700">
                                    {data.map((log) => (
                                        <tr key={log.id} className="bg-white dark:bg-neutral-800/30">
                                            <td className="px-4 py-3 text-sm text-neutral-600 dark:text-neutral-400 whitespace-nowrap">
                                                {formatDate(log.created_at)}
                                            </td>
                                            <td className="px-4 py-3 text-sm text-neutral-900 dark:text-white">
                                                {log.user?.name ?? log.user?.email ?? '–'}
                                            </td>
                                            <td className="px-4 py-3 text-sm font-mono text-neutral-700 dark:text-neutral-300">
                                                {log.action}
                                            </td>
                                            <td className="px-4 py-3 text-sm text-neutral-600 dark:text-neutral-400">
                                                {log.meta && Object.keys(log.meta).length > 0
                                                    ? JSON.stringify(log.meta)
                                                    : '–'}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    ) : (
                        <div className="px-6 py-12 text-center text-neutral-500 dark:text-neutral-400">
                            <FileText className="mx-auto h-12 w-12 text-neutral-400 mb-3" />
                            <p>{t('client.no_audit_entries') || 'No audit entries yet'}</p>
                        </div>
                    )}
                </div>

                {links?.length > 0 && (
                    <div className="flex justify-center gap-2">
                        {logs.prev_page_url && (
                            <a
                                href={logs.prev_page_url}
                                className="rounded-lg border border-neutral-300 dark:border-neutral-600 px-3 py-2 text-sm hover:bg-neutral-50 dark:hover:bg-neutral-700"
                            >
                                {t('pagination.previous') || 'Previous'}
                            </a>
                        )}
                        {logs.next_page_url && (
                            <a
                                href={logs.next_page_url}
                                className="rounded-lg border border-neutral-300 dark:border-neutral-600 px-3 py-2 text-sm hover:bg-neutral-50 dark:hover:bg-neutral-700"
                            >
                                {t('pagination.next') || 'Next'}
                            </a>
                        )}
                    </div>
                )}
            </div>
        </ClientLayout>
    );
}
