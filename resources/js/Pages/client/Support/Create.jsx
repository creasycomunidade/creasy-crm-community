import ClientLayout from '@/Layouts/ClientLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { AlertTriangle, ArrowLeft, ChevronDown, LifeBuoy, Send } from 'lucide-react';
import { useTranslation } from 'react-i18next';

const PRIORITY_OPTION_VALUES = ['low', 'normal', 'high', 'urgent'];

const MAX_MSG = 5000;

export default function SupportCreate() {
    const { t } = useTranslation();
    const { data, setData, post, processing, errors } = useForm({
        subject: '',
        message: '',
        priority: 'normal',
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('client.support.store'));
    };

    const remaining = MAX_MSG - data.message.length;

    const priorityOptions = PRIORITY_OPTION_VALUES.map(v => ({
        value: v,
        label: t(`support_tickets.priority_${v}`),
        desc: t(`support_tickets.priority_${v}_desc`),
    }));

    return (
        <ClientLayout title={t('support_tickets.new_ticket_title')}>
            <Head title={t('support_tickets.new_ticket')} />
            <div className="max-w-2xl space-y-6">
                {/* Header */}
                <div className="flex items-center gap-3">
                    <Link href={route('client.support.index')} className="p-1.5 rounded-soft text-neutral-400 hover:text-neutral-700 dark:hover:text-neutral-200 hover:bg-neutral-100 dark:hover:bg-neutral-700 transition-colors">
                        <ArrowLeft className="h-4 w-4" />
                    </Link>
                    <div>
                        <h1 className="text-xl font-bold text-neutral-900 dark:text-white">{t('support_tickets.new_ticket_title')}</h1>
                        <p className="text-sm text-neutral-500 dark:text-neutral-400">{t('support_tickets.response_time')}</p>
                    </div>
                </div>

                <form onSubmit={submit} className="space-y-4">
                    {/* Subject + Priority */}
                    <div className="bg-white dark:bg-neutral-800/70 rounded-xl border border-neutral-200 dark:border-neutral-700/50 p-5 space-y-4 shadow-soft">
                        <div>
                            <label className="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-1.5">
                                {t('support_tickets.subject_label')} <span className="text-red-500">*</span>
                            </label>
                            <input
                                type="text"
                                value={data.subject}
                                onChange={e => setData('subject', e.target.value)}
                                placeholder={t('support_tickets.subject_placeholder')}
                                className={`w-full border rounded-soft-lg px-3 py-2.5 text-sm text-neutral-900 dark:text-white bg-white dark:bg-neutral-700 placeholder-neutral-400 dark:placeholder-neutral-500 transition-colors focus:outline-none focus:ring-2 focus:ring-brand-500/30 focus:border-brand-500 ${errors.subject ? 'border-red-400 dark:border-red-500' : 'border-neutral-300 dark:border-neutral-600'}`}
                                required
                            />
                            {errors.subject && (
                                <p className="flex items-center gap-1 text-red-500 text-xs mt-1.5">
                                    <AlertTriangle className="h-3 w-3" /> {errors.subject}
                                </p>
                            )}
                        </div>

                        <div>
                            <label className="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-1.5">{t('support_tickets.priority_label')}</label>
                            <div className="relative">
                                <select
                                    value={data.priority}
                                    onChange={e => setData('priority', e.target.value)}
                                    className="w-full appearance-none border border-neutral-300 dark:border-neutral-600 rounded-soft-lg px-3 py-2.5 text-sm text-neutral-900 dark:text-white bg-white dark:bg-neutral-700 focus:outline-none focus:ring-2 focus:ring-brand-500/30 focus:border-brand-500 transition-colors pr-8"
                                >
                                    {priorityOptions.map(p => (
                                        <option key={p.value} value={p.value}>{p.label} — {p.desc}</option>
                                    ))}
                                </select>
                                <ChevronDown className="absolute right-3 top-1/2 -translate-y-1/2 h-4 w-4 text-neutral-400 pointer-events-none" />
                            </div>
                            <div className="mt-2 grid grid-cols-4 gap-1.5">
                                {priorityOptions.map(p => (
                                    <button
                                        key={p.value}
                                        type="button"
                                        onClick={() => setData('priority', p.value)}
                                        className={`py-1.5 px-2 text-xs rounded-soft border transition-all ${data.priority === p.value
                                            ? 'border-brand-500 bg-brand-50 dark:bg-brand-900/30 text-brand-700 dark:text-brand-300 font-medium'
                                            : 'border-neutral-200 dark:border-neutral-600 text-neutral-500 dark:text-neutral-400 hover:border-neutral-300 dark:hover:border-neutral-500'
                                        }`}
                                    >
                                        {p.label}
                                    </button>
                                ))}
                            </div>
                        </div>
                    </div>

                    {/* Message */}
                    <div className="bg-white dark:bg-neutral-800/70 rounded-xl border border-neutral-200 dark:border-neutral-700/50 p-5 shadow-soft">
                        <div className="flex items-center justify-between mb-1.5">
                            <label className="text-sm font-medium text-neutral-700 dark:text-neutral-300">
                                {t('support_tickets.message_label')} <span className="text-red-500">*</span>
                            </label>
                            <span className={`text-xs tabular-nums ${remaining < 200 ? 'text-orange-500' : 'text-neutral-400 dark:text-neutral-500'}`}>
                                {t('support_tickets.chars_left', { count: remaining })}
                            </span>
                        </div>
                        <textarea
                            value={data.message}
                            onChange={e => setData('message', e.target.value)}
                            rows={8}
                            maxLength={MAX_MSG}
                            placeholder={t('support_tickets.message_placeholder')}
                            className={`w-full border rounded-soft-lg px-3 py-2.5 text-sm text-neutral-900 dark:text-white bg-white dark:bg-neutral-700 placeholder-neutral-400 dark:placeholder-neutral-500 resize-y transition-colors focus:outline-none focus:ring-2 focus:ring-brand-500/30 focus:border-brand-500 ${errors.message ? 'border-red-400 dark:border-red-500' : 'border-neutral-300 dark:border-neutral-600'}`}
                            required
                        />
                        {errors.message && (
                            <p className="flex items-center gap-1 text-red-500 text-xs mt-1.5">
                                <AlertTriangle className="h-3 w-3" /> {errors.message}
                            </p>
                        )}
                    </div>

                    {/* Tips */}
                    <div className="bg-brand-50 dark:bg-brand-900/20 border border-brand-100 dark:border-brand-800 rounded-xl p-4">
                        <div className="flex items-start gap-2">
                            <LifeBuoy className="h-4 w-4 text-brand-500 dark:text-brand-400 mt-0.5 flex-shrink-0" />
                            <div>
                                <p className="text-xs font-semibold text-brand-700 dark:text-brand-300 mb-1">{t('support_tickets.tips_title')}</p>
                                <ul className="text-xs text-brand-600 dark:text-brand-400 space-y-0.5 list-disc list-inside">
                                    <li>{t('support_tickets.tip_1')}</li>
                                    <li>{t('support_tickets.tip_2')}</li>
                                    <li>{t('support_tickets.tip_3')}</li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    {/* Actions */}
                    <div className="flex items-center justify-between pt-2">
                        <Link href={route('client.support.index')} className="text-sm text-neutral-500 dark:text-neutral-400 hover:text-neutral-700 dark:hover:text-neutral-200 transition-colors">
                            {t('common.cancel')}
                        </Link>
                        <button
                            type="submit"
                            disabled={processing || !data.subject || !data.message}
                            className="flex items-center gap-2 px-5 py-2.5 bg-brand-600 hover:bg-brand-700 text-white text-sm font-medium rounded-soft-lg disabled:opacity-50 disabled:cursor-not-allowed transition-colors shadow-soft"
                        >
                            <Send className="h-4 w-4" />
                            {processing ? t('support_tickets.submitting') : t('support_tickets.submit_ticket')}
                        </button>
                    </div>
                </form>
            </div>
        </ClientLayout>
    );
}
