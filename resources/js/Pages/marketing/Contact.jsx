import { useForm, usePage } from '@inertiajs/react';
import { Mail, Send, MessageSquare, Clock, CheckCircle2 } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import LandingLayout from '@/Layouts/LandingLayout';
import SeoHead from '@/Components/SeoHead';

function Badge({ text }) {
    if (!text) return null;
    return (
        <span className="inline-flex items-center gap-1.5 rounded-full bg-[#8B5CF6]/15 text-[#8B5CF6] text-xs font-semibold px-3 py-1 border border-[#8B5CF6]/30">
            <span className="h-1.5 w-1.5 rounded-full bg-[#8B5CF6] inline-block" />
            {text}
        </span>
    );
}

const inputClass =
    'w-full rounded-xl border border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-900 px-4 py-2.5 text-sm text-neutral-900 dark:text-white placeholder:text-neutral-400 dark:placeholder:text-neutral-500 transition-colors focus:border-[#8B5CF6] focus:outline-none focus:ring-2 focus:ring-[#8B5CF6]/30';

export default function Contact({ landing = {} }) {
    const { t } = useTranslation();
    const { flash } = usePage().props;
    const appName = usePage().props.branding?.app_name || 'CREASY CRM';
    const contactEmail = landing['landing.contact_email'] || `support@${appName.toLowerCase().replace(/\s+/g, '')}.com`;

    const { data, setData, post, processing, errors, recentlySuccessful } = useForm({
        name: '',
        email: '',
        subject: '',
        message: '',
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('contact.store'), { preserveScroll: true, onSuccess: () => setData({ name: '', email: '', subject: '', message: '' }) });
    };

    const infoCards = [
        {
            icon: Mail,
            label: t('contact_page.email_label', { defaultValue: 'Email us' }),
            desc: t('contact_page.email_desc', { defaultValue: 'We reply to every message.' }),
            value: contactEmail,
            href: `mailto:${contactEmail}`,
        },
        {
            icon: MessageSquare,
            label: t('contact_page.chat_label', { defaultValue: 'Live chat' }),
            desc: t('contact_page.chat_desc', { defaultValue: 'Available in your dashboard, Mon–Fri.' }),
        },
        {
            icon: Clock,
            label: t('contact_page.response_label', { defaultValue: 'Response time' }),
            desc: t('contact_page.response_desc', { defaultValue: 'Within one business day.' }),
        },
    ];

    return (
        <LandingLayout>
            <SeoHead
                title={`${t('contact_page.title', { defaultValue: 'Contact Us' })} — ${appName}`}
                description={t('contact_page.subtitle')}
            />

            {/* Hero */}
            <section
                className="relative overflow-hidden"
                style={{ background: 'radial-gradient(ellipse 70% 65% at 50% 0%, rgba(139,92,246,0.18) 0%, transparent 60%), #160B2D' }}
            >
                <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 pt-24 pb-20 text-center">
                    <div className="flex justify-center mb-6">
                        <Badge text={t('contact_page.badge', { defaultValue: 'Get in touch' })} />
                    </div>
                    <h1 className="text-4xl sm:text-5xl font-bold tracking-tight text-white max-w-3xl mx-auto leading-tight">
                        {t('contact_page.title', { defaultValue: 'Contact Us' })}
                    </h1>
                    <p className="mt-6 text-lg text-neutral-300 max-w-2xl mx-auto leading-relaxed">
                        {t('contact_page.subtitle')}
                    </p>
                </div>
            </section>

            {/* Body */}
            <section className="py-20 sm:py-24">
                <div className="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div className="grid gap-10 lg:grid-cols-5 lg:gap-12">
                        {/* Contact info */}
                        <div className="lg:col-span-2">
                            <h2 className="text-xl font-bold text-neutral-900 dark:text-white tracking-tight">
                                {t('contact_page.info_heading', { defaultValue: 'Other ways to reach us' })}
                            </h2>
                            <div className="mt-6 space-y-4">
                                {infoCards.map((card, idx) => {
                                    const Icon = card.icon;
                                    const inner = (
                                        <>
                                            <div className="inline-flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-xl" style={{ background: 'rgba(139,92,246,0.12)' }}>
                                                <Icon className="h-5 w-5" style={{ color: '#8B5CF6' }} />
                                            </div>
                                            <div>
                                                <h3 className="text-sm font-semibold text-neutral-900 dark:text-white">{card.label}</h3>
                                                <p className="mt-0.5 text-sm text-neutral-600 dark:text-neutral-400 leading-relaxed">{card.desc}</p>
                                                {card.value && (
                                                    <p className="mt-1 text-sm font-medium text-brand-600 dark:text-brand-400 break-all">{card.value}</p>
                                                )}
                                            </div>
                                        </>
                                    );
                                    const cls = 'flex items-start gap-4 rounded-2xl border border-neutral-200 dark:border-neutral-800 bg-white dark:bg-neutral-900 p-5 transition-all duration-200';
                                    return card.href ? (
                                        <a key={idx} href={card.href} className={`${cls} hover:border-[#8B5CF6]/40 hover:shadow-md`}>
                                            {inner}
                                        </a>
                                    ) : (
                                        <div key={idx} className={cls}>{inner}</div>
                                    );
                                })}
                            </div>
                        </div>

                        {/* Form */}
                        <div className="lg:col-span-3">
                            <div className="rounded-3xl border border-neutral-200 dark:border-neutral-800 bg-white dark:bg-neutral-900 p-6 sm:p-8 shadow-sm">
                                <h2 className="text-xl font-bold text-neutral-900 dark:text-white tracking-tight mb-6">
                                    {t('contact_page.form_heading', { defaultValue: 'Send us a message' })}
                                </h2>

                                {(flash?.success || recentlySuccessful) && (
                                    <div className="mb-6 flex items-start gap-2.5 rounded-xl bg-[#8B5CF6]/10 border border-[#8B5CF6]/30 px-4 py-3 text-sm text-neutral-800 dark:text-neutral-100">
                                        <CheckCircle2 className="h-5 w-5 flex-shrink-0" style={{ color: '#65A30D' }} />
                                        <span>{flash?.success || t('contact_page.title')}</span>
                                    </div>
                                )}

                                <form onSubmit={submit} className="space-y-5">
                                    <div className="grid gap-5 sm:grid-cols-2">
                                        <div>
                                            <label className="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-1.5">{t('common.name')}</label>
                                            <input
                                                type="text"
                                                value={data.name}
                                                onChange={(e) => setData('name', e.target.value)}
                                                placeholder={t('contact_page.name_placeholder', { defaultValue: '' })}
                                                className={inputClass}
                                                required
                                            />
                                            {errors.name && <p className="text-coral-600 text-xs mt-1.5">{errors.name}</p>}
                                        </div>
                                        <div>
                                            <label className="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-1.5">{t('common.email')}</label>
                                            <input
                                                type="email"
                                                value={data.email}
                                                onChange={(e) => setData('email', e.target.value)}
                                                placeholder={t('contact_page.email_placeholder', { defaultValue: '' })}
                                                className={inputClass}
                                                required
                                            />
                                            {errors.email && <p className="text-coral-600 text-xs mt-1.5">{errors.email}</p>}
                                        </div>
                                    </div>
                                    <div>
                                        <label className="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-1.5">{t('contact_page.subject')}</label>
                                        <input
                                            type="text"
                                            value={data.subject}
                                            onChange={(e) => setData('subject', e.target.value)}
                                            placeholder={t('contact_page.subject_placeholder', { defaultValue: '' })}
                                            className={inputClass}
                                        />
                                        {errors.subject && <p className="text-coral-600 text-xs mt-1.5">{errors.subject}</p>}
                                    </div>
                                    <div>
                                        <label className="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-1.5">{t('contact_page.message')}</label>
                                        <textarea
                                            value={data.message}
                                            onChange={(e) => setData('message', e.target.value)}
                                            rows={6}
                                            placeholder={t('contact_page.message_placeholder', { defaultValue: '' })}
                                            className={`${inputClass} resize-y`}
                                            required
                                        />
                                        {errors.message && <p className="text-coral-600 text-xs mt-1.5">{errors.message}</p>}
                                    </div>
                                    <button
                                        type="submit"
                                        disabled={processing}
                                        className="inline-flex w-full items-center justify-center gap-2 rounded-xl px-7 py-3 text-base font-bold text-white shadow-lg transition-all duration-200 hover:opacity-90 disabled:opacity-50 sm:w-auto"
                                        style={{ background: '#8B5CF6' }}
                                    >
                                        <Send className="h-4 w-4" />
                                        {processing ? t('contact_page.sending', { defaultValue: 'Sending…' }) : t('contact_page.send_message')}
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </LandingLayout>
    );
}
