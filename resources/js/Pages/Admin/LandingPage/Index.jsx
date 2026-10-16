import { useState } from 'react';
import AdminLayout from '@/Layouts/AdminLayout';
import { Button, Card } from '@/Components/ui';
import { Head, router, usePage } from '@inertiajs/react';
import { Globe, ToggleLeft, ToggleRight } from 'lucide-react';
import { useTranslation } from 'react-i18next';

// ─── Shared helpers ────────────────────────────────────────────────────────────

function SectionToggle({ label, enabled, onChange, t }) {
    return (
        <div className="flex items-center justify-between pb-4 border-b border-neutral-100 dark:border-neutral-800 mb-5">
            <div>
                <h3 className="font-semibold text-neutral-900 dark:text-neutral-100">{label}</h3>
                <p className="text-xs text-neutral-500 dark:text-neutral-400 mt-0.5">
                    {enabled ? t('landing_page_admin.visible') : t('landing_page_admin.hidden')}
                </p>
            </div>
            <button
                type="button"
                onClick={() => onChange(!enabled)}
                className={`flex items-center gap-2 px-3 py-1.5 rounded-full text-xs font-medium transition-colors ${
                    enabled
                        ? 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300'
                        : 'bg-neutral-100 text-neutral-500 dark:bg-neutral-800 dark:text-neutral-400'
                }`}
            >
                {enabled ? <ToggleRight className="h-4 w-4" /> : <ToggleLeft className="h-4 w-4" />}
                {enabled ? t('landing_page_admin.enabled') : t('landing_page_admin.disabled')}
            </button>
        </div>
    );
}

function MasterToggleCard({ enabled, onChange, t }) {
    return (
        <Card>
            <Card.Body>
                <div className="flex items-center justify-between gap-4">
                    <div className="flex items-start gap-3">
                        <span className={`mt-0.5 p-2 rounded-soft ${enabled ? 'bg-green-100 text-green-600 dark:bg-green-900/40 dark:text-green-300' : 'bg-neutral-100 text-neutral-400 dark:bg-neutral-800 dark:text-neutral-500'}`}>
                            <Globe className="h-5 w-5" />
                        </span>
                        <div>
                            <h3 className="font-semibold text-neutral-900 dark:text-neutral-100">{t('landing_page_admin.master_toggle_title')}</h3>
                            <p className="text-xs text-neutral-500 dark:text-neutral-400 mt-0.5">
                                {enabled ? t('landing_page_admin.master_toggle_on') : t('landing_page_admin.master_toggle_off')}
                            </p>
                        </div>
                    </div>
                    <button
                        type="button"
                        onClick={() => onChange(!enabled)}
                        className={`flex items-center gap-2 px-3 py-1.5 rounded-full text-xs font-medium transition-colors shrink-0 ${
                            enabled
                                ? 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300'
                                : 'bg-neutral-100 text-neutral-500 dark:bg-neutral-800 dark:text-neutral-400'
                        }`}
                    >
                        {enabled ? <ToggleRight className="h-4 w-4" /> : <ToggleLeft className="h-4 w-4" />}
                        {enabled ? t('landing_page_admin.enabled') : t('landing_page_admin.disabled')}
                    </button>
                </div>
            </Card.Body>
        </Card>
    );
}

function Field({ label, hint, children }) {
    return (
        <div className="space-y-1">
            <label className="block text-sm font-medium text-neutral-700 dark:text-neutral-300">{label}</label>
            {hint && <p className="text-xs text-neutral-400 dark:text-neutral-500">{hint}</p>}
            {children}
        </div>
    );
}

function Input({ value, onChange, placeholder, multiline = false, rows = 3 }) {
    const cls = "w-full rounded-soft border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500/20";
    if (multiline) {
        return <textarea value={value || ''} onChange={onChange} placeholder={placeholder} rows={rows} className={cls} />;
    }
    return <input type="text" value={value || ''} onChange={onChange} placeholder={placeholder} className={cls} />;
}

// ─── Navbar Tab ───────────────────────────────────────────────────────────────

function LinkTypeField({ label, typeKey, urlKey, data, setData, t }) {
    const type = data[typeKey] ?? 'dynamic';
    const url  = data[urlKey] ?? '';
    return (
        <div className="space-y-3 p-4 rounded-soft border border-neutral-200 dark:border-neutral-700">
            <p className="text-sm font-medium text-neutral-700 dark:text-neutral-300">{label}</p>
            <div className="flex gap-4">
                <label className="flex items-center gap-2 cursor-pointer">
                    <input
                        type="radio"
                        name={typeKey}
                        value="dynamic"
                        checked={type === 'dynamic'}
                        onChange={() => setData(typeKey, 'dynamic')}
                        className="accent-brand-600"
                    />
                    <span className="text-sm text-neutral-700 dark:text-neutral-300">{t('landing_page_admin.dynamic_label')} <span className="text-xs text-neutral-400">{t('landing_page_admin.dynamic_hint')}</span></span>
                </label>
                <label className="flex items-center gap-2 cursor-pointer">
                    <input
                        type="radio"
                        name={typeKey}
                        value="static"
                        checked={type === 'static'}
                        onChange={() => setData(typeKey, 'static')}
                        className="accent-brand-600"
                    />
                    <span className="text-sm text-neutral-700 dark:text-neutral-300">{t('landing_page_admin.static_label')} <span className="text-xs text-neutral-400">{t('landing_page_admin.static_hint')}</span></span>
                </label>
            </div>
            {type === 'static' && (
                <Input
                    value={url}
                    onChange={(e) => setData(urlKey, e.target.value)}
                    placeholder="https://example.com/login"
                />
            )}
        </div>
    );
}

function NavbarTab({ data, setData, t }) {
    return (
        <Card>
            <Card.Body className="space-y-6">
                <div>
                    <h3 className="font-semibold text-neutral-900 dark:text-neutral-100 pb-3 border-b border-neutral-100 dark:border-neutral-800 mb-5">{t('landing_page_admin.navbar_buttons')}</h3>
                    <div className="space-y-5">
                        <Field label={t('landing_page_admin.signin_label')}>
                            <Input
                                value={data['landing.signin_label'] ?? 'Sign In'}
                                onChange={(e) => setData('landing.signin_label', e.target.value)}
                                placeholder="Sign In"
                            />
                        </Field>
                        <LinkTypeField
                            label={t('landing_page_admin.signin_link')}
                            typeKey="landing.signin_link_type"
                            urlKey="landing.signin_link_url"
                            data={data}
                            setData={setData}
                            t={t}
                        />
                        <Field label={t('landing_page_admin.getstarted_label')}>
                            <Input
                                value={data['landing.getstarted_label'] ?? 'Get Started'}
                                onChange={(e) => setData('landing.getstarted_label', e.target.value)}
                                placeholder="Get Started"
                            />
                        </Field>
                        <LinkTypeField
                            label={t('landing_page_admin.getstarted_link')}
                            typeKey="landing.getstarted_link_type"
                            urlKey="landing.getstarted_link_url"
                            data={data}
                            setData={setData}
                            t={t}
                        />
                    </div>
                </div>
            </Card.Body>
        </Card>
    );
}

// ─── Hero Tab ─────────────────────────────────────────────────────────────────

function HeroTab({ data, setData, t }) {
    const s = (key) => data[`landing.${key}`] ?? '';
    const set = (key, val) => setData(`landing.${key}`, val);
    const enabled = s('hero_enabled') === '1';

    return (
        <Card>
            <Card.Body className="space-y-5">
                <SectionToggle
                    label={t('landing_page_admin.hero_section')}
                    enabled={enabled}
                    onChange={(v) => set('hero_enabled', v ? '1' : '0')}
                    t={t}
                />
                <div className="grid grid-cols-1 gap-5">
                    <Field label={t('landing_page_admin.badge_text')} hint={t('landing_page_admin.badge_text_hint')}>
                        <Input value={s('hero_badge')} onChange={(e) => set('hero_badge', e.target.value)} placeholder="Now with AI-powered automation" />
                    </Field>
                    <Field label={t('landing_page_admin.headline')}>
                        <Input value={s('hero_title')} onChange={(e) => set('hero_title', e.target.value)} placeholder="The All-in-One Platform for Modern Teams" />
                    </Field>
                    <Field label={t('landing_page_admin.subtitle')}>
                        <Input value={s('hero_subtitle')} onChange={(e) => set('hero_subtitle', e.target.value)} placeholder="Automate workflows..." multiline rows={2} />
                    </Field>
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <Field label={t('landing_page_admin.primary_cta')}>
                            <Input value={s('hero_cta_primary')} onChange={(e) => set('hero_cta_primary', e.target.value)} placeholder="Get Started Free" />
                        </Field>
                        <Field label={t('landing_page_admin.secondary_cta')}>
                            <Input value={s('hero_cta_secondary')} onChange={(e) => set('hero_cta_secondary', e.target.value)} placeholder="View Pricing" />
                        </Field>
                    </div>
                    <div className="pt-1">
                        <p className="text-xs font-semibold text-neutral-500 dark:text-neutral-400 uppercase tracking-wide mb-3">{t('landing_page_admin.trust_badges_label')}</p>
                        <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            {[1, 2, 3].map((i) => (
                                <Field key={i} label={t('landing_page_admin.badge_n', { n: i })}>
                                    <Input value={s(`hero_trust_${i}`)} onChange={(e) => set(`hero_trust_${i}`, e.target.value)} placeholder={['WhatsApp QR Login', 'Official WhatsApp Cloud API', 'Secure & Scalable'][i - 1]} />
                                </Field>
                            ))}
                        </div>
                    </div>
                </div>
            </Card.Body>
        </Card>
    );
}

// ─── Trusted By Tab ───────────────────────────────────────────────────────────

function TrustedByTab({ data, setData, t }) {
    const s = (key) => data[`landing.${key}`] ?? '';
    const set = (key, val) => setData(`landing.${key}`, val);
    const enabled = s('stats_enabled') === '1';

    return (
        <Card>
            <Card.Body className="space-y-5">
                <SectionToggle
                    label={t('landing_page_admin.trustedby_section')}
                    enabled={enabled}
                    onChange={(v) => set('stats_enabled', v ? '1' : '0')}
                    t={t}
                />
                <Field label={t('landing_page_admin.heading')} hint={t('landing_page_admin.heading_hint')}>
                    <Input value={s('stats_heading')} onChange={(e) => set('stats_heading', e.target.value)} placeholder="Trusted by thousands of businesses across the world" />
                </Field>
                <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    {[1, 2, 3, 4, 5, 6].map((i) => (
                        <Field key={i} label={t('landing_page_admin.brand_n', { n: i })}>
                            <Input value={s(`stats_${i}_label`)} onChange={(e) => set(`stats_${i}_label`, e.target.value)} placeholder={['Meta', 'Amazon', 'Spotify', 'GitHub', 'Jotform', 'PaySigns'][i - 1]} />
                        </Field>
                    ))}
                </div>
            </Card.Body>
        </Card>
    );
}

// ─── Problem / Solution Tab ───────────────────────────────────────────────────

function ProblemSolutionTab({ data, setData, t }) {
    const s = (key) => data[`landing.${key}`] ?? '';
    const set = (key, val) => setData(`landing.${key}`, val);
    const enabled = s('problems_enabled') === '1';

    return (
        <Card>
            <Card.Body className="space-y-5">
                <SectionToggle
                    label={t('landing_page_admin.problems_section')}
                    enabled={enabled}
                    onChange={(v) => set('problems_enabled', v ? '1' : '0')}
                    t={t}
                />
                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                    {/* Problem column */}
                    <div className="space-y-4">
                        <Field label={t('landing_page_admin.problem_col_title')}>
                            <Input value={s('problems_title')} onChange={(e) => set('problems_title', e.target.value)} placeholder="The Problem" />
                        </Field>
                        {[1, 2, 3, 4].map((i) => (
                            <Field key={i} label={t('landing_page_admin.problem_n', { n: i })}>
                                <Input value={s(`problem_${i}`)} onChange={(e) => set(`problem_${i}`, e.target.value)} placeholder="Without us, this pain point exists..." />
                            </Field>
                        ))}
                    </div>
                    {/* Solution column */}
                    <div className="space-y-4">
                        <Field label={t('landing_page_admin.solution_col_title')}>
                            <Input value={s('solution_title')} onChange={(e) => set('solution_title', e.target.value)} placeholder="The Solution" />
                        </Field>
                        <Field label={t('landing_page_admin.solution_desc_label')} hint={t('landing_page_admin.solution_desc_hint')}>
                            <Input value={s('solution_desc')} onChange={(e) => set('solution_desc', e.target.value)} placeholder="Our platform automates outreach, engagement, and conversations with AI." multiline rows={2} />
                        </Field>
                        {[1, 2, 3, 4].map((i) => (
                            <Field key={i} label={t('landing_page_admin.solution_n', { n: i })}>
                                <Input value={s(`solution_${i}`)} onChange={(e) => set(`solution_${i}`, e.target.value)} placeholder="AI-powered automation" />
                            </Field>
                        ))}
                    </div>
                </div>
            </Card.Body>
        </Card>
    );
}

// ─── Features Tab ─────────────────────────────────────────────────────────────

const ICON_OPTIONS = ['cpu', 'message-square', 'bar-chart-2', 'users', 'share-2', 'shield-check', 'zap', 'star', 'layout', 'arrow-right', 'globe', 'trending-up', 'check-circle', 'server'];

function FeaturesTab({ data, setData, t }) {
    const s = (key) => data[`landing.${key}`] ?? '';
    const set = (key, val) => setData(`landing.${key}`, val);
    const enabled = s('features_enabled') === '1';

    return (
        <Card>
            <Card.Body className="space-y-5">
                <SectionToggle
                    label={t('landing_page_admin.features_section')}
                    enabled={enabled}
                    onChange={(v) => set('features_enabled', v ? '1' : '0')}
                    t={t}
                />
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <Field label={t('landing_page_admin.section_badge')}>
                        <Input value={s('features_badge')} onChange={(e) => set('features_badge', e.target.value)} placeholder="Features" />
                    </Field>
                    <Field label={t('landing_page_admin.section_title')}>
                        <Input value={s('features_title')} onChange={(e) => set('features_title', e.target.value)} placeholder="Everything You Need to Scale" />
                    </Field>
                    <Field label={t('landing_page_admin.section_subtitle')} hint={t('landing_page_admin.section_subtitle_hint')}>
                        <Input value={s('features_subtitle')} onChange={(e) => set('features_subtitle', e.target.value)} placeholder="Powerful tools built for growth" />
                    </Field>
                </div>
                <div className="grid grid-cols-1 gap-4 pt-2">
                    {[1, 2, 3, 4, 5, 6, 7, 8, 9].map((i) => (
                        <div key={i} className="p-4 rounded-soft border border-neutral-200 dark:border-neutral-700 space-y-3">
                            <p className="text-xs font-semibold text-neutral-500 dark:text-neutral-400 uppercase tracking-wide">{t('landing_page_admin.feature_n', { n: i })}</p>
                            <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                <Field label={t('landing_page_admin.icon')}>
                                    <select
                                        value={s(`feature_${i}_icon`)}
                                        onChange={(e) => set(`feature_${i}_icon`, e.target.value)}
                                        className="w-full rounded-soft border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500/20"
                                    >
                                        {ICON_OPTIONS.map((ic) => (
                                            <option key={ic} value={ic}>{ic}</option>
                                        ))}
                                    </select>
                                </Field>
                                <Field label={t('landing_page_admin.title')}>
                                    <Input value={s(`feature_${i}_title`)} onChange={(e) => set(`feature_${i}_title`, e.target.value)} placeholder="Feature title" />
                                </Field>
                                <Field label={t('landing_page_admin.description')}>
                                    <Input value={s(`feature_${i}_desc`)} onChange={(e) => set(`feature_${i}_desc`, e.target.value)} placeholder="Feature description" />
                                </Field>
                            </div>
                        </div>
                    ))}
                </div>
            </Card.Body>
        </Card>
    );
}

// ─── How It Works Tab ─────────────────────────────────────────────────────────

function HowItWorksTab({ data, setData, t }) {
    const s = (key) => data[`landing.${key}`] ?? '';
    const set = (key, val) => setData(`landing.${key}`, val);
    const enabled = s('howitworks_enabled') === '1';

    return (
        <Card>
            <Card.Body className="space-y-5">
                <SectionToggle
                    label={t('landing_page_admin.howitworks_section')}
                    enabled={enabled}
                    onChange={(v) => set('howitworks_enabled', v ? '1' : '0')}
                    t={t}
                />
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <Field label={t('landing_page_admin.section_badge')}>
                        <Input value={s('howitworks_badge')} onChange={(e) => set('howitworks_badge', e.target.value)} placeholder="Simple Process" />
                    </Field>
                    <Field label={t('landing_page_admin.section_title')}>
                        <Input value={s('howitworks_title')} onChange={(e) => set('howitworks_title', e.target.value)} placeholder="Get Started in Minutes" />
                    </Field>
                    <Field label={t('landing_page_admin.section_subtitle')}>
                        <Input value={s('howitworks_subtitle')} onChange={(e) => set('howitworks_subtitle', e.target.value)} placeholder="Three simple steps..." />
                    </Field>
                </div>
                {[1, 2, 3].map((i) => (
                    <div key={i} className="p-4 rounded-soft border border-neutral-200 dark:border-neutral-700 space-y-3">
                        <p className="text-xs font-semibold text-neutral-500 dark:text-neutral-400 uppercase tracking-wide">{t('landing_page_admin.step_n', { n: i })}</p>
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <Field label={t('landing_page_admin.title')}>
                                <Input value={s(`step_${i}_title`)} onChange={(e) => set(`step_${i}_title`, e.target.value)} placeholder={`Step ${i} title`} />
                            </Field>
                            <Field label={t('landing_page_admin.description')}>
                                <Input value={s(`step_${i}_desc`)} onChange={(e) => set(`step_${i}_desc`, e.target.value)} placeholder="Step description" />
                            </Field>
                        </div>
                    </div>
                ))}
            </Card.Body>
        </Card>
    );
}

// ─── Why Tab ──────────────────────────────────────────────────────────────────

function WhyTab({ data, setData, t }) {
    const s = (key) => data[`landing.${key}`] ?? '';
    const set = (key, val) => setData(`landing.${key}`, val);
    const enabled = s('why_enabled') === '1';

    return (
        <Card>
            <Card.Body className="space-y-5">
                <SectionToggle
                    label={t('landing_page_admin.why_section')}
                    enabled={enabled}
                    onChange={(v) => set('why_enabled', v ? '1' : '0')}
                    t={t}
                />
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <Field label={t('landing_page_admin.section_badge')}>
                        <Input value={s('why_badge')} onChange={(e) => set('why_badge', e.target.value)} placeholder="Why Choose Us" />
                    </Field>
                    <Field label={t('landing_page_admin.section_title')}>
                        <Input value={s('why_title')} onChange={(e) => set('why_title', e.target.value)} placeholder="Why CREASY CRM" />
                    </Field>
                    <Field label={t('landing_page_admin.section_subtitle')}>
                        <Input value={s('why_subtitle')} onChange={(e) => set('why_subtitle', e.target.value)} placeholder="Built for scale, security, and results." />
                    </Field>
                </div>
                <div className="grid grid-cols-1 gap-4 pt-2">
                    {[1, 2, 3, 4, 5, 6].map((i) => (
                        <div key={i} className="p-4 rounded-soft border border-neutral-200 dark:border-neutral-700 space-y-3">
                            <p className="text-xs font-semibold text-neutral-500 dark:text-neutral-400 uppercase tracking-wide">{t('landing_page_admin.item_n', { n: i })}</p>
                            <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                <Field label={t('landing_page_admin.icon')}>
                                    <select
                                        value={s(`why_${i}_icon`)}
                                        onChange={(e) => set(`why_${i}_icon`, e.target.value)}
                                        className="w-full rounded-soft border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500/20"
                                    >
                                        {ICON_OPTIONS.map((ic) => (
                                            <option key={ic} value={ic}>{ic}</option>
                                        ))}
                                    </select>
                                </Field>
                                <Field label={t('landing_page_admin.title')}>
                                    <Input value={s(`why_${i}_title`)} onChange={(e) => set(`why_${i}_title`, e.target.value)} placeholder="Benefit title" />
                                </Field>
                                <Field label={t('landing_page_admin.description')}>
                                    <Input value={s(`why_${i}_desc`)} onChange={(e) => set(`why_${i}_desc`, e.target.value)} placeholder="Short description" />
                                </Field>
                            </div>
                        </div>
                    ))}
                </div>
            </Card.Body>
        </Card>
    );
}

// ─── Testimonials Tab ─────────────────────────────────────────────────────────

function TestimonialsTab({ data, setData, t }) {
    const s = (key) => data[`landing.${key}`] ?? '';
    const set = (key, val) => setData(`landing.${key}`, val);
    const enabled = s('testimonials_enabled') === '1';

    return (
        <Card>
            <Card.Body className="space-y-5">
                <SectionToggle
                    label={t('landing_page_admin.testimonials_section')}
                    enabled={enabled}
                    onChange={(v) => set('testimonials_enabled', v ? '1' : '0')}
                    t={t}
                />
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <Field label={t('landing_page_admin.section_badge')}>
                        <Input value={s('testimonials_badge')} onChange={(e) => set('testimonials_badge', e.target.value)} placeholder="Testimonials" />
                    </Field>
                    <Field label={t('landing_page_admin.section_title')}>
                        <Input value={s('testimonials_title')} onChange={(e) => set('testimonials_title', e.target.value)} placeholder="What Our Customers Say" />
                    </Field>
                    <Field label={t('landing_page_admin.section_subtitle')}>
                        <Input value={s('testimonials_subtitle')} onChange={(e) => set('testimonials_subtitle', e.target.value)} placeholder="Join thousands of satisfied businesses" />
                    </Field>
                </div>
                {[1, 2, 3, 4, 5, 6].map((i) => (
                    <div key={i} className="p-4 rounded-soft border border-neutral-200 dark:border-neutral-700 space-y-3">
                        <p className="text-xs font-semibold text-neutral-500 dark:text-neutral-400 uppercase tracking-wide">{t('landing_page_admin.testimonial_n', { n: i })}</p>
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <Field label={t('landing_page_admin.name')}>
                                <Input value={s(`testimonial_${i}_name`)} onChange={(e) => set(`testimonial_${i}_name`, e.target.value)} placeholder="Jane Smith" />
                            </Field>
                            <Field label={t('landing_page_admin.role_company')}>
                                <Input value={s(`testimonial_${i}_role`)} onChange={(e) => set(`testimonial_${i}_role`, e.target.value)} placeholder="CEO at Acme Inc." />
                            </Field>
                            <div className="sm:col-span-2">
                                <Field label={t('landing_page_admin.quote')}>
                                    <Input value={s(`testimonial_${i}_text`)} onChange={(e) => set(`testimonial_${i}_text`, e.target.value)} placeholder="This product is amazing..." multiline rows={2} />
                                </Field>
                            </div>
                        </div>
                    </div>
                ))}
            </Card.Body>
        </Card>
    );
}

// ─── FAQ Tab ──────────────────────────────────────────────────────────────────

function FaqTab({ data, setData, t }) {
    const s = (key) => data[`landing.${key}`] ?? '';
    const set = (key, val) => setData(`landing.${key}`, val);
    const enabled = s('faq_enabled') === '1';

    return (
        <Card>
            <Card.Body className="space-y-5">
                <SectionToggle
                    label={t('landing_page_admin.faq_section')}
                    enabled={enabled}
                    onChange={(v) => set('faq_enabled', v ? '1' : '0')}
                    t={t}
                />
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <Field label={t('landing_page_admin.section_badge')}>
                        <Input value={s('faq_badge')} onChange={(e) => set('faq_badge', e.target.value)} placeholder="FAQ" />
                    </Field>
                    <Field label={t('landing_page_admin.section_title')}>
                        <Input value={s('faq_title')} onChange={(e) => set('faq_title', e.target.value)} placeholder="Frequently Asked Questions" />
                    </Field>
                    <Field label={t('landing_page_admin.section_subtitle')}>
                        <Input value={s('faq_subtitle')} onChange={(e) => set('faq_subtitle', e.target.value)} placeholder="Everything you need to know" />
                    </Field>
                </div>
                {[1, 2, 3, 4, 5].map((i) => (
                    <div key={i} className="p-4 rounded-soft border border-neutral-200 dark:border-neutral-700 space-y-3">
                        <p className="text-xs font-semibold text-neutral-500 dark:text-neutral-400 uppercase tracking-wide">{t('landing_page_admin.faq_n', { n: i })}</p>
                        <Field label={t('landing_page_admin.question')}>
                            <Input value={s(`faq_${i}_q`)} onChange={(e) => set(`faq_${i}_q`, e.target.value)} placeholder="Your question here?" />
                        </Field>
                        <Field label={t('landing_page_admin.answer')}>
                            <Input value={s(`faq_${i}_a`)} onChange={(e) => set(`faq_${i}_a`, e.target.value)} placeholder="Your answer here..." multiline rows={2} />
                        </Field>
                    </div>
                ))}
            </Card.Body>
        </Card>
    );
}

// ─── CTA Tab ──────────────────────────────────────────────────────────────────

function CtaTab({ data, setData, t }) {
    const s = (key) => data[`landing.${key}`] ?? '';
    const set = (key, val) => setData(`landing.${key}`, val);
    const enabled = s('cta_enabled') === '1';

    return (
        <Card>
            <Card.Body className="space-y-5">
                <SectionToggle
                    label={t('landing_page_admin.cta_section')}
                    enabled={enabled}
                    onChange={(v) => set('cta_enabled', v ? '1' : '0')}
                    t={t}
                />
                <Field label={t('landing_page_admin.cta_title')}>
                    <Input value={s('cta_title')} onChange={(e) => set('cta_title', e.target.value)} placeholder="Ready to Transform Your Business?" />
                </Field>
                <Field label={t('landing_page_admin.cta_subtitle')}>
                    <Input value={s('cta_subtitle')} onChange={(e) => set('cta_subtitle', e.target.value)} placeholder="Join thousands of teams..." />
                </Field>
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <Field label={t('landing_page_admin.primary_button')}>
                        <Input value={s('cta_primary')} onChange={(e) => set('cta_primary', e.target.value)} placeholder="Start Free Trial" />
                    </Field>
                    <Field label={t('landing_page_admin.secondary_button')}>
                        <Input value={s('cta_secondary')} onChange={(e) => set('cta_secondary', e.target.value)} placeholder="Schedule a Demo" />
                    </Field>
                </div>
            </Card.Body>
        </Card>
    );
}

// ─── SEO Tab ──────────────────────────────────────────────────────────────────

function SeoTab({ data, setData, t }) {
    const s = (key) => data[`landing.${key}`] ?? '';
    const set = (key, val) => setData(`landing.${key}`, val);

    return (
        <Card>
            <Card.Body className="space-y-5">
                <h3 className="font-semibold text-neutral-900 dark:text-neutral-100 pb-3 border-b border-neutral-100 dark:border-neutral-800">{t('landing_page_admin.seo_section', { defaultValue: 'Search Engine Optimization' })}</h3>
                <Field label={t('landing_page_admin.seo_title', { defaultValue: 'Meta Title' })} hint={t('landing_page_admin.seo_title_hint', { defaultValue: 'Shown in browser tabs and search results (~60 chars).' })}>
                    <Input value={s('seo_title')} onChange={(e) => set('seo_title', e.target.value)} placeholder="CREASY CRM — One Inbox for WhatsApp, Messenger & Instagram" />
                </Field>
                <Field label={t('landing_page_admin.seo_description', { defaultValue: 'Meta Description' })} hint={t('landing_page_admin.seo_description_hint', { defaultValue: 'Shown under the title in search results (~155 chars).' })}>
                    <Input value={s('seo_description')} onChange={(e) => set('seo_description', e.target.value)} multiline rows={3} placeholder="Unify WhatsApp, Messenger and Instagram..." />
                </Field>
                <Field label={t('landing_page_admin.seo_keywords', { defaultValue: 'Keywords' })} hint={t('landing_page_admin.seo_keywords_hint', { defaultValue: 'Comma-separated.' })}>
                    <Input value={s('seo_keywords')} onChange={(e) => set('seo_keywords', e.target.value)} placeholder="WhatsApp Business API, team inbox, AI chatbot" />
                </Field>
                <Field label={t('landing_page_admin.seo_og_image', { defaultValue: 'Social Share Image URL' })} hint={t('landing_page_admin.seo_og_image_hint', { defaultValue: 'Absolute URL of the og:image (1200×630 recommended).' })}>
                    <Input value={s('seo_og_image')} onChange={(e) => set('seo_og_image', e.target.value)} placeholder="https://example.com/og-image.png" />
                </Field>
            </Card.Body>
        </Card>
    );
}

// ─── Metrics Tab ──────────────────────────────────────────────────────────────

function MetricsTab({ data, setData, t }) {
    const s = (key) => data[`landing.${key}`] ?? '';
    const set = (key, val) => setData(`landing.${key}`, val);
    const enabled = s('metrics_enabled') === '1';

    return (
        <Card>
            <Card.Body className="space-y-5">
                <SectionToggle
                    label={t('landing_page_admin.metrics_section', { defaultValue: 'Metrics Band' })}
                    enabled={enabled}
                    onChange={(v) => set('metrics_enabled', v ? '1' : '0')}
                    t={t}
                />
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    {[1, 2, 3, 4].map((i) => (
                        <div key={i} className="p-4 rounded-soft border border-neutral-200 dark:border-neutral-700 space-y-3">
                            <p className="text-xs font-semibold text-neutral-500 dark:text-neutral-400 uppercase tracking-wide">{t('landing_page_admin.metric_n', { n: i, defaultValue: `Metric ${i}` })}</p>
                            <Field label={t('landing_page_admin.value', { defaultValue: 'Value' })}>
                                <Input value={s(`metric_${i}_value`)} onChange={(e) => set(`metric_${i}_value`, e.target.value)} placeholder="50M+" />
                            </Field>
                            <Field label={t('landing_page_admin.label', { defaultValue: 'Label' })}>
                                <Input value={s(`metric_${i}_label`)} onChange={(e) => set(`metric_${i}_label`, e.target.value)} placeholder="Messages delivered" />
                            </Field>
                        </div>
                    ))}
                </div>
            </Card.Body>
        </Card>
    );
}

// ─── Channels Tab ─────────────────────────────────────────────────────────────

const CHANNEL_OPTIONS = ['whatsapp', 'messenger', 'instagram', 'telegram', 'sms', 'email'];

function ChannelsTab({ data, setData, t }) {
    const s = (key) => data[`landing.${key}`] ?? '';
    const set = (key, val) => setData(`landing.${key}`, val);
    const enabled = s('channels_enabled') === '1';

    return (
        <Card>
            <Card.Body className="space-y-5">
                <SectionToggle
                    label={t('landing_page_admin.channels_section', { defaultValue: 'Channels Showcase' })}
                    enabled={enabled}
                    onChange={(v) => set('channels_enabled', v ? '1' : '0')}
                    t={t}
                />
                <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <Field label={t('landing_page_admin.section_badge')}>
                        <Input value={s('channels_badge')} onChange={(e) => set('channels_badge', e.target.value)} placeholder="Omnichannel" />
                    </Field>
                    <Field label={t('landing_page_admin.section_title')}>
                        <Input value={s('channels_title')} onChange={(e) => set('channels_title', e.target.value)} placeholder="Meet customers where they already are" />
                    </Field>
                    <Field label={t('landing_page_admin.section_subtitle')}>
                        <Input value={s('channels_subtitle')} onChange={(e) => set('channels_subtitle', e.target.value)} placeholder="Connect every messaging channel..." />
                    </Field>
                </div>
                <div className="grid grid-cols-1 gap-4 pt-2">
                    {[1, 2, 3, 4, 5].map((i) => (
                        <div key={i} className="p-4 rounded-soft border border-neutral-200 dark:border-neutral-700 space-y-3">
                            <p className="text-xs font-semibold text-neutral-500 dark:text-neutral-400 uppercase tracking-wide">{t('landing_page_admin.channel_n', { n: i, defaultValue: `Channel ${i}` })}</p>
                            <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                <Field label={t('landing_page_admin.channel_type', { defaultValue: 'Channel (icon)' })}>
                                    <select
                                        value={s(`channel_${i}_key`)}
                                        onChange={(e) => set(`channel_${i}_key`, e.target.value)}
                                        className="w-full rounded-soft border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500/20"
                                    >
                                        {CHANNEL_OPTIONS.map((c) => (<option key={c} value={c}>{c}</option>))}
                                    </select>
                                </Field>
                                <Field label={t('landing_page_admin.title')}>
                                    <Input value={s(`channel_${i}_title`)} onChange={(e) => set(`channel_${i}_title`, e.target.value)} placeholder="WhatsApp Business" />
                                </Field>
                                <Field label={t('landing_page_admin.description')}>
                                    <Input value={s(`channel_${i}_desc`)} onChange={(e) => set(`channel_${i}_desc`, e.target.value)} placeholder="Official WhatsApp Cloud API..." />
                                </Field>
                            </div>
                        </div>
                    ))}
                </div>
            </Card.Body>
        </Card>
    );
}

// ─── Integrations Strip Tab ───────────────────────────────────────────────────

function IntegrationsStripTab({ data, setData, t }) {
    const s = (key) => data[`landing.${key}`] ?? '';
    const set = (key, val) => setData(`landing.${key}`, val);
    const enabled = s('integrations_strip_enabled') === '1';

    return (
        <Card>
            <Card.Body className="space-y-5">
                <SectionToggle
                    label={t('landing_page_admin.integrations_strip_section', { defaultValue: 'Integrations Strip (home)' })}
                    enabled={enabled}
                    onChange={(v) => set('integrations_strip_enabled', v ? '1' : '0')}
                    t={t}
                />
                <p className="text-xs text-neutral-400 dark:text-neutral-500">{t('landing_page_admin.integrations_strip_hint', { defaultValue: 'The logo chips shown here are pulled from the integrations listed on the Integrations Page tab.' })}</p>
                <Field label={t('landing_page_admin.section_title')}>
                    <Input value={s('integrations_strip_title')} onChange={(e) => set('integrations_strip_title', e.target.value)} placeholder="Works with the tools you already use" />
                </Field>
                <Field label={t('landing_page_admin.section_subtitle')}>
                    <Input value={s('integrations_strip_subtitle')} onChange={(e) => set('integrations_strip_subtitle', e.target.value)} multiline rows={2} placeholder="Connect CREASY CRM to 100+ apps..." />
                </Field>
            </Card.Body>
        </Card>
    );
}

// ─── Security Tab ─────────────────────────────────────────────────────────────

function SecurityTab({ data, setData, t }) {
    const s = (key) => data[`landing.${key}`] ?? '';
    const set = (key, val) => setData(`landing.${key}`, val);
    const enabled = s('security_enabled') === '1';

    return (
        <Card>
            <Card.Body className="space-y-5">
                <SectionToggle
                    label={t('landing_page_admin.security_section', { defaultValue: 'Security & Compliance' })}
                    enabled={enabled}
                    onChange={(v) => set('security_enabled', v ? '1' : '0')}
                    t={t}
                />
                <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <Field label={t('landing_page_admin.section_badge')}>
                        <Input value={s('security_badge')} onChange={(e) => set('security_badge', e.target.value)} placeholder="Security & Compliance" />
                    </Field>
                    <Field label={t('landing_page_admin.section_title')}>
                        <Input value={s('security_title')} onChange={(e) => set('security_title', e.target.value)} placeholder="Enterprise-grade security by default" />
                    </Field>
                    <Field label={t('landing_page_admin.section_subtitle')}>
                        <Input value={s('security_subtitle')} onChange={(e) => set('security_subtitle', e.target.value)} placeholder="Your data is protected at every layer." />
                    </Field>
                </div>
                <div className="grid grid-cols-1 gap-4 pt-2">
                    {[1, 2, 3, 4].map((i) => (
                        <div key={i} className="p-4 rounded-soft border border-neutral-200 dark:border-neutral-700 space-y-3">
                            <p className="text-xs font-semibold text-neutral-500 dark:text-neutral-400 uppercase tracking-wide">{t('landing_page_admin.item_n', { n: i })}</p>
                            <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                <Field label={t('landing_page_admin.icon')}>
                                    <select
                                        value={s(`security_${i}_icon`)}
                                        onChange={(e) => set(`security_${i}_icon`, e.target.value)}
                                        className="w-full rounded-soft border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500/20"
                                    >
                                        {ICON_OPTIONS.map((ic) => (<option key={ic} value={ic}>{ic}</option>))}
                                    </select>
                                </Field>
                                <Field label={t('landing_page_admin.title')}>
                                    <Input value={s(`security_${i}_title`)} onChange={(e) => set(`security_${i}_title`, e.target.value)} placeholder="End-to-End Encryption" />
                                </Field>
                                <Field label={t('landing_page_admin.description')}>
                                    <Input value={s(`security_${i}_desc`)} onChange={(e) => set(`security_${i}_desc`, e.target.value)} placeholder="Data encrypted in transit and at rest." />
                                </Field>
                            </div>
                        </div>
                    ))}
                </div>
            </Card.Body>
        </Card>
    );
}

// ─── About Page Tab ───────────────────────────────────────────────────────────

function AboutTab({ data, setData, t }) {
    const s = (key) => data[`landing.${key}`] ?? '';
    const set = (key, val) => setData(`landing.${key}`, val);

    return (
        <Card>
            <Card.Body className="space-y-6">
                <div className="flex items-center justify-between pb-3 border-b border-neutral-100 dark:border-neutral-800">
                    <h3 className="font-semibold text-neutral-900 dark:text-neutral-100">{t('landing_page_admin.about_section', { defaultValue: 'About Page' })}</h3>
                    <a href="/about" target="_blank" rel="noreferrer" className="text-xs text-brand-500 hover:underline">{t('landing_page_admin.preview_link')}</a>
                </div>
                <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <Field label={t('landing_page_admin.section_badge')}>
                        <Input value={s('about_badge')} onChange={(e) => set('about_badge', e.target.value)} placeholder="About CREASY CRM" />
                    </Field>
                    <div className="sm:col-span-2">
                        <Field label={t('landing_page_admin.headline')}>
                            <Input value={s('about_title')} onChange={(e) => set('about_title', e.target.value)} placeholder="We are on a mission..." />
                        </Field>
                    </div>
                </div>
                <Field label={t('landing_page_admin.subtitle')}>
                    <Input value={s('about_subtitle')} onChange={(e) => set('about_subtitle', e.target.value)} multiline rows={2} />
                </Field>
                <div className="grid grid-cols-1 gap-4">
                    <Field label={t('landing_page_admin.about_story_title', { defaultValue: 'Story Heading' })}>
                        <Input value={s('about_story_title')} onChange={(e) => set('about_story_title', e.target.value)} placeholder="Our story" />
                    </Field>
                    <Field label={t('landing_page_admin.about_story_body', { defaultValue: 'Story Body' })} hint={t('landing_page_admin.about_story_hint', { defaultValue: 'Separate paragraphs with a blank line.' })}>
                        <Input value={s('about_story_body')} onChange={(e) => set('about_story_body', e.target.value)} multiline rows={5} />
                    </Field>
                </div>
                <div>
                    <p className="text-xs font-semibold text-neutral-500 dark:text-neutral-400 uppercase tracking-wide mb-3">{t('landing_page_admin.about_values', { defaultValue: 'Values' })}</p>
                    <div className="grid grid-cols-1 gap-4">
                        {[1, 2, 3, 4].map((i) => (
                            <div key={i} className="p-4 rounded-soft border border-neutral-200 dark:border-neutral-700 grid grid-cols-1 sm:grid-cols-3 gap-3">
                                <Field label={t('landing_page_admin.icon')}>
                                    <select
                                        value={s(`about_value_${i}_icon`)}
                                        onChange={(e) => set(`about_value_${i}_icon`, e.target.value)}
                                        className="w-full rounded-soft border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500/20"
                                    >
                                        {ICON_OPTIONS.map((ic) => (<option key={ic} value={ic}>{ic}</option>))}
                                    </select>
                                </Field>
                                <Field label={t('landing_page_admin.title')}>
                                    <Input value={s(`about_value_${i}_title`)} onChange={(e) => set(`about_value_${i}_title`, e.target.value)} placeholder="Move Fast" />
                                </Field>
                                <Field label={t('landing_page_admin.description')}>
                                    <Input value={s(`about_value_${i}_desc`)} onChange={(e) => set(`about_value_${i}_desc`, e.target.value)} placeholder="We ship quickly..." />
                                </Field>
                            </div>
                        ))}
                    </div>
                </div>
                <div>
                    <p className="text-xs font-semibold text-neutral-500 dark:text-neutral-400 uppercase tracking-wide mb-3">{t('landing_page_admin.about_stats', { defaultValue: 'Stats' })}</p>
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        {[1, 2, 3, 4].map((i) => (
                            <div key={i} className="p-4 rounded-soft border border-neutral-200 dark:border-neutral-700 grid grid-cols-2 gap-3">
                                <Field label={t('landing_page_admin.value', { defaultValue: 'Value' })}>
                                    <Input value={s(`about_stat_${i}_value`)} onChange={(e) => set(`about_stat_${i}_value`, e.target.value)} placeholder="12,000+" />
                                </Field>
                                <Field label={t('landing_page_admin.label', { defaultValue: 'Label' })}>
                                    <Input value={s(`about_stat_${i}_label`)} onChange={(e) => set(`about_stat_${i}_label`, e.target.value)} placeholder="Businesses served" />
                                </Field>
                            </div>
                        ))}
                    </div>
                </div>
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <Field label={t('landing_page_admin.cta_title')}>
                        <Input value={s('about_cta_title')} onChange={(e) => set('about_cta_title', e.target.value)} placeholder="Want to join our journey?" />
                    </Field>
                    <Field label={t('landing_page_admin.cta_subtitle')}>
                        <Input value={s('about_cta_subtitle')} onChange={(e) => set('about_cta_subtitle', e.target.value)} placeholder="Start free today..." />
                    </Field>
                </div>
            </Card.Body>
        </Card>
    );
}

// ─── Integrations Page Tab ────────────────────────────────────────────────────

function IntegrationsPageTab({ data, setData, t }) {
    const s = (key) => data[`landing.${key}`] ?? '';
    const set = (key, val) => setData(`landing.${key}`, val);

    return (
        <Card>
            <Card.Body className="space-y-6">
                <div className="flex items-center justify-between pb-3 border-b border-neutral-100 dark:border-neutral-800">
                    <h3 className="font-semibold text-neutral-900 dark:text-neutral-100">{t('landing_page_admin.integrations_page_section', { defaultValue: 'Integrations Page' })}</h3>
                    <a href="/integrations" target="_blank" rel="noreferrer" className="text-xs text-brand-500 hover:underline">{t('landing_page_admin.preview_link')}</a>
                </div>
                <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <Field label={t('landing_page_admin.section_badge')}>
                        <Input value={s('integrations_page_badge')} onChange={(e) => set('integrations_page_badge', e.target.value)} placeholder="Integrations" />
                    </Field>
                    <div className="sm:col-span-2">
                        <Field label={t('landing_page_admin.headline')}>
                            <Input value={s('integrations_page_title')} onChange={(e) => set('integrations_page_title', e.target.value)} placeholder="Connect CREASY CRM to your entire stack" />
                        </Field>
                    </div>
                </div>
                <Field label={t('landing_page_admin.subtitle')}>
                    <Input value={s('integrations_page_subtitle')} onChange={(e) => set('integrations_page_subtitle', e.target.value)} multiline rows={2} />
                </Field>
                <div className="grid grid-cols-1 gap-4">
                    {[1, 2, 3, 4, 5, 6, 7].map((i) => (
                        <div key={i} className="p-4 rounded-soft border border-neutral-200 dark:border-neutral-700 space-y-3">
                            <p className="text-xs font-semibold text-neutral-500 dark:text-neutral-400 uppercase tracking-wide">{t('landing_page_admin.category_n', { n: i, defaultValue: `Category ${i}` })}</p>
                            <Field label={t('landing_page_admin.title')}>
                                <Input value={s(`intcat_${i}_title`)} onChange={(e) => set(`intcat_${i}_title`, e.target.value)} placeholder="Messaging Channels" />
                            </Field>
                            <Field label={t('landing_page_admin.intcat_items', { defaultValue: 'Integrations (one per line)' })}>
                                <Input value={s(`intcat_${i}_items`)} onChange={(e) => set(`intcat_${i}_items`, e.target.value)} multiline rows={4} placeholder={"WhatsApp Business\nMessenger\nInstagram"} />
                            </Field>
                        </div>
                    ))}
                </div>
            </Card.Body>
        </Card>
    );
}

// ─── Page ─────────────────────────────────────────────────────────────────────

export default function LandingPageIndex({ settings: initialSettings }) {
    const { t } = useTranslation();
    const { props } = usePage();
    const flash = props.flash ?? {};

    const [localData, setLocalData] = useState({ ...initialSettings });
    const [processing, setProcessing] = useState(false);

    const setField = (key, val) => setLocalData((prev) => ({ ...prev, [key]: val }));

    const handleSubmit = (e) => {
        e?.preventDefault();
        setProcessing(true);
        router.put(route('admin.landing-page.update'), { settings: localData }, {
            preserveScroll: true,
            onFinish: () => setProcessing(false),
        });
    };

    const TABS = [
        { key: 'navbar',             group: 'general', label: t('landing_page_admin.tab_navbar'),                                          C: NavbarTab },
        { key: 'seo',                group: 'general', label: t('landing_page_admin.tab_seo', { defaultValue: 'SEO' }),                     C: SeoTab },
        { key: 'hero',               group: 'home',    label: t('landing_page_admin.tab_hero'),                                            C: HeroTab },
        { key: 'metrics',            group: 'home',    label: t('landing_page_admin.tab_metrics', { defaultValue: 'Metrics' }),            C: MetricsTab },
        { key: 'trustedby',          group: 'home',    label: t('landing_page_admin.tab_trustedby'),                                       C: TrustedByTab },
        { key: 'channels',           group: 'home',    label: t('landing_page_admin.tab_channels', { defaultValue: 'Channels' }),          C: ChannelsTab },
        { key: 'problems',           group: 'home',    label: t('landing_page_admin.tab_problems'),                                        C: ProblemSolutionTab },
        { key: 'features',           group: 'home',    label: t('landing_page_admin.tab_features'),                                        C: FeaturesTab },
        { key: 'howitworks',         group: 'home',    label: t('landing_page_admin.tab_howitworks'),                                      C: HowItWorksTab },
        { key: 'integrations_strip', group: 'home',    label: t('landing_page_admin.tab_integrations_strip', { defaultValue: 'Integrations Strip' }), C: IntegrationsStripTab },
        { key: 'why',                group: 'home',    label: t('landing_page_admin.tab_why'),                                             C: WhyTab },
        { key: 'security',           group: 'home',    label: t('landing_page_admin.tab_security', { defaultValue: 'Security' }),          C: SecurityTab },
        { key: 'faq',                group: 'home',    label: t('landing_page_admin.tab_faq'),                                             C: FaqTab },
        { key: 'cta',                group: 'home',    label: t('landing_page_admin.tab_cta'),                                             C: CtaTab },
        { key: 'about',              group: 'pages',   label: t('landing_page_admin.tab_about', { defaultValue: 'About Page' }),           C: AboutTab },
        { key: 'integrations_page',  group: 'pages',   label: t('landing_page_admin.tab_integrations_page', { defaultValue: 'Integrations Page' }), C: IntegrationsPageTab },
    ];

    const GROUPS = [
        { key: 'general', label: t('landing_page_admin.group_general', { defaultValue: 'General' }) },
        { key: 'home',    label: t('landing_page_admin.group_home', { defaultValue: 'Home Page' }) },
        { key: 'pages',   label: t('landing_page_admin.group_pages', { defaultValue: 'Other Pages' }) },
    ];

    const [activeKey, setActiveKey] = useState('hero');
    const ActiveTab = (TABS.find((x) => x.key === activeKey) ?? TABS[0]).C;

    return (
        <AdminLayout title={t('landing_page_admin.page_title')}>
            <Head title={t('landing_page_admin.page_head')} />
            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h2 className="text-xl font-semibold text-neutral-900 dark:text-neutral-100">{t('landing_page_admin.page_title')}</h2>
                        <p className="text-sm text-neutral-500 dark:text-neutral-400 mt-0.5">
                            {t('landing_page_admin.page_subtitle')}
                            <a href="/" target="_blank" rel="noreferrer" className="ml-2 text-brand-500 hover:underline">{t('landing_page_admin.preview_link')}</a>
                        </p>
                    </div>
                    <Button type="button" variant="primary" disabled={processing} onClick={handleSubmit}>
                        {processing ? t('landing_page_admin.saving') : t('landing_page_admin.save_all')}
                    </Button>
                </div>

                {flash?.success && (
                    <div className="rounded-soft-lg bg-green-50 dark:bg-green-900/30 text-green-800 dark:text-green-200 px-4 py-2 text-sm">
                        {flash.success}
                    </div>
                )}

                <MasterToggleCard
                    enabled={(localData['landing.page_enabled'] ?? '1') === '1'}
                    onChange={(v) => setField('landing.page_enabled', v ? '1' : '0')}
                    t={t}
                />

                {/* Grouped, wrapping tab bar */}
                <div className="space-y-3 rounded-soft-lg border border-neutral-200 dark:border-neutral-800 bg-white dark:bg-neutral-900 p-4">
                    {GROUPS.map((g) => (
                        <div key={g.key} className="flex flex-wrap items-center gap-2">
                            <span className="text-xs font-semibold text-neutral-400 dark:text-neutral-500 uppercase tracking-wide w-20 shrink-0">{g.label}</span>
                            {TABS.filter((x) => x.group === g.key).map((x) => (
                                <button
                                    key={x.key}
                                    type="button"
                                    onClick={() => setActiveKey(x.key)}
                                    className={`rounded-full px-3 py-1.5 text-sm font-medium transition-colors ${
                                        activeKey === x.key
                                            ? 'bg-brand-600 text-white shadow-sm'
                                            : 'bg-neutral-100 text-neutral-600 hover:bg-neutral-200 dark:bg-neutral-800 dark:text-neutral-300 dark:hover:bg-neutral-700'
                                    }`}
                                >
                                    {x.label}
                                </button>
                            ))}
                        </div>
                    ))}
                </div>

                <form onSubmit={handleSubmit}>
                    <ActiveTab data={localData} setData={setField} t={t} />

                    <div className="mt-6 flex justify-end">
                        <Button type="submit" variant="primary" disabled={processing}>
                            {processing ? t('landing_page_admin.saving') : t('landing_page_admin.save_all')}
                        </Button>
                    </div>
                </form>
            </div>
        </AdminLayout>
    );
}
