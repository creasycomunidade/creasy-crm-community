import { Head, router, usePage, Link } from '@inertiajs/react';
import ClientLayout from '@/Layouts/ClientLayout';
import EmptyState from '@/Components/EmptyState';
import { Plus, Trash2, Code, ExternalLink, Pencil, Check, Clock, Globe } from 'lucide-react';
import { ChannelBrandIcon } from '@/Components/BrandIcons';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';

export default function WhatsappWidgetIndex({ widgets }) {
    const { t } = useTranslation();
    const { props } = usePage();
    const flash = props.flash ?? {};
    const [copied, setCopied] = useState(null);

    const handleDelete = (id) => {
        if (confirm(t('whatsapp.widget_delete_confirm'))) {
            router.delete(route('client.whatsapp.widgets.destroy', id), { preserveScroll: true });
        }
    };

    const handleCopy = (key) => {
        const snippet = `<script src="${window.location.origin}/widgets/whatsapp/${key}.js" async defer></script>`;
        navigator.clipboard?.writeText(snippet) ?? fallbackCopy(snippet);
        setCopied(key);
        setTimeout(() => setCopied(null), 2500);
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
        <ClientLayout title={t('whatsapp.widget_title')}>
            <Head title={t('whatsapp.widget_head_title')} />
            <div className="space-y-6">
                {/* Header */}
                <div className="flex items-center justify-between gap-3">
                    <div>
                        <h2 className="text-xl font-semibold text-neutral-900 dark:text-neutral-100">{t('whatsapp.widget_title')}</h2>
                        <p className="text-sm text-neutral-500 dark:text-neutral-400 mt-0.5">
                            {t('whatsapp.widget_subtitle')}
                        </p>
                    </div>
                    {(
                        <Link href={route('client.whatsapp.widgets.create')}
                            className="flex items-center gap-1.5 rounded-lg bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-700 transition">
                            <Plus className="h-4 w-4" /> {t('whatsapp.widget_new')}
                        </Link>
                    )}
                </div>

                {/* Flash */}
                {flash.success && (
                    <div className="rounded-lg bg-green-50 dark:bg-green-900/30 border border-green-200 dark:border-green-800 text-green-800 dark:text-green-200 px-4 py-3 text-sm flex items-center gap-2">
                        <Check className="h-4 w-4 flex-shrink-0" /> {flash.success}
                    </div>
                )}

                {/* List */}
                {widgets.length === 0 ? (
                    <EmptyState
                        icon={<ChannelBrandIcon channel="whatsapp" className="h-8 w-8" />}
                        title={t('whatsapp.widget_empty_title')}
                        description={t('whatsapp.widget_empty_description')}
                        action={{ label: t('whatsapp.widget_new'), href: route('client.whatsapp.widgets.create') }}
                    />
                ) : (
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        {widgets.map(w => (
                            <WidgetCard
                                key={w.id}
                                widget={w}
                                copied={copied === w.widget_key}
                                onCopy={() => handleCopy(w.widget_key)}
                                onDelete={() => handleDelete(w.id)}
                            />
                        ))}
                    </div>
                )}
            </div>
        </ClientLayout>
    );
}

function WidgetCard({ widget: w, copied, onCopy, onDelete }) {
    const { t } = useTranslation();
    const color = w.button_color ?? '#25D366';
    const posRight = w.position !== 'bottom_left';

    return (
        <div className="rounded-2xl border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-900 overflow-hidden flex flex-col">
            {/* Color accent bar */}
            <div className="h-1.5 flex-shrink-0" style={{ background: color }} />

            <div className="p-5 flex flex-col gap-3 flex-1">
                {/* Title row */}
                <div className="flex items-start justify-between gap-2">
                    <div className="flex items-center gap-3 min-w-0">
                        <div className="h-10 w-10 rounded-full flex items-center justify-center border-2 bg-neutral-50 dark:bg-neutral-800 flex-shrink-0"
                            style={{ borderColor: color }}>
                            <ChannelBrandIcon channel="whatsapp" className="h-5 w-5" />
                        </div>
                        <div className="min-w-0">
                            <p className="font-semibold text-sm text-neutral-900 dark:text-neutral-100 truncate">
                                {w.name || w.display_phone}
                            </p>
                            {w.name && (
                                <p className="text-xs text-neutral-500 truncate">{w.display_phone}</p>
                            )}
                        </div>
                    </div>
                    <div className="flex items-center gap-1 flex-shrink-0">
                        <Link href={route('client.whatsapp.widgets.edit', w.id)}
                            className="p-1.5 rounded-lg text-neutral-400 hover:text-brand-600 hover:bg-brand-50 dark:hover:bg-brand-900/20 transition"
                            title={t('whatsapp.widget_edit_tooltip')}>
                            <Pencil className="h-4 w-4" />
                        </Link>
                        <button onClick={onDelete}
                            className="p-1.5 rounded-lg text-neutral-400 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 transition"
                            title={t('whatsapp.widget_delete_tooltip')}>
                            <Trash2 className="h-4 w-4" />
                        </button>
                    </div>
                </div>

                {/* Badges */}
                <div className="flex flex-wrap gap-1.5">
                    <span className="inline-flex items-center rounded-full bg-neutral-100 dark:bg-neutral-800 px-2.5 py-0.5 text-xs text-neutral-600 dark:text-neutral-400">
                        {posRight ? `↘ ${t('whatsapp.widget_position_bottom_right')}` : `↙ ${t('whatsapp.widget_position_bottom_left')}`}
                    </span>
                    {w.working_hours_json?.enabled && (
                        <span className="inline-flex items-center gap-1 rounded-full bg-blue-50 dark:bg-blue-900/30 px-2.5 py-0.5 text-xs text-blue-700 dark:text-blue-300">
                            <Clock className="h-3 w-3" /> {t('whatsapp.widget_badge_hours_set')}
                        </span>
                    )}
                    {w.allowed_domains?.length > 0 && (
                        <span className="inline-flex items-center gap-1 rounded-full bg-amber-50 dark:bg-amber-900/30 px-2.5 py-0.5 text-xs text-amber-700 dark:text-amber-300">
                            <Globe className="h-3 w-3" /> {t('whatsapp.widget_badge_domains', { count: w.allowed_domains.length })}
                        </span>
                    )}
                    {w.greeting_message && (
                        <span className="inline-flex items-center rounded-full bg-purple-50 dark:bg-purple-900/30 px-2.5 py-0.5 text-xs text-purple-700 dark:text-purple-300">
                            {t('whatsapp.widget_badge_greeting_on')}
                        </span>
                    )}
                </div>

                {w.prefilled_message && (
                    <p className="text-xs text-neutral-500 dark:text-neutral-400 truncate italic">"{w.prefilled_message}"</p>
                )}

                {/* Embed snippet */}
                <div className="rounded-lg bg-neutral-950 border border-neutral-800 px-3 py-2 mt-auto">
                    <p className="text-[11px] font-mono text-green-400 truncate select-all">
                        {`<script src="${window.location.origin}/widgets/whatsapp/${w.widget_key}.js" async defer></script>`}
                    </p>
                </div>

                {/* Actions */}
                <div className="flex gap-2">
                    <button onClick={onCopy}
                        className="flex-1 flex items-center justify-center gap-1.5 rounded-lg border border-neutral-300 dark:border-neutral-600 px-3 py-2 text-xs font-medium text-neutral-700 dark:text-neutral-300 hover:bg-neutral-50 dark:hover:bg-neutral-800 transition">
                        {copied
                            ? <><Check className="h-3.5 w-3.5 text-green-500" /> {t('whatsapp.widget_copied')}</>
                            : <><Code className="h-3.5 w-3.5" /> {t('whatsapp.widget_copy_snippet')}</>}
                    </button>
                    <a href={`/widgets/whatsapp/${w.widget_key}.js`} target="_blank" rel="noreferrer"
                        className="flex items-center gap-1 rounded-lg border border-neutral-300 dark:border-neutral-600 px-2.5 py-2 text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-200 transition"
                        title={t('whatsapp.widget_view_embed_js')}>
                        <ExternalLink className="h-3.5 w-3.5" />
                    </a>
                </div>
            </div>
        </div>
    );
}
