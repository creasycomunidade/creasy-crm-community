import { useState } from 'react';
import { Head, useForm, router } from '@inertiajs/react';
import ClientLayout from '@/Layouts/ClientLayout';
import { Bell, Mail, Smartphone, CheckCircle } from 'lucide-react';
import { subscribeToPush, unsubscribeFromPush } from '@/push';
import { useTranslation } from 'react-i18next';

// Channels that are intentionally not offered for a given event.
// New-message emails are too noisy (one per inbound message), so email is removed.
const UNAVAILABLE = {
    new_message: ['mail'],
};

const EVENT_TYPES = [
    { key: 'new_message',         labelKey: 'settings.notif_event_new_message_label',        descriptionKey: 'settings.notif_event_new_message_desc' },
    { key: 'mention',             labelKey: 'settings.notif_event_mention_label',            descriptionKey: 'settings.notif_event_mention_desc' },
    { key: 'campaign_completed',  labelKey: 'settings.notif_event_campaign_completed_label', descriptionKey: 'settings.notif_event_campaign_completed_desc' },
    { key: 'automation_failed',   labelKey: 'settings.notif_event_automation_failed_label',  descriptionKey: 'settings.notif_event_automation_failed_desc' },
    { key: 'billing_failed',      labelKey: 'settings.notif_event_billing_failed_label',     descriptionKey: 'settings.notif_event_billing_failed_desc' },
];

const CHANNELS = [
    { key: 'mail',     labelKey: 'common.email',          icon: Mail },
    { key: 'web_push', labelKey: 'settings.channel_web_push', icon: Smartphone },
];

export default function NotificationSettings({ preferences = {} }) {
    const { t } = useTranslation();
    const { data, setData, post, processing, transform } = useForm({ preferences: [] });
    const [pushError, setPushError] = useState('');

    // Build the preference grid from props
    const getEnabled = (event, channel) => {
        if (preferences[event] !== undefined && preferences[event][channel] !== undefined) {
            return preferences[event][channel];
        }
        return true; // default on
    };

    const toggle = (event, channel) => {
        const current = getEnabled(event, channel);
        // Inertia's useForm().post() ignores a `data` key in its options and
        // submits the form's own state instead, so set the payload via transform()
        // right before the request is sent.
        transform(() => ({
            preferences: [
                { event, channel, enabled: !current }
            ]
        }));
        post(route('client.notification-preferences.update'), {
            preserveScroll: true,
        });
    };

    const handlePushToggle = async () => {
        const isEnabled = getEnabled('new_message', 'web_push');
        setPushError('');

        // Turning OFF: unsubscribe (best effort) then persist the preference.
        if (isEnabled) {
            try {
                await unsubscribeFromPush();
            } catch {
                // ignore — we still want to record the user's intent to disable
            }
            toggle('new_message', 'web_push');
            return;
        }

        // Turning ON: only persist the preference once we actually hold a
        // browser subscription, otherwise the toggle would lie (no push arrives).
        try {
            const subscription = await subscribeToPush();
            if (! subscription) {
                setPushError(t('settings.push_unavailable'));
                return;
            }
        } catch (e) {
            setPushError(t('settings.push_enable_error', { error: e?.message ?? t('settings.push_subscription_failed') }));
            return;
        }

        toggle('new_message', 'web_push');
    };

    return (
        <ClientLayout title={t('settings.notifications_title')}>
            <Head title={t('settings.notifications_title')} />

            <div className="max-w-2xl mx-auto space-y-6">
                <div>
                    <h1 className="text-xl font-semibold text-neutral-900 dark:text-neutral-100 flex items-center gap-2">
                        <Bell className="h-5 w-5" />
                        {t('settings.notif_preferences')}
                    </h1>
                    <p className="text-sm text-neutral-500 dark:text-neutral-400 mt-1">
                        {t('settings.notif_subtitle')}
                    </p>
                </div>

                <div className="bg-white dark:bg-neutral-900 rounded-xl border border-neutral-200 dark:border-neutral-700 overflow-hidden">
                    {/* Header row */}
                    <div className="grid bg-neutral-50 dark:bg-neutral-800 border-b border-neutral-200 dark:border-neutral-700 px-6 py-3 text-xs font-semibold uppercase tracking-wider text-neutral-500 dark:text-neutral-400"
                         style={{ gridTemplateColumns: '1fr repeat(2, 100px)' }}>
                        <span>{t('notifications.col_event')}</span>
                        {CHANNELS.map(ch => (
                            <span key={ch.key} className="text-center flex items-center justify-center gap-1">
                                <ch.icon className="h-3.5 w-3.5" /> {t(ch.labelKey)}
                            </span>
                        ))}
                    </div>

                    {/* Rows */}
                    {EVENT_TYPES.map((evt, i) => (
                        <div
                            key={evt.key}
                            className={`grid items-center px-6 py-4 ${i < EVENT_TYPES.length - 1 ? 'border-b border-neutral-100 dark:border-neutral-800' : ''}`}
                            style={{ gridTemplateColumns: '1fr repeat(2, 100px)' }}
                        >
                            <div>
                                <p className="text-sm font-medium text-neutral-900 dark:text-neutral-100">{t(evt.labelKey)}</p>
                                <p className="text-xs text-neutral-500 dark:text-neutral-400">{t(evt.descriptionKey)}</p>
                            </div>
                            {CHANNELS.map(ch => {
                                if ((UNAVAILABLE[evt.key] ?? []).includes(ch.key)) {
                                    return (
                                        <div key={ch.key} className="flex justify-center">
                                            <span className="text-neutral-300 dark:text-neutral-600" title={t('settings.notif_not_available')}>—</span>
                                        </div>
                                    );
                                }
                                const enabled = getEnabled(evt.key, ch.key);
                                const isPushToggle = ch.key === 'web_push' && evt.key === 'new_message';
                                return (
                                    <div key={ch.key} className="flex justify-center">
                                        <button
                                            onClick={() => isPushToggle ? handlePushToggle() : toggle(evt.key, ch.key)}
                                            disabled={processing}
                                            className={`relative inline-flex h-5 w-9 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 ${enabled ? 'bg-brand-600' : 'bg-neutral-300 dark:bg-neutral-600'}`}
                                            title={enabled ? t('settings.notif_toggle_on') : t('settings.notif_toggle_off')}
                                        >
                                            <span className={`inline-block h-3.5 w-3.5 transform rounded-full bg-white transition-transform ${enabled ? 'translate-x-4' : 'translate-x-1'}`} />
                                        </button>
                                    </div>
                                );
                            })}
                        </div>
                    ))}
                </div>

                <p className="text-xs text-neutral-400 dark:text-neutral-500">
                    {t('settings.push_permission_hint')}
                </p>

                {pushError && (
                    <p className="text-xs text-red-600 dark:text-red-400">
                        {pushError}
                    </p>
                )}
            </div>
        </ClientLayout>
    );
}
