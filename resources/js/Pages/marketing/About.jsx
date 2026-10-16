import { Link, usePage } from '@inertiajs/react';
import LandingLayout from '@/Layouts/LandingLayout';
import SeoHead from '@/Components/SeoHead';
import { FeatureIcon } from '@/Components/LandingIcons';
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

export default function About({ canRegister, landing = {} }) {
    const { t } = useTranslation();
    const appName = usePage().props.branding?.app_name || 'CREASY CRM';
    const s = (key, def = '') => landing[`landing.${key}`] ?? def;

    const values = [1, 2, 3, 4].map((i) => ({
        icon: s(`about_value_${i}_icon`, 'star'),
        title: s(`about_value_${i}_title`),
        desc: s(`about_value_${i}_desc`),
    })).filter((v) => v.title);

    const storyParagraphs = (s('about_story_body') || '').split('\n').map((p) => p.trim()).filter(Boolean);

    return (
        <LandingLayout>
            <SeoHead
                title={`${s('about_title') || t('nav.about', { defaultValue: 'About' })} — ${appName}`}
                description={s('about_subtitle')}
            />

            {/* Hero */}
            <section
                className="relative overflow-hidden"
                style={{ background: 'radial-gradient(ellipse 70% 65% at 50% 0%, rgba(139,92,246,0.18) 0%, transparent 60%), #160B2D' }}
            >
                <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 pt-24 pb-20 text-center">
                    <div className="flex justify-center mb-6"><Badge text={s('about_badge')} /></div>
                    <h1 className="text-4xl sm:text-5xl font-bold tracking-tight text-white max-w-3xl mx-auto leading-tight">
                        {s('about_title')}
                    </h1>
                    {s('about_subtitle') && (
                        <p className="mt-6 text-lg text-neutral-300 max-w-2xl mx-auto leading-relaxed">{s('about_subtitle')}</p>
                    )}
                </div>
            </section>

            {/* Story */}
            {storyParagraphs.length > 0 && (
                <section className="py-24">
                    <div className="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
                        <h2 className="text-3xl font-bold text-neutral-900 dark:text-white tracking-tight mb-6">
                            {s('about_story_title')}
                        </h2>
                        <div className="space-y-4">
                            {storyParagraphs.map((p, idx) => (
                                <p key={idx} className="text-lg text-neutral-600 dark:text-neutral-400 leading-relaxed">{p}</p>
                            ))}
                        </div>
                    </div>
                </section>
            )}

            {/* Values */}
            {values.length > 0 && (
                <section className="py-24 bg-neutral-50 dark:bg-neutral-900/30">
                    <div className="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
                        <div className="text-center mb-16">
                            <h2 className="text-3xl sm:text-4xl font-bold text-neutral-900 dark:text-white tracking-tight">
                                {t('about_page.values_title', { defaultValue: 'What we stand for' })}
                            </h2>
                        </div>
                        <div className="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                            {values.map((v, idx) => (
                                <div key={idx} className="rounded-2xl border border-neutral-200 dark:border-neutral-800 bg-white dark:bg-neutral-900 p-6">
                                    <div className="mb-4 inline-flex h-11 w-11 items-center justify-center rounded-xl" style={{ background: 'rgba(139,92,246,0.12)' }}>
                                        <FeatureIcon name={v.icon} className="h-5 w-5" style={{ color: '#8B5CF6' }} />
                                    </div>
                                    <h3 className="text-base font-semibold text-neutral-900 dark:text-white mb-1">{v.title}</h3>
                                    <p className="text-sm text-neutral-600 dark:text-neutral-400 leading-relaxed">{v.desc}</p>
                                </div>
                            ))}
                        </div>
                    </div>
                </section>
            )}

            {/* CTA */}
            <section className="py-20">
                <div className="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div
                        className="relative overflow-hidden rounded-3xl px-8 py-16 text-center"
                        style={{ background: 'radial-gradient(ellipse 60% 80% at 50% 50%, rgba(139,92,246,0.15) 0%, transparent 70%), #160B2D', border: '1px solid rgba(139,92,246,0.2)' }}
                    >
                        <h2 className="relative text-3xl sm:text-4xl font-bold text-white tracking-tight max-w-2xl mx-auto">
                            {s('about_cta_title')}
                        </h2>
                        {s('about_cta_subtitle') && (
                            <p className="relative mt-4 text-lg text-neutral-400 max-w-xl mx-auto">{s('about_cta_subtitle')}</p>
                        )}
                        <div className="relative mt-10 flex flex-wrap items-center justify-center gap-4">
                            {canRegister && (
                                <Link href={route('register')} className="inline-flex items-center gap-2 rounded-xl px-7 py-3.5 text-base font-bold text-white shadow-lg transition-all duration-200 hover:opacity-90" style={{ background: '#8B5CF6' }}>
                                    {t('welcome.get_started_free', { defaultValue: 'Get Started Free' })}
                                </Link>
                            )}
                            <Link href="/contact" className="inline-flex items-center gap-2 rounded-xl border border-white/20 bg-white/8 text-white px-7 py-3.5 text-base font-semibold hover:bg-white/15 backdrop-blur-sm transition-all duration-200">
                                {t('nav.contact', { defaultValue: 'Contact' })}
                            </Link>
                        </div>
                    </div>
                </div>
            </section>
        </LandingLayout>
    );
}
