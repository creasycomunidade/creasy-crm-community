import ClientLayout from '@/Layouts/ClientLayout';
import { Head, router, usePage } from '@inertiajs/react';
import { BarChart, DonutChart } from '@/Components/Charts';
import { DatePicker } from '@/Components/ui';
import { useState } from 'react';
import { ExternalLink } from 'lucide-react';
import { formatInTz } from '@/Utils/datetime';
import { useTranslation } from 'react-i18next';

const STATUS_COLORS = {
    scheduled: 'bg-blue-100 text-blue-700',
    published: 'bg-emerald-100 text-emerald-700',
    failed: 'bg-red-100 text-red-700',
    draft: 'bg-gray-100 text-gray-600',
};

export default function SocialReportIndex({ postsByNetwork, postsByStatus, recentPosts, dateRange }) {
    const { t } = useTranslation();
    const userTz = usePage().props.timezone || 'America/Sao_Paulo';
    const [from, setFrom] = useState(dateRange.from);
    const [to, setTo] = useState(dateRange.to);

    const applyRange = () => {
        router.get(route('client.reports.social.index'), { from, to }, { preserveState: true });
    };

    return (
        <ClientLayout title={t('reports.social_title')}>
            <Head title={t('reports.social_title')} />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <h1 className="text-xl font-bold text-gray-900 dark:text-white">{t('reports.social_title')}</h1>
                        <p className="text-sm text-gray-500 dark:text-gray-400">{t('reports.social_subtitle')}</p>
                    </div>
                    <div className="flex items-center gap-2">
                        <DatePicker value={from} onChange={setFrom} className="w-40" />
                        <span className="text-gray-500">—</span>
                        <DatePicker value={to} onChange={setTo} className="w-40" />
                        <button onClick={applyRange} className="rounded-lg bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-500">{t('reports.apply')}</button>
                    </div>
                </div>

                {/* Charts */}
                <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
                    <div className="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-4">
                        <h3 className="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-3">{t('reports.posts_by_network')}</h3>
                        {postsByNetwork.length > 0 ? (
                            <BarChart data={postsByNetwork} xKey="name" yKeys={['value']} labels={{ value: t('reports.series_posts') }} height={240} />
                        ) : (
                            <p className="text-sm text-gray-400 py-16 text-center">{t('reports.no_posts')}</p>
                        )}
                    </div>
                    <div className="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-4">
                        <h3 className="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-3">{t('reports.status_breakdown')}</h3>
                        {postsByStatus.length > 0 ? (
                            <DonutChart data={postsByStatus} nameKey="name" valueKey="value" height={240} />
                        ) : (
                            <p className="text-sm text-gray-400 py-16 text-center">{t('reports.no_data')}</p>
                        )}
                    </div>
                </div>

                {/* Recent posts */}
                <div className="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-4">
                    <h3 className="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-3">{t('reports.recent_posts')}</h3>
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b border-gray-200 dark:border-gray-700">
                                    <th className="pb-2 text-left text-xs text-gray-500">{t('reports.col_title')}</th>
                                    <th className="pb-2 text-left text-xs text-gray-500">{t('reports.col_status')}</th>
                                    <th className="pb-2 text-left text-xs text-gray-500">{t('reports.col_scheduled')}</th>
                                    <th className="pb-2 text-left text-xs text-gray-500">{t('reports.col_published')}</th>
                                    <th className="pb-2 text-left text-xs text-gray-500">{t('reports.col_link')}</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100 dark:divide-gray-700/50">
                                {recentPosts.map(post => (
                                    <tr key={post.id}>
                                        <td className="py-2 font-medium text-gray-800 dark:text-gray-200 max-w-xs truncate">{post.title || t('reports.post_no_title')}</td>
                                        <td className="py-2">
                                            <span className={`inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ${STATUS_COLORS[post.status] ?? 'bg-gray-100 text-gray-600'}`}>
                                                {post.status}
                                            </span>
                                        </td>
                                        <td className="py-2 text-gray-500 text-xs">{post.scheduled_at ? formatInTz(post.scheduled_at, userTz) : '—'}</td>
                                        <td className="py-2 text-gray-500 text-xs">{post.published_at ? formatInTz(post.published_at, userTz) : '—'}</td>
                                        <td className="py-2">
                                            {post.post_url ? (
                                                <a href={post.post_url} target="_blank" rel="noopener noreferrer" className="text-indigo-500 hover:text-indigo-600">
                                                    <ExternalLink className="h-3.5 w-3.5" />
                                                </a>
                                            ) : '—'}
                                        </td>
                                    </tr>
                                ))}
                                {recentPosts.length === 0 && (
                                    <tr><td colSpan={5} className="py-6 text-center text-gray-400">{t('reports.no_posts_found')}</td></tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </ClientLayout>
    );
}
