import { Head, usePage } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { ArrowLeft } from 'lucide-react';
import { formatInTz } from '@/Utils/datetime';
import { useTranslation } from 'react-i18next';

const ACTION_COLORS = {
    create:  'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300',
    update:  'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/40 dark:text-yellow-300',
    delete:  'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300',
    enable:  'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300',
    disable: 'bg-neutral-100 text-neutral-600 dark:bg-neutral-700 dark:text-neutral-300',
    test:    'bg-purple-100 text-purple-700 dark:bg-purple-900/40 dark:text-purple-300',
    rotate:  'bg-orange-100 text-orange-700 dark:bg-orange-900/40 dark:text-orange-300',
};

export default function IntegrationsAuditLog({ logs }) {
    const { t } = useTranslation();
    const adminTz = usePage().props.timezone || 'America/Sao_Paulo';
    return (
        <AdminLayout title={t('integrations.audit_log_title')}>
            <Head title={`${t('integrations.audit_log_head')} · ${t('head.admin')}`} />
            <div className="space-y-6">
                <div className="flex items-center gap-3">
                    <a href={route('admin.integrations.index')} className="text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-200 transition">
                        <ArrowLeft className="h-5 w-5" />
                    </a>
                    <h2 className="text-xl font-semibold text-neutral-900 dark:text-neutral-100">{t('integrations.audit_log_heading')}</h2>
                </div>

                <div className="overflow-x-auto rounded-xl border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-900">
                    <table className="min-w-full divide-y divide-neutral-200 dark:divide-neutral-700 text-sm">
                        <thead className="bg-neutral-50 dark:bg-neutral-800">
                            <tr>
                                {[
                                    t('integrations.col_time'),
                                    t('integrations.col_admin'),
                                    t('integrations.col_provider'),
                                    t('integrations.col_action'),
                                    t('integrations.col_changed_keys'),
                                    t('integrations.col_ip'),
                                ].map(h => (
                                    <th key={h} className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-neutral-500 dark:text-neutral-400">
                                        {h}
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-neutral-100 dark:divide-neutral-800">
                            {logs.data.map(log => (
                                <tr key={log.id} className="hover:bg-neutral-50 dark:hover:bg-neutral-800/50">
                                    <td className="px-4 py-3 text-neutral-500 dark:text-neutral-400 whitespace-nowrap">
                                        {formatInTz(log.created_at, adminTz)}
                                    </td>
                                    <td className="px-4 py-3 text-neutral-800 dark:text-neutral-200 whitespace-nowrap">
                                        {log.admin?.email ?? '—'}
                                    </td>
                                    <td className="px-4 py-3 font-mono text-xs text-neutral-600 dark:text-neutral-300">
                                        {log.provider}
                                    </td>
                                    <td className="px-4 py-3">
                                        <span className={`rounded-full px-2 py-0.5 text-xs font-medium ${ACTION_COLORS[log.action] ?? ''}`}>
                                            {log.action}
                                        </span>
                                    </td>
                                    <td className="px-4 py-3 text-xs text-neutral-500 dark:text-neutral-400 font-mono">
                                        {log.diff_json?.length ? log.diff_json.join(', ') : '—'}
                                    </td>
                                    <td className="px-4 py-3 text-neutral-400 dark:text-neutral-500 text-xs whitespace-nowrap">
                                        {log.ip}
                                    </td>
                                </tr>
                            ))}
                            {logs.data.length === 0 && (
                                <tr>
                                    <td colSpan={6} className="px-4 py-8 text-center text-neutral-400">{t('integrations.no_audit_entries')}</td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                {/* Simple pagination */}
                {logs.last_page > 1 && (
                    <div className="flex gap-2">
                        {logs.links.map((link, i) => (
                            <a
                                key={i}
                                href={link.url ?? '#'}
                                className={`px-3 py-1.5 rounded text-sm border ${link.active ? 'bg-brand-600 text-white border-brand-600' : 'border-neutral-300 dark:border-neutral-600 text-neutral-700 dark:text-neutral-300 hover:bg-neutral-50 dark:hover:bg-neutral-800'} ${!link.url ? 'opacity-40 pointer-events-none' : ''}`}
                                dangerouslySetInnerHTML={{ __html: link.label }}
                            />
                        ))}
                    </div>
                )}
            </div>
        </AdminLayout>
    );
}
