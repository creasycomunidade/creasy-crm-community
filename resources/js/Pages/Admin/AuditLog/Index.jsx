import AdminLayout from '@/Layouts/AdminLayout';
import { Card, Pagination } from '@/Components/ui';
import { Head, router, usePage } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { formatInTz } from '@/Utils/datetime';

export default function AdminAuditLogIndex({ logs, filters = {} }) {
    const { t } = useTranslation();
    const adminTz = usePage().props.timezone || 'America/Sao_Paulo';
    return (
        <AdminLayout title={t('admin.audit_log')}>
            <Head title={`${t('admin.audit_log')} · ${t('head.admin')}`} />
            <div className="space-y-6">
                <h2 className="text-xl font-semibold text-neutral-900 dark:text-neutral-100">{t('admin.audit_log')}</h2>
                <form className="flex flex-wrap gap-2" onSubmit={(e) => { e.preventDefault(); const f = e.target; router.get(route('admin.audit-log.index'), { user_id: f.user_id?.value, action: f.action?.value }, { preserveState: true }); }}>
                    <input type="text" name="user_id" placeholder={t('admin.user_id')} className="rounded-soft border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 px-3 py-2 text-sm w-24 focus:outline-none focus:ring-2 focus:ring-brand-500/20" defaultValue={filters.user_id} />
                    <input type="text" name="action" placeholder={t('admin.action')} className="rounded-soft border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 px-3 py-2 text-sm w-32 focus:outline-none focus:ring-2 focus:ring-brand-500/20" defaultValue={filters.action} />
                    <button type="submit" className="rounded-soft border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 px-3 py-2 text-sm hover:bg-neutral-50 dark:hover:bg-neutral-700 focus:outline-none focus:ring-2 focus:ring-brand-500/20">{t('common.filter')}</button>
                </form>
                <Card>
                    <div className="overflow-x-auto">
                        <table className="min-w-full text-sm">
                            <thead>
                                <tr className="border-b border-neutral-200 dark:border-neutral-700 text-left text-neutral-500 dark:text-neutral-400">
                                    <th className="pb-2 pr-4 font-medium">{t('client.date')}</th>
                                    <th className="pb-2 pr-4 font-medium">{t('client.user')}</th>
                                    <th className="pb-2 pr-4 font-medium">{t('client.action')}</th>
                                    <th className="pb-2 pr-4 font-medium">{t('admin.col_auditable')}</th>
                                    <th className="pb-2 pr-4 font-medium">{t('admin.col_ip')}</th>
                                </tr>
                            </thead>
                            <tbody>
                                {logs.data?.map((l) => (
                                    <tr key={l.id} className="border-b border-neutral-100 dark:border-neutral-800">
                                        <td className="py-3 pr-4 text-neutral-500 dark:text-neutral-400">{l.created_at && formatInTz(l.created_at, adminTz)}</td>
                                        <td className="py-3 pr-4 text-neutral-900 dark:text-neutral-100">{l.actor_admin ? l.actor_admin.email : (l.user ? l.user.email : '—')}</td>
                                        <td className="py-3 pr-4 font-medium text-neutral-900 dark:text-neutral-100">{l.action}</td>
                                        <td className="py-3 pr-4 text-neutral-900 dark:text-neutral-100">{l.auditable_type ? `${l.auditable_type}#${l.auditable_id}` : '—'}</td>
                                        <td className="py-3 pr-4 text-neutral-500 dark:text-neutral-400">{l.ip ?? '—'}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    {logs.data?.length === 0 && <div className="py-8 text-center text-neutral-500 dark:text-neutral-400">{t('client.no_audit_entries')}</div>}
                    <Pagination data={logs} />
                </Card>
            </div>
        </AdminLayout>
    );
}
