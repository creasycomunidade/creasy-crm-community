import { Head, useForm, Link } from '@inertiajs/react';
import ClientLayout from '@/Layouts/ClientLayout';
import { ChevronLeft, Clock, Globe, Palette, MessageSquare, Code, Check, Smartphone } from 'lucide-react';
import { useState } from 'react';
import { useTranslation, Trans } from 'react-i18next';
import {
    widgetToForm, inputCls, Field,
    WidgetPreview, WorkingHoursEditor, DomainEditor,
} from './Partials/WidgetForm';

const TABS = [
    { key: 'basic',      labelKey: 'whatsapp.widget_tab_basic',      icon: MessageSquare },
    { key: 'appearance', labelKey: 'whatsapp.widget_tab_appearance', icon: Palette },
    { key: 'hours',      labelKey: 'whatsapp.widget_tab_hours',      icon: Clock },
    { key: 'domains',    labelKey: 'whatsapp.widget_tab_domains',    icon: Globe },
    { key: 'embed',      labelKey: 'whatsapp.widget_tab_embed',      icon: Code },
];

export default function EditWidget({ widget }) {
    const { t } = useTranslation();
    const [tab, setTab] = useState('basic');
    const [copied, setCopied] = useState(false);
    const { data, setData, put, processing, errors, isDirty } = useForm(widgetToForm(widget));

    const handleSubmit = (e) => {
        e.preventDefault();
        put(route('client.whatsapp.widgets.update', widget.id));
    };

    const embedSnippet = `<script src="${window.location.origin}/widgets/whatsapp/${widget.widget_key}.js" async defer></script>`;

    const handleCopy = () => {
        navigator.clipboard?.writeText(embedSnippet) ?? fallbackCopy(embedSnippet);
        setCopied(true);
        setTimeout(() => setCopied(false), 2500);
    };

    const fallbackCopy = (text) => {
        const ta = document.createElement('textarea');
        ta.value = text;
        ta.style.cssText = 'position:fixed;opacity:0';
        document.body.appendChild(ta);
        ta.select();
        document.execCommand('copy');
        document.body.removeChild(ta);
    };

    return (
        <ClientLayout title={t('whatsapp.widget_edit_title')}>
            <Head title={t('whatsapp.widget_edit_head_title', { name: widget.name || widget.display_phone })} />

            <div className="max-w-6xl mx-auto space-y-5">
                {/* Breadcrumb */}
                <div className="flex items-center gap-2">
                    <Link href={route('client.whatsapp.widget.index')}
                        className="flex items-center gap-1.5 text-sm text-neutral-500 hover:text-neutral-800 dark:hover:text-neutral-200 transition">
                        <ChevronLeft className="h-4 w-4" /> {t('whatsapp.widget_title')}
                    </Link>
                    <span className="text-neutral-300 dark:text-neutral-600">/</span>
                    <span className="text-sm font-medium text-neutral-800 dark:text-neutral-200">
                        {widget.name || widget.display_phone}
                    </span>
                </div>

                <div className="flex items-start justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-semibold text-neutral-900 dark:text-neutral-100">
                            {widget.name || t('whatsapp.widget_edit_title')}
                        </h1>
                        <p className="mt-1 text-sm text-neutral-500 dark:text-neutral-400">{widget.display_phone}</p>
                    </div>
                    {isDirty && (
                        <span className="mt-1.5 text-xs bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-300 px-2.5 py-1 rounded-full font-medium">
                            {t('whatsapp.widget_unsaved_changes')}
                        </span>
                    )}
                </div>

                <form onSubmit={handleSubmit}>
                    <div className="flex flex-col gap-5 xl:flex-row xl:items-start">
                        {/* ── Left: sidebar + content ── */}
                        <div className="flex min-w-0 flex-1 flex-col gap-4 md:flex-row md:gap-5">
                            {/* Sidebar */}
                            <div className="flex w-full gap-1 overflow-x-auto pb-1 md:block md:w-44 md:flex-shrink-0 md:space-y-0.5 md:overflow-visible md:pb-0 md:pt-0.5">
                                {TABS.map(tabItem => {
                                    const Icon = tabItem.icon;
                                    return (
                                        <button key={tabItem.key} type="button" onClick={() => setTab(tabItem.key)}
                                            className={`flex min-h-11 shrink-0 items-center gap-2.5 rounded-lg px-3 py-2.5 text-left text-sm font-medium transition md:w-full ${tab === tabItem.key ? 'bg-brand-50 dark:bg-brand-900/20 text-brand-700 dark:text-brand-400' : 'text-neutral-600 dark:text-neutral-400 hover:bg-neutral-100 dark:hover:bg-neutral-800'}`}>
                                            <Icon className="h-4 w-4 flex-shrink-0" />
                                            {t(tabItem.labelKey)}
                                        </button>
                                    );
                                })}
                            </div>

                            {/* Content panel */}
                            <div className="min-w-0 flex-1 space-y-5 rounded-2xl border border-neutral-200 bg-white p-4 dark:border-neutral-700 dark:bg-neutral-900 sm:p-6">
                                {tab === 'basic' && (
                                    <>
                                        <SectionHeader title={t('whatsapp.widget_basic_info')} description={t('whatsapp.widget_edit_basic_desc')} />
                                        <Field label={t('whatsapp.widget_field_name')} hint={t('whatsapp.widget_field_name_hint')}>
                                            <input type="text" value={data.name} onChange={e => setData('name', e.target.value)}
                                                placeholder={t('whatsapp.widget_field_name_placeholder')} className={inputCls()} />
                                        </Field>
                                        <Field label={t('whatsapp.widget_field_phone')} error={errors.display_phone} hint={t('whatsapp.widget_field_phone_hint')}>
                                            <input type="tel" value={data.display_phone} onChange={e => setData('display_phone', e.target.value)}
                                                placeholder="+8801XXXXXXXXX" required className={inputCls(errors.display_phone)} />
                                        </Field>
                                        <Field label={t('whatsapp.widget_field_prefilled')} hint={t('whatsapp.widget_field_prefilled_hint')}>
                                            <input type="text" value={data.prefilled_message} onChange={e => setData('prefilled_message', e.target.value)}
                                                placeholder={t('whatsapp.widget_field_prefilled_placeholder')} className={inputCls()} />
                                        </Field>
                                        <Field label={t('whatsapp.widget_field_greeting')} hint={t('whatsapp.widget_field_greeting_hint')}>
                                            <textarea rows={3} value={data.greeting_message} onChange={e => setData('greeting_message', e.target.value)}
                                                placeholder={t('whatsapp.widget_field_greeting_placeholder')} className={inputCls() + ' resize-none'} />
                                        </Field>
                                    </>
                                )}

                                {tab === 'appearance' && (
                                    <>
                                        <SectionHeader title={t('whatsapp.widget_tab_appearance')} description={t('whatsapp.widget_appearance_desc')} />
                                        <Field label={t('whatsapp.widget_field_agent_name')} hint={t('whatsapp.widget_field_agent_name_hint')}>
                                            <input type="text" value={data.agent_name} onChange={e => setData('agent_name', e.target.value)}
                                                placeholder={t('whatsapp.widget_default_agent_name')} className={inputCls()} />
                                        </Field>
                                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                            <Field label={t('whatsapp.widget_field_button_color')}>
                                                <div className="flex items-center gap-2 mt-0.5">
                                                    <input type="color" value={data.button_color} onChange={e => setData('button_color', e.target.value)}
                                                        className="h-10 w-12 rounded-lg border border-neutral-300 dark:border-neutral-600 cursor-pointer p-0.5 flex-shrink-0" />
                                                    <input type="text" value={data.button_color} onChange={e => setData('button_color', e.target.value)}
                                                        className={inputCls() + ' font-mono'} maxLength={9} />
                                                </div>
                                            </Field>
                                            <Field label={t('whatsapp.widget_field_avatar_color')}>
                                                <div className="flex items-center gap-2 mt-0.5">
                                                    <input type="color" value={data.agent_avatar_color} onChange={e => setData('agent_avatar_color', e.target.value)}
                                                        className="h-10 w-12 rounded-lg border border-neutral-300 dark:border-neutral-600 cursor-pointer p-0.5 flex-shrink-0" />
                                                    <input type="text" value={data.agent_avatar_color} onChange={e => setData('agent_avatar_color', e.target.value)}
                                                        className={inputCls() + ' font-mono'} maxLength={9} />
                                                </div>
                                            </Field>
                                        </div>
                                        <Field label={t('whatsapp.widget_field_position')}>
                                            <div className="mt-0.5 grid grid-cols-1 gap-3 sm:grid-cols-2">
                                                {[
                                                    { v: 'bottom_right', l: 'whatsapp.widget_position_bottom_right', d: 'whatsapp.widget_position_bottom_right_desc' },
                                                    { v: 'bottom_left',  l: 'whatsapp.widget_position_bottom_left',  d: 'whatsapp.widget_position_bottom_left_desc'  },
                                                ].map(opt => (
                                                    <label key={opt.v}
                                                        className={`flex flex-col gap-1 rounded-xl border-2 p-4 cursor-pointer transition ${data.position === opt.v ? 'border-brand-600 bg-brand-50 dark:bg-brand-900/20' : 'border-neutral-200 dark:border-neutral-700 hover:border-neutral-300 dark:hover:border-neutral-600'}`}>
                                                        <input type="radio" name="position" value={opt.v} checked={data.position === opt.v}
                                                            onChange={() => setData('position', opt.v)} className="sr-only" />
                                                        <span className={`text-sm font-semibold ${data.position === opt.v ? 'text-brand-700 dark:text-brand-400' : 'text-neutral-700 dark:text-neutral-300'}`}>{t(opt.l)}</span>
                                                        <span className="text-xs text-neutral-500">{t(opt.d)}</span>
                                                    </label>
                                                ))}
                                            </div>
                                        </Field>
                                    </>
                                )}

                                {tab === 'hours' && (
                                    <WorkingHoursEditor value={data.working_hours_json} onChange={v => setData('working_hours_json', v)} />
                                )}

                                {tab === 'domains' && (
                                    <DomainEditor value={data.allowed_domains} onChange={v => setData('allowed_domains', v)} />
                                )}

                                {tab === 'embed' && (
                                    <>
                                        <SectionHeader title={t('whatsapp.widget_tab_embed')} description={t('whatsapp.widget_embed_desc')} />
                                        <p className="text-sm text-neutral-600 dark:text-neutral-400">
                                            <Trans i18nKey="whatsapp.widget_embed_instructions">
                                                Add this snippet to your website's <code className="bg-neutral-100 dark:bg-neutral-800 px-1.5 py-0.5 rounded text-xs font-mono">&lt;head&gt;</code> or just before the closing <code className="bg-neutral-100 dark:bg-neutral-800 px-1.5 py-0.5 rounded text-xs font-mono">&lt;/body&gt;</code> tag.
                                            </Trans>
                                        </p>
                                        <div className="relative rounded-xl bg-neutral-950 border border-neutral-800">
                                            <pre className="p-5 text-sm text-green-400 font-mono overflow-x-auto whitespace-pre-wrap break-all leading-relaxed">{embedSnippet}</pre>
                                            <button type="button" onClick={handleCopy}
                                                className="absolute top-3 right-3 flex items-center gap-1.5 rounded-lg bg-neutral-800 hover:bg-neutral-700 px-3 py-1.5 text-xs text-neutral-200 transition">
                                                {copied ? <><Check className="h-3.5 w-3.5 text-green-400" /> {t('whatsapp.widget_copied')}</> : <><Code className="h-3.5 w-3.5" /> {t('whatsapp.widget_copy')}</>}
                                            </button>
                                        </div>
                                        <div className="rounded-xl bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 p-4 text-sm text-blue-800 dark:text-blue-200 space-y-1">
                                            <p className="font-semibold">{t('whatsapp.widget_install_tips')}</p>
                                            <ul className="list-disc list-inside space-y-0.5 text-xs">
                                                <li>{t('whatsapp.widget_install_tip_platforms')}</li>
                                                <li>
                                                    <Trans i18nKey="whatsapp.widget_install_tip_async">
                                                        The <code className="bg-blue-100 dark:bg-blue-900/40 px-1 rounded">async defer</code> attributes ensure it doesn't block page load.
                                                    </Trans>
                                                </li>
                                                <li>{t('whatsapp.widget_install_tip_cache')}</li>
                                            </ul>
                                        </div>
                                        <div className="pt-1">
                                            <p className="text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-2">{t('whatsapp.widget_direct_js_link')}</p>
                                            <a href={`/widgets/whatsapp/${widget.widget_key}.js`} target="_blank" rel="noreferrer"
                                                className="inline-flex items-center gap-1.5 text-sm text-brand-600 hover:underline font-mono break-all">
                                                {window.location.origin}/widgets/whatsapp/{widget.widget_key}.js
                                            </a>
                                        </div>
                                    </>
                                )}
                            </div>
                        </div>

                        {/* ── Right: live preview panel ── */}
                        <div className="w-full space-y-3 xl:sticky xl:top-6 xl:w-72 xl:flex-shrink-0">
                            <div className="flex items-center gap-2 text-sm font-medium text-neutral-600 dark:text-neutral-400">
                                <Smartphone className="h-4 w-4" />
                                {t('whatsapp.widget_live_preview')}
                            </div>
                            <div className="rounded-2xl border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-900 overflow-hidden">
                                <WidgetPreview data={data} />
                            </div>
                            {/* Quick summary */}
                            <div className="rounded-xl bg-neutral-50 dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 p-4 space-y-2">
                                <p className="text-xs font-semibold text-neutral-500 uppercase tracking-wide">{t('whatsapp.widget_summary')}</p>
                                <dl className="space-y-1.5 text-xs">
                                    <SummaryRow label={t('whatsapp.widget_summary_phone')} value={data.display_phone || '—'} />
                                    <SummaryRow label={t('whatsapp.widget_summary_position')} value={data.position.replace('_', ' ')} />
                                    <SummaryRow label={t('whatsapp.widget_summary_greeting')} value={data.greeting_message ? t('whatsapp.widget_summary_enabled') : t('whatsapp.widget_summary_off')} />
                                    <SummaryRow label={t('whatsapp.widget_summary_hours')} value={data.working_hours_json?.enabled ? t('whatsapp.widget_summary_enabled') : t('whatsapp.widget_summary_off')} />
                                    <SummaryRow label={t('whatsapp.widget_summary_domains')} value={data.allowed_domains?.length ? t('whatsapp.widget_summary_restricted', { count: data.allowed_domains.length }) : t('whatsapp.widget_summary_all')} />
                                </dl>
                            </div>
                        </div>
                    </div>

                    {/* Footer */}
                    <div className="mt-6 flex flex-col-reverse gap-3 border-t border-neutral-200 pt-4 dark:border-neutral-700 sm:flex-row sm:items-center sm:justify-between">
                        <Link href={route('client.whatsapp.widget.index')}
                            className="rounded-lg border border-neutral-300 dark:border-neutral-600 px-5 py-2.5 text-sm font-medium text-neutral-700 dark:text-neutral-300 hover:bg-neutral-50 dark:hover:bg-neutral-800 transition">
                            ← {t('whatsapp.widget_back_to_widgets')}
                        </Link>
                        <button type="submit" disabled={processing || !isDirty}
                            className="rounded-lg bg-brand-600 px-6 py-2.5 text-sm font-semibold text-white hover:bg-brand-700 disabled:opacity-50 transition">
                            {processing ? t('whatsapp.widget_saving') : t('whatsapp.widget_save_changes')}
                        </button>
                    </div>
                </form>
            </div>
        </ClientLayout>
    );
}

function SectionHeader({ title, description }) {
    return (
        <div className="pb-3 border-b border-neutral-100 dark:border-neutral-800">
            <h2 className="text-base font-semibold text-neutral-900 dark:text-neutral-100">{title}</h2>
            <p className="text-sm text-neutral-500 mt-0.5">{description}</p>
        </div>
    );
}

function SummaryRow({ label, value }) {
    return (
        <div className="flex justify-between gap-2">
            <dt className="text-neutral-500">{label}</dt>
            <dd className="text-neutral-800 dark:text-neutral-200 font-medium capitalize truncate">{value}</dd>
        </div>
    );
}
