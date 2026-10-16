import AdminLayout from '@/Layouts/AdminLayout';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { CheckCircle, Clock, LifeBuoy, MessageSquare, Plus, TrendingUp } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { formatDateTz } from '@/Utils/datetime';

const STATUS_CLS = {
    open:        'bg-emerald-50 text-emerald-700 border border-emerald-200 dark:bg-emerald-900/20 dark:text-emerald-400 dark:border-emerald-800',
    in_progress: 'bg-blue-50 text-blue-700 border border-blue-200 dark:bg-blue-900/20 dark:text-blue-400 dark:border-blue-800',
    closed:      'bg-neutral-100 text-neutral-500 border border-neutral-200 dark:bg-neutral-700/50 dark:text-neutral-400 dark:border-neutral-600',
};
const STATUS_DOT = {
    open: 'bg-emerald-500',
    in_progress: 'bg-blue-500',
    closed: 'bg-neutral-400',
};

const PRIORITY_STYLES = {
    urgent: { cls: 'text-red-600 dark:text-red-400 font-semibold', dot: 'bg-red-500' },
    high:   { cls: 'text-orange-500 dark:text-orange-400 font-medium', dot: 'bg-orange-500' },
    normal: { cls: 'text-neutral-500 dark:text-neutral-400', dot: 'bg-neutral-400' },
    low:    { cls: 'text-neutral-400 dark:text-neutral-500', dot: 'bg-neutral-300' },
};

const TAB_VALUES = ['all', 'open', 'in_progress', 'closed'];

function StatCard({ icon: Icon, label, value, iconCls }) {
    return (
        <div className="bg-white dark:bg-neutral-800/70 rounded-xl border border-neutral-200 dark:border-neutral-700/50 p-4 flex items-center gap-3 shadow-soft">
            <div className={`p-2 rounded-soft-lg ${iconCls}`}>
                <Icon className="h-4 w-4" />
            </div>
            <div>
                <p className="text-2xl font-bold text-neutral-900 dark:text-white tabular-nums">{value}</p>
                <p className="text-xs text-neutral-500 dark:text-neutral-400">{label}</p>
            </div>
        </div>
    );
}

export default function AdminSupportIndex({ tickets, stats, filters }) {
    const { t } = useTranslation();
    const adminTz = usePage().props.timezone || 'America/Sao_Paulo';
    const [activeTab, setActiveTab] = useState(filters?.status ?? 'all');
    const TABS = TAB_VALUES.map(v => ({ value: v, label: v === 'all' ? t('support_tickets.tab_all') : t(`support_tickets.${v}`) }));

    const handleTab = (tab) => {
        setActiveTab(tab);
        router.get(route('admin.support.index'), tab !== 'all' ? { status: tab } : {}, { preserveState: false, replace: true });
    };

    return (
        <AdminLayout title={t('support_tickets.admin_title')}>
            <Head title={t('support_tickets.admin_title')} />
            <div className="space-y-6">
                {/* Header */}
                <div className="flex items-center gap-3">
                    <div className="p-2 bg-brand-50 dark:bg-brand-900/30 rounded-soft-lg">
                        <LifeBuoy className="h-5 w-5 text-brand-600 dark:text-brand-400" />
                    </div>
                    <div>
                        <h1 className="text-xl font-bold text-neutral-900 dark:text-white">{t('support_tickets.admin_title')}</h1>
                        <p className="text-sm text-neutral-500 dark:text-neutral-400">{t('support_tickets.admin_subtitle')}</p>
                    </div>
                    <Link
                        href={route('admin.support.create')}
                        className="ml-auto flex items-center gap-2 px-4 py-2 bg-brand-600 hover:bg-brand-700 text-white text-sm font-medium rounded-soft-lg transition-colors shadow-soft"
                    >
                        <Plus className="h-4 w-4" />
                        {t('support_tickets.new_ticket')}
                    </Link>
                </div>

                {/* Stats */}
                {stats && (
                    <div className="grid grid-cols-2 lg:grid-cols-4 gap-3">
                        <StatCard icon={TrendingUp}  label={t('support_tickets.total')}       value={stats.total}       iconCls="bg-brand-50 dark:bg-brand-900/30 text-brand-600 dark:text-brand-400" />
                        <StatCard icon={LifeBuoy}    label={t('support_tickets.open')}        value={stats.open}        iconCls="bg-emerald-50 dark:bg-emerald-900/30 text-emerald-600 dark:text-emerald-400" />
                        <StatCard icon={Clock}       label={t('support_tickets.in_progress')} value={stats.in_progress} iconCls="bg-blue-50 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400" />
                        <StatCard icon={CheckCircle} label={t('support_tickets.closed')}      value={stats.closed}      iconCls="bg-neutral-100 dark:bg-neutral-700 text-neutral-500 dark:text-neutral-400" />
                    </div>
                )}

                {/* Filter Tabs */}
                <div className="flex items-center gap-1 p-1 bg-neutral-100 dark:bg-neutral-800 rounded-xl w-fit border border-neutral-200 dark:border-neutral-700">
                    {TABS.map(tab => (
                        <button
                            key={tab.value}
                            onClick={() => handleTab(tab.value)}
                            className={`px-3 py-1.5 text-xs font-medium rounded-soft-lg transition-all ${activeTab === tab.value
                                ? 'bg-white dark:bg-neutral-700 text-neutral-900 dark:text-white shadow-soft'
                                : 'text-neutral-500 dark:text-neutral-400 hover:text-neutral-700 dark:hover:text-neutral-300'
                            }`}
                        >
                            {tab.label}
                            {tab.value !== 'all' && stats?.[tab.value] > 0 && (
                                <span className={`ml-1.5 px-1.5 py-0.5 rounded-full text-xs ${activeTab === tab.value ? 'bg-brand-100 dark:bg-brand-900/50 text-brand-600 dark:text-brand-400' : 'bg-neutral-200 dark:bg-neutral-600 text-neutral-500 dark:text-neutral-400'}`}>
                                    {stats[tab.value]}
                                </span>
                            )}
                        </button>
                    ))}
                </div>

                {/* Table */}
                <div className="overflow-x-auto bg-white dark:bg-neutral-800/70 rounded-xl border border-neutral-200 dark:border-neutral-700/50 shadow-soft">
                    {tickets.data?.length > 0 ? (
                        <>
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b border-neutral-100 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800">
                                        <th className="px-4 py-3 text-left text-xs font-semibold text-neutral-500 dark:text-neutral-400 uppercase tracking-wide">{t('support_tickets.col_ticket')}</th>
                                        <th className="px-4 py-3 text-left text-xs font-semibold text-neutral-500 dark:text-neutral-400 uppercase tracking-wide hidden sm:table-cell">{t('support_tickets.col_customer')}</th>
                                        <th className="px-4 py-3 text-left text-xs font-semibold text-neutral-500 dark:text-neutral-400 uppercase tracking-wide hidden md:table-cell">{t('support_tickets.col_priority')}</th>
                                        <th className="px-4 py-3 text-left text-xs font-semibold text-neutral-500 dark:text-neutral-400 uppercase tracking-wide">{t('support_tickets.col_status')}</th>
                                        <th className="px-4 py-3 text-left text-xs font-semibold text-neutral-500 dark:text-neutral-400 uppercase tracking-wide hidden md:table-cell">{t('support_tickets.col_replies')}</th>
                                        <th className="px-4 py-3 text-left text-xs font-semibold text-neutral-500 dark:text-neutral-400 uppercase tracking-wide hidden lg:table-cell">{t('support_tickets.col_submitted')}</th>
                                        <th className="px-4 py-3" />
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-neutral-50 dark:divide-neutral-700/50">
                                    {tickets.data.map(ticket => {
                                        const stCls = STATUS_CLS[ticket.status] ?? STATUS_CLS.open;
                                        const stDot = STATUS_DOT[ticket.status] ?? STATUS_DOT.open;
                                        const stLabel = t(`support_tickets.${ticket.status}`) || ticket.status;
                                        const pr = PRIORITY_STYLES[ticket.priority] ?? PRIORITY_STYLES.normal;
                                        return (
                                            <tr key={ticket.id} className="hover:bg-neutral-50 dark:hover:bg-neutral-700/30 transition-colors">
                                                <td className="px-4 py-3.5">
                                                    <div className="flex items-start gap-2">
                                                        <span className="text-xs text-neutral-400 dark:text-neutral-500 tabular-nums mt-0.5">#{ticket.id}</span>
                                                        <div>
                                                            <Link href={route('admin.support.show', ticket.id)} className="font-medium text-neutral-900 dark:text-white hover:text-brand-600 dark:hover:text-brand-400 transition-colors line-clamp-1">
                                                                {ticket.subject}
                                                            </Link>
                                                            <p className="text-xs text-neutral-400 dark:text-neutral-500 mt-0.5 sm:hidden">{ticket.name}</p>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td className="px-4 py-3.5 hidden sm:table-cell">
                                                    <p className="text-sm text-neutral-700 dark:text-neutral-300">{ticket.name}</p>
                                                    <p className="text-xs text-neutral-400 dark:text-neutral-500">{ticket.email}</p>
                                                </td>
                                                <td className="px-4 py-3.5 hidden md:table-cell">
                                                    <div className="flex items-center gap-1.5">
                                                        <span className={`h-1.5 w-1.5 rounded-full ${pr.dot}`} />
                                                        <span className={`text-xs capitalize ${pr.cls}`}>{ticket.priority}</span>
                                                    </div>
                                                </td>
                                                <td className="px-4 py-3.5">
                                                    <span className={`inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium ${stCls}`}>
                                                        <span className={`h-1.5 w-1.5 rounded-full ${stDot}`} />
                                                        {stLabel}
                                                    </span>
                                                </td>
                                                <td className="px-4 py-3.5 hidden md:table-cell">
                                                    <div className="flex items-center gap-1 text-neutral-500 dark:text-neutral-400">
                                                        <MessageSquare className="h-3.5 w-3.5" />
                                                        <span className="text-xs">{ticket.replies_count}</span>
                                                    </div>
                                                </td>
                                                <td className="px-4 py-3.5 text-xs text-neutral-400 dark:text-neutral-500 hidden lg:table-cell tabular-nums">
                                                    {formatDateTz(ticket.created_at, adminTz)}
                                                </td>
                                                <td className="px-4 py-3.5 text-right">
                                                    <Link href={route('admin.support.show', ticket.id)} className="text-xs text-brand-600 dark:text-brand-400 hover:text-brand-700 dark:hover:text-brand-300 font-medium">
                                                        {t('support_tickets.view')}
                                                    </Link>
                                                </td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>

                            {tickets.last_page > 1 && (
                                <div className="flex items-center justify-between px-4 py-3 border-t border-neutral-100 dark:border-neutral-700">
                                    <p className="text-xs text-neutral-500 dark:text-neutral-400">
                                        {t('support_tickets.showing', { from: tickets.from, to: tickets.to, total: tickets.total })}
                                    </p>
                                    <div className="flex gap-1">
                                        {tickets.links?.map((link, i) => (
                                            link.url ? (
                                                <Link key={i} href={link.url} className={`px-3 py-1 text-xs rounded-soft border transition-colors ${link.active ? 'bg-brand-600 text-white border-brand-600' : 'border-neutral-200 dark:border-neutral-600 text-neutral-600 dark:text-neutral-400 hover:bg-neutral-50 dark:hover:bg-neutral-700'}`} dangerouslySetInnerHTML={{ __html: link.label }} />
                                            ) : (
                                                <span key={i} className="px-3 py-1 text-xs rounded-soft border border-neutral-100 dark:border-neutral-700 text-neutral-300 dark:text-neutral-600 cursor-not-allowed" dangerouslySetInnerHTML={{ __html: link.label }} />
                                            )
                                        ))}
                                    </div>
                                </div>
                            )}
                        </>
                    ) : (
                        <div className="flex flex-col items-center justify-center py-16 text-center">
                            <div className="p-4 bg-neutral-100 dark:bg-neutral-700 rounded-full mb-4">
                                <LifeBuoy className="h-8 w-8 text-neutral-400 dark:text-neutral-500" />
                            </div>
                            <p className="text-sm font-medium text-neutral-600 dark:text-neutral-400">{t('support_tickets.no_tickets_found')}</p>
                            <p className="text-xs text-neutral-400 dark:text-neutral-500 mt-1">
                                {activeTab !== 'all' ? t(`support_tickets.${activeTab}`) : t('support_tickets.no_tickets_admin_desc')}
                            </p>
                        </div>
                    )}
                </div>
            </div>
        </AdminLayout>
    );
}
