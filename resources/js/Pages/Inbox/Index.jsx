import { Head, Link, router, usePage } from '@inertiajs/react';
import InboxLayout from '@/Layouts/InboxLayout';
import EmptyState from '@/Components/EmptyState';
import NewConversationModal from '@/Components/Inbox/NewConversationModal';
import { Skeleton } from '@/Components/ui';
import {
    MessageSquare, Inbox, CheckCircle, Clock, User, RefreshCw,
    Search, Plus, SlidersHorizontal, X,
} from 'lucide-react';
import { useState, useEffect, useRef, useCallback } from 'react';
import { useTranslation } from 'react-i18next';
import { ChannelBrandIcon, CHANNEL_LABELS } from '@/Components/BrandIcons';
import { formatTimeTz } from '@/Utils/datetime';
import { playInboundSound } from '@/Utils/notificationSound';
import { toast } from 'sonner';
import axios from 'axios';

const FOLDERS = [
    { key: null,         labelKey: 'inbox.folder_all',        icon: Inbox },
    { key: 'mine',       labelKey: 'inbox.folder_mine',       icon: User },
    { key: 'unassigned', labelKey: 'inbox.folder_unassigned', icon: MessageSquare },
    { key: 'resolved',   labelKey: 'inbox.folder_resolved',   icon: CheckCircle },
    { key: 'snoozed',    labelKey: 'inbox.folder_snoozed',    icon: Clock },
];

const ALL_CHANNELS = ['whatsapp', 'instagram', 'messenger', 'telegram', 'sms', 'email', 'webchat'];

function StatusDot({ status }) {
    const colors = {
        open: 'bg-green-500',
        pending: 'bg-amber-400',
        resolved: 'bg-neutral-400',
        snoozed: 'bg-purple-400',
    };
    return <span className={`inline-block h-2 w-2 rounded-full shrink-0 ${colors[status] ?? 'bg-neutral-300'}`} />;
}

function ConversationCard({ conv, isFlashing, isActive, userTz, filters = {} }) {
    const { t } = useTranslation();
    const channel = conv.channel_account?.channel ?? 'whatsapp';
    const lastMsg = conv.last_message ?? {};
    const name = conv.contact?.first_name || conv.contact?.last_name
        ? `${conv.contact.first_name ?? ''} ${conv.contact.last_name ?? ''}`.trim()
        : conv.contact?.phone_e164 ?? t('inbox.unknown_contact');

    return (
        <Link
            href={route('client.inbox.show', { conversation: conv.uuid, ...filters })}
            className={`block px-3 py-3 border-b border-neutral-100 dark:border-neutral-800 transition-colors ${
                isActive
                    ? 'bg-brand-50 dark:bg-brand-900/20 border-l-2 border-l-brand-600'
                    : isFlashing
                        ? 'bg-brand-50/60 dark:bg-brand-900/10'
                        : 'hover:bg-neutral-50 dark:hover:bg-neutral-800/50'
            }`}
        >
            <div className="flex items-start gap-2.5">
                <div className="relative shrink-0">
                    <div className="h-9 w-9 rounded-full bg-brand-100 dark:bg-brand-900/30 flex items-center justify-center text-sm font-semibold text-brand-700 dark:text-brand-300">
                        {name[0]?.toUpperCase() ?? '?'}
                    </div>
                    <span className="absolute -bottom-0.5 -right-0.5 h-4 w-4 rounded-full bg-white dark:bg-neutral-900 flex items-center justify-center">
                        <ChannelBrandIcon channel={channel} className="h-3 w-3" />
                    </span>
                </div>

                {/* Content */}
                <div className="flex-1 min-w-0">
                    <div className="flex items-center justify-between gap-1">
                        <span className={`text-sm truncate text-left ${conv.unread_count > 0 ? 'font-semibold text-neutral-900 dark:text-neutral-100' : 'font-medium text-neutral-700 dark:text-neutral-300'}`}>
                            {name}
                        </span>
                        <span className="text-[11px] text-neutral-400 shrink-0">
                            {conv.last_message_at ? formatTimeTz(conv.last_message_at, userTz) : ''}
                        </span>
                    </div>
                    <div className="flex items-center gap-1.5 mt-0.5">
                        <p className={`text-xs truncate flex-1 ${conv.unread_count > 0 ? 'text-neutral-700 dark:text-neutral-300' : 'text-neutral-400 dark:text-neutral-500'}`}>
                            {lastMsg.body || ''}
                        </p>
                        {conv.unread_count > 0 && (
                            <span className="shrink-0 h-5 min-w-5 rounded-full bg-brand-600 text-white text-[10px] font-bold flex items-center justify-center px-1">
                                {conv.unread_count > 99 ? '99+' : conv.unread_count}
                            </span>
                        )}
                    </div>
                    {conv.labels?.length > 0 && (
                        <div className="flex flex-wrap gap-1 mt-1.5">
                            {conv.labels.map(label => (
                                <span key={label.id} className="inline-flex items-center rounded-full px-1.5 py-px text-[10px] font-medium text-white" style={{ backgroundColor: label.color }}>
                                    {label.name}
                                </span>
                            ))}
                        </div>
                    )}
                </div>
            </div>
        </Link>
    );
}

function ConversationSkeleton() {
    return (
        <div className="flex items-start gap-2.5 px-3 py-3 border-b border-neutral-100 dark:border-neutral-800">
            <Skeleton variant="circle" className="h-9 w-9 shrink-0" />
            <div className="flex-1 min-w-0 space-y-1.5">
                <Skeleton className="h-3.5 w-28" />
                <Skeleton className="h-3 w-44" />
            </div>
        </div>
    );
}

function FilterSidebar({ filters, labels, channelAccounts = [], onFolder, onChannel, onAccount, onLabel }) {
    const { t } = useTranslation();
    return (
        <div className="flex flex-col h-full overflow-y-auto">
            {/* Folders */}
            <div className="p-2 border-b border-neutral-100 dark:border-neutral-800">
                <p className="text-[10px] font-bold uppercase tracking-wider text-neutral-400 dark:text-neutral-500 px-2 py-1.5">{t('inbox.views')}</p>
                {FOLDERS.map(({ key, labelKey, icon: Icon }) => (
                    <button
                        key={key ?? 'all'}
                        onClick={() => onFolder(key)}
                        className={`w-full flex items-center gap-2.5 px-2 py-2 rounded-lg text-sm transition ${
                            (filters.folder ?? null) === key
                                ? 'bg-brand-50 dark:bg-brand-900/30 text-brand-700 dark:text-brand-300 font-semibold'
                                : 'text-neutral-600 dark:text-neutral-400 hover:bg-neutral-100 dark:hover:bg-neutral-800'
                        }`}
                    >
                        <Icon className="h-4 w-4 shrink-0" />
                        <span>{t(labelKey)}</span>
                    </button>
                ))}
            </div>

            {/* Channels */}
            <div className="p-2 border-b border-neutral-100 dark:border-neutral-800">
                <p className="text-[10px] font-bold uppercase tracking-wider text-neutral-400 dark:text-neutral-500 px-2 py-1.5">{t('inbox.channels')}</p>
                {ALL_CHANNELS.map(ch => (
                    <button
                        key={ch}
                        onClick={() => onChannel(ch)}
                        className={`w-full flex items-center gap-2.5 px-2 py-2 rounded-lg text-sm transition ${
                            filters.channel === ch
                                ? 'bg-brand-50 dark:bg-brand-900/30 text-brand-700 dark:text-brand-300 font-semibold'
                                : 'text-neutral-600 dark:text-neutral-400 hover:bg-neutral-100 dark:hover:bg-neutral-800'
                        }`}
                    >
                        <ChannelBrandIcon channel={ch} className="h-4 w-4 shrink-0" />
                        <span>{CHANNEL_LABELS[ch] ?? ch}</span>
                    </button>
                ))}
            </div>

            {/* Numbers (channel accounts) */}
            {channelAccounts.length > 0 && (
                <div className="p-2 border-b border-neutral-100 dark:border-neutral-800">
                    <p className="text-[10px] font-bold uppercase tracking-wider text-neutral-400 dark:text-neutral-500 px-2 py-1.5">{t('inbox.numbers')}</p>
                    {channelAccounts.map(account => (
                        <button
                            key={account.id}
                            onClick={() => onAccount(account.id)}
                            className={`w-full flex items-center gap-2.5 px-2 py-2 rounded-lg text-sm transition ${
                                String(filters.account_id) === String(account.id)
                                    ? 'bg-brand-50 dark:bg-brand-900/30 text-brand-700 dark:text-brand-300 font-semibold'
                                    : 'text-neutral-600 dark:text-neutral-400 hover:bg-neutral-100 dark:hover:bg-neutral-800'
                            }`}
                        >
                            <ChannelBrandIcon channel={account.channel} className="h-4 w-4 shrink-0" />
                            <span className="truncate">{account.display_name || account.phone_number_id || account.channel}</span>
                        </button>
                    ))}
                </div>
            )}

            {/* Labels / Tags */}
            {labels.length > 0 && (
                <div className="p-2">
                    <p className="text-[10px] font-bold uppercase tracking-wider text-neutral-400 dark:text-neutral-500 px-2 py-1.5">{t('inbox.labels')}</p>
                    {labels.map(label => (
                        <button
                            key={label.id}
                            onClick={() => onLabel(label.id)}
                            className={`w-full flex items-center gap-2.5 px-2 py-2 rounded-lg text-sm transition ${
                                String(filters.label) === String(label.id)
                                    ? 'bg-brand-50 dark:bg-brand-900/30 font-semibold'
                                    : 'text-neutral-600 dark:text-neutral-400 hover:bg-neutral-100 dark:hover:bg-neutral-800'
                            }`}
                        >
                            <span className="h-3 w-3 rounded-full shrink-0 ring-1 ring-white dark:ring-neutral-900" style={{ backgroundColor: label.color }} />
                            <span className="truncate">{label.name}</span>
                        </button>
                    ))}
                </div>
            )}
        </div>
    );
}

export default function InboxIndex({ conversations: initialConversations, filters, labels = [], channelAccounts = [], latestMessageId = 0 }) {
    const { t } = useTranslation();
    const { props } = usePage();
    const authUser = props.auth?.user;
    const workspaceId = props.currentWorkspace?.id ?? authUser?.workspace_id;
    const channelAccountKey = channelAccounts.map(account => Number(account.id)).filter(Boolean).sort((a, b) => a - b).join(',');
    const userTz = props.timezone || 'America/Sao_Paulo';

    const [conversations, setConversations] = useState(initialConversations);
    const [flashingIds, setFlashingIds]     = useState(new Set());
    const [loading, setLoading]             = useState(false);
    const [search, setSearch]               = useState('');
    const [showNewModal, setShowNewModal]   = useState(false);
    const [mobileFiltersOpen, setMobileFiltersOpen] = useState(false);
    const latestMessageIdRef = useRef(Math.max(
        0,
        Number(latestMessageId) || 0,
        ...(initialConversations?.data ?? []).map(item => Number(item.last_message?.id) || 0),
    ));
    const notifiedMessageIdsRef = useRef(new Set(
        (initialConversations?.data ?? []).map(item => Number(item.last_message?.id) || 0),
    ));

    const notifyInbound = useCallback((event) => {
        if (event.direction !== 'in') return;
        const id = Number(event.id) || 0;
        if (id && notifiedMessageIdsRef.current.has(id)) return;
        if (id) notifiedMessageIdsRef.current.add(id);

        playInboundSound(event.channel);
        const contact = event.contact_name || CHANNEL_LABELS[event.channel] || t('inbox.contact');
        const conversationUrl = event.conversation_uuid ? route('client.inbox.show', {
            conversation: event.conversation_uuid,
            ...filters,
            account_id: event.channel_account_id ?? filters.account_id,
        }) : null;
        toast(t('inbox.new_message_alert'), {
            description: `${contact}: ${event.body || t('inbox.new_message_without_preview')}`,
            action: event.conversation_uuid
                ? {
                    label: t('inbox.open_conversation'),
                    onClick: () => router.visit(conversationUrl),
                }
                : undefined,
        });

    }, [filters, t]);

    useEffect(() => {
        setConversations(initialConversations);
        setLoading(false);
    }, [initialConversations]);

    useEffect(() => {
        if (!window.Echo || !workspaceId || !channelAccountKey) return;
        const accountIds = channelAccountKey.split(',');
        const handleMessage = (e) => {
                latestMessageIdRef.current = Math.max(latestMessageIdRef.current, Number(e.id) || 0);
                notifyInbound(e);
                setConversations(prev => {
                    const convId = e.conversation_id;
                    const exists = prev.data.find(c => c.id === convId);
                    if (!exists) {
                        router.reload({ preserveScroll: true, preserveState: true });
                        return prev;
                    }
                    setFlashingIds(ids => new Set([...ids, convId]));
                    setTimeout(() => setFlashingIds(ids => {
                        const next = new Set(ids);
                        next.delete(convId);
                        return next;
                    }), 2000);
                    return {
                        ...prev,
                        data: [
                            { ...exists, unread_count: (exists.unread_count ?? 0) + 1, last_message_at: e.created_at, last_message: { body: e.body } },
                            ...prev.data.filter(c => c.id !== convId),
                        ],
                    };
                });
            };
        accountIds.forEach(id => window.Echo.private(`channel-account.${id}`).listen('.MessageReceived', handleMessage));
        return () => accountIds.forEach(id => window.Echo.leave(`channel-account.${id}`));
    }, [workspaceId, channelAccountKey, notifyInbound]);

    useEffect(() => {
        let stopped = false;
        let timer = null;
        let running = false;

        const poll = async () => {
            if (stopped || running) return;
            running = true;
            try {
                const response = await axios.get(route('client.inbox.workspace-updates'), {
                    params: {
                        after_id: latestMessageIdRef.current,
                        folder: filters.folder || undefined,
                        channel: filters.channel || undefined,
                        account_id: filters.account_id || undefined,
                        label: filters.label || undefined,
                    },
                    headers: { Accept: 'application/json' },
                });

                for (const event of response.data?.messages ?? []) {
                    latestMessageIdRef.current = Math.max(latestMessageIdRef.current, Number(event.id) || 0);
                    notifyInbound(event);
                }
                latestMessageIdRef.current = Math.max(
                    latestMessageIdRef.current,
                    Number(response.data?.latest_id) || 0,
                );
                if (Array.isArray(response.data?.conversations)) {
                    setConversations(prev => ({ ...(prev ?? {}), data: response.data.conversations }));
                }
            } catch {
                // Keep retrying after temporary network failures.
            } finally {
                running = false;
                if (!stopped) timer = window.setTimeout(poll, 3000);
            }
        };

        timer = window.setTimeout(poll, 1000);
        return () => {
            stopped = true;
            if (timer) window.clearTimeout(timer);
        };
    }, [
        filters.folder,
        filters.channel,
        filters.account_id,
        filters.label,
        notifyInbound,
    ]);

    const navigate = (params) => {
        setLoading(true);
        router.get(route('client.inbox.index'), { ...filters, ...params }, { preserveState: true, replace: true });
    };

    const handleFolder  = (key) => navigate({ folder: key, channel: undefined, label: undefined, account_id: undefined });
    const handleChannel = (ch)  => navigate({ channel: filters.channel === ch ? undefined : ch, account_id: undefined });
    const handleAccount = (id)  => navigate({ account_id: String(filters.account_id) === String(id) ? undefined : id, channel: undefined });
    const handleLabel   = (id)  => navigate({ label: String(filters.label) === String(id) ? undefined : id });

    const filtered = search.trim()
        ? conversations.data.filter(c => {
            const name = `${c.contact?.first_name ?? ''} ${c.contact?.last_name ?? ''} ${c.contact?.phone_e164 ?? ''}`.toLowerCase();
            return name.includes(search.toLowerCase());
        })
        : conversations.data;

    const activeFolder = FOLDERS.find(f => (f.key ?? null) === (filters.folder ?? null));

    const selectMobileFilter = (callback) => (...args) => {
        setMobileFiltersOpen(false);
        callback(...args);
    };

    return (
        <InboxLayout>
            <Head title={t('inbox.title')} />
            {showNewModal && <NewConversationModal onClose={() => setShowNewModal(false)} />}
            {mobileFiltersOpen && (
                <div className="fixed inset-0 z-50 lg:hidden" role="dialog" aria-modal="true" aria-label="Filtros da caixa de entrada">
                    <button type="button" className="absolute inset-0 bg-black/45" aria-label="Fechar filtros" onClick={() => setMobileFiltersOpen(false)} />
                    <aside className="absolute inset-y-0 left-0 flex w-[min(88vw,22rem)] flex-col overflow-hidden bg-white shadow-2xl dark:bg-neutral-900">
                        <div className="flex min-h-14 items-center justify-between border-b border-neutral-200 px-4 dark:border-neutral-800">
                            <span className="flex items-center gap-2 font-semibold"><SlidersHorizontal className="h-5 w-5 text-brand-600" />Filtros</span>
                            <button type="button" onClick={() => setMobileFiltersOpen(false)} className="flex h-11 w-11 items-center justify-center rounded-xl hover:bg-neutral-100 dark:hover:bg-neutral-800" aria-label="Fechar filtros"><X className="h-5 w-5" /></button>
                        </div>
                        <FilterSidebar
                            filters={filters}
                            labels={labels}
                            channelAccounts={channelAccounts}
                            onFolder={selectMobileFilter(handleFolder)}
                            onChannel={selectMobileFilter(handleChannel)}
                            onAccount={selectMobileFilter(handleAccount)}
                            onLabel={selectMobileFilter(handleLabel)}
                        />
                    </aside>
                </div>
            )}
            <div className="flex min-h-0 min-w-0 flex-1 overflow-hidden">

                {/* Filter sidebar */}
                <aside className="hidden w-48 shrink-0 flex-col overflow-hidden border-r border-neutral-200 bg-white dark:border-neutral-700 dark:bg-neutral-900 lg:flex">
                    <div className="px-3 py-3 border-b border-neutral-100 dark:border-neutral-800 flex items-center justify-between gap-1">
                        <p className="text-sm font-bold text-neutral-800 dark:text-neutral-200 flex items-center gap-2">
                            <Inbox className="h-4 w-4 text-brand-600" />
                            {t('inbox.title')}
                        </p>
                        <button
                            onClick={() => setShowNewModal(true)}
                            title={t('inbox.new_conversation')}
                            className="h-7 w-7 rounded-lg bg-brand-600 hover:bg-brand-700 text-white flex items-center justify-center transition shrink-0"
                        >
                            <Plus className="h-4 w-4" />
                        </button>
                    </div>
                    <FilterSidebar
                        filters={filters}
                        labels={labels}
                        channelAccounts={channelAccounts}
                        onFolder={handleFolder}
                        onChannel={handleChannel}
                        onAccount={handleAccount}
                        onLabel={handleLabel}
                    />
                </aside>

                {/* Conversation list */}
                <div className="flex min-w-0 w-full shrink-0 flex-col border-r border-neutral-200 bg-white dark:border-neutral-700 dark:bg-neutral-900 sm:w-80 lg:w-72">
                    {/* List header */}
                    <div className="px-3 py-2.5 border-b border-neutral-100 dark:border-neutral-800 space-y-2">
                        <div className="flex min-h-9 items-center justify-between gap-2">
                            <button type="button" onClick={() => setMobileFiltersOpen(true)} className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg text-neutral-500 hover:bg-neutral-100 dark:hover:bg-neutral-800 lg:hidden" aria-label="Abrir filtros"><SlidersHorizontal className="h-4 w-4" /></button>
                            <span className="flex min-w-0 flex-1 items-center gap-1 overflow-hidden text-sm font-semibold text-neutral-800 dark:text-neutral-200">
                                {activeFolder ? t(activeFolder.labelKey) : t('inbox.folder_all')}
                                {filters.channel && <span className="text-xs font-normal text-neutral-400">· {CHANNEL_LABELS[filters.channel] ?? filters.channel}</span>}
                                {filters.account_id && (() => {
                                    const acct = channelAccounts.find(a => String(a.id) === String(filters.account_id));
                                    return acct ? <span className="text-xs font-normal text-neutral-400">· {acct.display_name || acct.phone_number_id}</span> : null;
                                })()}
                            </span>
                            <div className="flex shrink-0 items-center gap-1">
                                <span className="text-xs text-neutral-400 tabular-nums">{conversations.total}</span>
                                <button
                                    onClick={() => { setLoading(true); router.reload(); }}
                                    className="p-1 rounded hover:bg-neutral-100 dark:hover:bg-neutral-800 text-neutral-400 hover:text-neutral-600 transition"
                                    title={t('inbox.refresh')}
                                >
                                    <RefreshCw className={`h-3.5 w-3.5 ${loading ? 'animate-spin' : ''}`} />
                                </button>
                                <button type="button" onClick={() => setShowNewModal(true)} className="flex h-9 w-9 items-center justify-center rounded-lg bg-brand-600 text-white hover:bg-brand-700 lg:hidden" aria-label={t('inbox.new_conversation')}><Plus className="h-4 w-4" /></button>
                            </div>
                        </div>
                        {/* Search */}
                        <div className="relative">
                            <Search className="absolute left-2.5 top-1/2 -translate-y-1/2 h-3.5 w-3.5 text-neutral-400 pointer-events-none" />
                            <input
                                type="text"
                                value={search}
                                onChange={e => setSearch(e.target.value)}
                                placeholder={t('inbox.search_conversations')}
                                className="w-full pl-8 pr-3 py-1.5 text-xs rounded-lg bg-neutral-100 dark:bg-neutral-800 border-0 focus:outline-none focus:ring-2 focus:ring-brand-500 placeholder-neutral-400"
                            />
                        </div>
                    </div>

                    {/* List body */}
                    <div className="flex-1 overflow-y-auto">
                        {loading ? (
                            Array.from({ length: 6 }).map((_, i) => <ConversationSkeleton key={i} />)
                        ) : filtered.length === 0 ? (
                            <div className="py-10 px-4">
                                <EmptyState
                                    icon={<Inbox className="h-7 w-7" />}
                                    title={t('inbox.no_conversations')}
                                    description={t('inbox.no_conversations_desc')}
                                />
                            </div>
                        ) : (
                            filtered.map(conv => (
                                <ConversationCard
                                    key={conv.id}
                                    conv={conv}
                                    isFlashing={flashingIds.has(conv.id)}
                                    isActive={false}
                                    userTz={userTz}
                                    filters={filters}
                                />
                            ))
                        )}
                    </div>
                </div>

                {/* Empty state – main pane */}
                <div className="hidden min-w-0 flex-1 items-center justify-center bg-neutral-50 dark:bg-neutral-950 sm:flex">
                    <div className="text-center">
                        <div className="mx-auto mb-4 h-16 w-16 rounded-2xl bg-neutral-100 dark:bg-neutral-800 flex items-center justify-center">
                            <MessageSquare className="h-8 w-8 text-neutral-300 dark:text-neutral-600" />
                        </div>
                        <p className="text-base font-semibold text-neutral-500 dark:text-neutral-400">{t('inbox.select_conversation')}</p>
                        <p className="text-sm text-neutral-400 dark:text-neutral-500 mt-1">{t('inbox.select_conversation_desc')}</p>
                    </div>
                </div>
            </div>
        </InboxLayout>
    );
}
