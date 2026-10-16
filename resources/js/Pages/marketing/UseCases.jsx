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

const USE_CASES = [
    {
        icon: (
            <svg className="h-7 w-7" fill="none" stroke="currentColor" strokeWidth={1.5} viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" d="M15.59 14.37a6 6 0 01-5.84 7.38v-4.8m5.84-2.58a14.98 14.98 0 006.16-12.12A14.98 14.98 0 009.631 8.41m5.96 5.96a14.926 14.926 0 01-5.841 2.58m-.119-8.54a6 6 0 00-7.381 5.84h4.8m2.581-5.84a14.927 14.927 0 00-2.58 5.84m2.699 2.7c-.103.021-.207.041-.311.06a15.09 15.09 0 01-2.448-2.448 14.9 14.9 0 01.06-.312m-2.24 2.39a4.493 4.493 0 00-1.757 4.306 4.493 4.493 0 004.306-1.758M16.5 9a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0z" />
            </svg>
        ),
        labelKey: 'use_cases.ecommerce_label',
        titleKey: 'use_cases.ecommerce_title',
        descKey: 'use_cases.ecommerce_desc',
        bulletKeys: ['use_cases.ecommerce_b1', 'use_cases.ecommerce_b2', 'use_cases.ecommerce_b3', 'use_cases.ecommerce_b4'],
        color: 'from-violet-500/20 to-violet-500/5',
        border: 'border-violet-200 dark:border-violet-800',
        iconBg: 'bg-violet-100 dark:bg-violet-900/40 text-violet-600 dark:text-violet-400',
    },
    {
        icon: (
            <svg className="h-7 w-7" fill="none" stroke="currentColor" strokeWidth={1.5} viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" d="M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0zm6 3a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0zm-13.5 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z" />
            </svg>
        ),
        labelKey: 'use_cases.realestate_label',
        titleKey: 'use_cases.realestate_title',
        descKey: 'use_cases.realestate_desc',
        bulletKeys: ['use_cases.realestate_b1', 'use_cases.realestate_b2', 'use_cases.realestate_b3', 'use_cases.realestate_b4'],
        color: 'from-blue-500/20 to-blue-500/5',
        border: 'border-blue-200 dark:border-blue-800',
        iconBg: 'bg-blue-100 dark:bg-blue-900/40 text-blue-600 dark:text-blue-400',
    },
    {
        icon: (
            <svg className="h-7 w-7" fill="none" stroke="currentColor" strokeWidth={1.5} viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" d="M4.26 10.147a60.436 60.436 0 00-.491 6.347A48.627 48.627 0 0112 20.904a48.627 48.627 0 018.232-4.41 60.46 60.46 0 00-.491-6.347m-15.482 0a50.57 50.57 0 00-2.658-.813A59.905 59.905 0 0112 3.493a59.902 59.902 0 0110.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.697 50.697 0 0112 13.489a50.702 50.702 0 017.74-3.342M6.75 15a.75.75 0 100-1.5.75.75 0 000 1.5zm0 0v-3.675A55.378 55.378 0 0112 8.443m-7.007 11.55A5.981 5.981 0 006.75 15.75v-1.5" />
            </svg>
        ),
        labelKey: 'use_cases.education_label',
        titleKey: 'use_cases.education_title',
        descKey: 'use_cases.education_desc',
        bulletKeys: ['use_cases.education_b1', 'use_cases.education_b2', 'use_cases.education_b3', 'use_cases.education_b4'],
        color: 'from-amber-500/20 to-amber-500/5',
        border: 'border-amber-200 dark:border-amber-800',
        iconBg: 'bg-amber-100 dark:bg-amber-900/40 text-amber-600 dark:text-amber-400',
    },
    {
        icon: (
            <svg className="h-7 w-7" fill="none" stroke="currentColor" strokeWidth={1.5} viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12z" />
            </svg>
        ),
        labelKey: 'use_cases.healthcare_label',
        titleKey: 'use_cases.healthcare_title',
        descKey: 'use_cases.healthcare_desc',
        bulletKeys: ['use_cases.healthcare_b1', 'use_cases.healthcare_b2', 'use_cases.healthcare_b3', 'use_cases.healthcare_b4'],
        color: 'from-rose-500/20 to-rose-500/5',
        border: 'border-rose-200 dark:border-rose-800',
        iconBg: 'bg-rose-100 dark:bg-rose-900/40 text-rose-600 dark:text-rose-400',
    },
    {
        icon: (
            <svg className="h-7 w-7" fill="none" stroke="currentColor" strokeWidth={1.5} viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" d="M13.5 21v-7.5a.75.75 0 01.75-.75h3a.75.75 0 01.75.75V21m-4.5 0H2.36m11.14 0H18m0 0h3.64m-1.39 0V9.349m-16.5 11.65V9.35m0 0a3.001 3.001 0 003.75-.615A2.993 2.993 0 009.75 9.75c.896 0 1.7-.393 2.25-1.016a2.993 2.993 0 002.25 1.016c.896 0 1.7-.393 2.25-1.016a3.001 3.001 0 003.75.614m-16.5 0a3.004 3.004 0 01-.621-4.72L4.318 3.44A1.5 1.5 0 015.378 3h13.243a1.5 1.5 0 011.06.44l1.19 1.189a3 3 0 01-.621 4.72m-13.5 8.65h3.75a.75.75 0 00.75-.75V13.5a.75.75 0 00-.75-.75H6.75a.75.75 0 00-.75.75v3.75c0 .415.336.75.75.75z" />
            </svg>
        ),
        labelKey: 'use_cases.retail_label',
        titleKey: 'use_cases.retail_title',
        descKey: 'use_cases.retail_desc',
        bulletKeys: ['use_cases.retail_b1', 'use_cases.retail_b2', 'use_cases.retail_b3', 'use_cases.retail_b4'],
        color: 'from-emerald-500/20 to-emerald-500/5',
        border: 'border-emerald-200 dark:border-emerald-800',
        iconBg: 'bg-emerald-100 dark:bg-emerald-900/40 text-emerald-600 dark:text-emerald-400',
    },
    {
        icon: (
            <svg className="h-7 w-7" fill="none" stroke="currentColor" strokeWidth={1.5} viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" d="M3.75 3v11.25A2.25 2.25 0 006 16.5h2.25M3.75 3h-1.5m1.5 0h16.5m0 0h1.5m-1.5 0v11.25A2.25 2.25 0 0118 16.5h-2.25m-7.5 0h7.5m-7.5 0l-1 3m8.5-3l1 3m0 0l.5 1.5m-.5-1.5h-9.5m0 0l-.5 1.5m.75-9l3-3 2.148 2.148A12.061 12.061 0 0116.5 7.605" />
            </svg>
        ),
        labelKey: 'use_cases.finance_label',
        titleKey: 'use_cases.finance_title',
        descKey: 'use_cases.finance_desc',
        bulletKeys: ['use_cases.finance_b1', 'use_cases.finance_b2', 'use_cases.finance_b3', 'use_cases.finance_b4'],
        color: 'from-cyan-500/20 to-cyan-500/5',
        border: 'border-cyan-200 dark:border-cyan-800',
        iconBg: 'bg-cyan-100 dark:bg-cyan-900/40 text-cyan-600 dark:text-cyan-400',
    },
];

export default function UseCases({ landing = {}, canRegister }) {
    const { t } = useTranslation();
    const [active, setActive] = useState(null);

    const stats = [
        { value: '98%', label: t('use_cases.stat_open_rate') },
        { value: '5×', label: t('use_cases.stat_more_replies') },
        { value: '3 min', label: t('use_cases.stat_response_time') },
        { value: '10K+', label: t('use_cases.stat_businesses') },
    ];

    const steps = [
        { n: '1', title: t('use_cases.step1_title'), desc: t('use_cases.step1_desc') },
        { n: '2', title: t('use_cases.step2_title'), desc: t('use_cases.step2_desc') },
        { n: '3', title: t('use_cases.step3_title'), desc: t('use_cases.step3_desc') },
    ];

    return (
        <LandingLayout>
            <Head>
                <title>{t('use_cases.head_title')}</title>
                <meta name="description" content={t('use_cases.meta_desc')} />
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
                    <Badge text={t('nav.use_cases')} />
                    <h1 className="mt-4 text-4xl sm:text-5xl font-bold text-white tracking-tight">{t('use_cases.hero_title')}</h1>
                    <p className="mt-4 text-lg text-neutral-300 max-w-2xl mx-auto">
                        {t('use_cases.hero_subtitle')}
                    </p>
                    {canRegister && (
                        <Link
                            href={route('register')}
                            className="mt-8 inline-flex items-center gap-2 rounded-xl px-7 py-3.5 text-base font-bold text-white shadow-lg transition-all duration-200 hover:opacity-90"
                            style={{ background: '#8B5CF6' }}
                        >
                            {t('use_cases.start_free_trial')}
                        </Link>
                    )}
                </div>
            </section>

            {/* ── Use case cards ── */}
            <section className="py-20">
                <div className="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div className="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                        {USE_CASES.map((uc, idx) => (
                            <div
                                key={idx}
                                className={`rounded-2xl border bg-gradient-to-br p-6 cursor-pointer transition-all duration-300 ${uc.border} ${uc.color} ${active === idx ? 'ring-2 ring-[#8B5CF6]/50 shadow-lg' : 'hover:shadow-md'}`}
                                onClick={() => setActive(active === idx ? null : idx)}
                            >
                                <div className={`inline-flex h-12 w-12 items-center justify-center rounded-xl mb-4 ${uc.iconBg}`}>
                                    {uc.icon}
                                </div>
                                <span className="text-xs font-semibold text-neutral-500 dark:text-neutral-400 uppercase tracking-wider">{t(uc.labelKey)}</span>
                                <h3 className="mt-1 text-lg font-bold text-neutral-900 dark:text-white">{t(uc.titleKey)}</h3>
                                <p className="mt-2 text-sm text-neutral-600 dark:text-neutral-400 leading-relaxed">{t(uc.descKey)}</p>

                                {active === idx && (
                                    <ul className="mt-4 space-y-2 border-t border-neutral-200 dark:border-neutral-700 pt-4">
                                        {uc.bulletKeys.map((b, bi) => (
                                            <li key={bi} className="flex items-center gap-2 text-sm text-neutral-700 dark:text-neutral-300">
                                                <svg className="h-4 w-4 flex-shrink-0" style={{ color: '#8B5CF6' }} fill="none" stroke="currentColor" strokeWidth={2.5} viewBox="0 0 24 24">
                                                    <path strokeLinecap="round" strokeLinejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                                </svg>
                                                {t(b)}
                                            </li>
                                        ))}
                                    </ul>
                                )}

                                <div className={`mt-4 flex items-center gap-1 text-xs font-semibold transition-colors ${active === idx ? 'text-[#8B5CF6]' : 'text-neutral-400'}`}>
                                    {active === idx ? t('use_cases.show_less') : t('use_cases.see_details')}
                                    <svg className={`h-3.5 w-3.5 transition-transform ${active === idx ? 'rotate-180' : ''}`} fill="none" stroke="currentColor" strokeWidth={2.5} viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" d="M19 9l-7 7-7-7" />
                                    </svg>
                                </div>
                            </div>
                        ))}
                    </div>
                </div>
            </section>

            {/* ── Stats strip ── */}
            <section className="py-16 border-y border-neutral-200 dark:border-neutral-800 bg-neutral-50 dark:bg-neutral-900/40">
                <div className="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 grid grid-cols-2 sm:grid-cols-4 gap-8 text-center">
                    {stats.map((stat, idx) => (
                        <div key={idx}>
                            <p className="text-3xl font-bold" style={{ color: '#8B5CF6' }}>{stat.value}</p>
                            <p className="mt-1 text-sm text-neutral-500 dark:text-neutral-400">{stat.label}</p>
                        </div>
                    ))}
                </div>
            </section>

            {/* ── How it works summary ── */}
            <section className="py-20">
                <div className="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div className="text-center mb-12">
                        <Badge text={t('use_cases.how_it_works')} />
                        <h2 className="mt-4 text-3xl font-bold text-neutral-900 dark:text-white tracking-tight">{t('use_cases.up_and_running')}</h2>
                    </div>
                    <div className="grid gap-8 sm:grid-cols-3">
                        {steps.map((step, idx) => (
                            <div key={idx} className="text-center">
                                <div
                                    className="mx-auto mb-4 h-12 w-12 rounded-full flex items-center justify-center font-bold text-lg text-white"
                                    style={{ background: '#8B5CF6', boxShadow: '0 4px 16px rgba(139,92,246,0.35)' }}
                                >
                                    {step.n}
                                </div>
                                <h3 className="text-base font-semibold text-neutral-900 dark:text-white mb-1">{step.title}</h3>
                                <p className="text-sm text-neutral-500 dark:text-neutral-400">{step.desc}</p>
                            </div>
                        ))}
                    </div>
                </div>
            </section>

            {/* ── CTA ── */}
            <section
                className="py-20"
                style={{ background: 'radial-gradient(ellipse 60% 80% at 50% 50%, rgba(139,92,246,0.12) 0%, transparent 70%), #160B2D' }}
            >
                <div className="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
                    <h2 className="text-3xl font-bold text-white tracking-tight">{t('use_cases.cta_title')}</h2>
                    <p className="mt-3 text-neutral-400">{t('use_cases.cta_subtitle')}</p>
                    {canRegister && (
                        <div className="mt-8 flex flex-wrap items-center justify-center gap-4">
                            <Link
                                href={route('register')}
                                className="inline-flex items-center gap-2 rounded-xl px-7 py-3.5 text-base font-bold text-white shadow-lg transition-all duration-200 hover:opacity-90"
                                style={{ background: '#8B5CF6' }}
                            >
                                {t('use_cases.start_free_trial')}
                                <svg className="h-4 w-4" fill="none" stroke="currentColor" strokeWidth={2} viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" /></svg>
                            </Link>
                            <Link href="/pricing" className="inline-flex items-center gap-2 rounded-xl border border-white/20 bg-white/8 text-white px-7 py-3.5 text-base font-semibold hover:bg-white/15 backdrop-blur-sm transition-all duration-200">
                                {t('use_cases.view_pricing')}
                            </Link>
                        </div>
                    )}
                </div>
            </section>
        </LandingLayout>
    );
}
