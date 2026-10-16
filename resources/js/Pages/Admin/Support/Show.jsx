import AdminLayout from '@/Layouts/AdminLayout';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { ArrowLeft, Calendar, CheckCircle, Clock, LifeBuoy, MessageSquare, Send, Shield, Tag } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { formatInTz } from '@/Utils/datetime';

const STATUS_VALUES = ['open', 'in_progress', 'closed'];
const STATUS_CLS = {
    open:        'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-900/20 dark:text-emerald-400 dark:border-emerald-800',
    in_progress: 'bg-blue-50 text-blue-700 border-blue-200 dark:bg-blue-900/20 dark:text-blue-400 dark:border-blue-800',
    closed:      'bg-neutral-100 text-neutral-500 border-neutral-200 dark:bg-neutral-700/50 dark:text-neutral-400 dark:border-neutral-600',
};

const PRIORITY_STYLES = {
    urgent: 'text-red-600 dark:text-red-400 font-semibold',
    high:   'text-orange-500 dark:text-orange-400 font-medium',
    normal: 'text-neutral-600 dark:text-neutral-400',
    low:    'text-neutral-400 dark:text-neutral-500',
};

function Avatar({ name, staff = false }) {
    const initials = name?.split(' ').map(n => n[0]).join('').slice(0, 2).toUpperCase() ?? '?';
    return (
        <div className={`w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold flex-shrink-0 ${staff ? 'bg-brand-600 text-white' : 'bg-neutral-200 dark:bg-neutral-600 text-neutral-700 dark:text-neutral-300'}`}>
            {staff ? <Shield className="h-3.5 w-3.5" /> : initials}
        </div>
    );
}

export default function AdminSupportShow({ ticket }) {
    const { flash, timezone } = usePage().props;
    const adminTz = timezone || 'America/Sao_Paulo';
    const formatTime = (iso) => formatInTz(iso, adminTz, { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit', timeZoneName: undefined });
    const { t } = useTranslation();
    const { data, setData, post, processing, errors, reset } = useForm({ message: '' });

    const STATUS_OPTIONS = STATUS_VALUES.map(v => ({ value: v, label: t(`support_tickets.${v}`), cls: STATUS_CLS[v] }));

    const submitReply = (e) => {
        e.preventDefault();
        post(route('admin.support.reply', ticket.id), { onSuccess: () => reset() });
    };

    const changeStatus = (status) => {
        router.post(route('admin.support.status', ticket.id), { status });
    };

    const currentStatus = STATUS_OPTIONS.find(s => s.value === ticket.status) ?? STATUS_OPTIONS[0];

    return (
        <AdminLayout title={`Ticket #${ticket.id}`}>
            <Head title={`Ticket #${ticket.id}`} />
            <div className="space-y-5 max-w-5xl">
                {/* Header */}
                <div className="flex items-start gap-3">
                    <Link href={route('admin.support.index')} className="mt-0.5 p-1.5 rounded-soft text-neutral-400 hover:text-neutral-700 dark:hover:text-neutral-200 hover:bg-neutral-100 dark:hover:bg-neutral-700 transition-colors flex-shrink-0">
                        <ArrowLeft className="h-4 w-4" />
                    </Link>
                    <div className="min-w-0 flex-1">
                        <div className="flex items-start justify-between gap-3 flex-wrap">
                            <div className="min-w-0">
                                <h1 className="text-xl font-bold text-neutral-900 dark:text-white leading-tight">{ticket.subject}</h1>
                                <div className="flex flex-wrap items-center gap-2 mt-1">
                                    <span className={`text-xs border px-2 py-0.5 rounded-full font-medium ${currentStatus.cls}`}>{currentStatus.label}</span>
                                    <span className={`text-xs capitalize font-medium ${PRIORITY_STYLES[ticket.priority] ?? ''}`}>{ticket.priority} {t('support_tickets.priority_suffix')}</span>
                                    <span className="text-xs text-neutral-400 dark:text-neutral-500">#{ticket.id}</span>
                                </div>
                            </div>
                            {/* Inline status buttons */}
                            <div className="flex items-center gap-1.5 flex-shrink-0">
                                {STATUS_OPTIONS.map(s => (
                                    <button
                                        key={s.value}
                                        onClick={() => s.value !== ticket.status && changeStatus(s.value)}
                                        disabled={s.value === ticket.status}
                                        className={`px-3 py-1.5 text-xs font-medium rounded-soft border transition-all ${s.value === ticket.status
                                            ? `${s.cls} cursor-default`
                                            : 'border-neutral-200 dark:border-neutral-600 text-neutral-500 dark:text-neutral-400 hover:border-neutral-300 dark:hover:border-neutral-500 hover:bg-neutral-50 dark:hover:bg-neutral-700'
                                        }`}
                                    >
                                        {s.label}
                                    </button>
                                ))}
                            </div>
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

                <div className="grid grid-cols-1 lg:grid-cols-3 gap-5">
                    {/* Conversation */}
                    <div className="lg:col-span-2 space-y-4">
                        {/* Original message */}
                        <div className="flex gap-3">
                            <Avatar name={ticket.name} />
                            <div className="flex-1 min-w-0">
                                <div className="bg-white dark:bg-neutral-800/70 rounded-xl rounded-tl-sm border border-neutral-200 dark:border-neutral-700/50 p-4 shadow-soft">
                                    <div className="flex items-center justify-between mb-3 gap-2">
                                        <span className="text-sm font-semibold text-neutral-900 dark:text-white">{ticket.name}</span>
                                        <span className="text-xs text-neutral-400 dark:text-neutral-500 flex-shrink-0">{formatTime(ticket.created_at)}</span>
                                    </div>
                                    <p className="text-sm text-neutral-700 dark:text-neutral-300 whitespace-pre-wrap leading-relaxed">{ticket.message}</p>
                                </div>
                            </div>
                        </div>

                        {/* Replies */}
                        {ticket.replies?.map(r => (
                            <div key={r.id} className={`flex gap-3 ${r.is_staff ? 'flex-row-reverse' : ''}`}>
                                <Avatar name={r.author_name} staff={r.is_staff} />
                                <div className="flex-1 min-w-0">
                                    <div className={`rounded-xl p-4 border shadow-soft ${r.is_staff
                                        ? 'bg-brand-50 dark:bg-brand-900/20 border-brand-200 dark:border-brand-800 rounded-tr-sm'
                                        : 'bg-white dark:bg-neutral-800/70 border-neutral-200 dark:border-neutral-700/50 rounded-tl-sm'
                                    }`}>
                                        <div className={`flex items-center justify-between mb-3 gap-2 ${r.is_staff ? 'flex-row-reverse' : ''}`}>
                                            <div className="flex items-center gap-1.5">
                                                <span className="text-sm font-semibold text-neutral-900 dark:text-white">{r.author_name}</span>
                                                {r.is_staff && (
                                                    <span className="text-xs px-1.5 py-0.5 bg-brand-100 dark:bg-brand-900/50 text-brand-700 dark:text-brand-400 rounded font-medium">{t('support_tickets.support_label')}</span>
                                                )}
                                            </div>
                                            <span className="text-xs text-neutral-400 dark:text-neutral-500 flex-shrink-0">{formatTime(r.created_at)}</span>
                                        </div>
                                        <p className="text-sm text-neutral-700 dark:text-neutral-300 whitespace-pre-wrap leading-relaxed">{r.message}</p>
                                    </div>
                                </div>
                            </div>
                        ))}

                        {/* Reply Form */}
                        <form onSubmit={submitReply} className="bg-white dark:bg-neutral-800/70 rounded-xl border border-neutral-200 dark:border-neutral-700/50 overflow-hidden shadow-soft">
                            <div className="p-4 border-b border-neutral-100 dark:border-neutral-700 flex items-center gap-2">
                                <Shield className="h-4 w-4 text-brand-500" />
                                <span className="text-sm font-medium text-neutral-700 dark:text-neutral-300">{t('support_tickets.reply_as_support')}</span>
                            </div>
                            <div className="p-4 space-y-3">
                                <textarea
                                    value={data.message}
                                    onChange={e => setData('message', e.target.value)}
                                    rows={5}
                                    placeholder={t('support_tickets.reply_placeholder_admin')}
                                    className={`w-full border rounded-soft-lg px-3 py-2.5 text-sm text-neutral-900 dark:text-white bg-white dark:bg-neutral-700 placeholder-neutral-400 dark:placeholder-neutral-500 resize-y transition-colors focus:outline-none focus:ring-2 focus:ring-brand-500/30 focus:border-brand-500 ${errors.message ? 'border-red-400' : 'border-neutral-300 dark:border-neutral-600'}`}
                                    required
                                />
                                {errors.message && <p className="text-red-500 text-xs">{errors.message}</p>}
                            </div>
                            <div className="px-4 pb-4 flex items-center justify-between">
                                <p className="text-xs text-neutral-400 dark:text-neutral-500">{t('support_tickets.reply_visible')}</p>
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
                    </div>

                    {/* Sidebar */}
                    <div className="space-y-4">
                        {/* Customer */}
                        <div className="bg-white dark:bg-neutral-800/70 rounded-xl border border-neutral-200 dark:border-neutral-700/50 p-4 shadow-soft">
                            <h3 className="text-xs font-semibold text-neutral-500 dark:text-neutral-400 uppercase tracking-wide mb-3">{t('support_tickets.customer_label')}</h3>
                            <div className="flex items-center gap-3">
                                <div className="w-9 h-9 rounded-full bg-neutral-200 dark:bg-neutral-600 flex items-center justify-center text-sm font-bold text-neutral-600 dark:text-neutral-300">
                                    {ticket.name?.charAt(0).toUpperCase()}
                                </div>
                                <div>
                                    <p className="text-sm font-semibold text-neutral-900 dark:text-white">{ticket.name}</p>
                                    <p className="text-xs text-neutral-500 dark:text-neutral-400">{ticket.email}</p>
                                </div>
                            </div>
                        </div>

                        {/* Details */}
                        <div className="bg-white dark:bg-neutral-800/70 rounded-xl border border-neutral-200 dark:border-neutral-700/50 p-4 space-y-3 shadow-soft">
                            <h3 className="text-xs font-semibold text-neutral-500 dark:text-neutral-400 uppercase tracking-wide">{t('support_tickets.details_label')}</h3>
                            <div className="space-y-2.5">
                                <div className="flex items-center justify-between text-xs">
                                    <div className="flex items-center gap-1.5 text-neutral-500 dark:text-neutral-400">
                                        <Tag className="h-3.5 w-3.5" />
                                        <span>{t('support_tickets.ticket_id')}</span>
                                    </div>
                                    <span className="font-mono text-neutral-700 dark:text-neutral-300">#{ticket.id}</span>
                                </div>
                                <div className="flex items-center justify-between text-xs">
                                    <div className="flex items-center gap-1.5 text-neutral-500 dark:text-neutral-400">
                                        <LifeBuoy className="h-3.5 w-3.5" />
                                        <span>{t('support_tickets.col_priority')}</span>
                                    </div>
                                    <span className={`capitalize font-medium ${PRIORITY_STYLES[ticket.priority] ?? ''}`}>{ticket.priority}</span>
                                </div>
                                <div className="flex items-center justify-between text-xs">
                                    <div className="flex items-center gap-1.5 text-neutral-500 dark:text-neutral-400">
                                        <MessageSquare className="h-3.5 w-3.5" />
                                        <span>{t('support_tickets.col_replies')}</span>
                                    </div>
                                    <span className="text-neutral-700 dark:text-neutral-300">{ticket.replies?.length ?? 0}</span>
                                </div>
                                <div className="flex items-start justify-between text-xs gap-2">
                                    <div className="flex items-center gap-1.5 text-neutral-500 dark:text-neutral-400 flex-shrink-0">
                                        <Calendar className="h-3.5 w-3.5" />
                                        <span>{t('support_tickets.opened_detail')}</span>
                                    </div>
                                    <span className="text-neutral-700 dark:text-neutral-300 text-right">
                                        {formatInTz(ticket.created_at, adminTz, { year: 'numeric', month: 'short', day: 'numeric', hour: undefined, minute: undefined, timeZoneName: undefined })}
                                    </span>
                                </div>
                            </div>
                        </div>

                        {/* Status Changer */}
                        <div className="bg-white dark:bg-neutral-800/70 rounded-xl border border-neutral-200 dark:border-neutral-700/50 p-4 shadow-soft">
                            <h3 className="text-xs font-semibold text-neutral-500 dark:text-neutral-400 uppercase tracking-wide mb-3">{t('support_tickets.change_status')}</h3>
                            <div className="space-y-1.5">
                                {STATUS_OPTIONS.map(s => (
                                    <button
                                        key={s.value}
                                        onClick={() => s.value !== ticket.status && changeStatus(s.value)}
                                        disabled={s.value === ticket.status}
                                        className={`w-full text-left px-3 py-2 text-sm rounded-soft border transition-all flex items-center justify-between ${s.value === ticket.status
                                            ? `${s.cls} cursor-default`
                                            : 'border-neutral-200 dark:border-neutral-600 text-neutral-600 dark:text-neutral-400 hover:border-neutral-300 dark:hover:border-neutral-500 hover:bg-neutral-50 dark:hover:bg-neutral-700'
                                        }`}
                                    >
                                        <span className="font-medium">{s.label}</span>
                                        {s.value === ticket.status && <CheckCircle className="h-3.5 w-3.5" />}
                                    </button>
                                ))}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </AdminLayout>
    );
}
