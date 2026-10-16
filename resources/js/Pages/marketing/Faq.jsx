import { useState } from 'react';
import { Head, Link } from '@inertiajs/react';
import LandingLayout from '@/Layouts/LandingLayout';
import { useTranslation } from 'react-i18next';

function Badge({ text }) {
    if (!text) return null;
    return (
        <span className="inline-flex items-center gap-1.5 rounded-full bg-[#8B5CF6]/15 text-[#8B5CF6] text-xs font-semibold px-3 py-1 border border-[#8B5CF6]/30">
            <span className="h-1.5 w-1.5 rounded-full bg-[#8B5CF6] inline-block" />
            {text}
        </span>
    );
}

const CATEGORIES = [
    { key: 'all',       labelKey: 'faq.cat_all' },
    { key: 'general',   labelKey: 'faq.cat_general' },
    { key: 'billing',   labelKey: 'faq.cat_billing' },
    { key: 'technical', labelKey: 'faq.cat_technical' },
    { key: 'security',  labelKey: 'faq.cat_security' },
];

export default function Faq({ landing = {}, canRegister }) {
    const { t } = useTranslation();
    const s = (key, def = '') => landing[`landing.${key}`] ?? def;
    const [open, setOpen] = useState(null);
    const [cat, setCat] = useState('all');

    const faqs = [1, 2, 3, 4, 5].map((i) => ({
        q: s(`faq_${i}_q`),
        a: s(`faq_${i}_a`),
    })).filter((f) => f.q && f.a);

    const title = s('faq_title', 'Frequently Asked Questions');
    const subtitle = s('faq_subtitle', 'Everything you need to know about our platform.');

    return (
        <LandingLayout>
            <Head>
                <title>{t('faq.head_title', { title })}</title>
                <meta name="description" content={subtitle} />
            </Head>

            {/* ── Page hero ── */}
            <section
                className="relative overflow-hidden py-20 text-center"
                style={{ background: 'radial-gradient(ellipse 60% 60% at 50% 0%, rgba(139,92,246,0.18) 0%, transparent 70%), #160B2D' }}
            >
                <div
                    className="pointer-events-none absolute inset-0"
                    style={{
                        backgroundImage: `linear-gradient(rgba(255,255,255,0.04) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,0.04) 1px, transparent 1px)`,
                        backgroundSize: '60px 60px',
                        maskImage: 'radial-gradient(ellipse 80% 100% at 50% 0%, black 50%, transparent 100%)',
                        WebkitMaskImage: 'radial-gradient(ellipse 80% 100% at 50% 0%, black 50%, transparent 100%)',
                    }}
                />
                <div className="relative max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
                    <Badge text={t('nav.faq')} />
                    <h1 className="mt-4 text-4xl sm:text-5xl font-bold text-white tracking-tight">{title}</h1>
                    <p className="mt-4 text-lg text-neutral-300 max-w-xl mx-auto">{subtitle}</p>
                </div>
            </section>

            {/* ── Category filter ── */}
            <section className="border-b border-neutral-200 dark:border-neutral-800 bg-white dark:bg-neutral-950 sticky top-16 z-10">
                <div className="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 flex items-center gap-2 overflow-x-auto py-3 scrollbar-none">
                    {CATEGORIES.map((c) => (
                        <button
                            key={c.key}
                            onClick={() => setCat(c.key)}
                            className={`flex-shrink-0 rounded-full px-4 py-1.5 text-sm font-medium transition-all ${
                                cat === c.key
                                    ? 'text-white font-bold'
                                    : 'text-neutral-500 dark:text-neutral-400 hover:text-neutral-900 dark:hover:text-neutral-200 bg-neutral-100 dark:bg-neutral-800'
                            }`}
                            style={cat === c.key ? { background: '#8B5CF6' } : {}}
                        >
                            {t(c.labelKey)}
                        </button>
                    ))}
                </div>
            </section>

            {/* ── FAQ list ── */}
            <section className="py-16">
                <div className="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
                    {faqs.length === 0 ? (
                        <p className="text-center text-neutral-500 dark:text-neutral-400 py-16">{t('faq.no_items')}</p>
                    ) : (
                        <div className="space-y-2">
                            {faqs.map((faq, idx) => (
                                <div
                                    key={idx}
                                    className="rounded-xl border border-neutral-200 dark:border-neutral-800 bg-white dark:bg-neutral-900 overflow-hidden"
                                >
                                    <button
                                        className="w-full flex items-center justify-between px-5 py-4 text-left gap-4"
                                        onClick={() => setOpen(open === idx ? null : idx)}
                                    >
                                        <span className="font-medium text-neutral-900 dark:text-white text-sm">{faq.q}</span>
                                        <svg
                                            className={`h-5 w-5 flex-shrink-0 text-neutral-400 transition-transform duration-200 ${open === idx ? 'rotate-180' : ''}`}
                                            fill="none" stroke="currentColor" strokeWidth={2} viewBox="0 0 24 24"
                                        >
                                            <path strokeLinecap="round" strokeLinejoin="round" d="M19 9l-7 7-7-7" />
                                        </svg>
                                    </button>
                                    {open === idx && (
                                        <div className="px-5 pb-5 border-t border-neutral-100 dark:border-neutral-800 pt-4">
                                            <p className="text-sm text-neutral-600 dark:text-neutral-400 leading-relaxed">{faq.a}</p>
                                        </div>
                                    )}
                                </div>
                            ))}
                        </div>
                    )}

                    {/* Still have questions? */}
                    <div
                        className="mt-12 rounded-2xl p-8 text-center"
                        style={{ background: 'rgba(139,92,246,0.08)', border: '1px solid rgba(139,92,246,0.25)' }}
                    >
                        <h3 className="text-lg font-bold text-neutral-900 dark:text-white mb-2">{t('faq.still_have_questions')}</h3>
                        <p className="text-sm text-neutral-600 dark:text-neutral-400 mb-5">{t('faq.still_have_questions_desc')}</p>
                        <Link
                            href="/contact"
                            className="inline-flex items-center gap-2 rounded-xl px-6 py-2.5 text-sm font-bold text-white transition-all hover:opacity-90"
                            style={{ background: '#8B5CF6' }}
                        >
                            {t('faq.contact_support')}
                        </Link>
                    </div>
                </div>
            </section>

            {/* ── CTA ── */}
            {canRegister && (
                <section className="py-16 bg-neutral-50 dark:bg-neutral-900/30">
                    <div className="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
                        <h2 className="text-2xl font-bold text-neutral-900 dark:text-white">{t('faq.ready_to_try')}</h2>
                        <p className="mt-2 text-neutral-500 dark:text-neutral-400 text-sm">{t('faq.ready_to_try_desc')}</p>
                        <Link
                            href={route('register')}
                            className="mt-6 inline-flex items-center gap-2 rounded-xl px-7 py-3.5 text-base font-bold text-white shadow-lg transition-all duration-200 hover:opacity-90"
                            style={{ background: '#8B5CF6' }}
                        >
                            {t('welcome.get_started_free')}
                        </Link>
                    </div>
                </section>
            )}
        </LandingLayout>
    );
}
