import { Link, usePage } from '@inertiajs/react';
import LandingLayout from '@/Layouts/LandingLayout';
import SeoHead from '@/Components/SeoHead';
import { BrandMark } from '@/Components/BrandIcons';
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

export default function Integrations({ canRegister, landing = {} }) {
    const { t } = useTranslation();
    const appName = usePage().props.branding?.app_name || 'CREASY CRM';
    const s = (key, def = '') => landing[`landing.${key}`] ?? def;

    const categories = [1, 2, 3, 4, 5, 6, 7].map((i) => ({
        title: s(`intcat_${i}_title`),
        items: (s(`intcat_${i}_items`) || '').split('\n').map((x) => x.trim()).filter(Boolean),
    })).filter((c) => c.title && c.items.length);

    return (
        <LandingLayout>
            <SeoHead
                title={`${s('integrations_page_title') || t('nav.integrations', { defaultValue: 'Integrations' })} — ${appName}`}
                description={s('integrations_page_subtitle')}
            />

            {/* Hero */}
            <section
                className="relative overflow-hidden"
                style={{ background: 'radial-gradient(ellipse 70% 65% at 50% 0%, rgba(139,92,246,0.18) 0%, transparent 60%), #160B2D' }}
            >
                <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 pt-24 pb-20 text-center">
                    <div className="flex justify-center mb-6"><Badge text={s('integrations_page_badge')} /></div>
                    <h1 className="text-4xl sm:text-5xl font-bold tracking-tight text-white max-w-3xl mx-auto leading-tight">
                        {s('integrations_page_title')}
                    </h1>
                    {s('integrations_page_subtitle') && (
                        <p className="mt-6 text-lg text-neutral-300 max-w-2xl mx-auto leading-relaxed">{s('integrations_page_subtitle')}</p>
                    )}
                </div>
            </section>

            {/* Categories */}
            <section className="py-24">
                <div className="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 space-y-12">
                    {categories.map((cat, ci) => (
                        <div key={ci}>
                            <h2 className="text-xl font-bold text-neutral-900 dark:text-white tracking-tight mb-5">{cat.title}</h2>
                            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                                {cat.items.map((item, ii) => (
                                    <div
                                        key={ii}
                                        className="flex items-center gap-3 rounded-2xl border border-neutral-200 dark:border-neutral-800 bg-white dark:bg-neutral-900 p-4 hover:border-[#8B5CF6]/40 hover:shadow-md transition-all duration-200"
                                    >
                                        <BrandMark name={item} tileClassName="h-10 w-10 rounded-xl flex-shrink-0" glyphClassName="h-6 w-6" />
                                        <span className="text-sm font-medium text-neutral-800 dark:text-neutral-200">{item}</span>
                                    </div>
                                ))}
                            </div>
                        </div>
                    ))}
                </div>
            </section>

            {/* CTA */}
            <section className="py-20 bg-neutral-50 dark:bg-neutral-900/30 border-t border-neutral-200 dark:border-neutral-800">
                <div className="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
                    <h2 className="text-3xl font-bold text-neutral-900 dark:text-white tracking-tight">
                        {t('integrations_page.cta_title', { defaultValue: 'Need a custom integration?' })}
                    </h2>
                    <p className="mt-4 text-lg text-neutral-600 dark:text-neutral-400">
                        {t('integrations_page.cta_subtitle', { defaultValue: 'Use our REST API and webhooks to connect CREASY CRM to anything, or talk to our team.' })}
                    </p>
                    <div className="mt-8 flex flex-wrap items-center justify-center gap-4">
                        {canRegister && (
                            <Link href={route('register')} className="inline-flex items-center gap-2 rounded-xl px-7 py-3.5 text-base font-bold text-white shadow-lg transition-all duration-200 hover:opacity-90" style={{ background: '#8B5CF6' }}>
                                {t('welcome.get_started_free', { defaultValue: 'Get Started Free' })}
                            </Link>
                        )}
                        <Link href="/contact" className="inline-flex items-center gap-2 rounded-xl border border-neutral-300 dark:border-neutral-700 px-7 py-3.5 text-base font-semibold text-neutral-700 dark:text-neutral-300 hover:border-[#8B5CF6]/50 transition-all duration-200">
                            {t('nav.contact', { defaultValue: 'Contact Sales' })}
                        </Link>
                    </div>
                </div>
            </section>
        </LandingLayout>
    );
}
