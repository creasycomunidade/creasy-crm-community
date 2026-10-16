import { withReactionTargets } from './reactionTargets';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import InboxLayout from '@/Layouts/InboxLayout';
import EmptyState from '@/Components/EmptyState';
import NewConversationModal from '@/Components/Inbox/NewConversationModal';
import { ScheduledImageField, ScheduledTimingFields } from '@/Components/Inbox/ScheduledMessageFields';
import {
    Send, AlertTriangle, Eye, StickyNote, MessageSquare, Phone, Globe,
    RefreshCw, Search, Inbox, User, CheckCircle, Clock, X, Smile,
    Paperclip, Image as ImageIcon, ChevronDown, UserCheck,
    LayoutTemplate, Plus, Loader2, Bot, Calendar, CalendarPlus, CalendarClock, BarChart2, PhoneMissed,
    Volume2, VolumeX, ShoppingBag, Columns3, MoreHorizontal, ListTodo, CheckSquare, Tags, ArrowLeft,
    Wrench, Link2, Unlink2, ClipboardList, ExternalLink, ChevronUp, CirclePause, Pencil, Play, Trash2, Repeat2, FileText, WifiOff,
} from 'lucide-react';
import { ChannelBrandIcon, CHANNEL_LABELS } from '@/Components/BrandIcons';
import { formatTimeTz, formatInTz, utcToTzLocal } from '@/Utils/datetime';
import { playInboundSound, getSoundPrefs, setChannelSoundEnabled, SOUND_CHANNELS } from '@/Utils/notificationSound';
import { useState, useEffect, useLayoutEffect, useRef, useCallback } from 'react';
import { useTranslation } from 'react-i18next';
import axios from 'axios';
import { toast } from 'sonner';

/* ─── helpers ─────────────────────────────────────────── */

const FOLDERS = [
    { key: null,         labelKey: 'inbox.folder_all',        icon: Inbox },
    { key: 'mine',       labelKey: 'inbox.folder_mine',       icon: User },
    { key: 'unassigned', labelKey: 'inbox.folder_unassigned', icon: MessageSquare },
    { key: 'resolved',   labelKey: 'inbox.folder_resolved',   icon: CheckCircle },
    { key: 'snoozed',    labelKey: 'inbox.folder_snoozed',    icon: Clock },
];
const ALL_CHANNELS = ['whatsapp', 'instagram', 'messenger', 'telegram', 'sms', 'email', 'webchat'];

const STATUS_COLORS = {
    open:     'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300',
    pending:  'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300',
    resolved: 'bg-neutral-100 text-neutral-600 dark:bg-neutral-800 dark:text-neutral-400',
    snoozed:  'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-300',
};

// Common emoji set for the picker
const EMOJI_LIST = [
    '😀','😂','😊','😍','🤔','😢','😡','👍','👎','❤️',
    '🎉','✅','❌','🔥','⭐','💡','📌','🚀','💬','📞',
    '✉️','📎','🖼️','📁','🕐','💰','🎯','👋','🙏','💪',
];

function renderCannedBody(body, contact) {
    return body.replace(/\{\{contact\.(\w+)\}\}/g, (_, key) => {
        if (key === 'name') return [contact?.first_name, contact?.last_name].filter(Boolean).join(' ');
        return contact?.[key] ?? '';
    });
}

/** Render a WA template body text from its components */
function templatePreview(components) {
    const body = components?.find(c => c.type === 'BODY');
    return body?.text ?? '';
}

/** Parse WhatsApp formatting: *bold*, _italic_, ~strike~, `code`, newlines */
function WaText({ text, className = '' }) {
    if (!text) return null;
    const parts = [];
    let remaining = text;
    let key = 0;

    // Replace patterns iteratively
    const patterns = [
        { re: /https?:\/\/[^\s<>]+[^\s<>.,!?;:'")\]]/gi, kind: 'link' },
        { re: /\*([^*\n]+)\*/g,   Tag: 'strong', cls: 'font-semibold' },
        { re: /_([^_\n]+)_/g,     Tag: 'em',     cls: 'italic' },
        { re: /~([^~\n]+)~/g,     Tag: 'del',    cls: 'line-through' },
        { re: /`([^`\n]+)`/g,     Tag: 'code',   cls: 'font-mono text-[0.85em] bg-black/10 rounded px-0.5' },
    ];

    // Build segments with React elements
    const segments = [];
    let src = text;
    // Simple approach: split on newlines first, then process each line
    const lines = src.split('\n');
    lines.forEach((line, li) => {
        if (li > 0) segments.push(<br key={`br-${li}`} />);
        // Process inline patterns on each line
        let rest = line;
        let lineSegs = [];
        let lk = 0;
        while (rest.length > 0) {
            let earliest = null;
            let earliestIdx = Infinity;
            for (const p of patterns) {
                p.re.lastIndex = 0;
                const m = p.re.exec(rest);
                if (m && m.index < earliestIdx) {
                    earliest = { ...p, match: m };
                    earliestIdx = m.index;
                }
            }
            if (!earliest) {
                lineSegs.push(<span key={lk++}>{rest}</span>);
                break;
            }
            if (earliest.match.index > 0) {
                lineSegs.push(<span key={lk++}>{rest.slice(0, earliest.match.index)}</span>);
            }
            const { Tag, cls, match } = earliest;
            lineSegs.push(earliest.kind === 'link'
                ? <a key={lk++} href={match[0]} target="_blank" rel="noopener noreferrer" className="break-all font-medium underline underline-offset-2 hover:opacity-80">{match[0]}</a>
                : <Tag key={lk++} className={cls}>{match[1]}</Tag>
            );
            rest = rest.slice(earliest.match.index + match[0].length);
        }
        segments.push(...lineSegs);
    });

    return <span className={className}>{segments}</span>;
}

/* ─── message type renderers ─────────────────────────── */

function MediaImage({ src, alt, conversationId, messageId, isOut }) {
    const { t } = useTranslation();
    const [loaded, setLoaded]   = useState(false);
    const [errored, setErrored] = useState(false);
    const [open, setOpen]       = useState(false);
    const proxyUrl = src ?? route('client.inbox.message-media', { conversation: conversationId, message: messageId });
    if (errored) return (
        <button type="button" onClick={() => { setErrored(false); setLoaded(false); }}
            className="inline-flex min-h-11 items-center gap-1.5 rounded-lg border border-current/20 px-3 py-2 text-xs opacity-75 hover:opacity-100">
            <RefreshCw className="h-3.5 w-3.5" />
            {t('inbox.image_unavailable')} — tentar novamente
        </button>
    );
    return (
        <>
            <button
                type="button"
                onClick={() => setOpen(true)}
                className="relative block rounded-xl overflow-hidden mb-1 bg-black/10 min-h-[80px] cursor-zoom-in"
                title={t('inbox.view_full_size')}
            >
                {!loaded && <div className="absolute inset-0 flex items-center justify-center"><Loader2 className="h-5 w-5 animate-spin opacity-40" /></div>}
                <img
                    src={proxyUrl}
                    alt={alt || 'image'}
                    onLoad={() => setLoaded(true)}
                    onError={() => setErrored(true)}
                    className={`max-w-full max-h-72 object-contain rounded-xl transition-opacity ${loaded ? 'opacity-100' : 'opacity-0'}`}
                />
            </button>
            {open && (
                <ImageLightbox
                    images={[{ id: messageId, src: proxyUrl, caption: alt || '' }]}
                    index={0}
                    onClose={() => setOpen(false)}
                    onIndex={() => {}}
                />
            )}
        </>
    );
}

function MediaVideo({ src, conversationId, messageId }) {
    const proxyUrl = src ?? route('client.inbox.message-media', { conversation: conversationId, message: messageId });
    return <video src={proxyUrl} controls className="rounded-xl max-w-full max-h-64 mb-1 bg-black" />;
}

// Images sent together as an album arrive from WhatsApp as separate messages
// (no album id), so we group consecutive same-direction image messages that
// land within this window and render them as one gallery.
const ALBUM_WINDOW_MS = 15000;
const MESSAGE_GROUP_WINDOW_MS = 5 * 60 * 1000;

function messageItemDirection(item) {
    return item.kind === 'album' ? item.messages[0]?.direction : item.msg?.direction;
}

function messageItemTime(item, edge = 'last') {
    const messages = item.kind === 'album' ? item.messages : [item.msg];
    const message = edge === 'first' ? messages[0] : messages[messages.length - 1];
    const timestamp = new Date(message?.sent_at).getTime();
    return Number.isFinite(timestamp) ? timestamp : 0;
}

function groupedBubbleClasses(isOut, position) {
    if (position === 'single') return isOut ? 'rounded-br-sm' : 'rounded-bl-sm';
    if (isOut) {
        if (position === 'first') return 'rounded-br-md';
        if (position === 'middle') return 'rounded-tr-md rounded-br-md';
        return 'rounded-tr-md rounded-br-sm';
    }
    if (position === 'first') return 'rounded-bl-md';
    if (position === 'middle') return 'rounded-tl-md rounded-bl-md';
    return 'rounded-tl-md rounded-bl-sm';
}

/**
 * A content-less "unsupported" stub WhatsApp emits (error "Message type unknown"
 * / 131051, no text or media). We only hide these when they sit next to album
 * images — a *standalone* unsupported message (e.g. a poll) is always shown.
 */
function isAlbumNoiseStub(msg) {
    if (msg.type !== 'unsupported') return false;
    if (msg.payload?.content_kind === 'album_stub') return true;
    const errs = Array.isArray(msg.payload?.errors) ? msg.payload.errors : [];
    if (!errs.length) return false;
    const blob = JSON.stringify(errs).toLowerCase();
    if (blob.includes('poll') || blob.includes('event')) return false;
    const p = msg.payload || {};
    const hasMedia = !!(p.image || p.video || p.audio || p.document || p.sticker);
    return (blob.includes('unsupported') || blob.includes('unknown') || blob.includes('131051')) && !hasMedia;
}

/**
 * Collapse runs of adjacent image messages into albums.
 * Returns render items: { kind: 'album', messages } or { kind: 'single', msg }.
 */
function groupMessagesForRender(messages) {
    // Always render in the order the platform received/sent the records. Provider
    // timestamps are not reliable enough for ordering (timezone and precision
    // differences are common, especially across Telegram and Meta channels).
    messages = withReactionTargets(messages).sort((a, b) => Number(a.id) - Number(b.id));

    // Hide album-noise stubs only when an adjacent same-direction message (within
    // the album window) is an image — never standalone polls / unsupported msgs.
    const hiddenIds = new Set();
    for (let k = 0; k < messages.length; k++) {
        const m = messages[k];
        if (!isAlbumNoiseStub(m)) continue;
        const adjacentToImage = [messages[k - 1], messages[k + 1]].some(
            (n) =>
                n &&
                n.type === 'image' &&
                n.direction === m.direction &&
                Math.abs(new Date(n.sent_at) - new Date(m.sent_at)) <= ALBUM_WINDOW_MS,
        );
        if (adjacentToImage) hiddenIds.add(m.id);
    }
    if (hiddenIds.size) {
        messages = messages.filter((m) => !hiddenIds.has(m.id));
    }

    const items = [];
    let i = 0;
    while (i < messages.length) {
        const msg = messages[i];
        if (msg.type === 'image' && msg.payload?.content_kind !== 'product_share') {
            const group = [msg];
            let j = i + 1;
            while (j < messages.length) {
                const next = messages[j];
                const prev = group[group.length - 1];
                if (
                    next.type !== 'image' ||
                    next.payload?.content_kind === 'product_share' ||
                    next.direction !== msg.direction ||
                    Math.abs(new Date(next.sent_at) - new Date(prev.sent_at)) > ALBUM_WINDOW_MS
                ) break;
                group.push(next);
                j++;
            }
            if (group.length >= 2) {
                items.push({ kind: 'album', key: `album-${group[0].id}`, messages: group });
                i = j;
                continue;
            }
        }
        items.push({ kind: 'single', key: msg.id, msg });
        i++;
    }
    return items.map((item, index) => {
        const previous = items[index - 1];
        const next = items[index + 1];
        const direction = messageItemDirection(item);
        const currentFirstAt = messageItemTime(item, 'first');
        const currentLastAt = messageItemTime(item);
        const previousLastAt = previous ? messageItemTime(previous) : 0;
        const nextFirstAt = next ? messageItemTime(next, 'first') : 0;
        const joinsPrevious = !!previous
            && messageItemDirection(previous) === direction
            && currentFirstAt > 0
            && previousLastAt > 0
            && Math.abs(currentFirstAt - previousLastAt) <= MESSAGE_GROUP_WINDOW_MS;
        const joinsNext = !!next
            && messageItemDirection(next) === direction
            && currentLastAt > 0
            && nextFirstAt > 0
            && Math.abs(nextFirstAt - currentLastAt) <= MESSAGE_GROUP_WINDOW_MS;
        const groupPosition = joinsPrevious
            ? (joinsNext ? 'middle' : 'last')
            : (joinsNext ? 'first' : 'single');

        return { ...item, groupPosition };
    });
}

function galleryImageSrc(msg, conversationId, forceProxy = false) {
    return forceProxy
        ? route('client.inbox.message-media', { conversation: conversationId, message: msg.id })
        : (msg.payload?.preview_url
            ?? route('client.inbox.message-media', { conversation: conversationId, message: msg.id }));
}

function ImageGallery({ messages, conversationId, provider, groupPosition = 'single', onCreateTask }) {
    const { props: pageProps } = usePage();
    const bubbleTz = pageProps.timezone || 'America/Sao_Paulo';
    const [lightbox, setLightbox] = useState(null);

    const isOut = messages[0].direction === 'out';
    const last = messages[messages.length - 1];

    const images = messages.map((m) => ({
        id: m.id,
        src: galleryImageSrc(m, conversationId, provider === 'evolution_go' && m.direction === 'in'),
        caption: m.payload?.caption ?? (m.body && m.body !== '(media)' ? m.body : ''),
    }));

    const shown = images.slice(0, 4);
    const extra = images.length - shown.length;

    const statusGlyph = last.status === 'read' || last.status === 'delivered' ? '✓✓'
        : last.status === 'sent' ? '✓'
        : last.status === 'failed' ? '✗' : '⋯';
    const statusClass = last.status === 'read' ? 'text-sky-200' : last.status === 'failed' ? 'text-red-200' : '';

    return (
        <div className={`group/message flex ${isOut ? 'justify-end' : 'justify-start'} ${['single', 'last'].includes(groupPosition) ? 'mb-2' : 'mb-0.5'}`}>
            <div className="relative max-w-[70%]">
                <MessageActions message={last} isOut={isOut} onCreateTask={onCreateTask} />
                <div className={`rounded-2xl overflow-hidden ${groupedBubbleClasses(isOut, groupPosition)} ${isOut
                ? 'bg-brand-600 text-white'
                : 'bg-white dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700'}`}>
                <div className={`grid gap-0.5 p-0.5 ${shown.length === 1 ? 'grid-cols-1' : 'grid-cols-2'}`}>
                    {shown.map((img, idx) => (
                        <button
                            key={img.id}
                            type="button"
                            onClick={() => setLightbox(idx)}
                            className="relative block aspect-square overflow-hidden bg-black/10 group"
                        >
                            <img src={img.src} alt="image" loading="lazy"
                                className="h-full w-full object-cover transition group-hover:opacity-90" />
                            {idx === 3 && extra > 0 && (
                                <span className="absolute inset-0 bg-black/55 text-white flex items-center justify-center text-xl font-semibold">
                                    +{extra}
                                </span>
                            )}
                        </button>
                    ))}
                </div>
                <div className={`flex items-center justify-end gap-1 px-3 py-1 text-[10px] ${isOut ? 'text-white/60' : 'text-neutral-400'}`}>
                    {last.sent_at ? formatTimeTz(last.sent_at, bubbleTz) : ''}
                    {isOut && <span title={last.status} className={statusClass}>{statusGlyph}</span>}
                </div>
                </div>
            </div>

            {lightbox !== null && (
                <ImageLightbox
                    images={images}
                    index={lightbox}
                    onClose={() => setLightbox(null)}
                    onIndex={setLightbox}
                />
            )}
        </div>
    );
}

function ImageLightbox({ images, index, onClose, onIndex }) {
    const go = (delta) => onIndex((index + delta + images.length) % images.length);

    useEffect(() => {
        const onKey = (e) => {
            if (e.key === 'Escape') onClose();
            else if (e.key === 'ArrowLeft') onIndex((index - 1 + images.length) % images.length);
            else if (e.key === 'ArrowRight') onIndex((index + 1) % images.length);
        };
        window.addEventListener('keydown', onKey);
        return () => window.removeEventListener('keydown', onKey);
    }, [index, images.length, onClose, onIndex]);

    const current = images[index];

    return (
        <div className="fixed inset-0 z-50 bg-black/80 flex items-center justify-center" onClick={onClose}>
            <button type="button" onClick={onClose} className="absolute top-4 right-4 text-white/80 hover:text-white">
                <X className="h-6 w-6" />
            </button>
            {images.length > 1 && (
                <button type="button" onClick={(e) => { e.stopPropagation(); go(-1); }}
                    className="absolute left-3 md:left-6 text-white/70 hover:text-white text-4xl leading-none px-2">‹</button>
            )}
            <figure className="flex flex-col items-center" onClick={(e) => e.stopPropagation()}>
                <img src={current.src} alt="image" className="max-w-[90vw] max-h-[80vh] object-contain rounded" />
                {current.caption && (
                    <figcaption className="mt-3 text-sm text-white/80 max-w-[80vw] text-center">{current.caption}</figcaption>
                )}
                {images.length > 1 && <div className="mt-2 text-xs text-white/50">{index + 1} / {images.length}</div>}
            </figure>
            {images.length > 1 && (
                <button type="button" onClick={(e) => { e.stopPropagation(); go(1); }}
                    className="absolute right-3 md:right-6 text-white/70 hover:text-white text-4xl leading-none px-2">›</button>
            )}
        </div>
    );
}

function MediaAudio({ src, conversationId, messageId, initialTranscription = '' }) {
    const [retry, setRetry] = useState(0);
    const [playbackError, setPlaybackError] = useState(false);
    const [transcription, setTranscription] = useState(initialTranscription);
    const [transcribing, setTranscribing] = useState(false);
    const [transcriptionError, setTranscriptionError] = useState('');
    const proxyUrl = `${route('client.inbox.message-media', { conversation: conversationId, message: messageId })}?attempt=${retry}`;

    const transcribe = async () => {
        setTranscribing(true);
        setTranscriptionError('');
        try {
            const { data } = await axios.post(route('client.inbox.message-transcribe', {
                conversation: conversationId,
                message: messageId,
            }));
            setTranscription(data.transcription ?? '');
        } catch (error) {
            setTranscriptionError(error.response?.data?.message ?? 'Não foi possível transcrever este áudio. Tente novamente.');
        } finally {
            setTranscribing(false);
        }
    };

    return (
        <div className="mb-1 min-w-[220px] space-y-2">
            <audio
                key={`${messageId}-${src || 'proxy'}-${retry}`}
                src={proxyUrl}
                controls
                preload="metadata"
                onLoadedMetadata={() => setPlaybackError(false)}
                onError={() => setPlaybackError(true)}
                className="w-full"
            />
            {playbackError && (
                <div role="alert" className="flex items-center justify-between gap-2 rounded-lg bg-red-50 px-2.5 py-2 text-xs text-red-700 dark:bg-red-950/40 dark:text-red-200">
                    <span>Não foi possível carregar o áudio.</span>
                    <button type="button" onClick={() => { setPlaybackError(false); setRetry(value => value + 1); }}
                        className="inline-flex min-h-11 items-center gap-1.5 rounded-lg px-2 font-semibold transition hover:bg-red-100 focus:outline-none focus:ring-2 focus:ring-red-400 dark:hover:bg-red-900/50">
                        <RefreshCw className="h-3.5 w-3.5" /> Tentar novamente
                    </button>
                </div>
            )}
            
            {!transcription && (
                <button type="button" onClick={transcribe} disabled={transcribing}
                    className="inline-flex min-h-11 items-center gap-2 rounded-lg border border-current/20 px-3 text-xs font-semibold transition hover:bg-black/5 focus:outline-none focus:ring-2 focus:ring-current/40 disabled:cursor-not-allowed disabled:opacity-60 dark:hover:bg-white/10">
                    {transcribing ? <Loader2 className="h-4 w-4 animate-spin" /> : <FileText className="h-4 w-4" />}
                    {transcribing ? 'Transcrevendo…' : 'Transcrever áudio'}
                </button>
            )}
            {transcription && (
                <div className="rounded-lg bg-black/5 px-2.5 py-2 text-xs leading-5 dark:bg-white/10">
                    <span className="font-semibold">Transcrição:</span> {transcription}
                </div>
            )}
            {transcriptionError && <p role="alert" className="text-xs text-red-600 dark:text-red-300">{transcriptionError}</p>}
        </div>
    );
}

function MediaDocument({ src, filename, conversationId, messageId, isOut }) {
    const { t } = useTranslation();
    const proxyUrl = src ?? route('client.inbox.message-media', { conversation: conversationId, message: messageId });
    return (
        <a href={proxyUrl} target="_blank" rel="noopener noreferrer"
            className={`flex items-center gap-2.5 rounded-xl px-3 py-2.5 mb-1 transition ${isOut ? 'bg-white/20 hover:bg-white/30' : 'bg-neutral-100 dark:bg-neutral-700 hover:bg-neutral-200 dark:hover:bg-neutral-600'}`}>
            <div className={`h-9 w-9 rounded-lg flex items-center justify-center shrink-0 ${isOut ? 'bg-white/20' : 'bg-neutral-200 dark:bg-neutral-600'}`}>
                <Paperclip className="h-4 w-4" />
            </div>
            <div className="min-w-0">
                <p className="text-xs font-medium truncate">{filename || t('inbox.document')}</p>
                <p className="text-[10px] opacity-60">{t('inbox.tap_to_open')}</p>
            </div>
        </a>
    );
}

function LocationCard({ location, isOut }) {
    const { t } = useTranslation();
    const { latitude, longitude, name, address } = location ?? {};
    if (!latitude || !longitude) return <span className="text-xs opacity-60 italic">{t('inbox.location_unavailable')}</span>;
    const mapUrl = `https://www.google.com/maps?q=${latitude},${longitude}`;
    const staticMap = `https://maps.googleapis.com/maps/api/staticmap?center=${latitude},${longitude}&zoom=14&size=300x150&markers=${latitude},${longitude}`;
    return (
        <a href={mapUrl} target="_blank" rel="noopener noreferrer" className="block rounded-xl overflow-hidden mb-1 border border-black/10">
            <div className="relative h-32 bg-neutral-200 dark:bg-neutral-700 flex items-center justify-center">
                <div className="text-center opacity-60">
                    <svg className="h-8 w-8 mx-auto mb-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" /><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                    <span className="text-xs">{t('inbox.map')}</span>
                </div>
            </div>
            <div className={`px-3 py-2 ${isOut ? 'bg-white/10' : 'bg-neutral-50 dark:bg-neutral-800'}`}>
                {name && <p className="text-xs font-semibold truncate">{name}</p>}
                {address && <p className="text-[10px] opacity-70 truncate">{address}</p>}
                <p className="text-[10px] opacity-50">{latitude}, {longitude}</p>
            </div>
        </a>
    );
}

function ContactCard({ contacts, isOut }) {
    const { t } = useTranslation();
    const c = contacts?.[0];
    if (!c) return <span className="text-xs opacity-60 italic">{t('inbox.contact_unavailable')}</span>;
    const name = c.name?.formatted_name ?? c.name?.first_name ?? t('inbox.contact');
    const phone = c.phones?.[0]?.phone ?? c.phones?.[0]?.wa_id ?? '';
    return (
        <div className={`flex items-center gap-2.5 rounded-xl px-3 py-2.5 mb-1 ${isOut ? 'bg-white/20' : 'bg-neutral-100 dark:bg-neutral-700'}`}>
            <div className={`h-9 w-9 rounded-full flex items-center justify-center text-sm font-bold shrink-0 ${isOut ? 'bg-white/20' : 'bg-neutral-200 dark:bg-neutral-600'}`}>
                {name[0]?.toUpperCase() ?? '?'}
            </div>
            <div className="min-w-0">
                <p className="text-xs font-semibold truncate">{name}</p>
                {phone && <p className="text-[10px] opacity-60">{phone}</p>}
                {contacts.length > 1 && <p className="text-[10px] opacity-50">{t('inbox.plus_n_more', { count: contacts.length - 1 })}</p>}
            </div>
        </div>
    );
}

function StickerBubble({ src, conversationId, messageId }) {
    const [failed, setFailed] = useState(false);
    const [attempt, setAttempt] = useState(0);
    const proxyUrl = src ?? route('client.inbox.message-media', { conversation: conversationId, message: messageId });
    const retryUrl = `${proxyUrl}${proxyUrl.includes('?') ? '&' : '?'}attempt=${attempt}`;
    if (failed) {
        return (
            <button type="button" onClick={() => { setFailed(false); setAttempt(value => value + 1); }}
                className="inline-flex min-h-11 items-center gap-1.5 rounded-lg border border-neutral-300 bg-white px-3 py-2 text-xs text-neutral-600 shadow-sm dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-300">
                <RefreshCw className="h-3.5 w-3.5" /> Recarregar figurinha
            </button>
        );
    }
    return <img src={retryUrl} alt="Figurinha recebida" onError={() => setFailed(true)} className="h-24 w-24 object-contain" />;
}

function QuotedMessagePreview({ quoted, conversationId, isOut, isReaction = false }) {
    if (!quoted) return null;
    const typeLabels = { image: 'Imagem', video: 'Vídeo', audio: 'Áudio', document: 'Documento', sticker: 'Figurinha', text: 'Mensagem' };
    const label = typeLabels[quoted.type] || 'Mensagem';
    const imageSrc = quoted.preview_url
        || quoted.thumbnail
        || (quoted.type === 'image' && quoted.message_id
            ? route('client.inbox.message-media', { conversation: conversationId, message: quoted.message_id })
            : null);
    return (
        <div className={`mb-2 flex min-w-0 items-stretch overflow-hidden rounded-lg border-l-4 ${isOut ? 'border-white/70 bg-black/15' : 'border-brand-500 bg-neutral-100 dark:bg-neutral-700/70'}`}>
            <div className="min-w-0 flex-1 px-2.5 py-1.5">
                <p className={`text-[10px] font-semibold ${isOut ? 'text-white/80' : 'text-brand-700 dark:text-brand-300'}`}>{isReaction ? 'Mensagem reagida' : `Em resposta a ${label.toLowerCase()}`}</p>
                <p className="truncate text-[11px] opacity-75">{quoted.body || label}</p>
            </div>
            {imageSrc && <img src={imageSrc} alt="Imagem da mensagem respondida" className="h-12 w-12 shrink-0 object-cover" />}
        </div>
    );
}

export function ReactionBubble({ reaction, quoted, conversationId }) {
    const removed = reaction?.action === 'removed' || !reaction?.emoji;
    return (
        <div className="max-w-[70%] rounded-xl border border-neutral-200 bg-white px-2.5 py-2 text-sm shadow-sm dark:border-neutral-700 dark:bg-neutral-800">
            <div className="flex items-center gap-1.5">
                {!removed && <span className="text-xl" aria-label={`Reação ${reaction.emoji}`}>{reaction.emoji}</span>}
                <span className="text-[11px] text-neutral-500 dark:text-neutral-400">{removed ? 'Reação removida' : 'Reagiu à mensagem'}</span>
            </div>
            {quoted ? <div className="mt-1"><QuotedMessagePreview quoted={quoted} conversationId={conversationId} isOut={false} isReaction /></div> : <p className="mt-1 text-xs text-neutral-500">Mensagem original não disponível nesta conversa.</p>}
        </div>
    );
}

function UnsupportedBubble({ payload, body }) {
    const { t } = useTranslation();
    const errors = payload?.errors ?? [];
    const errorTitle = errors[0]?.title ?? '';
    const isEvent = errorTitle.toLowerCase().includes('event') || body?.toLowerCase().includes('event');
    const isPoll  = errorTitle.toLowerCase().includes('poll') || body?.toLowerCase().includes('poll');
    const Icon  = isEvent ? Calendar : isPoll ? BarChart2 : AlertTriangle;
    const label = isEvent ? t('inbox.event_unsupported')
                : isPoll  ? t('inbox.poll_unsupported')
                : (errorTitle || body || t('inbox.unsupported_message_type'));
    return (
        <div className="flex items-center gap-2 opacity-70 italic text-xs py-0.5">
            <Icon className="h-3.5 w-3.5 shrink-0" />
            <span>{label}</span>
        </div>
    );
}

/** Resolve {{N}} placeholders in a template component text using its parameters array */
function resolveTemplateText(text, parameters = []) {
    if (!text) return text;
    return text.replace(/\{\{(\d+)\}\}/g, (_, n) => {
        const param = parameters[parseInt(n, 10) - 1];
        if (!param) return `{{${n}}}`;
        return param.text ?? param.payload ?? param.default ?? `{{${n}}}`;
    });
}

/** Render a full WA template: body text, footer, and quick-reply / call-to-action buttons */
function TemplateBodyContent({ components, isOut }) {
    if (!components?.length) return null;
    const header   = components.find(c => (c.type ?? '').toUpperCase() === 'HEADER');
    const body     = components.find(c => (c.type ?? '').toUpperCase() === 'BODY');
    const footer   = components.find(c => (c.type ?? '').toUpperCase() === 'FOOTER');
    const buttons  = components.find(c => (c.type ?? '').toUpperCase() === 'BUTTONS');

    const headerText = header?.format === 'TEXT' ? resolveTemplateText(header.text, header.parameters) : null;
    const bodyText   = body   ? resolveTemplateText(body.text, body.parameters)   : null;
    const footerText = footer ? resolveTemplateText(footer.text, footer.parameters) : null;
    const btns       = buttons?.buttons ?? [];

    return (
        <div>
            {headerText && (
                <p className="font-semibold mb-1"><WaText text={headerText} /></p>
            )}
            {bodyText && <WaText text={bodyText} />}
            {footerText && (
                <p className={`text-[11px] mt-1.5 ${isOut ? 'text-white/50' : 'text-neutral-400'}`}>{footerText}</p>
            )}
            {btns.length > 0 && (
                <div className={`mt-2 pt-2 border-t ${isOut ? 'border-white/20' : 'border-neutral-200 dark:border-neutral-600'} flex flex-col gap-1`}>
                    {btns.map((b, i) => (
                        <div key={i} className={`text-xs text-center py-1 rounded ${isOut ? 'text-white/80' : 'text-brand-600 dark:text-brand-400'}`}>
                            {b.type === 'URL' ? '🔗 ' : b.type === 'PHONE_NUMBER' ? '📞 ' : '↩ '}
                            {b.text}
                        </div>
                    ))}
                </div>
            )}
        </div>
    );
}

function TemplateHeaderMedia({ components, conversationId, messageId }) {
    const headerComp = components?.find(c => (c.type ?? '').toUpperCase() === 'HEADER');
    if (!headerComp) return null;
    const param = headerComp.parameters?.[0];
    if (!param) return null;
    const mediaType  = param.type; // 'image' | 'video' | 'document'
    const mediaObj   = param[mediaType] ?? {};
    const displayUrl = mediaObj.preview_url ?? mediaObj.link ?? null;
    if (mediaType === 'image') return <MediaImage src={displayUrl} conversationId={conversationId} messageId={messageId} />;
    if (mediaType === 'video') return <MediaVideo src={displayUrl} conversationId={conversationId} messageId={messageId} />;
    if (mediaType === 'document') return <MediaDocument src={displayUrl} filename={mediaObj.filename} conversationId={conversationId} messageId={messageId} />;
    return null;
}

function PollBubble({ nfmReply, isOut }) {
    const { t } = useTranslation();
    const name = nfmReply?.name ?? '';
    // poll vote response
    if (name === 'vote' || name === 'poll_creation') {
        let responseJson = {};
        try {
            responseJson = JSON.parse(nfmReply?.response_json ?? '{}');
        } catch {
            // Keep the empty fallback for malformed provider payloads.
        }
        const pollName = responseJson.poll_name ?? responseJson.title ?? '';
        const selectedOptions = responseJson.selected_options ?? [];
        const options = responseJson.options ?? [];
        return (
            <div className={`rounded-xl px-3 py-2.5 mb-1 ${isOut ? 'bg-white/20' : 'bg-neutral-100 dark:bg-neutral-700'}`}>
                <div className="flex items-center gap-1.5 mb-2">
                    <BarChart2 className="h-3.5 w-3.5 shrink-0" />
                    <span className="text-xs font-semibold">{pollName || t('inbox.poll')}</span>
                </div>
                {options.length > 0 && options.map((opt, i) => {
                    const label = opt.name ?? opt.title ?? opt;
                    const voted = selectedOptions.some(s => (s.local_id ?? s.id) === i || s.name === label);
                    return (
                        <div key={i} className={`text-xs px-2 py-1 rounded mt-1 ${voted ? (isOut ? 'bg-white/30 font-semibold' : 'bg-brand-100 dark:bg-brand-900/30 font-semibold text-brand-700 dark:text-brand-300') : 'opacity-70'}`}>
                            {voted ? '✓ ' : ''}{label}
                        </div>
                    );
                })}
                {selectedOptions.length > 0 && options.length === 0 && (
                    <div className="text-xs opacity-70 mt-1">{t('inbox.voted_on_poll')}</div>
                )}
            </div>
        );
    }
    return <WaText text={`[interactive: ${name}]`} />;
}

function InteractiveBubble({ payload, isOut }) {
    const interactive = payload?.interactive ?? {};
    const body = interactive.body?.text ?? '';
    const buttons = interactive.action?.buttons ?? [];
    const buttonReply = interactive.button_reply;
    const listReply   = interactive.list_reply;
    const nfmReply    = interactive.nfm_reply;
    if (buttonReply) return <WaText text={`✓ ${buttonReply.title}`} />;
    if (listReply)   return <WaText text={`☑ ${listReply.title}`} />;
    if (nfmReply)    return <PollBubble nfmReply={nfmReply} isOut={isOut} />;
    return (
        <div>
            {body && <WaText text={body} className="mb-2 block" />}
            {buttons.map((b, i) => (
                <div key={i} className={`mt-1 rounded-lg border px-3 py-1.5 text-xs text-center ${isOut ? 'border-white/30' : 'border-neutral-200 dark:border-neutral-600'}`}>
                    {b.reply?.title ?? b.title ?? b.text}
                </div>
            ))}
        </div>
    );
}

function PollTopLevelBubble({ payload, isOut }) {
    const { t } = useTranslation();
    const poll = payload?.poll ?? {};
    const title = poll.title ?? t('inbox.poll');
    const options = poll.options ?? [];
    return (
        <div className={`rounded-xl px-3 py-2.5 mb-1 ${isOut ? 'bg-white/20' : 'bg-neutral-100 dark:bg-neutral-700'}`}>
            <div className="flex items-center gap-1.5 mb-2">
                <BarChart2 className="h-3.5 w-3.5 shrink-0" />
                <span className="text-xs font-semibold">{title}</span>
            </div>
            {options.map((opt, i) => (
                <div key={i} className="text-xs px-2 py-1 rounded mt-1 opacity-80 border border-current/10">
                    {opt.name ?? opt.title ?? opt}
                </div>
            ))}
        </div>
    );
}

function EventBubble({ payload, body }) {
    const { t } = useTranslation();
    const ev = payload?.event ?? {};
    const type = ev.type ?? '';
    const dir  = ev.direction ?? '';
    const label = body || [type, dir].filter(Boolean).join(' — ') || t('inbox.call_event');
    const CallIcon = dir?.includes('MISSED') ? PhoneMissed : Phone;
    return (
        <div className="flex items-center gap-2 opacity-70 italic text-xs py-0.5">
            <CallIcon className="h-3.5 w-3.5 shrink-0" />
            <span>{label}</span>
        </div>
    );
}

/* ─── main MessageBubble ─────────────────────────────── */

function SoundPrefsMenu() {
    const { t } = useTranslation();
    const [open, setOpen]   = useState(false);
    const [prefs, setPrefs] = useState(getSoundPrefs());
    const ref = useRef(null);

    useEffect(() => {
        if (!open) return;
        const onDoc = (e) => { if (ref.current && !ref.current.contains(e.target)) setOpen(false); };
        document.addEventListener('mousedown', onDoc);
        return () => document.removeEventListener('mousedown', onDoc);
    }, [open]);

    const anyOn = Object.values(prefs).some(Boolean);

    const toggle = (key) => {
        const next = !prefs[key];
        setChannelSoundEnabled(key, next);
        setPrefs((p) => ({ ...p, [key]: next }));
        if (next) playInboundSound(key); // preview the sound when enabling
    };

    return (
        <div className="relative" ref={ref}>
            <button
                type="button"
                onClick={() => setOpen((o) => !o)}
                title={t('inbox.notification_sounds')}
                className="p-1 rounded hover:bg-neutral-100 dark:hover:bg-neutral-800 text-neutral-400 transition"
            >
                {anyOn ? <Volume2 className="h-3.5 w-3.5" /> : <VolumeX className="h-3.5 w-3.5" />}
            </button>
            {open && (
                <div className="absolute right-0 z-30 mt-1 w-48 rounded-lg border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-800 shadow-lg p-1.5">
                    <div className="px-2 py-1 text-[11px] font-semibold uppercase tracking-wide text-neutral-400">
                        {t('inbox.notification_sounds')}
                    </div>
                    {SOUND_CHANNELS.map((c) => (
                        <button
                            key={c.key}
                            type="button"
                            onClick={() => toggle(c.key)}
                            className="flex w-full items-center justify-between gap-2 rounded-md px-2 py-1.5 text-xs text-neutral-700 dark:text-neutral-200 hover:bg-neutral-50 dark:hover:bg-neutral-700"
                        >
                            <span className="flex items-center gap-1.5">
                                <ChannelBrandIcon channel={c.key} className="h-3.5 w-3.5" />
                                {c.label}
                            </span>
                            <span className={`relative inline-block h-4 w-7 shrink-0 rounded-full transition ${prefs[c.key] ? 'bg-brand-500' : 'bg-neutral-300 dark:bg-neutral-600'}`}>
                                <span className={`absolute top-0.5 h-3 w-3 rounded-full bg-white shadow transition-all ${prefs[c.key] ? 'left-3.5' : 'left-0.5'}`} />
                            </span>
                        </button>
                    ))}
                </div>
            )}
        </div>
    );
}

function MessageActions({ message, isOut, onCreateTask }) {
    const [open, setOpen] = useState(false);
    const ref = useRef(null);

    useEffect(() => {
        if (!open) return;
        const close = (event) => {
            if (ref.current && !ref.current.contains(event.target)) setOpen(false);
        };
        document.addEventListener('mousedown', close);
        return () => document.removeEventListener('mousedown', close);
    }, [open]);

    return (
        <div ref={ref} className={`absolute top-0 z-20 ${isOut ? '-left-8' : '-right-8'}`}>
            <button type="button" onClick={() => setOpen(value => !value)}
                aria-label="Opções da mensagem"
                className="rounded-lg p-1 text-neutral-400 opacity-0 transition hover:bg-neutral-200 hover:text-neutral-700 group-hover/message:opacity-100 focus:opacity-100 dark:hover:bg-neutral-700 dark:hover:text-neutral-100">
                <MoreHorizontal className="h-4 w-4" />
            </button>
            {open && (
                <div className={`absolute top-7 w-52 rounded-xl border border-neutral-200 bg-white p-1.5 text-neutral-700 shadow-xl dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-100 ${isOut ? 'right-0' : 'left-0'}`}>
                    <button type="button" onClick={() => { setOpen(false); onCreateTask?.(message); }}
                        className="flex w-full items-center gap-2 rounded-lg px-2.5 py-2 text-left text-xs font-medium hover:bg-neutral-100 dark:hover:bg-neutral-700">
                        <ListTodo className="h-4 w-4 text-brand-600" />
                        Transformar em tarefa
                    </button>
                </div>
            )}
        </div>
    );
}

function MessageBubble({ msg, conversationId, provider, groupPosition = 'single', onCreateTask }) {
    const { props: pageProps } = usePage();
    const bubbleTz = pageProps.timezone || 'America/Sao_Paulo';
    const isOut = msg.direction === 'out';
    const p     = msg.payload ?? {};
    const agentSignature = isOut ? p.agent_signature : null;
    const groupParticipant = !isOut ? p.whatsapp_group?.participant_name : null;

    // Resolve media source: outbound has preview_url directly; inbound raw webhook nests under type key
    const mediaType   = msg.type ?? 'text';
    const previewUrl  = p.preview_url ?? p[mediaType]?.preview_url ?? null;
    const rawMediaId  = p[mediaType]?.id ?? p.media_id ?? null;
    // Use proxy if no previewUrl for inbound media
    const isProtectedLocalMedia = typeof previewUrl === 'string'
        && (/^\/storage\/message-media\/\d+\.[a-z0-9]+$/i.test(new URL(previewUrl, window.location.origin).pathname)
            || /^\/storage\/message-media\/evolution-go\//i.test(new URL(previewUrl, window.location.origin).pathname));
    const forceEvolutionProxy = !isOut && provider === 'evolution_go'
        && ['image', 'video', 'audio', 'document', 'sticker'].includes(mediaType);
    const mediaSrc = (forceEvolutionProxy || isProtectedLocalMedia || (!previewUrl && rawMediaId))
        ? route('client.inbox.message-media', { conversation: conversationId, message: msg.id })
        : previewUrl;

    // Template header components — prefer `definition` (full text+params) over raw `components` (params-only)
    const templateComponents = p.template?.definition ?? p.template?.components ?? (Array.isArray(p.components) ? p.components : null);

    // Inbound raw payload extras
    const location = p.location ?? p[mediaType];
    const contacts = p.contacts;
    const reaction = p.reaction;
    const quoted   = p.quoted;
    const sticker  = mediaType === 'sticker';
    const caption  = p.caption ?? p[mediaType]?.caption ?? (msg.body && msg.body !== '(media)' ? msg.body : '');

    const bubbleBase = `max-w-[70%] rounded-2xl overflow-hidden text-sm ${groupedBubbleClasses(isOut, groupPosition)} ${isOut
        ? 'bg-brand-600 text-white'
        : 'bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 border border-neutral-200 dark:border-neutral-700'}`;

    // Reaction: no bubble, just float
    if (mediaType === 'reaction' || reaction) {
        return (
            <div className={`flex ${isOut ? 'justify-end' : 'justify-start'} mb-1`}>
                <ReactionBubble reaction={reaction ?? p} quoted={quoted} conversationId={conversationId} />
            </div>
        );
    }

    // Sticker: transparent background
    if (sticker) {
        return (
            <div className={`flex ${isOut ? 'justify-end' : 'justify-start'} ${['single', 'last'].includes(groupPosition) ? 'mb-2' : 'mb-0.5'}`}>
                <StickerBubble src={mediaSrc} conversationId={conversationId} messageId={msg.id} />
            </div>
        );
    }

    const statusGlyph = msg.status === 'read'      ? '✓✓'
                       : msg.status === 'delivered' ? '✓✓'
                       : msg.status === 'sent'      ? '✓'
                       : msg.status === 'failed'    ? '✗'
                       : '⋯';
    const statusClass = msg.status === 'read'   ? 'text-sky-200'
                      : msg.status === 'failed' ? 'text-red-200'
                      : '';

    const timeRow = (
        <div className={`flex items-center gap-1 text-[10px] mt-1 ${isOut ? 'text-white/60 justify-end' : 'text-neutral-400'}`}>
            {msg.sent_at ? formatTimeTz(msg.sent_at, bubbleTz) : ''}
            {isOut && (
                <span title={msg.status} className={statusClass}>{statusGlyph}</span>
            )}
        </div>
    );

    return (
        <div className={`group/message flex ${isOut ? 'justify-end' : 'justify-start'} ${['single', 'last'].includes(groupPosition) ? 'mb-2' : 'mb-0.5'}`}>
            <div className="relative max-w-[70%]">
                <MessageActions message={msg} isOut={isOut} onCreateTask={onCreateTask} />
                <div className={bubbleBase.replace('max-w-[70%] ', '')}>
                {/* Template header image/video/doc */}
                {templateComponents && (
                    <TemplateHeaderMedia components={templateComponents} conversationId={conversationId} messageId={msg.id} />
                )}

                <div className="px-3 py-2.5">
                    {groupParticipant && (
                        <div className="mb-1 text-[11px] font-semibold text-emerald-700 dark:text-emerald-300">
                            {groupParticipant}
                        </div>
                    )}
                    {agentSignature && (
                        <div className="font-bold mb-1">{agentSignature}</div>
                    )}
                    {quoted && <QuotedMessagePreview quoted={quoted} conversationId={conversationId} isOut={isOut} />}
                    {/* IMAGE */}
                    {mediaType === 'image' && (
                        <MediaImage src={mediaSrc} alt={caption} conversationId={conversationId} messageId={msg.id} isOut={isOut} />
                    )}

                    {/* VIDEO */}
                    {mediaType === 'video' && (
                        <MediaVideo src={mediaSrc} conversationId={conversationId} messageId={msg.id} />
                    )}

                    {/* AUDIO */}
                    {mediaType === 'audio' && (
                        <>
                            <MediaAudio src={mediaSrc} conversationId={conversationId} messageId={msg.id} initialTranscription={p.transcription} />
                        </>
                    )}

                    {/* DOCUMENT */}
                    {mediaType === 'document' && (
                        <MediaDocument
                            src={mediaSrc}
                            filename={p.filename ?? p.document?.filename ?? p[mediaType]?.filename}
                            conversationId={conversationId}
                            messageId={msg.id}
                            isOut={isOut}
                        />
                    )}

                    {/* LOCATION */}
                    {mediaType === 'location' && (
                        <LocationCard location={p.location ?? p} isOut={isOut} />
                    )}

                    {/* CONTACTS */}
                    {mediaType === 'contacts' && (
                        <ContactCard contacts={contacts ?? [p]} isOut={isOut} />
                    )}

                    {/* INTERACTIVE */}
                    {mediaType === 'interactive' && (
                        <InteractiveBubble payload={p} isOut={isOut} />
                    )}

                    {/* TEMPLATE — with components (outbound structured) */}
                    {templateComponents && (
                        <TemplateBodyContent components={templateComponents} isOut={isOut} />
                    )}

                    {/* TEMPLATE — inbound raw (no components) */}
                    {mediaType === 'template' && !templateComponents && (
                        <WaText text={msg.body || '[template]'} />
                    )}

                    {/* POLL (native WhatsApp poll type) */}
                    {mediaType === 'poll' && (
                        <PollTopLevelBubble payload={p} body={msg.body} isOut={isOut} />
                    )}

                    {/* EVENT */}
                    {mediaType === 'event' && (
                        <EventBubble payload={p} body={msg.body} isOut={isOut} />
                    )}

                    {/* UNSUPPORTED */}
                    {mediaType === 'unsupported' && (
                        <UnsupportedBubble payload={p} body={msg.body} />
                    )}

                    {/* TEXT / fallback */}
                    {!templateComponents && (mediaType === 'text' || (!['image','video','audio','document','location','contacts','interactive','template','poll','event','unsupported'].includes(mediaType))) && (
                        <WaText text={msg.body || '(media)'} />
                    )}

                    {/* Caption below media */}
                    {['image','video','document','audio'].includes(mediaType) && caption && (
                        <p className="text-xs mt-1 opacity-90"><WaText text={caption} /></p>
                    )}

                    {timeRow}
                </div>
            </div>
            </div>
        </div>
    );
}

function ConversationCard({ conv, isActive, userTz, filters = {} }) {
    const { t } = useTranslation();
    const channel = conv.channel_account?.channel ?? 'whatsapp';
    const name = conv.contact?.first_name || conv.contact?.last_name
        ? `${conv.contact.first_name ?? ''} ${conv.contact.last_name ?? ''}`.trim()
        : conv.contact?.phone_e164 ?? t('inbox.unknown_contact');
    const accountName = conv.channel_account?.display_name
        || conv.channel_account?.phone_number_id
        || CHANNEL_LABELS[channel]
        || channel;
    const contactTags = conv.contact?.tags ?? [];
    const contactSegments = conv.contact?.segments ?? [];

    return (
        <Link
            href={route('client.inbox.show', { conversation: conv.uuid, ...filters })}
            className={`block px-3 py-3 border-b border-neutral-100 dark:border-neutral-800 transition-colors ${
                isActive
                    ? 'bg-brand-50 dark:bg-brand-900/20 border-l-2 border-l-brand-600'
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
                <div className="flex-1 min-w-0">
                    <div className="flex items-center justify-between gap-1">
                        <span className={`text-sm truncate text-left ${conv.unread_count > 0 ? 'font-semibold text-neutral-900 dark:text-neutral-100' : 'font-medium text-neutral-700 dark:text-neutral-300'}`}>
                            {name}
                        </span>
                        <span className="text-[11px] text-neutral-400 shrink-0">
                            {conv.last_message_at ? formatTimeTz(conv.last_message_at, userTz) : ''}
                        </span>
                    </div>
                    <div className="mt-0.5 flex min-w-0 items-center gap-1 text-[10px] font-medium text-neutral-400" title={`Canal: ${accountName}`}>
                        <ChannelBrandIcon channel={channel} className="h-3 w-3 shrink-0" />
                        <span className="truncate">{accountName}</span>
                    </div>
                    <p className={`text-xs truncate mt-0.5 ${conv.unread_count > 0 ? 'text-neutral-700 dark:text-neutral-300' : 'text-neutral-400'}`}>
                        {conv.last_message?.body || ''}
                    </p>
                    {(contactSegments.length > 0 || contactTags.length > 0) && (
                        <div className="mt-1 flex min-w-0 flex-wrap gap-1">
                            {contactSegments.slice(0, 2).map(segment => (
                                <span key={`segment-${segment.id}`} title={`Segmento: ${segment.name}`}
                                    className="max-w-24 truncate rounded-full border border-brand-300 bg-brand-50 px-1.5 py-px text-[9px] font-medium text-brand-700 dark:border-brand-800 dark:bg-brand-950/40 dark:text-brand-300">
                                    S · {segment.name}
                                </span>
                            ))}
                            {contactTags.slice(0, 2).map(tag => (
                                <span key={`tag-${tag.id}`} title={`Etiqueta: ${tag.name}`}
                                    className="max-w-24 truncate rounded-full border px-1.5 py-px text-[9px] font-medium"
                                    style={{ borderColor: tag.color, color: tag.color }}>
                                    E · {tag.name}
                                </span>
                            ))}
                            {(contactSegments.length + contactTags.length) > 4 && (
                                <span className="text-[9px] text-neutral-400">+{contactSegments.length + contactTags.length - 4}</span>
                            )}
                        </div>
                    )}
                    {conv.labels?.length > 0 && (
                        <div className="flex flex-wrap gap-1 mt-1">
                            {conv.labels.map(l => (
                                <span key={l.id} className="rounded-full px-1.5 py-px text-[10px] font-medium text-white" style={{ backgroundColor: l.color }}>{l.name}</span>
                            ))}
                        </div>
                    )}
                </div>
                {conv.unread_count > 0 && (
                    <span className="shrink-0 h-5 min-w-5 rounded-full bg-brand-600 text-white text-[10px] font-bold flex items-center justify-center px-1 mt-0.5">
                        {conv.unread_count > 99 ? '99+' : conv.unread_count}
                    </span>
                )}
            </div>
        </Link>
    );
}

function FilterSidebar({ filters, labels, channelAccounts = [], onFolder, onChannel, onAccount, onLabel }) {
    const { t } = useTranslation();
    return (
        <div className="flex flex-col h-full overflow-y-auto">
            <div className="p-2 border-b border-neutral-100 dark:border-neutral-800">
                <p className="text-[10px] font-bold uppercase tracking-wider text-neutral-400 px-2 py-1.5">{t('inbox.views')}</p>
                {FOLDERS.map(({ key, labelKey, icon: Icon }) => (
                    <button key={key ?? 'all'} onClick={() => onFolder(key)}
                        className={`w-full flex items-center gap-2.5 px-2 py-2 rounded-lg text-sm transition ${
                            (filters.folder ?? null) === key
                                ? 'bg-brand-50 dark:bg-brand-900/30 text-brand-700 dark:text-brand-300 font-semibold'
                                : 'text-neutral-600 dark:text-neutral-400 hover:bg-neutral-100 dark:hover:bg-neutral-800'
                        }`}>
                        <Icon className="h-4 w-4 shrink-0" />{t(labelKey)}
                    </button>
                ))}
            </div>
            <div className="p-2 border-b border-neutral-100 dark:border-neutral-800">
                <p className="text-[10px] font-bold uppercase tracking-wider text-neutral-400 px-2 py-1.5">{t('inbox.channels')}</p>
                {ALL_CHANNELS.map(ch => (
                    <button key={ch} onClick={() => onChannel(ch)}
                        className={`w-full flex items-center gap-2.5 px-2 py-2 rounded-lg text-sm transition ${
                            filters.channel === ch
                                ? 'bg-brand-50 dark:bg-brand-900/30 text-brand-700 dark:text-brand-300 font-semibold'
                                : 'text-neutral-600 dark:text-neutral-400 hover:bg-neutral-100 dark:hover:bg-neutral-800'
                        }`}>
                        <ChannelBrandIcon channel={ch} className="h-4 w-4 shrink-0" />
                        <span>{CHANNEL_LABELS[ch] ?? ch}</span>
                    </button>
                ))}
            </div>
            {channelAccounts.length > 0 && (
                <div className="p-2 border-b border-neutral-100 dark:border-neutral-800">
                    <p className="text-[10px] font-bold uppercase tracking-wider text-neutral-400 px-2 py-1.5">{t('inbox.numbers')}</p>
                    {channelAccounts.map(account => (
                        <button key={account.id} onClick={() => onAccount(account.id)}
                            className={`w-full flex items-center gap-2.5 px-2 py-2 rounded-lg text-sm transition ${
                                String(filters.account_id) === String(account.id)
                                    ? 'bg-brand-50 dark:bg-brand-900/30 text-brand-700 dark:text-brand-300 font-semibold'
                                    : 'text-neutral-600 dark:text-neutral-400 hover:bg-neutral-100 dark:hover:bg-neutral-800'
                            }`}>
                            <ChannelBrandIcon channel={account.channel} className="h-4 w-4 shrink-0" />
                            <span className="truncate">{account.display_name || account.phone_number_id || account.channel}</span>
                        </button>
                    ))}
                </div>
            )}
            {labels.length > 0 && (
                <div className="p-2">
                    <p className="text-[10px] font-bold uppercase tracking-wider text-neutral-400 px-2 py-1.5">{t('inbox.labels')}</p>
                    {labels.map(label => (
                        <button key={label.id} onClick={() => onLabel(label.id)}
                            className={`w-full flex items-center gap-2.5 px-2 py-2 rounded-lg text-sm transition ${
                                String(filters.label) === String(label.id)
                                    ? 'bg-brand-50 dark:bg-brand-900/30 font-semibold'
                                    : 'text-neutral-600 dark:text-neutral-400 hover:bg-neutral-100 dark:hover:bg-neutral-800'
                            }`}>
                            <span className="h-3 w-3 rounded-full shrink-0" style={{ backgroundColor: label.color }} />
                            <span className="truncate">{label.name}</span>
                        </button>
                    ))}
                </div>
            )}
        </div>
    );
}

/* ─── emoji picker ───────────────────────────────────── */
function EmojiPicker({ onPick, onClose }) {
    const ref = useRef(null);
    useEffect(() => {
        const handler = (e) => { if (ref.current && !ref.current.contains(e.target)) onClose(); };
        document.addEventListener('mousedown', handler);
        return () => document.removeEventListener('mousedown', handler);
    }, [onClose]);
    return (
        <div ref={ref} className="absolute bottom-full mb-2 left-0 z-50 bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-700 rounded-xl shadow-lg p-2 w-64">
            <div className="grid grid-cols-10 gap-0.5">
                {EMOJI_LIST.map(e => (
                    <button key={e} type="button" onClick={() => { onPick(e); onClose(); }}
                        className="h-8 w-8 flex items-center justify-center text-lg rounded hover:bg-neutral-100 dark:hover:bg-neutral-800 transition">
                        {e}
                    </button>
                ))}
            </div>
        </div>
    );
}

/* ─── template picker ────────────────────────────────── */
/** Extract {{N}} variable indices from a template component text */
function extractVars(components) {
    const body = components?.find(c => c.type === 'BODY');
    const text = body?.text ?? '';
    const matches = [...text.matchAll(/\{\{(\d+)\}\}/g)];
    const indices = [...new Set(matches.map(m => m[1]))].sort((a, b) => Number(a) - Number(b));
    return indices; // e.g. ['1', '2']
}

function resolveBody(text, vars) {
    return text.replace(/\{\{(\d+)\}\}/g, (_, i) => vars[i] ?? `{{${i}}}`);
}

function TemplatePicker({ conversationId, onSent, onClose }) {
    const { t } = useTranslation();
    const ref = useRef(null);
    const headerFileRef = useRef(null);
    const [query, setQuery]                 = useState('');
    const [templates, setTemplates]         = useState([]);
    const [loading, setLoading]             = useState(true);
    const [picked, setPicked]               = useState(null);
    const [vars, setVars]                   = useState({});
    const [headerMedia, setHeaderMedia]     = useState(null);
    const [uploadError, setUploadError]     = useState('');
    const [sending, setSending]             = useState(false);
    const [sendError, setSendError]         = useState('');

    useEffect(() => {
        const h = (e) => { if (ref.current && !ref.current.contains(e.target)) onClose(); };
        document.addEventListener('mousedown', h);
        return () => document.removeEventListener('mousedown', h);
    }, [onClose]);

    useEffect(() => {
        axios.get(route('client.inbox.templates'))
            .then(r => setTemplates(r.data ?? []))
            .catch(() => {})
            .finally(() => setLoading(false));
    }, []);

    const filtered = templates.filter(t => t.name.toLowerCase().includes(query.toLowerCase()));

    const pickTemplate = (t) => {
        const varIds = extractVars(t.components);
        setPicked(t);
        setVars(Object.fromEntries(varIds.map(id => [id, ''])));
        setHeaderMedia(null);
        setUploadError('');
    };

    // Detect header component type
    const headerComp = picked?.components?.find(c => c.type === 'HEADER');
    const headerFormat = headerComp?.format ?? null; // IMAGE | VIDEO | DOCUMENT | TEXT | null

    const handleHeaderFile = async (e) => {
        const file = e.target.files?.[0];
        if (!file) return;
        const previewUrl = file.type.startsWith('image/') ? URL.createObjectURL(file) : null;
        setHeaderMedia({ file, previewUrl, mediaId: null, uploading: true });
        setUploadError('');
        const fd = new FormData();
        fd.append('file', file);
        try {
            const r = await axios.post(
                route('client.inbox.upload-media', conversationId),
                fd,
                { headers: { 'Content-Type': 'multipart/form-data' } }
            );
            setHeaderMedia(prev => ({ ...prev, mediaId: r.data.media_id, previewUrl: r.data.preview_url ?? prev.previewUrl, uploading: false }));
        } catch (err) {
            setUploadError(err.response?.data?.error ?? t('inbox.upload_failed'));
            setHeaderMedia(prev => ({ ...prev, uploading: false }));
        }
        e.target.value = '';
    };

    const handleSend = async () => {
        setSendError('');
        setSending(true);

        const bodyComp     = picked.components?.find(c => c.type === 'BODY');
        const bodyText     = bodyComp?.text ?? picked.name;
        const resolvedBody = resolveBody(bodyText, vars);
        const varIds       = extractVars(picked.components);
        const components   = [];

        if (headerFormat && headerFormat !== 'TEXT' && headerMedia?.mediaId) {
            const typeMap   = { IMAGE: 'image', VIDEO: 'video', DOCUMENT: 'document' };
            const mediaType = typeMap[headerFormat] ?? 'image';
            const mediaObj  = { id: headerMedia.mediaId };
            if (headerMedia.previewUrl) mediaObj.preview_url = headerMedia.previewUrl;
            components.push({
                type: 'header',
                parameters: [{ type: mediaType, [mediaType]: mediaObj }],
            });
        }

        if (varIds.length > 0) {
            components.push({
                type: 'body',
                parameters: varIds.map(id => ({ type: 'text', text: vars[id] ?? '' })),
            });
        }

        // Build full definition with text + parameters merged for UI display
        const definition = (picked.components ?? []).map(comp => {
            const ct = (comp.type ?? '').toUpperCase();
            if (ct === 'HEADER' && headerFormat && headerFormat !== 'TEXT' && headerMedia?.mediaId) {
                const typeMap   = { IMAGE: 'image', VIDEO: 'video', DOCUMENT: 'document' };
                const mediaType = typeMap[headerFormat] ?? 'image';
                const mediaObj  = { id: headerMedia.mediaId };
                if (headerMedia.previewUrl) mediaObj.preview_url = headerMedia.previewUrl;
                return { ...comp, parameters: [{ type: mediaType, [mediaType]: mediaObj }] };
            }
            if (ct === 'BODY' && varIds.length > 0) {
                return { ...comp, parameters: varIds.map(id => ({ type: 'text', text: vars[id] ?? '' })) };
            }
            return comp;
        });

        try {
            const r = await axios.post(route('client.inbox.reply', conversationId), {
                body: resolvedBody,
                type: 'template',
                payload: {
                    template: {
                        name: picked.name,
                        language: picked.language ?? 'en',
                        components,
                        definition,
                    },
                },
            });
            onSent?.(r.data?.message);
            onClose();
        } catch (err) {
            setSendError(err.response?.data?.message ?? err.response?.data?.error ?? t('inbox.send_failed'));
            setSending(false);
        }
    };

    const bodyText = picked ? (picked.components?.find(c => c.type === 'BODY')?.text ?? '') : '';
    const preview  = picked ? resolveBody(bodyText, vars) : '';
    const varIds   = picked ? extractVars(picked.components) : [];
    const needsHeader = headerFormat && headerFormat !== 'TEXT';
    const headerReady = !needsHeader || (headerMedia?.mediaId && !headerMedia?.uploading);
    const canSend  = headerReady && !sending && varIds.every(id => (vars[id] ?? '').trim() !== '');

    const headerAccept = headerFormat === 'IMAGE' ? 'image/*'
        : headerFormat === 'VIDEO' ? 'video/*'
        : headerFormat === 'DOCUMENT' ? '.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx'
        : '*/*';

    return (
        <div ref={ref} className="absolute bottom-full mb-2 left-0 right-0 z-50 bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-700 rounded-xl shadow-lg overflow-hidden flex flex-col max-h-[28rem]">
            {/* Picker header */}
            <div className="flex items-center gap-2 px-3 py-2 border-b border-neutral-100 dark:border-neutral-800 shrink-0">
                <LayoutTemplate className="h-4 w-4 text-neutral-400 shrink-0" />
                {!picked ? (
                    <input autoFocus value={query} onChange={e => setQuery(e.target.value)}
                        placeholder={t('inbox.search_templates')}
                        className="flex-1 text-sm bg-transparent focus:outline-none placeholder-neutral-400" />
                ) : (
                    <button type="button" onClick={() => setPicked(null)} className="text-xs text-brand-600 hover:underline dark:text-brand-400 flex-1 text-left">
                        {t('inbox.back_to_templates')}
                    </button>
                )}
                <button type="button" onClick={onClose} className="text-neutral-400 hover:text-neutral-600 shrink-0">
                    <X className="h-4 w-4" />
                </button>
            </div>

            {/* Template list */}
            {!picked && (
                <div className="overflow-y-auto flex-1">
                    {loading && <p className="text-xs text-neutral-400 text-center py-6">{t('inbox.loading_templates')}</p>}
                    {!loading && filtered.length === 0 && <p className="text-xs text-neutral-400 text-center py-6">{t('inbox.no_approved_templates')}</p>}
                    {filtered.map(t => {
                        const bodyPreview = t.components?.find(c => c.type === 'BODY')?.text ?? '';
                        const hdr = t.components?.find(c => c.type === 'HEADER');
                        return (
                            <button key={t.id} type="button" onClick={() => pickTemplate(t)}
                                className="w-full text-left px-3 py-2.5 hover:bg-brand-50 dark:hover:bg-brand-900/20 transition border-b border-neutral-100 dark:border-neutral-800 last:border-0">
                                <div className="flex items-center gap-1.5 mb-0.5 flex-wrap">
                                    <span className="text-xs font-semibold text-neutral-800 dark:text-neutral-200">{t.name}</span>
                                    <span className="text-[10px] text-neutral-400 bg-neutral-100 dark:bg-neutral-800 rounded px-1.5">{t.language}</span>
                                    <span className="text-[10px] text-neutral-400 bg-neutral-100 dark:bg-neutral-800 rounded px-1.5">{t.category}</span>
                                    {hdr?.format && hdr.format !== 'TEXT' && (
                                        <span className="text-[10px] text-amber-600 bg-amber-50 dark:bg-amber-900/20 rounded px-1.5 flex items-center gap-0.5">
                                            <ImageIcon className="h-2.5 w-2.5" />{hdr.format}
                                        </span>
                                    )}
                                </div>
                                <p className="text-xs text-neutral-500 line-clamp-2">{bodyPreview || t('inbox.no_body_preview')}</p>
                            </button>
                        );
                    })}
                </div>
            )}

            {/* Fill variables + header media */}
            {picked && (
                <div className="overflow-y-auto flex-1 p-3 space-y-3">
                    <div className="flex items-center gap-2">
                        <span className="text-xs font-semibold text-neutral-800 dark:text-neutral-200">{picked.name}</span>
                        <span className="text-[10px] text-neutral-400 bg-neutral-100 dark:bg-neutral-800 rounded px-1.5">{picked.language}</span>
                        {needsHeader && <span className="text-[10px] text-amber-600 bg-amber-50 dark:bg-amber-900/20 rounded px-1.5">{t('inbox.format_header', { format: headerFormat })}</span>}
                    </div>

                    {/* Header media upload */}
                    {needsHeader && (
                        <div>
                            <p className="text-[10px] font-bold uppercase tracking-wider text-neutral-400 mb-1">
                                {t('inbox.header_label', { format: headerFormat })} <span className="text-red-400">{t('inbox.required')}</span>
                            </p>
                            <input ref={headerFileRef} type="file" accept={headerAccept} className="hidden" onChange={handleHeaderFile} />
                            {!headerMedia ? (
                                <button type="button" onClick={() => headerFileRef.current?.click()}
                                    className="w-full rounded-xl border-2 border-dashed border-neutral-300 dark:border-neutral-600 py-4 flex flex-col items-center gap-1.5 text-neutral-400 hover:border-brand-400 hover:text-brand-500 transition">
                                    <ImageIcon className="h-6 w-6" />
                                    <span className="text-xs">{t('inbox.click_to_upload', { format: headerFormat.toLowerCase() })}</span>
                                </button>
                            ) : (
                                <div className="rounded-xl border border-neutral-200 dark:border-neutral-700 overflow-hidden">
                                    {headerMedia.previewUrl && (
                                        <img src={headerMedia.previewUrl} alt="header preview" className="w-full h-32 object-cover" />
                                    )}
                                    <div className="flex items-center gap-2 px-3 py-2 bg-neutral-50 dark:bg-neutral-800">
                                        {headerMedia.uploading ? (
                                            <><Loader2 className="h-4 w-4 animate-spin text-brand-600" /><span className="text-xs text-neutral-500">{t('inbox.uploading_to_whatsapp')}</span></>
                                        ) : headerMedia.mediaId ? (
                                            <><span className="text-xs text-green-600 dark:text-green-400 font-medium flex-1">{t('inbox.uploaded')}</span></>
                                        ) : (
                                            <span className="text-xs text-red-500 flex-1">{t('inbox.upload_failed')}</span>
                                        )}
                                        <button type="button" onClick={() => { setHeaderMedia(null); setUploadError(''); }}
                                            className="text-neutral-400 hover:text-red-500">
                                            <X className="h-3.5 w-3.5" />
                                        </button>
                                    </div>
                                </div>
                            )}
                            {uploadError && <p className="text-xs text-red-500 mt-1">{uploadError}</p>}
                        </div>
                    )}

                    {/* Body variables */}
                    {varIds.length > 0 && (
                        <div className="space-y-2">
                            <p className="text-[10px] font-bold uppercase tracking-wider text-neutral-400">{t('inbox.body_variables')}</p>
                            {varIds.map(id => (
                                <div key={id}>
                                    <label className="text-xs text-neutral-500 mb-0.5 block">{'{{' + id + '}}'}</label>
                                    <input type="text" value={vars[id] ?? ''} onChange={e => setVars(v => ({ ...v, [id]: e.target.value }))}
                                        placeholder={t('inbox.value_for_variable', { token: `{{${id}}}` })}
                                        className="w-full rounded-lg border border-neutral-300 dark:border-neutral-600 bg-neutral-50 dark:bg-neutral-800 px-2.5 py-1.5 text-xs focus:outline-none focus:ring-2 focus:ring-brand-500" />
                                </div>
                            ))}
                        </div>
                    )}

                    {/* Live preview */}
                    <div>
                        <p className="text-[10px] font-bold uppercase tracking-wider text-neutral-400 mb-1">{t('inbox.preview')}</p>
                        <div className="rounded-xl bg-neutral-50 dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 overflow-hidden">
                            {needsHeader && headerMedia?.previewUrl && (
                                <img src={headerMedia.previewUrl} alt="" className="w-full h-24 object-cover" />
                            )}
                            <div className="p-3 text-xs text-neutral-700 dark:text-neutral-300 whitespace-pre-wrap max-h-28 overflow-y-auto">
                                <WaText text={preview || bodyText} />
                            </div>
                        </div>
                    </div>

                    {sendError && (
                        <p className="text-xs text-red-500 bg-red-50 dark:bg-red-900/20 rounded-lg px-3 py-2">{sendError}</p>
                    )}

                    <button type="button" onClick={handleSend} disabled={!canSend}
                        className="w-full rounded-xl bg-brand-600 hover:bg-brand-700 disabled:opacity-50 text-white text-sm font-semibold py-2.5 transition flex items-center justify-center gap-2">
                        {(headerMedia?.uploading || sending) ? <Loader2 className="h-4 w-4 animate-spin" /> : <Send className="h-4 w-4" />}
                        {headerMedia?.uploading ? t('inbox.uploading') : sending ? t('inbox.sending') : t('inbox.send')}
                    </button>
                </div>
            )}
        </div>
    );
}

/* ─── product picker (share a store product) ─────────── */
function ProductPicker({ conversationId, onSent, onClose }) {
    const { t } = useTranslation();
    const ref = useRef(null);
    const [query, setQuery]       = useState('');
    const [products, setProducts] = useState([]);
    const [loading, setLoading]   = useState(true);
    const [sendingId, setSendingId] = useState(null);
    const [error, setError]       = useState('');

    useEffect(() => {
        const h = (e) => { if (ref.current && !ref.current.contains(e.target)) onClose(); };
        document.addEventListener('mousedown', h);
        return () => document.removeEventListener('mousedown', h);
    }, [onClose]);

    // Debounced search so each keystroke doesn't fire a request.
    useEffect(() => {
        let active = true;
        setLoading(true);
        const t = setTimeout(() => {
            axios.get(route('client.ecommerce.products.search'), { params: { q: query } })
                .then(r => { if (active) setProducts(r.data ?? []); })
                .catch(() => { if (active) setProducts([]); })
                .finally(() => { if (active) setLoading(false); });
        }, 250);
        return () => { active = false; clearTimeout(t); };
    }, [query]);

    const share = (p) => {
        if (sendingId !== null) return;
        setSendingId(p.id);
        setError('');
        axios.post(route('client.inbox.share-product', conversationId), { product_id: p.id }, { headers: { Accept: 'application/json' } })
            .then(r => { onSent(r.data?.message, r.data?.error); })
            .catch(err => setError(err.response?.data?.error ?? err.response?.data?.message ?? t('inbox.failed_share_product')))
            .finally(() => setSendingId(null));
    };

    return (
        <div ref={ref} className="absolute bottom-full mb-2 left-0 right-0 z-20 rounded-xl border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-900 shadow-xl flex flex-col max-h-80">
            <div className="p-2 border-b border-neutral-100 dark:border-neutral-800 flex items-center gap-2">
                <Search className="h-4 w-4 text-neutral-400 shrink-0" />
                <input autoFocus value={query} onChange={e => setQuery(e.target.value)}
                    placeholder={t('inbox.search_products_share')}
                    className="flex-1 bg-transparent text-sm focus:outline-none text-neutral-700 dark:text-neutral-200" />
                <button type="button" onClick={onClose} className="text-neutral-400 hover:text-neutral-600 transition"><X className="h-4 w-4" /></button>
            </div>
            {error && <p className="px-3 py-2 text-xs text-red-500">{error}</p>}
            <div className="overflow-y-auto">
                {loading ? (
                    <p className="text-sm text-neutral-400 py-6 text-center">{t('inbox.loading_products')}</p>
                ) : products.length === 0 ? (
                    <div className="py-6"><EmptyState icon={<ShoppingBag className="h-7 w-7" />} title={t('inbox.no_products')} description={t('inbox.no_products_match')} /></div>
                ) : products.map(p => (
                    <button key={p.id} type="button" onClick={() => share(p)} disabled={sendingId !== null}
                        className="w-full flex items-center gap-3 px-3 py-2 text-left hover:bg-brand-50 dark:hover:bg-brand-900/20 transition border-b border-neutral-100 dark:border-neutral-800 last:border-0 disabled:opacity-60">
                        {p.image_url
                            ? <img src={p.image_url} alt="" className="h-10 w-10 rounded-lg object-cover shrink-0" />
                            : <div className="h-10 w-10 rounded-lg bg-neutral-200 dark:bg-neutral-700 flex items-center justify-center shrink-0"><ShoppingBag className="h-4 w-4 text-neutral-400" /></div>}
                        <div className="flex-1 min-w-0">
                            <p className="text-sm font-medium text-neutral-800 dark:text-neutral-100 truncate">{p.name}</p>
                            <p className="text-xs text-neutral-500 truncate">
                                {p.sku ? `${p.sku} · ` : ''}{p.currency ? `${p.currency} ` : ''}{p.price}
                                {p.inventory_quantity != null && <span className="text-neutral-400"> · {t('inbox.in_stock', { count: p.inventory_quantity })}</span>}
                            </p>
                        </div>
                        {sendingId === p.id
                            ? <Loader2 className="h-4 w-4 animate-spin text-brand-500 shrink-0" />
                            : <Send className="h-4 w-4 text-neutral-300 dark:text-neutral-600 shrink-0" />}
                    </button>
                ))}
            </div>
        </div>
    );
}

/* ─── agent assign dropdown ──────────────────────────── */
function AgentDropdown({ teamMembers, currentUserId, conversationId, onAssigned, onClose }) {
    const { t } = useTranslation();
    const ref = useRef(null);
    const [query, setQuery] = useState('');
    const [loading, setLoading] = useState(false);
    useEffect(() => {
        const handler = (e) => { if (ref.current && !ref.current.contains(e.target)) onClose(); };
        document.addEventListener('mousedown', handler);
        return () => document.removeEventListener('mousedown', handler);
    }, [onClose]);

    const assign = (userId) => {
        setLoading(true);
        axios.post(route('client.inbox.assign', conversationId), { user_id: userId })
            .then(() => { onAssigned(userId); onClose(); })
            .catch(() => {})
            .finally(() => setLoading(false));
    };

    const filtered = (teamMembers ?? []).filter(m =>
        m.name.toLowerCase().includes(query.toLowerCase()) ||
        m.email.toLowerCase().includes(query.toLowerCase())
    );

    return (
        <div ref={ref} className="absolute top-full mt-1 right-0 z-50 w-56 bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-700 rounded-xl shadow-lg overflow-hidden">
            <div className="p-2 border-b border-neutral-100 dark:border-neutral-800">
                <input
                    autoFocus
                    value={query}
                    onChange={e => setQuery(e.target.value)}
                    placeholder={t('inbox.search_agents')}
                    className="w-full text-xs bg-neutral-100 dark:bg-neutral-800 rounded-lg px-2.5 py-1.5 focus:outline-none placeholder-neutral-400"
                />
            </div>
            <div className="max-h-52 overflow-y-auto">
                <button type="button" onClick={() => assign(null)}
                    className="w-full text-left px-3 py-2 text-xs text-neutral-500 hover:bg-neutral-50 dark:hover:bg-neutral-800 transition border-b border-neutral-100 dark:border-neutral-800">
                    {t('inbox.unassign')}
                </button>
                {filtered.map(m => (
                    <button key={m.id} type="button" onClick={() => assign(m.id)}
                        className={`w-full text-left px-3 py-2 hover:bg-brand-50 dark:hover:bg-brand-900/20 transition ${loading ? 'opacity-50 pointer-events-none' : ''}`}>
                        <div className="flex items-center gap-2">
                            <div className="h-6 w-6 rounded-full bg-brand-100 dark:bg-brand-900/30 flex items-center justify-center text-xs font-semibold text-brand-700 dark:text-brand-300 shrink-0">
                                {m.name[0]?.toUpperCase()}
                            </div>
                            <div className="min-w-0">
                                <p className={`text-xs font-medium truncate ${m.id === currentUserId ? 'text-brand-600 dark:text-brand-400' : 'text-neutral-800 dark:text-neutral-200'}`}>
                                    {m.name} {m.id === currentUserId && t('inbox.you_paren')}
                                </p>
                                <p className="text-[10px] text-neutral-400 truncate">{m.email}</p>
                            </div>
                        </div>
                    </button>
                ))}
            </div>
        </div>
    );
}

/* ─── main component ─────────────────────────────────── */

const FAZOS_STATUS = {
    Booked_In: 'Recebida', In_Progress: 'Em andamento', Waiting_For_Parts: 'Aguardando peças',
    Waiting_For_User_Approval: 'Aguardando aprovação', Job_Complete: 'Concluída',
    Customer_Notified: 'Cliente avisado', Complete_Awaiting_Finalise: 'Aguardando finalização', Finalised: 'Finalizada',
};

function fazOSNumber(value) {
    const number = String(value || '').trim();
    if (!number) return 'OS sem número';
    return /^OS(?:\s|[-–—#]|$)/i.test(number) ? number : `OS ${number}`;
}

function fazOSStatusTone(status) {
    const normalized = String(status || '').toLocaleLowerCase('pt-BR');
    if (/(cancel|rejeitad)/.test(normalized)) return 'bg-red-50 text-red-700 ring-red-200 dark:bg-red-950/30 dark:text-red-300 dark:ring-red-900';
    if (/(pront|conclu|finaliz|complete)/.test(normalized)) return 'bg-emerald-50 text-emerald-700 ring-emerald-200 dark:bg-emerald-950/30 dark:text-emerald-300 dark:ring-emerald-900';
    if (/(aguard|esper|pending)/.test(normalized)) return 'bg-amber-50 text-amber-700 ring-amber-200 dark:bg-amber-950/30 dark:text-amber-300 dark:ring-amber-900';
    return 'bg-blue-50 text-blue-700 ring-blue-200 dark:bg-blue-950/30 dark:text-blue-300 dark:ring-blue-900';
}

function uniqueDetails(values) {
    return [...new Set(values.map(value => String(value || '').trim()).filter(Boolean))];
}

function FazOSPanel({ conversationId, contact, initialData }) {
    const [data, setData] = useState(initialData ?? { connected: false, links: [] });
    const [linking, setLinking] = useState(false);
    const [query, setQuery] = useState('');
    const [results, setResults] = useState([]);
    const [loading, setLoading] = useState(false);
    const [expandedOrder, setExpandedOrder] = useState(null);
    const [refreshing, setRefreshing] = useState(false);

    useEffect(() => {
        setData(initialData ?? { connected: false, links: [] });
        setLinking(false);
        setQuery('');
        setResults([]);
        setExpandedOrder(null);
        let active = true;
        axios.get(route('client.inbox.fazos.data', conversationId))
            .then(response => {
                if (active) setData(response.data ?? { connected: false, links: [] });
            })
            .catch(() => {});
        return () => { active = false; };
    }, [conversationId]);

    const refresh = () => {
        setRefreshing(true);
        axios.post(route('client.inbox.fazos.refresh', conversationId))
            .then(response => {
                setData(response.data ?? { connected: false, links: [] });
                toast.success('Clientes e ordens de serviço atualizados.');
            })
            .catch(error => toast.error(error?.response?.data?.message || 'Não foi possível atualizar os dados do FazOS.'))
            .finally(() => setRefreshing(false));
    };

    useEffect(() => {
        if (!linking) return undefined;
        const timer = setTimeout(() => {
            setLoading(true);
            axios.get(route('client.inbox.fazos.search', conversationId), { params: { q: query || contact?.phone_e164 || '' } })
                .then(response => setResults(response.data ?? []))
                .catch(error => toast.error(error?.response?.data?.message || 'Não foi possível buscar clientes no FazOS.'))
                .finally(() => setLoading(false));
        }, 250);
        return () => clearTimeout(timer);
    }, [query, linking, conversationId, contact?.phone_e164]);

    const link = customerUuid => {
        setLoading(true);
        axios.post(route('client.inbox.fazos.link', conversationId), { customer_uuid: customerUuid })
            .then(response => {
                setData(response.data);
                setLinking(false);
                toast.success('Cliente do FazOS vinculado à conversa.');
            })
            .catch(error => toast.error(error?.response?.data?.message || 'Não foi possível criar o vínculo.'))
            .finally(() => setLoading(false));
    };

    const unlink = linkId => {
        if (!confirm('Desvincular este cliente do FazOS?')) return;
        axios.delete(route('client.inbox.fazos.unlink', { conversation: conversationId, link: linkId }))
            .then(response => {
                setData(response.data);
                toast.success('Vínculo removido.');
            })
            .catch(error => toast.error(error?.response?.data?.message || 'Não foi possível remover o vínculo.'));
    };

    if (!data?.connected) {
        return <div className="p-4">
            <div className="rounded-xl border border-amber-200 bg-amber-50 p-3 text-xs leading-5 text-amber-900 dark:border-amber-900 dark:bg-amber-950/20 dark:text-amber-200">
                A integração FazOS ainda não está conectada neste workspace.
                <Link href={route('client.integrations.index')} className="mt-2 block font-semibold text-brand-600 hover:underline">Configurar integração →</Link>
            </div>
        </div>;
    }

    const links = data?.links ?? [];
    return <div className="flex flex-col">
        <div className="border-b border-neutral-100 p-3 dark:border-neutral-800">
            <div className="grid grid-cols-[1fr_auto] gap-2">
            <button type="button" onClick={() => setLinking(value => !value)} disabled={loading || refreshing}
                className="flex min-h-11 items-center justify-center gap-2 rounded-lg border border-brand-200 bg-brand-50 px-3 py-2 text-xs font-semibold text-brand-700 transition hover:bg-brand-100 disabled:opacity-50 dark:border-brand-800 dark:bg-brand-950/20 dark:text-brand-300">
                {loading ? <Loader2 className="h-4 w-4 animate-spin" /> : <Link2 className="h-4 w-4" />}
                {links.length ? 'Alterar vínculo' : 'Vincular cliente do FazOS'}
            </button>
            <button type="button" onClick={refresh} disabled={loading || refreshing} title="Atualizar clientes e OS"
                className="flex h-11 w-11 items-center justify-center rounded-lg border border-neutral-200 text-neutral-500 transition hover:bg-neutral-50 disabled:opacity-50 dark:border-neutral-700 dark:hover:bg-neutral-800">
                <RefreshCw className={`h-4 w-4 ${refreshing ? 'animate-spin' : ''}`} />
            </button>
            </div>
            {linking && <div className="mt-3">
                <label className="text-[10px] font-bold uppercase tracking-wider text-neutral-400">Buscar por nome, telefone ou e-mail</label>
                <div className="mt-1 flex items-center gap-2 rounded-lg border border-neutral-300 px-2 dark:border-neutral-700">
                    <Search className="h-3.5 w-3.5 text-neutral-400" />
                    <input autoFocus value={query} onChange={event => setQuery(event.target.value)}
                        placeholder={contact?.phone_e164 || 'Digite para buscar'}
                        className="min-h-11 min-w-0 flex-1 bg-transparent text-xs outline-none" />
                </div>
                <div className="mt-2 max-h-64 space-y-1 overflow-y-auto">
                    {!loading && results.length === 0 && <p className="p-2 text-xs text-neutral-400">Nenhum cliente encontrado.</p>}
                    {results.map(customer => <button key={customer.uuid} type="button" onClick={() => link(customer.uuid)}
                        className="min-h-11 w-full rounded-lg border border-neutral-200 p-2 text-left transition hover:border-brand-400 hover:bg-brand-50 dark:border-neutral-700 dark:hover:bg-brand-950/20">
                        <p className="truncate text-xs font-semibold text-neutral-800 dark:text-neutral-100">{customer.name || customer.company || 'Cliente sem nome'}</p>
                        <p className="truncate text-[10px] text-neutral-500">{customer.phone || customer.email || `ID ${customer.uuid}`}</p>
                        <p className="mt-1 text-[10px] font-medium text-brand-600">{customer.service_orders_count} OS</p>
                    </button>)}
                </div>
            </div>}
        </div>

        {links.length === 0 && !linking && <div className="p-4 text-center">
            <ClipboardList className="mx-auto h-8 w-8 text-neutral-300" />
            <p className="mt-2 text-xs font-semibold text-neutral-600 dark:text-neutral-300">Contato ainda não vinculado</p>
            <p className="mt-1 text-[11px] leading-4 text-neutral-400">O vínculo automático ocorre apenas quando há um único telefone correspondente.</p>
        </div>}

        {links.map(item => {
            const customer = item.customer;
            return <section key={item.id} className="border-b border-neutral-100 dark:border-neutral-800">
                <div className="p-4">
                    <div className="flex items-start gap-2">
                        <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300"><Wrench className="h-4 w-4" /></div>
                        <div className="min-w-0 flex-1">
                            <p className="truncate text-sm font-semibold text-neutral-900 dark:text-neutral-100">{customer.name || customer.company || 'Cliente FazOS'}</p>
                            <p className="truncate text-[11px] text-neutral-400">{customer.phone || customer.email}</p>
                            <span className="mt-1 inline-block rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-medium text-emerald-700 dark:bg-emerald-950/30 dark:text-emerald-300">{item.type === 'automatic' ? 'Vínculo automático por telefone' : 'Vínculo manual'}</span>
                        </div>
                        <button type="button" onClick={() => unlink(item.id)} aria-label="Desvincular cliente do FazOS"
                            className="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg text-neutral-400 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-950/20"><Unlink2 className="h-4 w-4" /></button>
                    </div>
                </div>
                <div className="px-3 pb-4">
                    <p className="mb-2 text-[10px] font-bold uppercase tracking-wider text-neutral-400">Ordens de serviço ({customer.service_orders?.length ?? 0})</p>
                    <div className="space-y-2">
                        {(customer.service_orders ?? []).map(order => {
                            const open = expandedOrder === order.uuid;
                            const details = order.details ?? {};
                            const device = details.device ?? {};
                            const labour = details.labour_products ?? details.services ?? [];
                            const parts = details.materials ?? details.parts ?? [];
                            const activities = details.activities ?? [];
                            const equipment = uniqueDetails([device.name, device.model_name, device.code, device.serial_no, device.plate_number]);
                            const statusLabel = FAZOS_STATUS[order.status] || order.status || 'Sem status';
                            return <article key={order.uuid} className={`overflow-hidden rounded-xl border bg-white transition-colors dark:bg-neutral-900 ${open ? 'border-brand-300 ring-1 ring-brand-100 dark:border-brand-700 dark:ring-brand-900/40' : 'border-neutral-200 dark:border-neutral-700'}`}>
                                <div className="p-3">
                                    <div className="flex items-start justify-between gap-2">
                                        <div className="min-w-0 flex-1">
                                            {order.url ? <a href={order.url} target="_blank" rel="noopener noreferrer"
                                                aria-label={`Abrir ${fazOSNumber(order.number || order.external_id)} no FazOS em uma nova aba`}
                                                className="inline-flex min-h-11 max-w-full cursor-pointer items-center gap-1.5 rounded-md py-1 text-xs font-bold text-brand-700 underline decoration-brand-200 underline-offset-4 transition hover:text-brand-800 hover:decoration-brand-500 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 dark:text-brand-300">
                                                <span className="truncate">{fazOSNumber(order.number || order.external_id)}</span>
                                                <ExternalLink className="h-3.5 w-3.5 shrink-0" aria-hidden="true" />
                                            </a> : <p className="flex min-h-11 items-center text-xs font-bold text-neutral-800 dark:text-neutral-100">{fazOSNumber(order.number || order.external_id)}</p>}
                                        </div>
                                        <span className={`mt-2 shrink-0 rounded-full px-2 py-1 text-[9px] font-semibold ring-1 ring-inset ${fazOSStatusTone(order.status)}`}>{statusLabel}</span>
                                    </div>
                                    <p className="line-clamp-2 text-[11px] font-medium leading-4 text-neutral-700 dark:text-neutral-200">{order.title || device.name || order.tracking_number || 'Sem descrição'}</p>
                                    {order.estimated_completion_at && <div className="mt-2 flex items-start gap-1.5 text-[10px] leading-4 text-neutral-500 dark:text-neutral-400">
                                        <Calendar className="mt-0.5 h-3 w-3 shrink-0" aria-hidden="true" />
                                        <span><span className="font-medium text-neutral-600 dark:text-neutral-300">Previsão:</span> {formatInTz(order.estimated_completion_at, 'America/Sao_Paulo')}</span>
                                    </div>}
                                    <button type="button" onClick={() => setExpandedOrder(open ? null : order.uuid)} aria-expanded={open}
                                        aria-label={`${open ? 'Ocultar' : 'Mostrar'} detalhes de ${fazOSNumber(order.number || order.external_id)}`}
                                        className="mt-2 flex min-h-11 w-full cursor-pointer items-center justify-between rounded-lg border border-neutral-200 px-3 text-[10px] font-semibold text-neutral-600 transition hover:border-brand-200 hover:bg-brand-50 hover:text-brand-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 dark:border-neutral-700 dark:text-neutral-300 dark:hover:border-brand-800 dark:hover:bg-brand-950/20">
                                        <span>{open ? 'Ocultar detalhes' : 'Ver detalhes da OS'}</span>
                                        {open ? <ChevronUp className="h-3.5 w-3.5" aria-hidden="true" /> : <ChevronDown className="h-3.5 w-3.5" aria-hidden="true" />}
                                    </button>
                                </div>
                                {open && <div className="space-y-4 border-t border-neutral-200 bg-neutral-50/80 p-3 text-[11px] leading-4 dark:border-neutral-700 dark:bg-neutral-950/40">
                                    <dl className="grid gap-3">
                                        {order.tracking_number && <div><dt className="text-[9px] font-bold uppercase tracking-wide text-neutral-400">Localizador</dt><dd className="mt-0.5 break-all font-medium text-neutral-800 dark:text-neutral-100">{order.tracking_number}</dd></div>}
                                        {equipment.length > 0 && <div><dt className="text-[9px] font-bold uppercase tracking-wide text-neutral-400">Equipamento</dt><dd className="mt-0.5 font-medium text-neutral-800 dark:text-neutral-100">{equipment.join(' · ')}</dd></div>}
                                        {details.issue_description && <div><dt className="text-[9px] font-bold uppercase tracking-wide text-neutral-400">Problema relatado</dt><dd className="mt-0.5 whitespace-pre-wrap font-medium text-neutral-800 dark:text-neutral-100">{details.issue_description}</dd></div>}
                                        {details.job_description && <div><dt className="text-[9px] font-bold uppercase tracking-wide text-neutral-400">Serviço executado</dt><dd className="mt-0.5 whitespace-pre-wrap font-medium text-neutral-800 dark:text-neutral-100">{details.job_description}</dd></div>}
                                        {details.additional_description && <div><dt className="text-[9px] font-bold uppercase tracking-wide text-neutral-400">Observações</dt><dd className="mt-0.5 whitespace-pre-wrap font-medium text-neutral-800 dark:text-neutral-100">{details.additional_description}</dd></div>}
                                        {order.total != null && <div><dt className="text-[9px] font-bold uppercase tracking-wide text-neutral-400">Total</dt><dd className="mt-0.5 text-sm font-bold tabular-nums text-neutral-900 dark:text-white">{new Intl.NumberFormat('pt-BR', { style: 'currency', currency: /^[A-Z]{3}$/.test(order.currency || '') ? order.currency : 'BRL' }).format(Number(order.total))}</dd></div>}
                                    </dl>
                                    {labour.length > 0 && <div><p className="text-[9px] font-bold uppercase tracking-wide text-neutral-400">Serviços</p><ul className="mt-1.5 space-y-1.5">{labour.map((line, index) => <li key={line.id ?? index} className="flex justify-between gap-2"><span>{line.name || line.description}</span><span className="shrink-0 tabular-nums text-neutral-500">× {line.qty ?? 1}</span></li>)}</ul></div>}
                                    {parts.length > 0 && <div><p className="text-[9px] font-bold uppercase tracking-wide text-neutral-400">Peças e materiais</p><ul className="mt-1.5 space-y-1.5">{parts.map((line, index) => <li key={line.id ?? index} className="flex justify-between gap-2"><span>{line.name || line.description}</span><span className="shrink-0 tabular-nums text-neutral-500">× {line.qty ?? line.estimated_qty ?? 1}</span></li>)}</ul></div>}
                                    {activities.length > 0 && <div><p className="text-[9px] font-bold uppercase tracking-wide text-neutral-400">Histórico</p><ol className="mt-2 space-y-2 border-l-2 border-neutral-200 pl-3 dark:border-neutral-700">{activities.map((activity, index) => <li key={activity.id ?? index}><p className="font-medium text-neutral-700 dark:text-neutral-200">{activity.description}</p><p className="mt-0.5 text-[9px] text-neutral-400">{activity.date}</p></li>)}</ol></div>}
                                    <p className="border-t border-neutral-200 pt-3 text-[9px] leading-4 text-neutral-500 dark:border-neutral-700 dark:text-neutral-400">Dados sincronizados do FazOS. Use o número da OS acima para abrir e editar o registro original.</p>
                                </div>}
                            </article>;
                        })}
                        {(customer.service_orders ?? []).length === 0 && <p className="rounded-lg bg-neutral-50 p-3 text-center text-[11px] text-neutral-400 dark:bg-neutral-800">Nenhuma OS para este cliente.</p>}
                    </div>
                </div>
            </section>;
        })}
    </div>;
}

function ConversationAppointmentModal({ contact, services, resources, onClose }) {
    const customerName = [contact?.first_name, contact?.last_name].filter(Boolean).join(' ') || contact?.phone_e164 || '';
    const form = useForm({
        service_uuid: services[0]?.uuid || '',
        resource_uuid: resources[0]?.uuid || '',
        contact_uuid: contact?.uuid || '',
        customer_name: customerName,
        customer_email: contact?.email || '',
        customer_phone: contact?.phone_e164 || '',
        starts_at: '',
        notes: '',
        customer_reminder_minutes: 1440,
        professional_reminder_minutes: 120,
    });
    const save = event => {
        event.preventDefault();
        form.post(route('client.appointments.store'), {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                toast.success('Agendamento criado para este contato.');
                onClose();
            },
        });
    };
    const fieldClass = 'mt-1 min-h-11 w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-base text-neutral-900 outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-100 sm:text-sm';
    return <div className="fixed inset-0 z-[80] flex items-center justify-center bg-black/45 p-4" onMouseDown={event => event.target === event.currentTarget && onClose()} role="dialog" aria-modal="true" aria-labelledby="conversation-appointment-title">
        <form onSubmit={save} className="w-full max-w-md rounded-2xl bg-white p-5 shadow-2xl dark:bg-neutral-900">
            <div className="mb-4 flex items-start justify-between">
                <div><h2 id="conversation-appointment-title" className="font-semibold text-neutral-900 dark:text-neutral-100">Agendar atendimento</h2><p className="mt-1 text-xs text-neutral-500">Os dados de {customerName} foram preenchidos pela conversa.</p></div>
                <button type="button" onClick={onClose} aria-label="Fechar janela de agendamento" className="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg transition hover:bg-neutral-100 focus:outline-none focus:ring-2 focus:ring-brand-500 dark:hover:bg-neutral-800"><X className="h-4 w-4" /></button>
            </div>
            {services.length === 0 || resources.length === 0 ? (
                <div className="rounded-xl bg-amber-50 p-4 text-sm text-amber-800">
                    Cadastre pelo menos um serviço e um profissional na área de Agendamentos antes de continuar.
                    <Link href={route('client.appointments.index')} className="mt-2 block font-semibold underline">Abrir Agendamentos</Link>
                </div>
            ) : <div className="grid gap-4">
                <label htmlFor="conversation-appointment-service" className="text-xs font-semibold text-neutral-700 dark:text-neutral-200">Serviço *<select id="conversation-appointment-service" className={fieldClass} value={form.data.service_uuid} onChange={event => form.setData('service_uuid', event.target.value)} required>{services.map(item => <option key={item.uuid} value={item.uuid}>{item.name}</option>)}</select>{form.errors.service_uuid && <span role="alert" className="mt-1 block text-xs font-normal text-red-600">{form.errors.service_uuid}</span>}</label>
                <label htmlFor="conversation-appointment-professional" className="text-xs font-semibold text-neutral-700 dark:text-neutral-200">Profissional ou recurso *<select id="conversation-appointment-professional" className={fieldClass} value={form.data.resource_uuid} onChange={event => form.setData('resource_uuid', event.target.value)} required>{resources.map(item => <option key={item.uuid} value={item.uuid}>{item.name}{item.position ? ` — ${item.position}` : ''}</option>)}</select>{form.errors.resource_uuid && <span role="alert" className="mt-1 block text-xs font-normal text-red-600">{form.errors.resource_uuid}</span>}</label>
                <label htmlFor="conversation-appointment-date" className="text-xs font-semibold text-neutral-700 dark:text-neutral-200">Data e horário *<input id="conversation-appointment-date" type="datetime-local" className={fieldClass} value={form.data.starts_at} onChange={event => form.setData('starts_at', event.target.value)} required />{form.errors.starts_at && <span role="alert" className="mt-1 block text-xs font-normal text-red-600">{form.errors.starts_at}</span>}</label>
                <label htmlFor="conversation-appointment-notes" className="text-xs font-semibold text-neutral-700 dark:text-neutral-200">Observações <span className="font-normal text-neutral-500">(opcional)</span><textarea id="conversation-appointment-notes" rows="3" className={fieldClass} value={form.data.notes} onChange={event => form.setData('notes', event.target.value)} />{form.errors.notes && <span role="alert" className="mt-1 block text-xs font-normal text-red-600">{form.errors.notes}</span>}</label>
                <div className="rounded-lg bg-neutral-50 p-3 dark:bg-neutral-800"><p className="text-[11px] font-semibold uppercase tracking-wide text-neutral-500">Cliente da conversa</p><p className="mt-1 text-sm font-semibold text-neutral-800 dark:text-neutral-100">{customerName}</p><p className="mt-0.5 text-xs text-neutral-500">{contact?.phone_e164 || 'Sem telefone'}{contact?.email ? ` · ${contact.email}` : ''}</p></div>
                {Object.entries(form.errors).filter(([field]) => !['service_uuid', 'resource_uuid', 'starts_at', 'notes'].includes(field)).map(([field, error]) => <p key={field} role="alert" className="text-xs text-red-600">{error}</p>)}
                <div className="flex justify-end gap-2"><button type="button" onClick={onClose} className="min-h-11 rounded-lg border px-4 text-sm transition hover:bg-neutral-50 focus:outline-none focus:ring-2 focus:ring-brand-500 dark:border-neutral-700 dark:hover:bg-neutral-800">Cancelar</button><button disabled={form.processing} className="min-h-11 rounded-lg bg-brand-600 px-4 text-sm font-semibold text-white transition hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500 disabled:cursor-not-allowed disabled:opacity-50">{form.processing ? 'Agendando…' : 'Confirmar agendamento'}</button></div>
            </div>}
        </form>
    </div>;
}

const APPOINTMENT_STATUS = {
    scheduled: { label: 'Agendado', className: 'bg-blue-100 text-blue-700 dark:bg-blue-950/40 dark:text-blue-300' },
    confirmed: { label: 'Confirmado', className: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300' },
    completed: { label: 'Concluído', className: 'bg-cyan-100 text-cyan-700 dark:bg-cyan-950/40 dark:text-cyan-300' },
    cancelled: { label: 'Cancelado', className: 'bg-red-100 text-red-700 dark:bg-red-950/40 dark:text-red-300' },
    no_show: { label: 'Não compareceu', className: 'bg-amber-100 text-amber-800 dark:bg-amber-950/40 dark:text-amber-300' },
};

const SCHEDULE_STATUS = {
    scheduled: { label: 'Agendada', className: 'bg-blue-100 text-blue-700 dark:bg-blue-950/40 dark:text-blue-300' },
    processing: { label: 'Enviando', className: 'bg-amber-100 text-amber-800 dark:bg-amber-950/40 dark:text-amber-300' },
    paused: { label: 'Pausada', className: 'bg-neutral-100 text-neutral-700 dark:bg-neutral-800 dark:text-neutral-300' },
    completed: { label: 'Concluída', className: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300' },
    failed: { label: 'Falhou', className: 'bg-red-100 text-red-700 dark:bg-red-950/40 dark:text-red-300' },
    cancelled: { label: 'Cancelada', className: 'bg-neutral-100 text-neutral-500 dark:bg-neutral-800 dark:text-neutral-400' },
};
const SCHEDULE_UNIT = { once: 'Envio único', minute: 'minuto(s)', hour: 'hora(s)', day: 'dia(s)', week: 'semana(s)', month: 'mês(es)' };

function ConversationAppointmentsTab({ appointments, scheduledMessages, userTz, onCreateAppointment, onCreateMessage, onEditMessage }) {
    const scheduleAction = (name, uuid) => router.post(
        route(`client.inbox.scheduled-messages.${name}`, uuid),
        {},
        { preserveScroll: true, only: ['scheduledMessages'] },
    );
    const deleteSchedule = (uuid) => {
        if (!confirm('Excluir definitivamente esta mensagem agendada?')) return;
        router.delete(route('client.inbox.scheduled-messages.destroy', uuid), {
            preserveScroll: true,
            only: ['scheduledMessages'],
        });
    };

    return (
        <div className="min-h-0 flex-1 overflow-y-auto p-3 sm:p-4">
            <div className="mx-auto max-w-3xl">
                <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 className="text-sm font-semibold text-neutral-900 dark:text-neutral-100">Agendamentos desta conversa</h2>
                        <p className="mt-0.5 text-xs text-neutral-500">Mensagens programadas e atendimentos vinculados ao lead.</p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <button type="button" onClick={onCreateAppointment} className="inline-flex min-h-11 items-center gap-2 rounded-xl border border-neutral-300 px-3 text-sm font-semibold text-neutral-700 transition hover:border-brand-300 hover:text-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500 dark:border-neutral-700 dark:text-neutral-200"><CalendarPlus className="h-4 w-4" /> Atendimento</button>
                        <button type="button" onClick={onCreateMessage} className="inline-flex min-h-11 items-center gap-2 rounded-xl bg-brand-600 px-3 text-sm font-semibold text-white transition hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 dark:focus:ring-offset-neutral-950"><CalendarClock className="h-4 w-4" /> Mensagem</button>
                    </div>
                </div>

                {scheduledMessages.length === 0 && appointments.length === 0 ? (
                    <EmptyState
                        icon={<CalendarClock className="h-8 w-8" />}
                        title="Nenhum agendamento nesta conversa"
                        description="Programe uma mensagem ou um atendimento sem sair da conversa."
                    />
                ) : (
                    <div className="space-y-6">
                        <section>
                            <div className="mb-2 flex items-center justify-between"><h3 className="text-xs font-bold uppercase tracking-wide text-neutral-500">Mensagens programadas</h3><span className="text-xs tabular-nums text-neutral-400">{scheduledMessages.length}</span></div>
                            {scheduledMessages.length === 0 ? <p className="rounded-xl border border-dashed border-neutral-300 p-4 text-center text-xs text-neutral-500 dark:border-neutral-700">Nenhuma mensagem programada.</p> : <div className="space-y-3">{scheduledMessages.map(schedule => {
                                const meta = SCHEDULE_STATUS[schedule.status] ?? SCHEDULE_STATUS.scheduled;
                                const recurring = schedule.recurrence_unit !== 'once';
                                return <article key={schedule.uuid} className="rounded-2xl border border-neutral-200 bg-white p-4 shadow-sm dark:border-neutral-700 dark:bg-neutral-900">
                                    <div className="flex flex-wrap items-start justify-between gap-3">
                                        <div className="min-w-0 flex-1"><div className="flex flex-wrap items-center gap-2"><CalendarClock className="h-4 w-4 text-brand-600" /><span className={`rounded-full px-2 py-1 text-[10px] font-semibold ${meta.className}`}>{meta.label}</span></div><p className="mt-2 whitespace-pre-wrap text-sm text-neutral-800 dark:text-neutral-100">{schedule.body || 'Mensagem com imagem'}</p></div>
                                        <div className="flex items-center gap-1">
                                            {['scheduled', 'paused', 'failed'].includes(schedule.status) && <button type="button" onClick={() => onEditMessage(schedule)} aria-label="Editar mensagem agendada" className="flex h-11 w-11 items-center justify-center rounded-xl text-neutral-500 transition hover:bg-brand-50 hover:text-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500 dark:hover:bg-brand-950/30"><Pencil className="h-5 w-5" /></button>}
                                            {['scheduled', 'processing'].includes(schedule.status) && <button type="button" onClick={() => scheduleAction('pause', schedule.uuid)} aria-label="Pausar mensagem agendada" className="flex h-11 w-11 items-center justify-center rounded-xl text-neutral-500 transition hover:bg-amber-50 hover:text-amber-700 focus:outline-none focus:ring-2 focus:ring-brand-500 dark:hover:bg-amber-950/30"><CirclePause className="h-5 w-5" /></button>}
                                            {['paused', 'failed'].includes(schedule.status) && <button type="button" onClick={() => scheduleAction('resume', schedule.uuid)} aria-label="Retomar mensagem agendada" className="flex h-11 w-11 items-center justify-center rounded-xl text-neutral-500 transition hover:bg-emerald-50 hover:text-emerald-700 focus:outline-none focus:ring-2 focus:ring-brand-500 dark:hover:bg-emerald-950/30"><Play className="h-5 w-5" /></button>}
                                            {schedule.status !== 'processing' && <button type="button" onClick={() => deleteSchedule(schedule.uuid)} aria-label="Excluir mensagem agendada" className="flex h-11 w-11 items-center justify-center rounded-xl text-neutral-500 transition hover:bg-red-50 hover:text-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 dark:hover:bg-red-950/30"><Trash2 className="h-5 w-5" /></button>}
                                        </div>
                                    </div>
                                    <div className="mt-3 grid gap-2 border-t border-neutral-100 pt-3 text-xs text-neutral-600 dark:border-neutral-800 dark:text-neutral-300 sm:grid-cols-2"><span className="flex items-center gap-1.5"><Calendar className="h-3.5 w-3.5 text-brand-500" />{formatInTz(schedule.next_send_at, schedule.timezone || userTz)}</span><span className="flex items-center gap-1.5"><Repeat2 className="h-3.5 w-3.5 text-brand-500" />{recurring ? `A cada ${schedule.recurrence_interval} ${SCHEDULE_UNIT[schedule.recurrence_unit]}` : SCHEDULE_UNIT.once} · {schedule.occurrences_sent} enviado(s)</span></div>
                                    {schedule.last_error && <p role="alert" className="mt-3 rounded-lg bg-red-50 px-3 py-2 text-xs text-red-700 dark:bg-red-950/30 dark:text-red-300">{schedule.last_error}</p>}
                                </article>;
                            })}</div>}
                        </section>

                        <section>
                            <div className="mb-2 flex items-center justify-between"><h3 className="text-xs font-bold uppercase tracking-wide text-neutral-500">Atendimentos</h3><span className="text-xs tabular-nums text-neutral-400">{appointments.length}</span></div>
                            {appointments.length === 0 ? <p className="rounded-xl border border-dashed border-neutral-300 p-4 text-center text-xs text-neutral-500 dark:border-neutral-700">Nenhum atendimento agendado.</p> : <div className="space-y-3">{appointments.map(appointment => {
                            const status = APPOINTMENT_STATUS[appointment.status] ?? APPOINTMENT_STATUS.scheduled;
                            return (
                                <article key={appointment.uuid} className="rounded-2xl border border-neutral-200 bg-white p-4 shadow-sm dark:border-neutral-700 dark:bg-neutral-900">
                                    <div className="flex flex-wrap items-start justify-between gap-3">
                                        <div className="min-w-0">
                                            <div className="flex flex-wrap items-center gap-2">
                                                <span className="font-mono text-xs font-semibold text-brand-700 dark:text-brand-300">{appointment.reference}</span>
                                                <span className={`rounded-full px-2 py-1 text-[10px] font-semibold ${status.className}`}>{status.label}</span>
                                            </div>
                                            <h3 className="mt-2 text-sm font-semibold text-neutral-900 dark:text-neutral-100">{appointment.service?.name ?? 'Atendimento'}</h3>
                                        </div>
                                        <Link
                                            href={route('client.appointments.calendar')}
                                            className="inline-flex min-h-11 items-center gap-1.5 rounded-xl border border-neutral-200 px-3 text-xs font-semibold text-neutral-600 transition hover:border-brand-300 hover:text-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500 dark:border-neutral-700 dark:text-neutral-300"
                                        >
                                            Ver na agenda <ExternalLink className="h-3.5 w-3.5" />
                                        </Link>
                                    </div>
                                    <dl className="mt-3 grid gap-3 border-t border-neutral-100 pt-3 text-xs dark:border-neutral-800 sm:grid-cols-2">
                                        <div>
                                            <dt className="font-medium text-neutral-400">Data e horário</dt>
                                            <dd className="mt-1 flex items-center gap-1.5 font-semibold text-neutral-700 dark:text-neutral-200"><Calendar className="h-3.5 w-3.5 text-brand-500" />{formatInTz(appointment.starts_at, userTz)}</dd>
                                        </div>
                                        <div>
                                            <dt className="font-medium text-neutral-400">Profissional ou recurso</dt>
                                            <dd className="mt-1 font-semibold text-neutral-700 dark:text-neutral-200">{appointment.resource?.name ?? 'Não definido'}</dd>
                                        </div>
                                    </dl>
                                    {appointment.notes && <p className="mt-3 whitespace-pre-wrap rounded-xl bg-neutral-50 px-3 py-2 text-xs leading-5 text-neutral-600 dark:bg-neutral-800 dark:text-neutral-300">{appointment.notes}</p>}
                                </article>
                            );
                            })}</div>}
                        </section>
                    </div>
                )}
            </div>
        </div>
    );
}

function ConversationKanbanModal({
    current,
    pipelines,
    pipelineUuid,
    stageUuid,
    onPipelineChange,
    onStageChange,
    onSave,
    saving,
    onClose,
}) {
    const selectedPipeline = pipelines.find((item) => item.uuid === pipelineUuid);

    return (
        <div className="fixed inset-0 z-[90] flex items-center justify-center bg-black/45 p-4" onMouseDown={event => event.target === event.currentTarget && onClose()}>
            <div className="w-full max-w-md rounded-2xl bg-white p-5 shadow-2xl dark:bg-neutral-900">
                <div className="mb-4 flex items-start justify-between gap-4">
                    <div>
                        <h2 className="flex items-center gap-2 font-semibold text-neutral-900 dark:text-neutral-100"><Columns3 className="h-5 w-5 text-brand-600" />Kanban do contato</h2>
                        <p className="mt-1 text-xs text-neutral-500">Escolha a pipeline e a coluna sem sair da conversa.</p>
                    </div>
                    <button type="button" onClick={onClose} className="rounded-lg p-1.5 hover:bg-neutral-100 dark:hover:bg-neutral-800"><X className="h-4 w-4" /></button>
                </div>
                {current && (
                    <div className="mb-4 rounded-xl border border-brand-200 bg-brand-50 p-3 text-xs text-brand-800 dark:border-brand-800 dark:bg-brand-950/30 dark:text-brand-200">
                        Atualmente em <strong>{current.pipeline?.name}</strong> → <strong>{current.stage?.name}</strong>
                    </div>
                )}
                <div className="grid gap-3">
                    <label className="text-xs font-medium text-neutral-600 dark:text-neutral-300">Pipeline
                        <select value={pipelineUuid} onChange={event => onPipelineChange(event.target.value)} className="mt-1 w-full rounded-xl border border-neutral-300 bg-white px-3 py-2.5 text-sm outline-none focus:border-brand-500 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-100">
                            {pipelines.map(pipeline => <option key={pipeline.uuid} value={pipeline.uuid}>{pipeline.name}</option>)}
                        </select>
                    </label>
                    <label className="text-xs font-medium text-neutral-600 dark:text-neutral-300">Coluna
                        <select value={stageUuid} onChange={event => onStageChange(event.target.value)} className="mt-1 w-full rounded-xl border border-neutral-300 bg-white px-3 py-2.5 text-sm outline-none focus:border-brand-500 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-100">
                            {(selectedPipeline?.stages ?? []).map(stage => <option key={stage.uuid} value={stage.uuid}>{stage.name}</option>)}
                        </select>
                    </label>
                </div>
                <div className="mt-5 flex justify-end gap-2">
                    <button type="button" onClick={onClose} className="rounded-xl border border-neutral-300 px-4 py-2 text-sm dark:border-neutral-700">Cancelar</button>
                    <button type="button" disabled={saving || !stageUuid} onClick={onSave} className="inline-flex items-center gap-2 rounded-xl bg-brand-600 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50">
                        {saving && <Loader2 className="h-4 w-4 animate-spin" />}{current ? 'Mover no Kanban' : 'Adicionar ao Kanban'}
                    </button>
                </div>
            </div>
        </div>
    );
}

function MessageTaskModal({ message, conversation, taskBoards, teamMembers, onClose }) {
    const columns = taskBoards?.[0]?.columns ?? [];
    const contactName = conversation.contact?.first_name || conversation.contact?.phone_e164 || 'cliente';
    const fallbackTitle = message?.body && message.body !== '(media)'
        ? message.body.slice(0, 180)
        : `Tarefa da conversa com ${contactName}`;
    const [form, setForm] = useState({
        column_id: columns[0]?.id ?? '', title: fallbackTitle, description: '',
        assigned_user_id: '', priority: 'normal', due_at: '',
    });
    const [checklist, setChecklist] = useState(['']);
    const [images, setImages] = useState([]);
    const [saving, setSaving] = useState(false);
    const imageInputRef = useRef(null);
    const inputClass = 'mt-1 w-full rounded-xl border border-neutral-300 bg-white px-3 py-2.5 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/15 dark:border-neutral-700 dark:bg-neutral-800';
    const addImages = files => setImages(current => [
        ...current,
        ...[...files].filter(file => file.type.startsWith('image/')),
    ].slice(0, 10));

    const submit = async event => {
        event.preventDefault();
        if (!form.title.trim() || !form.column_id || saving) return;
        setSaving(true);
        const data = new FormData();
        Object.entries(form).forEach(([key, value]) => value !== '' && data.append(key, value));
        checklist.forEach(item => item.trim() && data.append('checklist[]', item.trim()));
        images.forEach(file => data.append('images[]', file));
        try {
            await axios.post(route('client.inbox.messages.task', {
                conversation: conversation.uuid, message: message.id,
            }), data, { headers: { 'Content-Type': 'multipart/form-data', Accept: 'application/json' } });
            toast.success('Mensagem transformada em tarefa.');
            onClose();
        } catch (error) {
            const errors = error?.response?.data?.errors;
            toast.error(errors ? Object.values(errors).flat()[0] : (error?.response?.data?.message || 'Não foi possível criar a tarefa.'));
        } finally {
            setSaving(false);
        }
    };

    return (
        <div className="fixed inset-0 z-[110] flex items-center justify-center bg-neutral-950/55 p-4" onMouseDown={onClose}>
            <form onSubmit={submit} onPaste={event => addImages(event.clipboardData?.files ?? [])}
                onMouseDown={event => event.stopPropagation()}
                className="max-h-[92vh] w-full max-w-2xl overflow-y-auto rounded-2xl bg-white p-5 shadow-2xl dark:bg-neutral-900">
                <div className="mb-5 flex items-start justify-between gap-4">
                    <div>
                        <h2 className="flex items-center gap-2 text-lg font-semibold"><CheckSquare className="h-5 w-5 text-brand-600" />Transformar mensagem em tarefa</h2>
                        <p className="mt-1 text-xs text-neutral-500">Complemente a tarefa sem sair desta conversa.</p>
                    </div>
                    <button type="button" onClick={onClose} className="rounded-lg p-1.5 hover:bg-neutral-100 dark:hover:bg-neutral-800"><X className="h-4 w-4" /></button>
                </div>
                <div className="mb-4 rounded-xl border-l-4 border-brand-500 bg-brand-50 px-3 py-2.5 text-xs dark:bg-brand-950/30">
                    <b>Mensagem original:</b> {message.body || `[${message.type || 'mensagem'}]`}
                </div>
                <div className="grid gap-4 sm:grid-cols-2">
                    <label className="text-xs font-medium sm:col-span-2">Título<input className={inputClass} value={form.title} maxLength={180} required onChange={event => setForm({ ...form, title: event.target.value })} /></label>
                    <label className="text-xs font-medium">Coluna<select className={inputClass} value={form.column_id} required onChange={event => setForm({ ...form, column_id: event.target.value })}>
                        {columns.map(column => <option key={column.id} value={column.id}>{column.name}</option>)}
                    </select></label>
                    <label className="text-xs font-medium">Responsável<select className={inputClass} value={form.assigned_user_id} onChange={event => setForm({ ...form, assigned_user_id: event.target.value })}>
                        <option value="">Sem responsável</option>{teamMembers.map(user => <option key={user.id} value={user.id}>{user.name}</option>)}
                    </select></label>
                    <label className="text-xs font-medium">Prioridade<select className={inputClass} value={form.priority} onChange={event => setForm({ ...form, priority: event.target.value })}>
                        <option value="low">Baixa</option><option value="normal">Normal</option><option value="high">Alta</option><option value="urgent">Urgente</option>
                    </select></label>
                    <label className="text-xs font-medium">Prazo<input type="datetime-local" className={inputClass} value={form.due_at} onChange={event => setForm({ ...form, due_at: event.target.value })} /></label>
                    <label className="text-xs font-medium sm:col-span-2">Detalhes<textarea rows="4" className={inputClass} value={form.description} onChange={event => setForm({ ...form, description: event.target.value })} placeholder="Contexto, instruções e resultado esperado…" /></label>
                </div>
                <div className="mt-4">
                    <div className="mb-2 flex items-center justify-between"><span className="text-xs font-semibold">Checklist</span>
                        <button type="button" onClick={() => setChecklist(items => [...items, ''])} className="text-xs font-medium text-brand-600">+ Adicionar item</button>
                    </div>
                    <div className="space-y-2">{checklist.map((item, index) => (
                        <div key={index} className="flex gap-2">
                            <input className={inputClass.replace('mt-1 ', '')} value={item} placeholder={`Item ${index + 1}`} onChange={event => setChecklist(items => items.map((value, itemIndex) => itemIndex === index ? event.target.value : value))} />
                            <button type="button" onClick={() => setChecklist(items => items.filter((_, itemIndex) => itemIndex !== index))} className="rounded-lg p-2 text-neutral-400 hover:bg-red-50 hover:text-red-600"><X className="h-4 w-4" /></button>
                        </div>
                    ))}</div>
                </div>
                <div className="mt-4 rounded-xl border border-dashed border-neutral-300 p-4 dark:border-neutral-700">
                    <input ref={imageInputRef} type="file" accept="image/*" multiple className="hidden" onChange={event => addImages(event.target.files ?? [])} />
                    <button type="button" onClick={() => imageInputRef.current?.click()} className="flex items-center gap-2 text-sm font-medium text-brand-600"><ImageIcon className="h-4 w-4" />Adicionar imagens ou prints</button>
                    <p className="mt-1 text-xs text-neutral-500">Você também pode colar uma captura de tela aqui com Ctrl+V.</p>
                    {images.length > 0 && <div className="mt-3 flex flex-wrap gap-2">{images.map((file, index) => (
                        <div key={`${file.name}-${index}`} className="relative">
                            <img src={URL.createObjectURL(file)} alt="" className="h-16 w-16 rounded-lg object-cover" />
                            <button type="button" onClick={() => setImages(items => items.filter((_, itemIndex) => itemIndex !== index))} className="absolute -right-1 -top-1 rounded-full bg-neutral-900 p-0.5 text-white"><X className="h-3 w-3" /></button>
                        </div>
                    ))}</div>}
                </div>
                <div className="mt-5 flex justify-end gap-2">
                    <button type="button" onClick={onClose} className="rounded-xl border px-4 py-2 text-sm dark:border-neutral-700">Cancelar</button>
                    <button disabled={saving || !columns.length} className="rounded-xl bg-brand-600 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50">{saving ? 'Criando…' : 'Criar tarefa'}</button>
                </div>
            </form>
        </div>
    );
}

function futureLocalTime() {
        const date = new Date(Date.now() + 5 * 60 * 1000);
        date.setMinutes(Math.ceil(date.getMinutes() / 5) * 5, 0, 0);
        return new Date(date.getTime() - date.getTimezoneOffset() * 60000).toISOString().slice(0, 16);
}

function ScheduleMessageModal({ conversation, schedule = null, initialBody, onClose, onCreated }) {
    const editing = Boolean(schedule);
    const [minimumSendAt] = useState(() => futureLocalTime());
    const scheduleTimezone = schedule?.timezone || Intl.DateTimeFormat().resolvedOptions().timeZone || 'America/Sao_Paulo';
    const storedSendAt = utcToTzLocal(schedule?.next_send_at, scheduleTimezone);
    const [form, setForm] = useState({
        body: schedule?.body ?? initialBody ?? '',
        image: null,
        remove_image: false,
        send_at: storedSendAt && storedSendAt > minimumSendAt ? storedSendAt : minimumSendAt,
        recurrence_unit: schedule?.recurrence_unit || 'once',
        recurrence_interval: schedule?.recurrence_interval || 1,
        max_occurrences: schedule?.max_occurrences ?? '',
        ends_at: utcToTzLocal(schedule?.ends_at, scheduleTimezone),
    });
    const [saving, setSaving] = useState(false);
    const [errors, setErrors] = useState({});
    const bodyRef = useRef(null);
    const inputClass = 'mt-1 min-h-11 w-full rounded-xl border border-neutral-300 bg-white px-3 py-2 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 dark:border-neutral-700 dark:bg-neutral-900';

    useEffect(() => {
        const previousOverflow = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
        bodyRef.current?.focus();
        const closeOnEscape = event => event.key === 'Escape' && onClose();
        document.addEventListener('keydown', closeOnEscape);
        return () => {
            document.body.style.overflow = previousOverflow;
            document.removeEventListener('keydown', closeOnEscape);
        };
    }, [onClose]);

    const update = (key, value) => setForm(current => ({ ...current, [key]: value }));
    const submit = async event => {
        event.preventDefault();
        if (saving) return;
        setSaving(true);
        setErrors({});
        try {
            const payload = new FormData();
            if (!editing) payload.append('conversation_uuid', conversation.uuid);
            if (editing) payload.append('_method', 'PATCH');
            Object.entries(form).forEach(([key, value]) => {
                if (value !== null && value !== undefined) payload.append(key, value);
            });
            const endpoint = editing ? route('client.inbox.scheduled-messages.update', schedule.uuid) : route('client.inbox.scheduled-messages.store');
            const response = await axios.post(endpoint, payload, { headers: { Accept: 'application/json' } });
            toast.success(response.data?.message || (editing ? 'Mensagem atualizada.' : 'Mensagem agendada.'));
            onCreated?.();
            onClose();
        } catch (error) {
            const validation = error?.response?.data?.errors ?? {};
            setErrors(validation);
            toast.error(Object.values(validation).flat()[0] || error?.response?.data?.message || `Não foi possível ${editing ? 'atualizar' : 'agendar'} a mensagem.`);
        } finally {
            setSaving(false);
        }
    };

    return (
        <div className="fixed inset-0 z-[140] flex items-end justify-center bg-neutral-950/60 p-0 sm:items-center sm:p-4" onMouseDown={event => event.target === event.currentTarget && onClose()}>
            <section role="dialog" aria-modal="true" aria-labelledby="schedule-message-title" className="flex max-h-[94vh] w-full max-w-2xl flex-col overflow-hidden rounded-t-2xl bg-white shadow-2xl dark:bg-neutral-900 sm:rounded-2xl">
                <header className="flex items-start justify-between gap-4 border-b border-neutral-200 p-5 dark:border-neutral-700">
                    <div>
                        <h2 id="schedule-message-title" className="flex items-center gap-2 text-lg font-semibold">{editing ? <Pencil className="h-5 w-5 text-brand-600" /> : <CalendarPlus className="h-5 w-5 text-brand-600" />}{editing ? 'Editar mensagem agendada' : 'Agendar mensagem'}</h2>
                        <p className="mt-1 text-xs text-neutral-500">{editing ? 'Altere o conteúdo, a imagem, o horário ou a repetição.' : 'Será enviada para este cliente sem sair da conversa.'}</p>
                    </div>
                    <button type="button" onClick={onClose} aria-label="Fechar agendamento" className="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl text-neutral-500 transition hover:bg-neutral-100 focus:outline-none focus:ring-2 focus:ring-brand-500 dark:hover:bg-neutral-800"><X className="h-5 w-5" /></button>
                </header>

                <form onSubmit={submit} className="min-h-0 overflow-y-auto p-5">
                    <label className="block text-sm font-medium">Mensagem
                        <textarea ref={bodyRef} maxLength={4096} rows={5} value={form.body} onChange={event => update('body', event.target.value)} className={`${inputClass} resize-y`} placeholder="Digite a mensagem…" />
                        <span className="mt-1 flex justify-between text-xs"><span className="text-red-600">{errors.body?.[0]}</span><span className="text-neutral-400">{form.body.length}/4096</span></span>
                    </label>
                    <ScheduledImageField image={form.image} onChange={(image) => setForm(current => ({ ...current, image, remove_image: false }))} error={errors.image} existingUrl={schedule?.media_url} existingName={schedule?.media_name} removeExisting={form.remove_image} onRemoveExisting={() => setForm(current => ({ ...current, image: null, remove_image: true }))} />

                    <ScheduledTimingFields form={form} update={update} errors={errors} minimumSendAt={minimumSendAt} />

                    <footer className="mt-5 flex flex-col-reverse gap-2 border-t border-neutral-200 pt-4 dark:border-neutral-700 sm:flex-row sm:justify-end">
                        <button type="button" onClick={onClose} className="min-h-11 rounded-xl border border-neutral-300 px-4 text-sm font-medium transition hover:bg-neutral-50 dark:border-neutral-700 dark:hover:bg-neutral-800">Cancelar</button>
                        <button disabled={saving || (!form.body.trim() && !form.image && (!schedule?.media_url || form.remove_image))} className="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-brand-600 px-5 text-sm font-semibold text-white transition hover:bg-brand-700 disabled:cursor-not-allowed disabled:opacity-50">{saving ? <Loader2 className="h-4 w-4 animate-spin" /> : editing ? <Pencil className="h-4 w-4" /> : <CalendarPlus className="h-4 w-4" />}{saving ? 'Salvando…' : editing ? 'Salvar alterações' : 'Agendar mensagem'}</button>
                    </footer>
                </form>
            </section>
        </div>
    );
}

export default function InboxShow({
    conversation,
    messages: initialMessages,
    allLabels = [],
    conversations: initialConversations,
    filters = {},
    teamMembers = [],
    whatsappTemplates = [],
    channelAccounts = [],
    latestMessageId = 0,
    hasEcommerceStore = false,
    kanbanPipelines = [],
    kanbanAssignment = null,
    appointmentServices = [],
    appointmentResources = [],
    contactAppointments = [],
    scheduledMessages = [],
    taskBoards = [],
    availableContactTags = [],
    availableStaticSegments = [],
    aiCopilotAgents = [],
    copilotHistory = [],
    fazos = { connected: false, links: [] },
}) {
    const { t } = useTranslation();
    const { props } = usePage();
    const flash = props.flash ?? {};
    const authUser = props.auth?.user;
    const userTz = props.timezone || 'America/Sao_Paulo';
    // Prefer the active workspace (user can switch between accessible workspaces);
    // fall back to the user's primary workspace if not present.
    const workspaceId = props.currentWorkspace?.id ?? authUser?.workspace_id;
    const channelAccountKey = channelAccounts.map(account => Number(account.id)).filter(Boolean).sort((a, b) => a - b).join(',');
    const channel = conversation.channel_account?.channel ?? 'whatsapp';
    const isWindowOpen = conversation.is_whatsapp_window_open ?? (channel !== 'whatsapp');
    const isWhatsApp = channel === 'whatsapp';
    const firstKanbanPipelineUuid = kanbanAssignment?.pipeline?.uuid ?? kanbanPipelines[0]?.uuid ?? '';
    const firstKanbanPipeline = kanbanPipelines.find((item) => item.uuid === firstKanbanPipelineUuid);

    const [messages, setMessages]           = useState(initialMessages ?? []);
    const [viewers, setViewers]             = useState([]);
    const [typingUsers, setTypingUsers]     = useState([]);
    const [activeTab, setActiveTab]         = useState('messages');
    const [notes, setNotes]                 = useState([]);
    const [noteBody, setNoteBody]           = useState('');
    const [notePosting, setNotePosting]     = useState(false);
    const [cannedReplies, setCannedReplies] = useState([]);
    const [slashMenu, setSlashMenu]         = useState([]);
    const [convLabels, setConvLabels]       = useState(conversation.labels ?? []);
    const [assignedTo, setAssignedTo]       = useState(conversation.assigned_to ?? 'bot');
    const [assignedUserId, setAssignedUserId] = useState(conversation.assigned_user_id ?? null);
    const [conversations, setConversations] = useState(initialConversations);
    const [listSearch, setListSearch]       = useState('');
    const [listLoading, setListLoading]     = useState(false);
    const [showNewModal, setShowNewModal]   = useState(false);
    const [showAppointmentModal, setShowAppointmentModal] = useState(false);
    const [showKanbanModal, setShowKanbanModal] = useState(false);
    const [showScheduleModal, setShowScheduleModal] = useState(false);
    const [editingSchedule, setEditingSchedule] = useState(null);
    const [taskSourceMessage, setTaskSourceMessage] = useState(null);
    const [sending, setSending]             = useState(false);
    const [sendError, setSendError]         = useState(null);
    const [sendErrorCode, setSendErrorCode] = useState(null);
    const [kanbanCurrent, setKanbanCurrent] = useState(kanbanAssignment);
    const [kanbanPipelineUuid, setKanbanPipelineUuid] = useState(firstKanbanPipelineUuid);
    const [kanbanStageUuid, setKanbanStageUuid] = useState(
        kanbanAssignment?.stage?.uuid ?? firstKanbanPipeline?.stages?.[0]?.uuid ?? '',
    );
    const [savingKanban, setSavingKanban] = useState(false);
    const [contactPermissions, setContactPermissions] = useState({
        opt_in_whatsapp: Boolean(conversation.contact?.opt_in_whatsapp),
        opt_in_sms: Boolean(conversation.contact?.opt_in_sms),
        opt_in_email: Boolean(conversation.contact?.opt_in_email),
    });
    const [savingPermission, setSavingPermission] = useState(null);
    const [contactTagOptions, setContactTagOptions] = useState(availableContactTags);
    const [selectedContactTagIds, setSelectedContactTagIds] = useState(
        (conversation.contact?.tags ?? []).map((item) => Number(item.id)),
    );
    const [selectedStaticSegmentIds, setSelectedStaticSegmentIds] = useState(
        (conversation.contact?.segments ?? []).filter((item) => item.type === 'static').map((item) => Number(item.id)),
    );
    const [savingSegmentation, setSavingSegmentation] = useState(false);
    const [newContactTagName, setNewContactTagName] = useState('');
    const [creatingContactTag, setCreatingContactTag] = useState(false);
    const [rightPanelTab, setRightPanelTab] = useState('profile');
    const [copilotAgentUuid] = useState(aiCopilotAgents[0]?.uuid ?? '');
    const [copilotInput, setCopilotInput] = useState('');
    const [copilotMessages, setCopilotMessages] = useState(copilotHistory ?? []);
    const [copilotLoading, setCopilotLoading] = useState(false);
    const latestMessageIdRef = useRef(Math.max(
        0,
        Number(latestMessageId) || 0,
        ...(initialMessages ?? []).map(message => Number(message.id) || 0),
        ...(initialConversations?.data ?? []).map(item => Number(item.last_message?.id) || 0),
    ));
    const notifiedMessageIdsRef = useRef(new Set([
        ...(initialMessages ?? []).map(message => Number(message.id) || 0),
        ...(initialConversations?.data ?? []).map(item => Number(item.last_message?.id) || 0),
    ]));

    // When Inertia navigates between conversations the page component is
    // re-used, so seed local state from the new server props on conversation
    // change. The websocket listeners dedupe by `id` so any freshly broadcast
    // message that already lives in local state is not duplicated.
    useEffect(() => {
        setMessages(initialMessages ?? []);
        setConvLabels(conversation.labels ?? []);
        setAssignedTo(conversation.assigned_to ?? 'bot');
        setAssignedUserId(conversation.assigned_user_id ?? null);
        setSendError(null);
        setSendErrorCode(null);
        setShowKanbanModal(false);
        setContactPermissions({
            opt_in_whatsapp: Boolean(conversation.contact?.opt_in_whatsapp),
            opt_in_sms: Boolean(conversation.contact?.opt_in_sms),
            opt_in_email: Boolean(conversation.contact?.opt_in_email),
        });
        setSelectedContactTagIds((conversation.contact?.tags ?? []).map((item) => Number(item.id)));
        setSelectedStaticSegmentIds(
            (conversation.contact?.segments ?? []).filter((item) => item.type === 'static').map((item) => Number(item.id)),
        );
        setNewContactTagName('');
        setCopilotMessages(copilotHistory ?? []);
        setCopilotInput('');
    }, [conversation.id, copilotHistory]);

    useEffect(() => {
        setContactTagOptions(availableContactTags);
    }, [availableContactTags]);

    useEffect(() => {
        setConversations(initialConversations);
        setListLoading(false);
    }, [initialConversations]);

    // Toolbar state
    const [showEmoji, setShowEmoji]           = useState(false);
    const [showTemplates, setShowTemplates]   = useState(false);
    const [showProducts, setShowProducts]     = useState(false);
    const [showAgentDrop, setShowAgentDrop]   = useState(false);
    const [attachPreview, setAttachPreview]   = useState(null); // { file, url, type }
    const fileRef = useRef(null);
    const bottomRef = useRef(null);
    const messageScrollRef = useRef(null);

    const { data, setData, reset } = useForm({ body: '', type: 'text', payload: null });

    const askCopilot = async (prompt = copilotInput) => {
        const question = String(prompt || '').trim();
        if (!question || !copilotAgentUuid || copilotLoading) return;
        setCopilotMessages((current) => [...current, { role: 'user', content: question }]);
        setCopilotInput('');
        setCopilotLoading(true);
        try {
            const { data: response } = await axios.post(route('client.ai.agents.run', copilotAgentUuid), {
                message: question,
                conversation_uuid: conversation.uuid,
                copilot: true,
            });
            setCopilotMessages((current) => [...current, {
                role: 'assistant',
                content: response.reply,
                delegatedAgent: response.delegated_agent || null,
            }]);
        } catch (error) {
            setCopilotMessages((current) => [...current, { role: 'error', content: error?.response?.data?.error || 'Não foi possível consultar o copiloto.' }]);
        } finally {
            setCopilotLoading(false);
        }
    };

    const scrollToBottom = useCallback(() => {
        bottomRef.current?.scrollIntoView({ behavior: 'smooth' });
    }, []);

    const notifyInbound = useCallback((event) => {
        if (event.direction !== 'in') return;

        const id = Number(event.id) || 0;
        if (id && notifiedMessageIdsRef.current.has(id)) return;
        if (id) notifiedMessageIdsRef.current.add(id);

        playInboundSound(event.channel);

        const contact = event.contact_name || CHANNEL_LABELS[event.channel] || t('inbox.contact');
        const preview = event.body || t('inbox.new_message_without_preview');
        const conversationUrl = event.conversation_uuid ? route('client.inbox.show', {
            conversation: event.conversation_uuid,
            ...filters,
            account_id: event.channel_account_id ?? filters.account_id,
        }) : null;
        toast(t('inbox.new_message_alert'), {
            description: `${contact}: ${preview}`,
            action: event.conversation_uuid
                ? {
                    label: t('inbox.open_conversation'),
                    onClick: () => router.visit(conversationUrl),
                }
                : undefined,
        });

    }, [filters, t]);

    useEffect(() => {
        axios.get(route('client.inbox.canned-replies.list'))
            .then(r => setCannedReplies(r.data ?? []))
            .catch(() => {});
    }, []);

    useLayoutEffect(() => {
        if (activeTab !== 'messages') return;
        const container = messageScrollRef.current;
        if (!container) return;
        container.scrollTop = container.scrollHeight;
        const frame = requestAnimationFrame(() => {
            container.scrollTop = container.scrollHeight;
        });
        return () => window.cancelAnimationFrame(frame);
    }, [conversation.id, activeTab, messages.length]);

    // WS: conversation events
    useEffect(() => {
        if (!window.Echo) {
            console.warn('[inbox] Echo not initialised — real-time updates disabled. Check Pusher config.');
            return;
        }
        const ch = window.Echo.private(`conversation.${conversation.id}`);
        ch
            .listen('.MessageReceived', (e) => {
                latestMessageIdRef.current = Math.max(latestMessageIdRef.current, Number(e.id) || 0);
                setMessages(prev => prev.some(m => m.id === e.id) ? prev : [...prev, e]);
            })
            .listen('.MessageSent', (e) => {
                latestMessageIdRef.current = Math.max(latestMessageIdRef.current, Number(e.id) || 0);
                setMessages(prev => {
                    if (prev.some(m => m.id === e.id)) {
                        // Existing optimistic message — refresh status / payload from server.
                        return prev.map(m => m.id === e.id ? { ...m, ...e } : m);
                    }
                    return [...prev, e];
                });
            })
            .listen('.MessageStatusUpdated', (e) => {
                setMessages(prev => prev.map(m =>
                    m.id === e.id ? { ...m, status: e.status } : m
                ));
            })
            .listen('.ConversationAssigned', () => {
                router.reload({ only: ['conversation'] });
            })
            .listen('.TypingChanged', (e) => {
                if (e.user_id === authUser?.id) return;
                if (e.is_typing) {
                    setTypingUsers(prev => prev.some(u => u.user_id === e.user_id) ? prev : [...prev, e]);
                    setTimeout(() => setTypingUsers(prev => prev.filter(u => u.user_id !== e.user_id)), 3000);
                } else {
                    setTypingUsers(prev => prev.filter(u => u.user_id !== e.user_id));
                }
            });
        window.Echo.join(`presence-conversation.${conversation.id}`)
            .here(u => setViewers(u))
            .joining(u => setViewers(prev => [...prev.filter(v => v.id !== u.id), u]))
            .leaving(u => setViewers(prev => prev.filter(v => v.id !== u.id)));
        return () => {
            window.Echo.leave(`conversation.${conversation.id}`);
            window.Echo.leave(`presence-conversation.${conversation.id}`);
        };
    }, [conversation.id]);

    // WS: list updates
    useEffect(() => {
        if (!window.Echo || !workspaceId || !channelAccountKey) return;
        const accountIds = channelAccountKey.split(',');
        const handleMessage = (e) => {
                latestMessageIdRef.current = Math.max(latestMessageIdRef.current, Number(e.id) || 0);
                notifyInbound(e);
                setConversations(prev => {
                    if (!prev) return prev;
                    const exists = prev.data?.find(c => c.id === e.conversation_id);
                    if (!exists) return prev;
                    return { ...prev, data: [
                        { ...exists, unread_count: (exists.unread_count ?? 0) + 1, last_message_at: e.created_at, last_message: { body: e.body } },
                        ...prev.data.filter(c => c.id !== e.conversation_id),
                    ]};
                });
            };
        accountIds.forEach(id => window.Echo.private(`channel-account.${id}`).listen('.MessageReceived', handleMessage));
        return () => accountIds.forEach(id => window.Echo.leave(`channel-account.${id}`));
    }, [workspaceId, channelAccountKey, notifyInbound]);

    // Shared-hosting realtime fallback. The production server may not have an
    // external Pusher account or a long-running Reverb process. Polling this
    // lightweight JSON endpoint keeps all channels live without reloading the
    // page, while websocket events (when available) are still applied instantly.
    useEffect(() => {
        let stopped = false;
        let timer = null;
        let requestRunning = false;

        const poll = async () => {
            if (stopped || requestRunning) return;
            requestRunning = true;

            try {
                const response = await axios.get(
                    route('client.inbox.updates', conversation.uuid),
                    {
                        params: {
                            after_id: latestMessageIdRef.current,
                            folder: filters.folder || undefined,
                            channel: filters.channel || undefined,
                            account_id: filters.account_id || undefined,
                            label: filters.label || undefined,
                        },
                        headers: { Accept: 'application/json' },
                    },
                );

                const events = response.data?.messages ?? [];
                for (const event of events) {
                    latestMessageIdRef.current = Math.max(
                        latestMessageIdRef.current,
                        Number(event.id) || 0,
                    );

                    if (Number(event.conversation_id) === Number(conversation.id)) {
                        setMessages(prev => {
                            if (prev.some(message => Number(message.id) === Number(event.id))) {
                                return prev.map(message => (
                                    Number(message.id) === Number(event.id)
                                        ? { ...message, ...event }
                                        : message
                                ));
                            }
                            return [...prev, event];
                        });
                    }

                    notifyInbound(event);
                }

                latestMessageIdRef.current = Math.max(
                    latestMessageIdRef.current,
                    Number(response.data?.latest_id) || 0,
                );

                if (Array.isArray(response.data?.conversations)) {
                    setConversations(prev => ({
                        ...(prev ?? {}),
                        data: response.data.conversations,
                    }));
                }
            } catch {
                // A transient request failure must not stop later sync attempts.
            } finally {
                requestRunning = false;
                if (!stopped) timer = window.setTimeout(poll, 3000);
            }
        };

        timer = window.setTimeout(poll, 1000);

        return () => {
            stopped = true;
            if (timer) window.clearTimeout(timer);
        };
    }, [
        conversation.id,
        conversation.uuid,
        filters.folder,
        filters.channel,
        filters.account_id,
        filters.label,
        notifyInbound,
    ]);

    const typingTimer = useRef(null);
    const handleTyping = () => {
        // Use server-side broadcast instead of Pusher client events (whispers),
        // which require "Client Events" to be enabled in the Pusher dashboard.
        clearTimeout(typingTimer.current);
        axios.post(route('client.inbox.typing', conversation.uuid), { is_typing: true }).catch(() => {});
        typingTimer.current = setTimeout(() => {
            axios.post(route('client.inbox.typing', conversation.uuid), { is_typing: false }).catch(() => {});
        }, 3000);
    };

    const loadNotes = useCallback(() => {
        axios.get(route('client.inbox.notes.index', conversation.uuid))
            .then(r => setNotes(r.data ?? []))
            .catch(() => {});
    }, [conversation.id]);

    useEffect(() => { if (activeTab === 'notes') loadNotes(); }, [activeTab]);

    const [orders, setOrders] = useState([]);
    const [ordersLoaded, setOrdersLoaded] = useState(false);
    const loadOrders = useCallback(() => {
        if (!conversation.contact?.uuid) return;
        axios.get(route('client.ecommerce.contacts.orders', conversation.contact.uuid))
            .then(r => setOrders(r.data ?? []))
            .catch(() => {})
            .finally(() => setOrdersLoaded(true));
    }, [conversation.id]);

    useEffect(() => { if (activeTab === 'orders') loadOrders(); }, [activeTab]);

    const postNote = (e) => {
        e.preventDefault();
        if (!noteBody.trim() || notePosting) return;
        setNotePosting(true);
        axios.post(route('client.inbox.notes.store', conversation.uuid), { body: noteBody })
            .then(r => { setNotes(prev => [r.data, ...prev]); setNoteBody(''); })
            .finally(() => setNotePosting(false));
    };

    const handleReplyChange = (value) => {
        setData('body', value);
        handleTyping();
        const match = value.match(/^\/(\w*)$/);
        if (match) {
            const q = match[1].toLowerCase();
            setSlashMenu(cannedReplies.filter(r => r.shortcut.toLowerCase().startsWith(q)));
        } else {
            setSlashMenu([]);
        }
    };

    const toggleLabel = (label) => {
        const attached = convLabels.some(l => l.id === label.id);
        if (attached) {
            axios.delete(route('client.inbox.labels.detach', { conversation: conversation.uuid, label: label.id }))
                .then(() => setConvLabels(prev => prev.filter(l => l.id !== label.id)))
                .catch(() => {});
        } else {
            axios.post(route('client.inbox.labels.attach', conversation.uuid), { label_id: label.id })
                .then(r => setConvLabels(prev => [...prev, r.data.label]))
                .catch(() => {});
        }
    };

    const appendMessage = (msg) => {
        if (!msg) return;
        setMessages(prev => prev.some(m => m.id === msg.id) ? prev : [...prev, msg]);
    };

    const handleSend = (e) => {
        e?.preventDefault?.();
        if (sending) return;
        if (!data.body.trim() && !attachPreview) return;

        setSending(true);
        setSendError(null);
        setSendErrorCode(null);
        axios.post(route('client.inbox.typing', conversation.uuid), { is_typing: false }).catch(() => {});

        const onDone = (res) => {
            const msg = res?.data?.message;
            const errText = res?.data?.error;
            const errCode = res?.data?.error_code;
            appendMessage(msg);
            reset();
            if (attachPreview?.url) URL.revokeObjectURL(attachPreview.url);
            setAttachPreview(null);
            if (errText) {
                setSendError(errText);
                setSendErrorCode(errCode || null);
            }
        };

        const onErr = (err) => {
            const errText = err?.response?.data?.error
                ?? err?.response?.data?.message
                ?? err?.message
                ?? t('inbox.failed_send_message');
            setSendError(errText);
            setSendErrorCode(err?.response?.data?.error_code || null);
        };

        const config = { headers: { Accept: 'application/json' } };

        if (attachPreview) {
            const fd = new FormData();
            fd.append('body', data.body || attachPreview.file.name);
            fd.append('type', attachPreview.type === 'image' ? 'image' : 'document');
            fd.append('attachment', attachPreview.file);
            axios.post(route('client.inbox.reply', conversation.uuid), fd, {
                headers: { 'Content-Type': 'multipart/form-data', Accept: 'application/json' },
            })
                .then(onDone)
                .catch(onErr)
                .finally(() => setSending(false));
        } else {
            axios.post(route('client.inbox.reply', conversation.uuid), {
                body: data.body,
                type: 'text',
                payload: null,
            }, config)
                .then(onDone)
                .catch(onErr)
                .finally(() => setSending(false));
        }
    };

    const prepareAttachment = (file) => {
        if (!file) return;
        const isImage = file.type.startsWith('image/');
        const url = URL.createObjectURL(file);
        setAttachPreview((current) => {
            if (current?.url) URL.revokeObjectURL(current.url);
            return { file, url, type: isImage ? 'image' : 'document' };
        });
    };

    const handleFileChange = (e) => {
        prepareAttachment(e.target.files?.[0]);
        e.target.value = '';
    };

    const handleComposerPaste = (event) => {
        const image = [...(event.clipboardData?.items ?? [])]
            .find((item) => item.kind === 'file' && item.type.startsWith('image/'))
            ?.getAsFile();
        if (!image) return;
        event.preventDefault();
        const extension = image.type.split('/')[1]?.replace('jpeg', 'jpg') || 'png';
        const namedImage = image.name && image.name !== 'image.png'
            ? image
            : new window.File([image], `imagem-colada-${Date.now()}.${extension}`, { type: image.type });
        prepareAttachment(namedImage);
        toast.success('Imagem colada. Revise e envie quando estiver pronto.');
    };

    const switchHandover = (mode) => {
        axios.post(route('client.inbox.handover', conversation.uuid), { mode })
            .then(() => setAssignedTo(mode))
            .catch(() => {});
    };

    const handleStatus = (status) => router.post(route('client.inbox.status', conversation.uuid), { status }, { preserveScroll: true });

    const navigateList = (params) => {
        setListLoading(true);
        router.get(route('client.inbox.show', conversation.uuid), { ...filters, ...params }, { preserveState: true, replace: true });
    };

    const changeKanbanPipeline = (pipelineUuid) => {
        const selected = kanbanPipelines.find((item) => item.uuid === pipelineUuid);
        setKanbanPipelineUuid(pipelineUuid);
        setKanbanStageUuid(selected?.stages?.[0]?.uuid ?? '');
    };
    const saveKanbanAssignment = () => {
        if (!kanbanPipelineUuid || !kanbanStageUuid || savingKanban) return;
        setSavingKanban(true);
        axios.post(route('client.inbox.kanban.update', conversation.uuid), {
            pipeline_uuid: kanbanPipelineUuid,
            stage_uuid: kanbanStageUuid,
        }, { headers: { Accept: 'application/json' } })
            .then(({ data }) => {
                setKanbanCurrent(data.assignment);
                setShowKanbanModal(false);
                toast.success(data.message || 'Contato atualizado no Kanban.');
            })
            .catch((error) => {
                const errors = error?.response?.data?.errors;
                toast.error(errors ? Object.values(errors).flat()[0] : (error?.response?.data?.message || 'Não foi possível atualizar o Kanban.'));
            })
            .finally(() => setSavingKanban(false));
    };

    const toggleSelection = (value, setter) => {
        const id = Number(value);
        setter((current) => current.includes(id) ? current.filter((item) => item !== id) : [...current, id]);
    };

    const saveContactSegmentation = () => {
        if (savingSegmentation) return;
        setSavingSegmentation(true);
        axios.patch(route('client.inbox.contact-segmentation.update', conversation.uuid), {
            tag_ids: selectedContactTagIds,
            segment_ids: selectedStaticSegmentIds,
        }, { headers: { Accept: 'application/json' } })
            .then(({ data }) => {
                setSelectedContactTagIds((data.tag_ids ?? []).map(Number));
                setSelectedStaticSegmentIds((data.segment_ids ?? []).map(Number));
                toast.success(data.message || 'Segmentos e etiquetas atualizados.');
            })
            .catch((error) => {
                const errors = error?.response?.data?.errors;
                toast.error(errors ? Object.values(errors).flat()[0] : (error?.response?.data?.message || 'Não foi possível atualizar a segmentação.'));
            })
            .finally(() => setSavingSegmentation(false));
    };

    const createContactTag = () => {
        const name = newContactTagName.trim();
        if (!name || creatingContactTag) return;
        setCreatingContactTag(true);
        axios.post(route('client.inbox.contact-tags.store'), { name }, { headers: { Accept: 'application/json' } })
            .then(({ data }) => {
                const tag = data.tag;
                setContactTagOptions((current) => [...current, tag].sort((a, b) => a.name.localeCompare(b.name, 'pt-BR')));
                setSelectedContactTagIds((current) => [...new Set([...current, Number(tag.id)])]);
                setNewContactTagName('');
                toast.success('Etiqueta criada e selecionada. Clique em salvar para aplicá-la ao contato.');
            })
            .catch((error) => {
                const errors = error?.response?.data?.errors;
                toast.error(errors ? Object.values(errors).flat()[0] : (error?.response?.data?.message || 'Não foi possível criar a etiqueta.'));
            })
            .finally(() => setCreatingContactTag(false));
    };

    const otherViewers = viewers.filter(v => v.id !== authUser?.id);
    const filteredList = listSearch.trim() && conversations?.data
        ? conversations.data.filter(c => {
            const n = `${c.contact?.first_name ?? ''} ${c.contact?.last_name ?? ''} ${c.contact?.phone_e164 ?? ''}`.toLowerCase();
            return n.includes(listSearch.toLowerCase());
        })
        : (conversations?.data ?? []);

    const contactName = conversation.contact?.first_name || conversation.contact?.last_name
        ? `${conversation.contact.first_name ?? ''} ${conversation.contact.last_name ?? ''}`.trim()
        : conversation.contact?.phone_e164 ?? t('inbox.unknown_contact');
    const assignedAgent = teamMembers.find(m => m.id === assignedUserId);

    return (
        <InboxLayout>
            <Head title={t('inbox.show_title', { name: contactName })} />
            {showNewModal && <NewConversationModal onClose={() => setShowNewModal(false)} />}
            {showAppointmentModal && <ConversationAppointmentModal contact={conversation.contact} services={appointmentServices} resources={appointmentResources} onClose={() => setShowAppointmentModal(false)} />}
            {showKanbanModal && (
                <ConversationKanbanModal
                    current={kanbanCurrent}
                    pipelines={kanbanPipelines}
                    pipelineUuid={kanbanPipelineUuid}
                    stageUuid={kanbanStageUuid}
                    onPipelineChange={changeKanbanPipeline}
                    onStageChange={setKanbanStageUuid}
                    onSave={saveKanbanAssignment}
                    saving={savingKanban}
                    onClose={() => setShowKanbanModal(false)}
                />
            )}
            {taskSourceMessage && (
                <MessageTaskModal
                    message={taskSourceMessage}
                    conversation={conversation}
                    taskBoards={taskBoards}
                    teamMembers={teamMembers}
                    onClose={() => setTaskSourceMessage(null)}
                />
            )}
            <div className="flex min-h-0 min-w-0 flex-1 overflow-hidden">

                {/* ── Filter sidebar ── */}
                <aside className="hidden w-48 shrink-0 flex-col overflow-hidden border-r border-neutral-200 bg-white dark:border-neutral-700 dark:bg-neutral-900 lg:flex">
                    <div className="px-3 py-3 border-b border-neutral-100 dark:border-neutral-800 flex items-center justify-between gap-1">
                        <Link href={route('client.inbox.index')} className="text-sm font-bold text-neutral-800 dark:text-neutral-200 flex items-center gap-2 hover:text-brand-600 transition">
                            <Inbox className="h-4 w-4 text-brand-600" />{t('inbox.title')}
                        </Link>
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
                        labels={allLabels}
                        channelAccounts={channelAccounts}
                        onFolder={(k) => navigateList({ folder: k, channel: undefined, label: undefined, account_id: undefined })}
                        onChannel={(ch) => navigateList({ channel: filters.channel === ch ? undefined : ch, account_id: undefined })}
                        onAccount={(id) => navigateList({ account_id: String(filters.account_id) === String(id) ? undefined : id, channel: undefined })}
                        onLabel={(id) => navigateList({ label: String(filters.label) === String(id) ? undefined : id })}
                    />
                </aside>

                {/* ── Conversation list ── */}
                <div className="hidden w-72 shrink-0 flex-col border-r border-neutral-200 bg-white dark:border-neutral-700 dark:bg-neutral-900 md:flex lg:w-72">
                    <div className="px-3 py-2.5 border-b border-neutral-100 dark:border-neutral-800 space-y-2">
                        <div className="flex items-center justify-between">
                            <span className="text-sm font-semibold text-neutral-800 dark:text-neutral-200 flex items-center gap-1 flex-wrap">
                                {t(FOLDERS.find(f => (f.key ?? null) === (filters.folder ?? null))?.labelKey ?? 'inbox.folder_all')}
                                {filters.channel && <span className="text-xs font-normal text-neutral-400">· {CHANNEL_LABELS[filters.channel] ?? filters.channel}</span>}
                                {filters.account_id && (() => {
                                    const acct = channelAccounts.find(a => String(a.id) === String(filters.account_id));
                                    return acct ? <span className="text-xs font-normal text-neutral-400">· {acct.display_name || acct.phone_number_id}</span> : null;
                                })()}
                            </span>
                            <div className="flex items-center gap-1">
                                <span className="text-xs text-neutral-400 tabular-nums">{conversations?.total ?? 0}</span>
                                <SoundPrefsMenu />
                                <button onClick={() => { setListLoading(true); router.reload(); }} className="p-1 rounded hover:bg-neutral-100 dark:hover:bg-neutral-800 text-neutral-400 transition">
                                    <RefreshCw className={`h-3.5 w-3.5 ${listLoading ? 'animate-spin' : ''}`} />
                                </button>
                            </div>
                        </div>
                        <div className="relative">
                            <Search className="absolute left-2.5 top-1/2 -translate-y-1/2 h-3.5 w-3.5 text-neutral-400 pointer-events-none" />
                            <input value={listSearch} onChange={e => setListSearch(e.target.value)}
                                placeholder={t('inbox.search_conversations')}
                                className="w-full pl-8 pr-3 py-1.5 text-xs rounded-lg bg-neutral-100 dark:bg-neutral-800 border-0 focus:outline-none focus:ring-2 focus:ring-brand-500 placeholder-neutral-400"
                            />
                        </div>
                    </div>
                    <div className="flex-1 overflow-y-auto">
                        {filteredList.length === 0 ? (
                            <div className="py-10 px-4">
                                <EmptyState icon={<Inbox className="h-7 w-7" />} title={t('inbox.no_conversations')} description={t('inbox.no_conversations_match')} />
                            </div>
                        ) : filteredList.map(conv => (
                            <ConversationCard key={conv.id} conv={conv} isActive={conv.uuid === conversation.uuid} userTz={userTz} filters={filters} />
                        ))}
                    </div>
                </div>

                {/* ── Main thread ── */}
                <div className="flex min-w-0 max-w-full flex-1 flex-col overflow-hidden bg-neutral-50 dark:bg-neutral-950">

                    {/* Header */}
                    <div className="flex min-w-0 shrink-0 items-center gap-2 border-b border-neutral-200 bg-white px-2 py-2.5 dark:border-neutral-700 dark:bg-neutral-900 sm:gap-3 sm:px-4">
                        <Link href={route('client.inbox.index', filters)} className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl text-neutral-600 hover:bg-neutral-100 dark:text-neutral-300 dark:hover:bg-neutral-800 md:hidden" aria-label="Voltar às conversas">
                            <ArrowLeft className="h-5 w-5" />
                        </Link>
                        <div className="relative shrink-0">
                            <div className="h-9 w-9 rounded-full bg-brand-100 dark:bg-brand-900/30 flex items-center justify-center text-sm font-semibold text-brand-700 dark:text-brand-300">
                                {contactName[0]?.toUpperCase() ?? '?'}
                            </div>
                            <span className="absolute -bottom-0.5 -right-0.5 h-4 w-4 rounded-full bg-white dark:bg-neutral-900 flex items-center justify-center">
                                <ChannelBrandIcon channel={channel} className="h-3 w-3" />
                            </span>
                        </div>
                        <div className="flex-1 min-w-0">
                            <p className="font-semibold text-sm text-neutral-900 dark:text-neutral-100 truncate">{contactName}</p>
                            <p className="text-xs text-neutral-400 flex items-center gap-1.5">
                                <ChannelBrandIcon channel={channel} className="h-3 w-3 shrink-0" />
                                <span>{CHANNEL_LABELS[channel] ?? channel}</span>
                                {conversation.channel_account?.name && <><span className="text-neutral-300 dark:text-neutral-600">·</span><span>{conversation.channel_account.name}</span></>}
                            </p>
                        </div>

                        {/* Presence */}
                        {otherViewers.length > 0 && (
                            <div className="flex items-center gap-1 text-xs text-neutral-400 hidden xl:flex">
                                <Eye className="h-3.5 w-3.5" />
                                <span>{t('inbox.viewing', { names: otherViewers.map(v => v.name).join(', ') })}</span>
                            </div>
                        )}

                        {/* Agent assign */}
                        <div className="relative hidden sm:block">
                            <button
                                type="button"
                                onClick={() => setShowAgentDrop(v => !v)}
                                className="flex items-center gap-1.5 rounded-lg border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-800 px-2.5 py-1.5 text-xs text-neutral-600 dark:text-neutral-400 hover:bg-neutral-50 dark:hover:bg-neutral-700 transition"
                            >
                                <UserCheck className="h-3.5 w-3.5 shrink-0" />
                                <span className="truncate max-w-[80px]">{assignedAgent?.name ?? t('inbox.assign')}</span>
                                <ChevronDown className="h-3 w-3 shrink-0" />
                            </button>
                            {showAgentDrop && (
                                <AgentDropdown
                                    teamMembers={teamMembers}
                                    currentUserId={authUser?.id}
                                    conversationId={conversation.uuid}
                                    onAssigned={(uid) => setAssignedUserId(uid)}
                                    onClose={() => setShowAgentDrop(false)}
                                />
                            )}
                        </div>

                        {/* Status */}
                        <select
                            defaultValue={conversation.status}
                            onChange={e => handleStatus(e.target.value)}
                            className={`max-w-[7rem] shrink-0 rounded-full border-0 px-2 py-1 text-xs font-medium cursor-pointer focus:outline-none focus:ring-2 focus:ring-brand-500 sm:max-w-none sm:px-3 ${STATUS_COLORS[conversation.status] ?? 'bg-neutral-100 text-neutral-600'}`}
                        >
                            {['open','pending','resolved','snoozed'].map(s => (
                                <option key={s} value={s} className="bg-white dark:bg-neutral-800 text-neutral-900">
                                    {t(`inbox.status_${s}`)}
                                </option>
                            ))}
                        </select>
                    </div>

                    {/* Tab bar */}
                    <div className="flex shrink-0 overflow-x-auto border-b border-neutral-200 bg-white dark:border-neutral-700 dark:bg-neutral-900">
                        {[
                            { key: 'messages', label: t('inbox.tab_messages'), icon: null },
                            { key: 'notes',    label: t('inbox.tab_notes'),    icon: <StickyNote className="inline h-3.5 w-3.5 mr-1 -mt-0.5" /> },
                            { key: 'appointments', label: t('inbox.tab_appointments'), icon: <Calendar className="inline h-3.5 w-3.5 mr-1 -mt-0.5" /> },
                            ...(hasEcommerceStore ? [{ key: 'orders', label: t('inbox.tab_orders'), icon: <ShoppingBag className="inline h-3.5 w-3.5 mr-1 -mt-0.5" /> }] : []),
                        ].map(tab => (
                            <button key={tab.key} onClick={() => setActiveTab(tab.key)}
                                className={`min-h-11 shrink-0 border-b-2 px-3 py-2.5 text-xs font-medium transition sm:px-5 sm:text-sm ${
                                    activeTab === tab.key
                                        ? 'border-brand-600 text-brand-700 dark:text-brand-300'
                                        : 'border-transparent text-neutral-500 hover:text-neutral-700 dark:hover:text-neutral-300'
                                }`}>
                                {tab.icon}{tab.label}{tab.key === 'appointments' && (contactAppointments.length + scheduledMessages.length) > 0 && <span className="ml-1.5 rounded-full bg-brand-100 px-1.5 py-0.5 text-[10px] font-bold text-brand-700 dark:bg-brand-900/50 dark:text-brand-200">{contactAppointments.length + scheduledMessages.length}</span>}
                            </button>
                        ))}
                    </div>

                    {/* Messages tab */}
                    {activeTab === 'messages' && (
                        <div ref={messageScrollRef} className="min-h-0 min-w-0 flex-1 overflow-y-auto overflow-x-hidden p-2 sm:p-4">
                            {groupMessagesForRender(messages).map(item => (
                                item.kind === 'album'
                                    ? <ImageGallery key={item.key} messages={item.messages} conversationId={conversation.uuid} provider={conversation.channel_account?.provider} groupPosition={item.groupPosition} onCreateTask={setTaskSourceMessage} />
                                    : <MessageBubble key={item.key} msg={item.msg} conversationId={conversation.uuid} provider={conversation.channel_account?.provider} groupPosition={item.groupPosition} onCreateTask={setTaskSourceMessage} />
                            ))}
                            {messages.length === 0 && (
                                <div className="py-8"><EmptyState icon={<MessageSquare className="h-8 w-8" />} title={t('inbox.no_messages_yet')} description={t('inbox.no_messages_desc')} /></div>
                            )}
                            <div ref={bottomRef} />
                        </div>
                    )}

                    {/* Notes tab */}
                    {activeTab === 'notes' && (
                        <div className="flex-1 overflow-y-auto p-4 space-y-3">
                            <form onSubmit={postNote} className="flex gap-2 mb-3">
                                <textarea value={noteBody} onChange={e => setNoteBody(e.target.value)}
                                    placeholder={t('inbox.note_placeholder')}
                                    rows={2}
                                    className="flex-1 rounded-xl border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 px-3 py-2 text-sm resize-none focus:outline-none focus:ring-2 focus:ring-brand-500"
                                />
                                <button type="submit" disabled={notePosting || !noteBody.trim()}
                                    className="self-end rounded-xl bg-amber-500 p-2.5 text-white hover:bg-amber-600 disabled:opacity-50 transition">
                                    <Send className="h-4 w-4" />
                                </button>
                            </form>
                            {notes.length === 0 ? (
                                <div className="py-6"><EmptyState icon={<StickyNote className="h-7 w-7" />} title={t('inbox.no_internal_notes')} description={t('inbox.no_internal_notes_desc')} /></div>
                            ) : notes.map(note => (
                                <div key={note.id} className="rounded-xl bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 p-3 text-sm">
                                    <div className="flex items-center gap-2 mb-1">
                                        <span className="font-semibold text-amber-800 dark:text-amber-300">{note.user?.name ?? t('inbox.you')}</span>
                                        <span className="text-xs text-neutral-400">{formatInTz(note.created_at, userTz)}</span>
                                    </div>
                                    <p className="text-neutral-700 dark:text-neutral-300 whitespace-pre-wrap">{note.body}</p>
                                </div>
                            ))}
                        </div>
                    )}

                    {/* Orders tab */}
                    {activeTab === 'orders' && (
                        <div className="flex-1 overflow-y-auto p-4 space-y-3">
                            {!ordersLoaded ? (
                                <p className="text-sm text-neutral-400 py-6 text-center">{t('inbox.loading_orders')}</p>
                            ) : orders.length === 0 ? (
                                <div className="py-6"><EmptyState icon={<ShoppingBag className="h-7 w-7" />} title={t('inbox.no_orders')} description={t('inbox.no_orders_desc')} /></div>
                            ) : orders.map((o, i) => (
                                <div key={i} className="rounded-xl border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-900 p-3 text-sm">
                                    <div className="flex items-center justify-between mb-1">
                                        <span className="font-semibold text-neutral-800 dark:text-neutral-200">{o.number || '—'}</span>
                                        <span className="font-semibold text-neutral-900 dark:text-neutral-100">{o.currency} {o.total}</span>
                                    </div>
                                    <div className="flex items-center gap-2 flex-wrap text-xs text-neutral-500">
                                        {o.fulfillment_status && <span className="px-2 py-0.5 rounded-full bg-neutral-100 dark:bg-neutral-800">{o.fulfillment_status}</span>}
                                        {o.financial_status && <span className="px-2 py-0.5 rounded-full bg-neutral-100 dark:bg-neutral-800">{o.financial_status}</span>}
                                        {o.placed_at && <span>{formatInTz(o.placed_at, userTz)}</span>}
                                    </div>
                                    {o.tracking_url && (
                                        <a href={o.tracking_url} target="_blank" rel="noopener noreferrer" className="mt-1.5 inline-block text-xs text-brand-600 hover:underline">{t('inbox.track_shipment')}</a>
                                    )}
                                </div>
                            ))}
                        </div>
                    )}

                    {activeTab === 'appointments' && (
                        <ConversationAppointmentsTab
                            appointments={contactAppointments}
                            scheduledMessages={scheduledMessages}
                            userTz={userTz}
                            onCreateAppointment={() => setShowAppointmentModal(true)}
                            onCreateMessage={() => setShowScheduleModal(true)}
                            onEditMessage={(schedule) => setEditingSchedule(schedule)}
                        />
                    )}

                    {/* Contact tab */}
                    {/* Typing indicator */}
                    {typingUsers.length > 0 && activeTab === 'messages' && (
                        <div className="px-4 py-1.5 text-xs text-neutral-400 italic bg-neutral-50 dark:bg-neutral-950 shrink-0">
                            {typingUsers.length === 1
                                ? t('inbox.typing_one', { names: typingUsers.map(u => u.user_name).join(', ') })
                                : t('inbox.typing_many', { names: typingUsers.map(u => u.user_name).join(', ') })}
                        </div>
                    )}

                    {/* 24h window warning */}
                    {isWhatsApp && !isWindowOpen && activeTab === 'messages' && (
                        <div className="flex items-center gap-2 px-4 py-2 bg-amber-50 dark:bg-amber-900/30 border-t border-amber-200 dark:border-amber-700 text-amber-700 dark:text-amber-300 text-xs shrink-0">
                            <AlertTriangle className="h-4 w-4 shrink-0" />
                            {t('inbox.session_closed_warning')}
                        </div>
                    )}

                    {/* Reply box */}
                    {activeTab === 'messages' && (
                    <div className="shrink-0 border-t border-neutral-200 bg-white p-2 pb-[max(0.5rem,env(safe-area-inset-bottom))] dark:border-neutral-700 dark:bg-neutral-900 sm:p-3">
                        {flash.error && <p className="text-xs text-red-500 mb-2">{flash.error}</p>}
                        {sendError && (
                            <div role="alert" className="mb-2 flex items-start gap-2 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-700 dark:border-red-900 dark:bg-red-900/20 dark:text-red-300">
                                {sendErrorCode === 'whatsapp_disconnected' && <WifiOff className="mt-0.5 h-4 w-4 shrink-0" />}
                                <div className="min-w-0 flex-1">
                                    <p>{sendError}</p>
                                    {sendErrorCode === 'whatsapp_disconnected' && (
                                        <Link href={route('client.inbox.setup')}
                                            className="mt-2 inline-flex min-h-11 items-center font-semibold text-red-800 underline underline-offset-2 hover:no-underline focus:outline-none focus:ring-2 focus:ring-red-500 dark:text-red-200">
                                            Abrir Configuração de canais
                                        </Link>
                                    )}
                                </div>
                                <button type="button" onClick={() => { setSendError(null); setSendErrorCode(null); }}
                                    aria-label="Fechar aviso de falha no envio"
                                    className="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg text-red-500 hover:bg-red-100 focus:outline-none focus:ring-2 focus:ring-red-500 dark:hover:bg-red-950/40">
                                    <X className="h-3.5 w-3.5" />
                                </button>
                            </div>
                        )}



                        {/* Attachment preview */}
                        {attachPreview && (
                            <div className="mb-2 flex items-center gap-2 bg-neutral-50 dark:bg-neutral-800 rounded-xl p-2 border border-neutral-200 dark:border-neutral-700">
                                {attachPreview.type === 'image'
                                    ? <img src={attachPreview.url} alt="" className="h-12 w-12 rounded-lg object-cover" />
                                    : <div className="h-12 w-12 rounded-lg bg-neutral-200 dark:bg-neutral-700 flex items-center justify-center"><Paperclip className="h-5 w-5 text-neutral-500" /></div>
                                }
                                <div className="flex-1 min-w-0">
                                    <p className="text-xs font-medium text-neutral-700 dark:text-neutral-300 truncate">{attachPreview.file.name}</p>
                                    <p className="text-[10px] text-neutral-400">{(attachPreview.file.size / 1024).toFixed(1)} KB</p>
                                </div>
                                <button type="button" onClick={() => { if (attachPreview.url) URL.revokeObjectURL(attachPreview.url); setAttachPreview(null); }} className="text-neutral-400 hover:text-red-500 transition">
                                    <X className="h-4 w-4" />
                                </button>
                            </div>
                        )}

                        {/* Canned reply picker */}
                        {slashMenu.length > 0 && (
                            <div className="mb-2 rounded-xl border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-900 shadow-lg max-h-48 overflow-y-auto">
                                {slashMenu.map(reply => (
                                    <button key={reply.id} type="button"
                                        onClick={() => { setData('body', renderCannedBody(reply.body, conversation.contact)); setSlashMenu([]); }}
                                        className="w-full text-left px-3 py-2 hover:bg-brand-50 dark:hover:bg-brand-900/20 transition border-b border-neutral-100 dark:border-neutral-800 last:border-0">
                                        <span className="font-mono text-xs font-semibold text-brand-600 dark:text-brand-400 mr-2">/{reply.shortcut}</span>
                                        <span className="text-xs text-neutral-500 line-clamp-1">{reply.body}</span>
                                    </button>
                                ))}
                            </div>
                        )}

                        {/* Toolbar + textarea */}
                        <div className="relative">
                            {/* Emoji picker */}
                            {showEmoji && <EmojiPicker onPick={e => setData('body', (data.body ?? '') + e)} onClose={() => setShowEmoji(false)} />}
                            {/* Template picker */}
                            {showTemplates && (
                                <TemplatePicker
                                    conversationId={conversation.uuid}
                                    onSent={(msg) => {
                                        if (msg) setMessages(prev => prev.some(m => m.id === msg.id) ? prev : [...prev, msg]);
                                        setShowTemplates(false);
                                    }}
                                    onClose={() => setShowTemplates(false)}
                                />
                            )}
                            {/* Product picker (share a store product) */}
                            {showProducts && (
                                <ProductPicker
                                    conversationId={conversation.uuid}
                                    onSent={(msg, err) => {
                                        if (msg) setMessages(prev => prev.some(m => m.id === msg.id) ? prev : [...prev, msg]);
                                        if (err) setSendError(err);
                                        setShowProducts(false);
                                    }}
                                    onClose={() => setShowProducts(false)}
                                />
                            )}

                            <form onSubmit={handleSend}>
                                {/* Toolbar */}
                                <div className="flex items-center gap-1 mb-1.5">
                                    {/* Emoji */}
                                    <button type="button" onClick={() => setShowEmoji(v => !v)}
                                        title={t('inbox.emoji')}
                                        className={`p-1.5 rounded-lg text-neutral-400 hover:text-neutral-600 hover:bg-neutral-100 dark:hover:bg-neutral-800 transition ${showEmoji ? 'bg-neutral-100 dark:bg-neutral-800 text-neutral-600' : ''}`}>
                                        <Smile className="h-4 w-4" />
                                    </button>
                                    {/* Attachment */}
                                    <button type="button" onClick={() => fileRef.current?.click()}
                                        title={t('inbox.attach_file')}
                                        className="p-1.5 rounded-lg text-neutral-400 hover:text-neutral-600 hover:bg-neutral-100 dark:hover:bg-neutral-800 transition">
                                        <Paperclip className="h-4 w-4" />
                                    </button>
                                    {/* Image */}
                                    <button type="button" onClick={() => { const i = document.createElement('input'); i.type='file'; i.accept='image/*'; i.onchange=handleFileChange; i.click(); }}
                                        title={t('inbox.attach_image')}
                                        className="p-1.5 rounded-lg text-neutral-400 hover:text-neutral-600 hover:bg-neutral-100 dark:hover:bg-neutral-800 transition">
                                        <ImageIcon className="h-4 w-4" />
                                    </button>
                                    {/* Share product */}
                                    {hasEcommerceStore && (
                                        <button type="button" onClick={() => setShowProducts(v => !v)}
                                            title={isWhatsApp && !isWindowOpen ? t('inbox.session_closed_reengage') : t('inbox.share_a_product')}
                                            disabled={isWhatsApp && !isWindowOpen}
                                            className={`p-1.5 rounded-lg text-neutral-400 hover:text-neutral-600 hover:bg-neutral-100 dark:hover:bg-neutral-800 transition disabled:opacity-40 disabled:cursor-not-allowed ${showProducts ? 'bg-neutral-100 dark:bg-neutral-800 text-neutral-600' : ''}`}>
                                            <ShoppingBag className="h-4 w-4" />
                                        </button>
                                    )}
                                    {/* WA Template */}
                                    {isWhatsApp && (
                                        <button type="button" onClick={() => setShowTemplates(v => !v)}
                                            title={t('inbox.send_template')}
                                            className={`flex items-center gap-1 p-1.5 rounded-lg text-xs transition ${
                                                !isWindowOpen
                                                    ? 'bg-amber-50 text-amber-600 border border-amber-200 hover:bg-amber-100 dark:bg-amber-900/20 dark:text-amber-400 dark:border-amber-800'
                                                    : 'text-neutral-400 hover:text-neutral-600 hover:bg-neutral-100 dark:hover:bg-neutral-800'
                                            } ${showTemplates ? 'bg-neutral-100 dark:bg-neutral-800' : ''}`}>
                                            <LayoutTemplate className="h-4 w-4" />
                                            {!isWindowOpen && <span className="font-medium">{t('inbox.template')}</span>}
                                        </button>
                                    )}
                                    {/* Hidden file input */}
                                    <input ref={fileRef} type="file" className="hidden" onChange={handleFileChange} />
                                    <button
                                        type="button"
                                        onClick={() => setShowScheduleModal(true)}
                                        title="Agendar mensagem"
                                        aria-label="Agendar mensagem nesta conversa"
                                        className="ml-auto flex min-h-11 items-center gap-1.5 rounded-lg px-2.5 text-xs font-medium text-neutral-500 transition hover:bg-brand-50 hover:text-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500 dark:hover:bg-brand-900/20 dark:hover:text-brand-300"
                                    >
                                        <CalendarPlus className="h-4 w-4" /> Agendar
                                    </button>

                                    
                                </div>

                                {/* Text input + send */}
                                <div className="flex gap-2 items-end">
                                    <textarea
                                        value={data.body}
                                        onChange={e => handleReplyChange(e.target.value)}
                                        onPaste={handleComposerPaste}
                                        onKeyDown={e => { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); handleSend(e); } }}
                                        placeholder={
                                            isWhatsApp && !isWindowOpen
                                                ? t('inbox.session_closed_placeholder')
                                                : t('inbox.type_message_placeholder')
                                        }
                                        rows={2}
                                        disabled={isWhatsApp && !isWindowOpen && !attachPreview}
                                        className="flex-1 rounded-xl border border-neutral-300 dark:border-neutral-600 bg-neutral-50 dark:bg-neutral-800 px-3 py-2 text-sm resize-none focus:outline-none focus:ring-2 focus:ring-brand-500 disabled:opacity-60 disabled:cursor-not-allowed"
                                    />
                                    <button
                                        type="submit"
                                        disabled={sending || (!data.body.trim() && !attachPreview) || (isWhatsApp && !isWindowOpen && !attachPreview)}
                                        className="self-end rounded-xl bg-brand-600 p-2.5 text-white hover:bg-brand-700 disabled:opacity-50 transition">
                                        {sending ? <Loader2 className="h-4 w-4 animate-spin" /> : <Send className="h-4 w-4" />}
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                    )}
                </div>

                {/* ── Contact panel (right) ── */}
                <div className="hidden w-96 shrink-0 flex-col overflow-y-auto border-l border-neutral-200 bg-white dark:border-neutral-700 dark:bg-neutral-900 xl:flex">
                    <div className="sticky top-0 z-10 grid grid-cols-3 border-b border-neutral-200 bg-white p-2 dark:border-neutral-700 dark:bg-neutral-900">
                        <button type="button" onClick={() => setRightPanelTab('profile')} className={`rounded-lg px-2 py-2 text-xs font-semibold ${rightPanelTab === 'profile' ? 'bg-brand-100 text-brand-700 dark:bg-brand-900/40 dark:text-brand-200' : 'text-neutral-500'}`}>Perfil</button>
                    </div>
                    {rightPanelTab === 'fazos' ? (
                        <FazOSPanel conversationId={conversation.uuid} contact={conversation.contact} initialData={fazos} />
                    ) : rightPanelTab === 'profile' ? <>
                    {/* Contact summary */}
                    <div className="p-4 border-b border-neutral-100 dark:border-neutral-800">
                        <div className="flex items-center gap-2.5 mb-3">
                            <div className="h-10 w-10 rounded-full bg-brand-100 dark:bg-brand-900/30 flex items-center justify-center text-base font-bold text-brand-700 dark:text-brand-300 shrink-0">
                                {contactName[0]?.toUpperCase() ?? '?'}
                            </div>
                            <div className="min-w-0">
                                <p className="text-sm font-semibold text-neutral-900 dark:text-neutral-100 truncate">{contactName}</p>
                                <p className="text-[11px] text-neutral-400 truncate">
                                    {conversation.contact?.phone_e164}
                                </p>
                            </div>
                        </div>
                        {conversation.contact?.email && (
                            <p className="flex items-center gap-1.5 text-xs text-neutral-500 mb-1">
                                <ChannelBrandIcon channel="email" className="h-3.5 w-3.5 shrink-0" />
                                <span className="truncate">{conversation.contact.email}</span>
                            </p>
                        )}
                        <Link href={route('client.contacts.show', conversation.contact?.uuid ?? '')} className="text-xs text-brand-600 hover:underline dark:text-brand-400">
                            {t('inbox.view_full_profile')}
                        </Link>
                        
                        <button
                            type="button"
                            onClick={() => setShowAppointmentModal(true)}
                            className="mt-3 flex w-full items-center justify-center gap-1.5 rounded-lg border border-brand-200 bg-brand-50 px-2.5 py-2 text-xs font-semibold text-brand-700 transition hover:bg-brand-100 dark:border-brand-800 dark:bg-brand-950/20 dark:text-brand-300"
                        >
                            <CalendarPlus className="h-3.5 w-3.5" />
                            Agendar atendimento
                        </button>
                        {conversation.contact?.uuid && kanbanPipelines.length > 0 && (
                            <button
                                type="button"
                                onClick={() => setShowKanbanModal(true)}
                                className="mt-3 flex w-full items-center justify-center gap-1.5 rounded-lg border border-brand-200 bg-brand-50 px-2.5 py-2 text-xs font-semibold text-brand-700 transition hover:bg-brand-100 dark:border-brand-800 dark:bg-brand-950/20 dark:text-brand-300"
                            >
                                    <Columns3 className="h-3.5 w-3.5" />
                                    Kanban
                            </button>
                        )}
                    </div>

                    {/* Conversation meta */}
                    <div className="p-4 border-b border-neutral-100 dark:border-neutral-800">
                        <p className="text-[10px] font-bold uppercase tracking-wider text-neutral-400 mb-2">{t('inbox.conversation')}</p>
                        <div className="space-y-1.5 text-xs">
                            <div className="flex items-center justify-between">
                                <span className="text-neutral-500">{t('inbox.status')}</span>
                                <span className={`rounded-full px-2 py-0.5 font-medium ${STATUS_COLORS[conversation.status] ?? 'bg-neutral-100 text-neutral-600'}`}>{t(`inbox.status_${conversation.status}`)}</span>
                            </div>
                            <div className="flex items-center justify-between">
                                <span className="text-neutral-500">{t('inbox.agent')}</span>
                                <span className="font-medium text-neutral-800 dark:text-neutral-200 truncate max-w-[100px]">{assignedAgent?.name ?? '—'}</span>
                            </div>
                            <div className="flex items-center justify-between">
                                <span className="text-neutral-500">{t('inbox.messages')}</span>
                                <span className="font-medium text-neutral-800 dark:text-neutral-200">{messages.length}</span>
                            </div>
                        </div>
                    </div>

                    {/* Labels / Tags */}
                    {allLabels.length > 0 && (
                        <div className="p-4 border-b border-neutral-100 dark:border-neutral-800">
                            <p className="text-[10px] font-bold uppercase tracking-wider text-neutral-400 mb-2">{t('inbox.tags')}</p>
                            <div className="flex flex-wrap gap-1.5">
                                {allLabels.map(label => {
                                    const active = convLabels.some(l => l.id === label.id);
                                    return (
                                        <button key={label.id} type="button" onClick={() => toggleLabel(label)}
                                            title={active ? t('inbox.remove_label', { name: label.name }) : t('inbox.add_label', { name: label.name })}
                                            className={`inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[11px] font-medium transition border ${
                                                active ? 'text-white border-transparent' : 'bg-transparent border-current opacity-40 hover:opacity-70'
                                            }`}
                                            style={{ backgroundColor: active ? label.color : undefined, color: active ? 'white' : label.color }}>
                                            {label.name}
                                        </button>
                                    );
                                })}
                            </div>
                        </div>
                    )}

                    {/* Contact permissions */}
                    <div className="p-4 border-b border-neutral-100 dark:border-neutral-800">
                        <p className="text-[10px] font-bold uppercase tracking-wider text-neutral-400 mb-2">{t('inbox.opt_ins')}</p>
                        {[
                            ['whatsapp', 'WhatsApp', 'opt_in_whatsapp'],
                            ['instagram', 'Instagram', 'channel'],
                            ['messenger', 'Messenger', 'channel'],
                            ['telegram', 'Telegram', 'channel'],
                            ['sms', 'SMS', 'opt_in_sms'],
                            ['email', t('common.email'), 'opt_in_email'],
                        ].map(([chKey, k, v]) => (
                            <div key={k} className="flex items-center justify-between text-xs py-1">
                                <span className="flex items-center gap-1.5 text-neutral-500">
                                    <ChannelBrandIcon channel={chKey} className="h-3.5 w-3.5 shrink-0" />{k}
                                </span>
                                {v === 'channel'
                                    ? <span className="text-[10px] font-medium text-neutral-400">{t('inbox.managed_by_channel')}</span>
                                    : <button
                                        type="button"
                                        disabled={savingPermission === v}
                                        onClick={async () => {
                                            const nextValue = !contactPermissions[v];
                                            setSavingPermission(v);
                                            try {
                                                const { data } = await axios.patch(route('client.inbox.contact-permissions.update', conversation.uuid), { [v]: nextValue });
                                                setContactPermissions((current) => ({ ...current, ...data.permissions }));
                                                toast.success('Permissão do contato atualizada.');
                                            } catch (error) {
                                                toast.error(error?.response?.data?.message || 'Não foi possível atualizar a permissão.');
                                            } finally {
                                                setSavingPermission(null);
                                            }
                                        }}
                                        role="switch"
                                        aria-checked={contactPermissions[v]}
                                        className={`relative h-5 w-9 rounded-full transition ${contactPermissions[v] ? 'bg-green-500' : 'bg-neutral-300 dark:bg-neutral-700'} disabled:opacity-50`}
                                    >
                                        <span className={`absolute top-0.5 h-4 w-4 rounded-full bg-white shadow transition-transform ${contactPermissions[v] ? 'translate-x-[18px]' : 'translate-x-0.5'}`} />
                                    </button>
                                }
                            </div>
                        ))}
                    </div>

                    {/* Contact campaign segmentation */}
                    <div className="p-4 border-b border-neutral-100 dark:border-neutral-800">
                        <p className="mb-1 flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-wider text-neutral-400">
                            <Tags className="h-3.5 w-3.5" />
                            Segmentação do contato
                        </p>
                        <p className="mb-3 text-[10px] leading-4 text-neutral-400">
                            <strong className="font-semibold text-neutral-500 dark:text-neutral-300">Segmentos</strong> agrupam públicos para campanhas e podem seguir regras automáticas.{' '}
                            <strong className="font-semibold text-neutral-500 dark:text-neutral-300">Etiquetas</strong> são marcadores flexíveis para identificar detalhes e ações de cada contato.
                        </p>

                        <p className="mb-1.5 text-[10px] font-semibold text-neutral-500 dark:text-neutral-300">Segmentos</p>
                        {availableStaticSegments.length > 0 ? (
                            <div className="mb-3 flex max-h-24 flex-wrap gap-1.5 overflow-y-auto">
                                {availableStaticSegments.map((segment) => {
                                    const active = selectedStaticSegmentIds.includes(Number(segment.id));
                                    return (
                                        <button
                                            key={segment.id}
                                            type="button"
                                            aria-pressed={active}
                                            onClick={() => toggleSelection(segment.id, setSelectedStaticSegmentIds)}
                                            className={`rounded-full border px-2 py-1 text-[10px] font-medium transition ${active
                                                ? 'border-brand-600 bg-brand-600 text-white'
                                                : 'border-neutral-300 text-neutral-600 hover:border-brand-400 hover:text-brand-600 dark:border-neutral-700 dark:text-neutral-300'
                                            }`}
                                        >
                                            {segment.name}
                                        </button>
                                    );
                                })}
                            </div>
                        ) : (
                            <p className="mb-3 text-[10px] text-neutral-400">
                                Nenhum segmento estático cadastrado.{' '}
                                <Link href={route('client.segments.index')} className="font-medium text-brand-600 hover:underline">Criar segmento</Link>
                            </p>
                        )}

                        <p className="mb-1.5 text-[10px] font-semibold text-neutral-500 dark:text-neutral-300">Etiquetas</p>
                        {contactTagOptions.length > 0 ? (
                            <div className="mb-2 flex max-h-24 flex-wrap gap-1.5 overflow-y-auto">
                                {contactTagOptions.map((tag) => {
                                    const active = selectedContactTagIds.includes(Number(tag.id));
                                    return (
                                        <button
                                            key={tag.id}
                                            type="button"
                                            aria-pressed={active}
                                            onClick={() => toggleSelection(tag.id, setSelectedContactTagIds)}
                                            className={`rounded-full border px-2 py-1 text-[10px] font-medium transition ${active ? 'text-white' : 'bg-transparent opacity-60 hover:opacity-100'}`}
                                            style={{
                                                backgroundColor: active ? tag.color : undefined,
                                                borderColor: tag.color,
                                                color: active ? '#fff' : tag.color,
                                            }}
                                        >
                                            {tag.name}
                                        </button>
                                    );
                                })}
                            </div>
                        ) : (
                            <p className="mb-2 text-[10px] text-neutral-400">Nenhuma etiqueta cadastrada.</p>
                        )}

                        <div className="flex gap-1.5">
                            <input
                                type="text"
                                value={newContactTagName}
                                maxLength={64}
                                onChange={(event) => setNewContactTagName(event.target.value)}
                                onKeyDown={(event) => {
                                    if (event.key === 'Enter') {
                                        event.preventDefault();
                                        createContactTag();
                                    }
                                }}
                                placeholder="Nova etiqueta"
                                className="min-w-0 flex-1 rounded-lg border border-neutral-300 bg-white px-2 py-1.5 text-[10px] outline-none focus:border-brand-500 dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-100"
                            />
                            <button
                                type="button"
                                disabled={!newContactTagName.trim() || creatingContactTag}
                                onClick={createContactTag}
                                title="Criar etiqueta"
                                className="inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-lg border border-brand-300 text-brand-600 hover:bg-brand-50 disabled:opacity-40 dark:border-brand-800 dark:hover:bg-brand-950"
                            >
                                {creatingContactTag ? <Loader2 className="h-3.5 w-3.5 animate-spin" /> : <Plus className="h-3.5 w-3.5" />}
                            </button>
                        </div>

                        <button
                            type="button"
                            disabled={savingSegmentation}
                            onClick={saveContactSegmentation}
                            className="mt-2 flex w-full items-center justify-center gap-1.5 rounded-lg bg-brand-600 px-2.5 py-2 text-xs font-semibold text-white transition hover:bg-brand-700 disabled:opacity-50"
                        >
                            {savingSegmentation && <Loader2 className="h-3.5 w-3.5 animate-spin" />}
                            Salvar segmentos e etiquetas
                        </button>
                    </div>

                    {/* AI Handover */}
                    <div className="p-4">
                        <p className="text-[10px] font-bold uppercase tracking-wider text-neutral-400 mb-2">{t('inbox.ai_handover')}</p>
                        <div className="flex items-center justify-between">
                            <span className={`inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium ${
                                assignedTo === 'human' ? 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300' : 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300'
                            }`}>
                                {assignedTo === 'human' ? <><User className="h-3 w-3" /> {t('inbox.human')}</> : <><Bot className="h-3 w-3" /> {t('inbox.bot')}</>}
                            </span>
                            {assignedTo === 'human'
                                ? <button onClick={() => switchHandover('bot')} className="text-xs text-brand-600 hover:underline">{t('inbox.back_to_bot')}</button>
                                : <button onClick={() => switchHandover('human')} className="text-xs text-amber-600 hover:underline">{t('inbox.take_over')}</button>
                            }
                        </div>
                    </div>
                    </> : <div className="flex min-h-0 flex-1 flex-col">
                        <div className="border-b border-neutral-100 p-3 dark:border-neutral-800">
                            <label className="text-[10px] font-bold uppercase tracking-wider text-neutral-400">Orquestrador do atendimento</label>
                            <div className="mt-1 rounded-lg border border-neutral-300 bg-white p-2 text-xs font-semibold text-neutral-700 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-200">
                                {aiCopilotAgents[0]?.name || 'Maestro'}
                                <span className="mt-0.5 block text-[10px] font-normal text-neutral-400">Aciona automaticamente o especialista adequado.</span>
                            </div>
                            <div className="mt-3 grid grid-cols-2 gap-1.5">
                                {[
                                    ['Resumir', 'Resuma esta conversa e destaque contexto, necessidade e pendências.'],
                                    ['Intenção', 'Identifique a intenção, objeções e sinais de compra deste cliente.'],
                                    ['Responder', 'Sugira a melhor resposta personalizada para enviar agora.'],
                                    ['Vender', 'Sugira uma estratégia de venda consultiva e o próximo passo.'],
                                    ['Proposta', 'Crie um rascunho de proposta comercial com base nesta conversa.'],
                                    ['Automação', 'Sugira uma automação adequada para este atendimento, sem executá-la.'],
                                ].map(([label, prompt]) => <button key={label} type="button" disabled={copilotLoading || !copilotAgentUuid} onClick={() => askCopilot(prompt)} className="rounded-lg border border-brand-200 px-2 py-1.5 text-[10px] font-medium text-brand-700 hover:bg-brand-50 disabled:opacity-50 dark:border-brand-800 dark:text-brand-300 dark:hover:bg-brand-950/30">{label}</button>)}
                            </div>
                        </div>
                        <div className="min-h-0 flex-1 space-y-2 overflow-y-auto p-3">
                            {copilotMessages.length === 0 && <div className="rounded-xl bg-neutral-50 p-3 text-xs leading-5 text-neutral-500 dark:bg-neutral-800/70">O copiloto lê o contexto desta conversa para ajudar o atendente. Ele não envia nada ao cliente automaticamente.</div>}
                            {copilotMessages.map((item, index) => <div key={index} className={`rounded-xl p-2.5 text-xs leading-5 ${item.role === 'user' ? 'ml-3 bg-brand-600 text-white' : item.role === 'error' ? 'bg-red-50 text-red-700 dark:bg-red-950/30 dark:text-red-300' : 'mr-3 bg-neutral-100 text-neutral-800 dark:bg-neutral-800 dark:text-neutral-100'}`}>
                                {item.delegatedAgent && <div className="mb-2 rounded-lg border border-brand-200 bg-brand-50 px-2 py-1 text-[10px] font-semibold text-brand-700 dark:border-brand-800 dark:bg-brand-950/30 dark:text-brand-300">Maestro consultou: {item.delegatedAgent}</div>}
                                <WaText text={item.content} />
                                {item.role === 'assistant' && <button type="button" onClick={() => { setData('body', item.content); toast.success('Sugestão colocada no campo de resposta.'); }} className="mt-2 rounded-lg border border-brand-300 px-2 py-1 text-[10px] font-semibold text-brand-700 dark:text-brand-300">Usar na resposta</button>}
                            </div>)}
                            {copilotLoading && <div className="flex items-center gap-2 text-xs text-neutral-500"><Loader2 className="h-3.5 w-3.5 animate-spin" />Analisando conversa…</div>}
                        </div>
                        <form onSubmit={(event) => { event.preventDefault(); askCopilot(); }} className="sticky bottom-0 border-t border-neutral-200 bg-white p-2 dark:border-neutral-700 dark:bg-neutral-900">
                            <textarea rows={3} value={copilotInput} onChange={(event) => setCopilotInput(event.target.value)} onKeyDown={(event) => { if (event.key === 'Enter' && !event.shiftKey) { event.preventDefault(); askCopilot(); } }} placeholder="Peça ajuda à IA…" className="w-full resize-none rounded-lg border border-neutral-300 bg-neutral-50 p-2 text-xs outline-none focus:border-brand-500 dark:border-neutral-700 dark:bg-neutral-800" />
                            <button disabled={!copilotInput.trim() || copilotLoading || !copilotAgentUuid} className="mt-1 flex w-full items-center justify-center gap-1.5 rounded-lg bg-brand-600 px-3 py-2 text-xs font-semibold text-white disabled:opacity-50"><Send className="h-3.5 w-3.5" />Perguntar</button>
                        </form>
                    </div>}
                </div>

            </div>
            {(showScheduleModal || editingSchedule) && (
                <ScheduleMessageModal
                    conversation={conversation}
                    schedule={editingSchedule}
                    initialBody={data.body}
                    onCreated={() => {
                        if (!editingSchedule) reset();
                        setActiveTab('appointments');
                        router.reload({ only: ['scheduledMessages'] });
                    }}
                    onClose={() => { setShowScheduleModal(false); setEditingSchedule(null); }}
                />
            )}
        </InboxLayout>
    );
}
