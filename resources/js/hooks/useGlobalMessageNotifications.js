import { useCallback, useEffect, useRef } from 'react';
import { router } from '@inertiajs/react';
import axios from 'axios';
import { toast } from 'sonner';
import { useTranslation } from 'react-i18next';
import { playInboundSound, unlockInboundSound } from '@/Utils/notificationSound';
import { subscribeToPush } from '@/push';

function safeStorageGet(key) {
    try {
        const value = Number(localStorage.getItem(key));
        return Number.isFinite(value) && value > 0 ? value : 0;
    } catch {
        return 0;
    }
}

function safeStorageSet(key, value) {
    try {
        localStorage.setItem(key, String(value));
    } catch {
        // Private browsing or restricted storage: this tab's refs still deduplicate.
    }
}

function conversationUrl(uuid) {
    if (!uuid) return null;
    try {
        return route('client.inbox.show', uuid);
    } catch {
        return `/painel/inbox/conversations/${uuid}`;
    }
}

/**
 * Monitor inbound messages from every channel while the user is on any client page.
 * Echo is the fast path; polling keeps shared-hosting installations reliable.
 */
export function useGlobalMessageNotifications({ workspaceId, channelAccountIds = [], enabled = true }) {
    const { t } = useTranslation();
    const cursorRef = useRef(0);
    const pendingTitleCountRef = useRef(0);
    const originalTitleRef = useRef('');
    const channelAccountKey = [...channelAccountIds].map(Number).filter(Boolean).sort((a, b) => a - b).join(',');

    const cursorKey = workspaceId ? `respondesk.inbox.cursor.${workspaceId}` : '';
    const notifiedKey = workspaceId ? `respondesk.inbox.notified.${workspaceId}` : '';

    const claimMessage = useCallback((id) => {
        const numericId = Number(id) || 0;
        if (!numericId) return true;

        const alreadyNotified = safeStorageGet(notifiedKey);
        if (numericId <= alreadyNotified) return false;
        safeStorageSet(notifiedKey, numericId);
        return true;
    }, [notifiedKey]);

    const notifyInbound = useCallback((event) => {
        if (event.direction !== 'in' || !claimMessage(event.id)) return;

        const channel = event.channel || 'default';
        const contact = event.contact_name || t('inbox.contact');
        const preview = event.body || t('inbox.new_message_without_preview');
        const url = conversationUrl(event.conversation_uuid);
        const title = t('inbox.new_message_channel', { channel });

        playInboundSound(channel);
        toast(title, {
            description: `${contact}: ${preview}`,
            duration: 10000,
            closeButton: true,
            action: url
                ? {
                    label: t('inbox.open_conversation'),
                    onClick: () => router.visit(url),
                }
                : undefined,
            cancel: {
                label: t('inbox.dismiss_all_alerts'),
                onClick: () => toast.dismiss(),
            },
        });

        if (document.hidden) {
            pendingTitleCountRef.current += 1;
            document.title = `(${pendingTitleCountRef.current}) ${originalTitleRef.current}`;
        }
    }, [claimMessage, t]);

    useEffect(() => {
        if (!enabled || !workspaceId) return;

        originalTitleRef.current = document.title;
        cursorRef.current = safeStorageGet(cursorKey);

        const unlock = () => unlockInboundSound();
        window.addEventListener('pointerdown', unlock, { once: true });
        window.addEventListener('keydown', unlock, { once: true });

        const resetTitle = () => {
            if (!document.hidden) {
                pendingTitleCountRef.current = 0;
                document.title = originalTitleRef.current;
            }
        };
        document.addEventListener('visibilitychange', resetTitle);

        const syncCursorFromAnotherTab = (event) => {
            if (event.key !== cursorKey) return;
            cursorRef.current = Math.max(cursorRef.current, Number(event.newValue) || 0);
        };
        window.addEventListener('storage', syncCursorFromAnotherTab);

        return () => {
            window.removeEventListener('pointerdown', unlock);
            window.removeEventListener('keydown', unlock);
            document.removeEventListener('visibilitychange', resetTitle);
            window.removeEventListener('storage', syncCursorFromAnotherTab);
            if (pendingTitleCountRef.current > 0) document.title = originalTitleRef.current;
        };
    }, [enabled, workspaceId, cursorKey]);

    useEffect(() => {
        if (!enabled || !workspaceId || !window.Echo || !channelAccountKey) return;

        const ids = channelAccountKey.split(',');
        ids.forEach(id => window.Echo.private(`channel-account.${id}`).listen('.MessageReceived', notifyInbound));

        return () => ids.forEach(id => window.Echo.leave(`channel-account.${id}`));
    }, [enabled, workspaceId, channelAccountKey, notifyInbound]);

    useEffect(() => {
        if (!enabled || !workspaceId) return;

        let stopped = false;
        let timer = null;
        let running = false;

        const poll = async () => {
            if (stopped || running) return;
            running = true;

            try {
                const params = cursorRef.current > 0 ? { after_id: cursorRef.current } : {};
                const response = await axios.get(route('client.inbox.notification-updates'), {
                    params,
                    headers: { Accept: 'application/json' },
                });

                for (const message of response.data?.messages ?? []) {
                    notifyInbound(message);
                }

                const latestId = Number(response.data?.latest_id) || cursorRef.current;
                cursorRef.current = Math.max(cursorRef.current, latestId);
                safeStorageSet(cursorKey, cursorRef.current);
            } catch {
                // Temporary network failures must not disable later alerts.
            } finally {
                running = false;
                if (!stopped) timer = window.setTimeout(poll, document.hidden ? 5000 : 3000);
            }
        };

        timer = window.setTimeout(poll, 800);
        return () => {
            stopped = true;
            if (timer) window.clearTimeout(timer);
        };
    }, [enabled, workspaceId, cursorKey, notifyInbound]);
}

/** Offer Web Push once, from a user-clicked action required by browser policy. */
export function usePushNotificationSetup({ enabled = true }) {
    const { t } = useTranslation();

    useEffect(() => {
        if (!enabled || typeof Notification === 'undefined') return;
        const vapidKey = document.querySelector('meta[name="vapid-public-key"]')?.content?.trim();
        if (!vapidKey || Notification.permission === 'denied') return;

        if (Notification.permission === 'granted') {
            subscribeToPush().catch(() => {});
            return;
        }

        const promptKey = 'respondesk.push.prompt.shown';
        if (sessionStorage.getItem(promptKey) === '1') return;
        sessionStorage.setItem(promptKey, '1');

        const timer = window.setTimeout(() => {
            toast(t('inbox.push_prompt_title'), {
                description: t('inbox.push_prompt_description'),
                duration: 20000,
                action: {
                    label: t('inbox.enable_notifications'),
                    onClick: async () => {
                        try {
                            const subscription = await subscribeToPush();
                            if (subscription) toast.success(t('inbox.notifications_enabled'));
                        } catch {
                            toast.error(t('inbox.notifications_enable_error'));
                        }
                    },
                },
            });
        }, 1500);

        return () => window.clearTimeout(timer);
    }, [enabled, t]);
}
