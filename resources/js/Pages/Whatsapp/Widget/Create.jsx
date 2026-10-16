import { Head, useForm, Link } from '@inertiajs/react';
import ClientLayout from '@/Layouts/ClientLayout';
import { ChevronLeft, Clock, Globe, Palette, MessageSquare, Eye, Smartphone } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import {
    DEFAULT_FORM, inputCls, Field,
    WidgetPreview, WorkingHoursEditor, DomainEditor,
} from './Partials/WidgetForm';

const TABS = [
    { key: 'basic',      labelKey: 'whatsapp.widget_tab_basic',      icon: MessageSquare },
    { key: 'appearance', labelKey: 'whatsapp.widget_tab_appearance', icon: Palette },
    { key: 'hours',      labelKey: 'whatsapp.widget_tab_hours',      icon: Clock },
    { key: 'domains',    labelKey: 'whatsapp.widget_tab_domains',    icon: Globe },
];

export default function CreateWidget() {
    const { t } = useTranslation();
    const [tab, setTab] = useState('basic');
    const { data, setData, post, processing, errors } = useForm({ ...DEFAULT_FORM });

    const handleSubmit = (e) => {
        e.preventDefault();
        post(route('client.whatsapp.widgets.store'));
    };

    return (
        <ClientLayout title={t('whatsapp.widget_create_title')}>
            <Head title={t('whatsapp.widget_create_head_title')} />

            <div className="max-w-6xl mx-auto space-y-5">
                {/* Breadcrumb */}
                <div className="flex items-center gap-2">
                    <Link href={route('client.whatsapp.widget.index')}
                        className="flex items-center gap-1.5 text-sm text-neutral-500 hover:text-neutral-800 dark:hover:text-neutral-200 transition">
                        <ChevronLeft className="h-4 w-4" /> {t('whatsapp.widget_title')}
                    </Link>
                    <span className="text-neutral-300 dark:text-neutral-600">/</span>
                    <span className="text-sm font-medium text-neutral-800 dark:text-neutral-200">{t('whatsapp.widget_new')}</span>
                </div>

                <div>
                    <h1 className="text-2xl font-semibold text-neutral-900 dark:text-neutral-100">{t('whatsapp.widget_create_title')}</h1>
                    <p className="mt-1 text-sm text-neutral-500 dark:text-neutral-400">{t('whatsapp.widget_create_subtitle')}</p>
                </div>

                <form onSubmit={handleSubmit}>
                    <div className="flex flex-col gap-5 xl:flex-row xl:items-start">
                        {/* ── Left: sidebar + content ── */}
                        <div className="flex min-w-0 flex-1 flex-col gap-4 md:flex-row md:gap-5">
                            {/* Sidebar tabs */}
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
                                        <SectionHeader title={t('whatsapp.widget_basic_info')} description={t('whatsapp.widget_create_basic_desc')} />
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

                    {/* Footer actions */}
                    <div className="mt-6 flex flex-col-reverse gap-3 border-t border-neutral-200 pt-4 dark:border-neutral-700 sm:flex-row sm:items-center sm:justify-between">
                        <Link href={route('client.whatsapp.widget.index')}
                            className="rounded-lg border border-neutral-300 dark:border-neutral-600 px-5 py-2.5 text-sm font-medium text-neutral-700 dark:text-neutral-300 hover:bg-neutral-50 dark:hover:bg-neutral-800 transition">
                            {t('common.cancel')}
                        </Link>
                        <div className="flex flex-wrap items-center justify-end gap-2 sm:gap-3">
                            <TabNavButtons tab={tab} setTab={setTab} tabs={TABS} />
                            {tab === TABS[TABS.length - 1].key && (
                                <button type="submit" disabled={processing}
                                    className="rounded-lg bg-brand-600 px-6 py-2.5 text-sm font-semibold text-white hover:bg-brand-700 disabled:opacity-60 transition">
                                    {processing ? t('whatsapp.widget_creating') : t('whatsapp.widget_create_submit')}
                                </button>
                            )}
                        </div>
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

function TabNavButtons({ tab, setTab, tabs }) {
    const { t } = useTranslation();
    const idx = tabs.findIndex(item => item.key === tab);
    return (
        <div className="flex gap-2">
            {idx > 0 && (
                <button type="button" onClick={() => setTab(tabs[idx - 1].key)}
                    className="rounded-lg border border-neutral-300 dark:border-neutral-600 px-4 py-2.5 text-sm font-medium text-neutral-700 dark:text-neutral-300 hover:bg-neutral-50 dark:hover:bg-neutral-800 transition">
                    ← {t('common.back')}
                </button>
            )}
            {idx < tabs.length - 1 && (
                <button type="button" onClick={() => setTab(tabs[idx + 1].key)}
                    className="rounded-lg bg-brand-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-brand-700 transition">
                    {t('common.next')} →
                </button>
            )}
        </div>
    );
}
