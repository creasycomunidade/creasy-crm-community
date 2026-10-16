import { useState } from 'react';
import ClientLayout from '@/Layouts/ClientLayout';
import { Head, router, usePage } from '@inertiajs/react';
import { Bell, Check, CheckCheck, Trash2, Settings } from 'lucide-react';
import { formatInTz } from '@/Utils/datetime';
import { useTranslation } from 'react-i18next';

const KNOWN_EVENTS = [
    { key: 'subscription.created', labelKey: 'notifications_page.event_subscription_created' },
    { key: 'subscription.cancelled', labelKey: 'notifications_page.event_subscription_cancelled' },
    { key: 'subscription.renewed', labelKey: 'notifications_page.event_subscription_renewed' },
    { key: 'payment.succeeded', labelKey: 'notifications_page.event_payment_succeeded' },
    { key: 'payment.failed', labelKey: 'notifications_page.event_payment_failed' },
    { key: 'team.invite_accepted', labelKey: 'notifications_page.event_team_invite_accepted' },
];

function PreferencesPanel({ preferences }) {
    const { t } = useTranslation();
    const [prefs, setPrefs] = useState(() => {
        const initial = {};
        KNOWN_EVENTS.forEach(({ key }) => {
            initial[key] = {
                database: preferences[key]?.database ?? true,
                email: preferences[key]?.email ?? true,
            };
        });
        return initial;
    });

    const handleSave = () => {
        const flat = [];
        Object.entries(prefs).forEach(([event, channels]) => {
            Object.entries(channels).forEach(([channel, enabled]) => {
                flat.push({ event, channel, enabled });
            });
        });
        router.post(route('client.notification-preferences.update'), { preferences: flat });
    };

    return (
        <div className="overflow-x-auto bg-white dark:bg-neutral-800 rounded-xl border border-neutral-200 dark:border-neutral-700 p-4 sm:p-6">
            <h2 className="text-base font-semibold text-neutral-900 dark:text-white mb-4">{t('notifications.preferences_title')}</h2>
            <table className="min-w-[520px] w-full text-sm">
                <thead>
                    <tr>
                        <th className="text-left pb-2 text-neutral-500 dark:text-neutral-400 font-medium">{t('notifications.col_event')}</th>
                        <th className="text-center pb-2 text-neutral-500 dark:text-neutral-400 font-medium w-24">{t('notifications.col_in_app')}</th>
                        <th className="text-center pb-2 text-neutral-500 dark:text-neutral-400 font-medium w-24">{t('notifications.col_email')}</th>
                    </tr>
                </thead>
                <tbody className="divide-y divide-neutral-100 dark:divide-neutral-700">
                    {KNOWN_EVENTS.map(({ key, labelKey }) => (
                        <tr key={key}>
                            <td className="py-3 text-neutral-700 dark:text-neutral-300">{t(labelKey)}</td>
                            <td className="py-3 text-center">
                                <input
                                    type="checkbox"
                                    checked={prefs[key]?.database ?? true}
                                    onChange={e => setPrefs(p => ({ ...p, [key]: { ...p[key], database: e.target.checked } }))}
                                    className="rounded"
                                />
                            </td>
                            <td className="py-3 text-center">
                                <input
                                    type="checkbox"
                                    checked={prefs[key]?.email ?? true}
                                    onChange={e => setPrefs(p => ({ ...p, [key]: { ...p[key], email: e.target.checked } }))}
                                    className="rounded"
                                />
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
            <div className="mt-4 flex justify-end">
                <button onClick={handleSave} className="px-4 py-2 text-sm bg-brand-500 text-white rounded-soft hover:bg-brand-600 shadow-soft transition-all duration-150">
                    {t('common.save')}
                </button>
            </div>
        </div>
    );
}

export default function NotificationsIndex({ notifications, preferences }) {
    const { t } = useTranslation();
    const { flash, timezone } = usePage().props;
    const userTz = timezone || 'America/Sao_Paulo';
    const [showPrefs, setShowPrefs] = useState(false);

    const markRead = (id) => {
        fetch(route('client.notifications.read', id), {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content },
        }).then(() => router.reload({ only: ['notifications'] }));
    };

    const remove = (id) => {
        fetch(route('client.notifications.destroy', id), {
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content },
        }).then(() => router.reload({ only: ['notifications'] }));
    };

    const markAllRead = () => {
        router.post(route('client.notifications.read-all'));
    };

    const unread = notifications.filter(n => !n.read_at);

    return (
        <ClientLayout title={t('notifications.title')}>
            <Head title={t('notifications.title')} />
            <div className="space-y-6 max-w-3xl">
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-3">
                        <Bell className="h-6 w-6 text-brand-600 dark:text-brand-400" />
                        <div>
                            <h1 className="text-xl font-bold text-neutral-900 dark:text-white">{t('notifications.title')}</h1>
                            {unread.length > 0 && (
                                <p className="text-sm text-neutral-500 dark:text-neutral-400">{t('notifications_page.unread_count', { count: unread.length })}</p>
                            )}
                        </div>
                    </div>
                    <div className="flex items-center gap-2">
                        {unread.length > 0 && (
                            <button onClick={markAllRead} className="flex items-center gap-1.5 text-sm text-brand-600 dark:text-brand-400 hover:underline">
                                <CheckCheck className="h-4 w-4" /> {t('notifications_page.mark_all_read')}
                            </button>
                        )}
                        <button onClick={() => setShowPrefs(v => !v)} className="flex items-center gap-1.5 text-sm text-neutral-600 dark:text-neutral-400 hover:text-neutral-900 dark:hover:text-white">
                            <Settings className="h-4 w-4" /> {t('notifications_page.preferences')}
                        </button>
                    </div>
                </div>

                {flash?.success && (
                    <div className="rounded-lg bg-green-50 dark:bg-green-900/20 text-green-800 dark:text-green-200 px-4 py-3 text-sm">{flash.success}</div>
                )}

                {showPrefs && <PreferencesPanel preferences={preferences} />}

                <div className="space-y-2">
                    {notifications.length === 0 && (
                        <div className="text-center py-12 text-neutral-400 dark:text-neutral-500">
                            <Bell className="h-10 w-10 mx-auto mb-3 opacity-30" />
                            <p>{t('notifications.no_notifications')}</p>
                        </div>
                    )}
                    {notifications.map(n => (
                        <div key={n.id} className={`flex items-start gap-3 p-4 rounded-xl border ${n.read_at ? 'border-neutral-100 dark:border-neutral-800 bg-white dark:bg-neutral-800/30' : 'border-brand-100 dark:border-brand-900/50 bg-brand-50 dark:bg-brand-900/20'}`}>
                            <div className={`mt-0.5 h-2 w-2 rounded-full shrink-0 ${n.read_at ? 'bg-neutral-300 dark:bg-neutral-600' : 'bg-brand-500'}`} />
                            <div className="flex-1 min-w-0">
                                <p className="text-sm font-medium text-neutral-900 dark:text-white">{n.data.title ?? n.type}</p>
                                {n.data.message && <p className="text-sm text-neutral-500 dark:text-neutral-400 mt-0.5">{n.data.message}</p>}
                                <p className="text-xs text-neutral-400 dark:text-neutral-500 mt-1">{formatInTz(n.created_at, userTz)}</p>
                            </div>
                            <div className="flex items-center gap-1 shrink-0">
                                {!n.read_at && (
                                    <button onClick={() => markRead(n.id)} className="p-1 text-neutral-400 hover:text-brand-600 dark:hover:text-brand-400 transition" title={t('notifications_page.mark_as_read')}>
                                        <Check className="h-4 w-4" />
                                    </button>
                                )}
                                <button onClick={() => remove(n.id)} className="p-1 text-neutral-400 hover:text-coral-600" title={t('common.delete')}>
                                    <Trash2 className="h-4 w-4" />
                                </button>
                            </div>
                        </div>
                    ))}
                </div>
            </div>
        </ClientLayout>
    );
}
