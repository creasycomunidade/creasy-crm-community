import ClientLayout from '@/Layouts/ClientLayout';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { ArrowLeft, CheckCircle, Clock, Lock, MessageSquare, Send, Shield } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { formatInTz } from '@/Utils/datetime';

const STATUS_CLS = {
    open:        'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-900/20 dark:text-emerald-400 dark:border-emerald-800',
    in_progress: 'bg-blue-50 text-blue-700 border-blue-200 dark:bg-blue-900/20 dark:text-blue-400 dark:border-blue-800',
    closed:      'bg-neutral-100 text-neutral-500 border-neutral-200 dark:bg-neutral-700/50 dark:text-neutral-400 dark:border-neutral-600',
};
const STATUS_DOT = {
    open: 'bg-emerald-500',
    in_progress: 'bg-blue-500',
    closed: 'bg-neutral-400',
};

const PRIORITY_STYLES = {
    urgent: 'text-red-600 dark:text-red-400',
    high:   'text-orange-500 dark:text-orange-400',
    normal: 'text-neutral-500 dark:text-neutral-400',
    low:    'text-neutral-400 dark:text-neutral-500',
};

function Avatar({ name, staff = false }) {
    const initials = name?.split(' ').map(n => n[0]).join('').slice(0, 2).toUpperCase() ?? '?';
    return (
        <div className={`w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold flex-shrink-0 ${staff ? 'bg-brand-600 text-white' : 'bg-neutral-200 dark:bg-neutral-600 text-neutral-600 dark:text-neutral-300'}`}>
            {staff ? <Shield className="h-3.5 w-3.5" /> : initials}
        </div>
    );
}

export default function SupportShow({ ticket }) {
    const { flash, timezone } = usePage().props;
    const userTz = timezone || 'America/Sao_Paulo';
    const formatTime = (iso) => formatInTz(iso, userTz, { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit', timeZoneName: undefined });
    const { t } = useTranslation();
    const { data, setData, post, processing, errors, reset } = useForm({ message: '' });
    const isClosed = ticket.status === 'closed';
    const stCls = STATUS_CLS[ticket.status] ?? STATUS_CLS.open;
    const stDot = STATUS_DOT[ticket.status] ?? STATUS_DOT.open;
    const stLabel = t(`support_tickets.${ticket.status}`) || ticket.status;

    const submit = (e) => {
        e.preventDefault();
        post(route('client.support.reply', ticket.id), { onSuccess: () => reset() });
    };

    return (
        <ClientLayout title={`Ticket #${ticket.id}`}>
            <Head title={`Ticket #${ticket.id}`} />
            <div className="max-w-2xl space-y-5">
                {/* Header */}
                <div className="flex items-start gap-3">
                    <Link href={route('client.support.index')} className="mt-0.5 p-1.5 rounded-soft text-neutral-400 hover:text-neutral-700 dark:hover:text-neutral-200 hover:bg-neutral-100 dark:hover:bg-neutral-700 transition-colors flex-shrink-0">
                        <ArrowLeft className="h-4 w-4" />
                    </Link>
                    <div className="min-w-0 flex-1">
                        <h1 className="text-xl font-bold text-neutral-900 dark:text-white leading-tight">{ticket.subject}</h1>
                        <div className="flex flex-wrap items-center gap-2 mt-1.5">
                            <span className={`inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium border ${stCls}`}>
                                <span className={`h-1.5 w-1.5 rounded-full ${stDot}`} />
                                {stLabel}
                            </span>
                            <span className={`text-xs capitalize font-medium ${PRIORITY_STYLES[ticket.priority] ?? ''}`}>
                                {ticket.priority} {t('support_tickets.priority_suffix')}
                            </span>
                            <span className="text-xs text-neutral-400 dark:text-neutral-500">#{ticket.id}</span>
                        </div>
                    </div>
                </div>

                {/* Flash */}
                {flash?.success && (
                    <div className="flex items-center gap-2 rounded-soft-lg bg-emerald-50 dark:bg-emerald-900/20 text-emerald-800 dark:text-emerald-200 px-4 py-3 text-sm border border-emerald-200 dark:border-emerald-800">
                        <CheckCircle className="h-4 w-4 flex-shrink-0" />
                        {flash.success}
                    </div>
                )}

                {/* Conversation */}
                <div className="space-y-4">
                    {/* Original message */}
                    <div className="flex gap-3">
                        <Avatar name={ticket.name} />
                        <div className="flex-1 min-w-0">
                            <div className="bg-white dark:bg-neutral-800/70 rounded-xl rounded-tl-sm border border-neutral-200 dark:border-neutral-700/50 p-4 shadow-soft">
                                <div className="flex items-center justify-between mb-2 gap-2">
                                    <div className="flex items-center gap-1.5">
                                        <span className="text-sm font-semibold text-neutral-900 dark:text-white truncate">{ticket.name}</span>
                                        <span className="text-xs px-1.5 py-0.5 bg-neutral-100 dark:bg-neutral-700 text-neutral-500 dark:text-neutral-400 rounded">{t('support_tickets.you_label')}</span>
                                    </div>
                                    <div className="flex items-center gap-1 text-xs text-neutral-400 dark:text-neutral-500 flex-shrink-0">
                                        <Clock className="h-3 w-3" />
                                        {formatTime(ticket.created_at)}
                                    </div>
                                </div>
                                <p className="text-sm text-neutral-700 dark:text-neutral-300 whitespace-pre-wrap leading-relaxed">{ticket.message}</p>
                            </div>
                        </div>
                    </div>

                    {/* Replies */}
                    {ticket.replies?.map(reply => (
                        <div key={reply.id} className={`flex gap-3 ${reply.is_staff ? 'flex-row-reverse' : ''}`}>
                            <Avatar name={reply.author_name} staff={reply.is_staff} />
                            <div className="flex-1 min-w-0">
                                <div className={`rounded-xl p-4 border shadow-soft ${reply.is_staff
                                    ? 'bg-brand-50 dark:bg-brand-900/20 border-brand-200 dark:border-brand-800 rounded-tr-sm'
                                    : 'bg-white dark:bg-neutral-800/70 border-neutral-200 dark:border-neutral-700/50 rounded-tl-sm'
                                }`}>
                                    <div className={`flex items-center justify-between mb-2 gap-2 ${reply.is_staff ? 'flex-row-reverse' : ''}`}>
                                        <div className="flex items-center gap-1.5">
                                            <span className="text-sm font-semibold text-neutral-900 dark:text-white truncate">{reply.author_name}</span>
                                            {reply.is_staff && (
                                                <span className="text-xs px-1.5 py-0.5 bg-brand-100 dark:bg-brand-900/50 text-brand-700 dark:text-brand-400 rounded font-medium">{t('support_tickets.support_label')}</span>
                                            )}
                                        </div>
                                        <div className="flex items-center gap-1 text-xs text-neutral-400 dark:text-neutral-500 flex-shrink-0">
                                            <Clock className="h-3 w-3" />
                                            {formatTime(reply.created_at)}
                                        </div>
                                    </div>
                                    <p className="text-sm text-neutral-700 dark:text-neutral-300 whitespace-pre-wrap leading-relaxed">{reply.message}</p>
                                </div>
                            </div>
                        </div>
                    ))}
                </div>

                {/* Reply or closed notice */}
                {isClosed ? (
                    <div className="flex items-center gap-3 p-4 bg-neutral-50 dark:bg-neutral-800/70 rounded-xl border border-neutral-200 dark:border-neutral-700/50">
                        <Lock className="h-4 w-4 text-neutral-400 flex-shrink-0" />
                        <div>
                            <p className="text-sm font-medium text-neutral-600 dark:text-neutral-400">{t('support_tickets.ticket_closed')}</p>
                            <p className="text-xs text-neutral-400 dark:text-neutral-500 mt-0.5">
                                {t('support_tickets.need_more_help')}{' '}
                                <Link href={route('client.support.create')} className="text-brand-600 dark:text-brand-400 hover:underline">
                                    {t('support_tickets.open_new_ticket')}
                                </Link>
                            </p>
                        </div>
                    </div>
                ) : (
                    <form onSubmit={submit} className="bg-white dark:bg-neutral-800/70 rounded-xl border border-neutral-200 dark:border-neutral-700/50 overflow-hidden shadow-soft">
                        <div className="p-4 border-b border-neutral-100 dark:border-neutral-700 flex items-center gap-2">
                            <MessageSquare className="h-4 w-4 text-neutral-400" />
                            <span className="text-sm font-medium text-neutral-700 dark:text-neutral-300">{t('support_tickets.add_reply')}</span>
                        </div>
                        <div className="p-4">
                            <textarea
                                value={data.message}
                                onChange={e => setData('message', e.target.value)}
                                rows={4}
                                placeholder={t('support_tickets.reply_placeholder')}
                                className={`w-full border rounded-soft-lg px-3 py-2.5 text-sm text-neutral-900 dark:text-white bg-white dark:bg-neutral-700 placeholder-neutral-400 dark:placeholder-neutral-500 resize-y transition-colors focus:outline-none focus:ring-2 focus:ring-brand-500/30 focus:border-brand-500 ${errors.message ? 'border-red-400' : 'border-neutral-300 dark:border-neutral-600'}`}
                                required
                            />
                            {errors.message && <p className="text-red-500 text-xs mt-1">{errors.message}</p>}
                        </div>
                        <div className="px-4 pb-4 flex justify-end">
                            <button
                                type="submit"
                                disabled={processing || !data.message.trim()}
                                className="flex items-center gap-2 px-4 py-2 bg-brand-600 hover:bg-brand-700 text-white text-sm font-medium rounded-soft-lg disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                            >
                                <Send className="h-4 w-4" />
                                {processing ? t('support_tickets.sending') : t('support_tickets.send_reply')}
                            </button>
                        </div>
                    </form>
                )}
            </div>
        </ClientLayout>
    );
}
