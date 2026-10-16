import ClientLayout from '@/Layouts/ClientLayout';
import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowLeft, Check, X } from 'lucide-react';
import { formatInTz } from '@/Utils/datetime';
import { useTranslation } from 'react-i18next';

export default function WebhookDeliveries({ endpoint, deliveries }) {
    const { t } = useTranslation();
    const userTz = usePage().props.timezone || 'America/Sao_Paulo';
    return (
        <ClientLayout title={t('webhook.deliveries_title')}>
            <Head title={t('webhook.deliveries_title')} />
            <div className="space-y-6 max-w-4xl">
                <div className="flex items-center gap-3">
                    <Link href={route('client.webhooks.index')} className="text-gray-400 hover:text-gray-700 dark:hover:text-gray-200">
                        <ArrowLeft className="h-5 w-5" />
                    </Link>
                    <div>
                        <h1 className="text-xl font-bold text-gray-900 dark:text-white">{t('webhook.deliveries_heading')}</h1>
                        <p className="text-sm text-gray-500 dark:text-gray-400 font-mono truncate">{endpoint.url}</p>
                    </div>
                </div>

                <div className="overflow-x-auto bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700">
                    <table className="min-w-[720px] w-full text-sm">
                        <thead className="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400">{t('webhook.col_status')}</th>
                                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400">{t('webhook.col_event')}</th>
                                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400">{t('webhook.col_http')}</th>
                                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400">{t('webhook.col_attempts')}</th>
                                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400">{t('webhook.col_date')}</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-100 dark:divide-gray-700">
                            {deliveries.data?.map(d => (
                                <tr key={d.id}>
                                    <td className="px-4 py-3">
                                        {d.response_status >= 200 && d.response_status < 300
                                            ? <span className="inline-flex items-center gap-1 text-green-600 text-xs"><Check className="h-3.5 w-3.5" /> {t('webhook.status_ok')}</span>
                                            : <span className="inline-flex items-center gap-1 text-red-500 text-xs"><X className="h-3.5 w-3.5" /> {t('webhook.status_failed')}</span>
                                        }
                                    </td>
                                    <td className="px-4 py-3"><code className="text-xs">{d.event}</code></td>
                                    <td className="px-4 py-3 text-gray-600 dark:text-gray-400">{d.response_status ?? '—'}</td>
                                    <td className="px-4 py-3 text-gray-600 dark:text-gray-400">{d.attempts}</td>
                                    <td className="px-4 py-3 text-gray-500 dark:text-gray-400 text-xs">{formatInTz(d.created_at, userTz)}</td>
                                </tr>
                            ))}
                            {!deliveries.data?.length && (
                                <tr>
                                    <td colSpan={5} className="px-4 py-8 text-center text-gray-400 dark:text-gray-500">{t('webhook.no_deliveries')}</td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </ClientLayout>
    );
}
