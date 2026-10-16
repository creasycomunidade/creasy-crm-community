import { Head, Link, router, usePage, useForm } from '@inertiajs/react';
import ClientLayout from '@/Layouts/ClientLayout';
import EmptyState from '@/Components/EmptyState';
import {
    Plus, Zap, Play, Pause, Trash2, BarChart2, Pencil, Clock,
    UserRound, Tag, MessageCircle, Megaphone, FileText, Link2,
    ShoppingBag, PackageCheck, XCircle, ShoppingCart, UserPlus,
    Sparkles, Loader2, AlertTriangle,
} from 'lucide-react';
import { useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import axios from 'axios';

const STATUS_COLORS = {
    active: 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300',
    paused: 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/40 dark:text-yellow-300',
    draft:  'bg-neutral-100 text-neutral-600 dark:bg-neutral-800 dark:text-neutral-400',
};

const STATUS_LABEL_KEYS = {
    active: 'automation.status_active',
    paused: 'automation.status_paused',
    draft: 'automation.status_draft',
};

// Maps a stored trigger_type to its display label + icon (mirrors the builder's TRIGGER_TYPES).
const TRIGGER_META = {
    'contact.created':   { labelKey: 'automation.trigger_contact_created',   Icon: UserRound     },
    'contact.tag_added': { labelKey: 'automation.trigger_tag_added',         Icon: Tag           },
    'message.received':  { labelKey: 'automation.trigger_message_received',  Icon: MessageCircle },
    'campaign.sent':     { labelKey: 'automation.trigger_campaign_sent',     Icon: Megaphone     },
    'form.submitted':    { labelKey: 'automation.trigger_form_submitted',    Icon: FileText      },
    'webhook.received':  { labelKey: 'automation.trigger_webhook_received',  Icon: Link2         },
    'order.placed':      { labelKey: 'automation.trigger_order_placed',      Icon: ShoppingBag   },
    'order.fulfilled':   { labelKey: 'automation.trigger_order_fulfilled',   Icon: PackageCheck  },
    'order.cancelled':   { labelKey: 'automation.trigger_order_cancelled',   Icon: XCircle       },
    'cart.abandoned':    { labelKey: 'automation.trigger_cart_abandoned',    Icon: ShoppingCart  },
    'customer.created':  { labelKey: 'automation.trigger_customer_created',  Icon: UserPlus      },
};

const formatDate = (iso) => {
    if (!iso) return '—';
    const d = new Date(iso);
    if (Number.isNaN(d.getTime())) return '—';
    return d.toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' });
};

export default function AutomationIndex({ automations, social_triggers = [] }) {
    const importRef = useRef(null);
    const [importError,setImportError] = useState('');
    const [importing,setImporting] = useState(false);
    const importFile = (event) => {
        const file=event.target.files?.[0];
        if (!file) return;
        setImportError(''); setImporting(true);
        router.post(route('client.automations.import'), {file}, {forceFormData:true,
            onError:errors=>setImportError(errors.file || Object.values(errors)[0] || 'Não foi possível importar.'),
            onFinish:()=>{setImporting(false);if(importRef.current)importRef.current.value='';},
        });
    };
    const { t } = useTranslation();
    const { props } = usePage();
    const flash = props.flash ?? {};
    const [showCreate, setShowCreate] = useState(false);
    const [showAi, setShowAi] = useState(false);
    const [aiPrompt, setAiPrompt] = useState('');
    const [aiLoading, setAiLoading] = useState(false);
    const [aiError, setAiError] = useState(null);

    const { data, setData, post, processing, reset } = useForm({ name: '' });

    const handleCreate = (e) => {
        e.preventDefault();
        post(route('client.automations.store'), { onSuccess: () => { reset(); setShowCreate(false); } });
    };

    // Generate a full automation from a prompt, persist it, then open the new builder.
    const handleGenerate = () => {
        setAiLoading(true);
        setAiError(null);
        axios.post(route('client.automations.generate'), { prompt: aiPrompt, persist: true })
            .then(res => {
                if (res.data?.ok && res.data.redirect) {
                    router.visit(res.data.redirect);
                } else {
                    setAiError(res.data?.error || t('automation.ai_failed'));
                    setAiLoading(false);
                }
            })
            .catch(err => {
                setAiError(err.response?.data?.error || err.response?.data?.message || t('automation.ai_failed'));
                setAiLoading(false);
            });
    };

    const toggleStatus = (automation) => {
        const newStatus = automation.status === 'active' ? 'paused' : 'active';
        router.put(route('client.automations.update', automation.uuid), { status: newStatus }, { preserveScroll: true });
    };

    const handleDelete = (automation) => {
        if (confirm(t('automation.delete_confirm', { name: automation.name }))) {
            router.delete(route('client.automations.destroy', automation.uuid));
        }
    };

    return (
        <ClientLayout title={t('automation.title')}>
            <Head title={t('automation.title')} />
            <div className="space-y-5">
                {importError && <p role="alert" className="rounded-lg bg-red-50 p-3 text-sm text-red-700">{importError}</p>}
                <div className="flex items-center justify-between gap-3">
                    <div>
                        <h2 className="text-xl font-semibold text-neutral-900 dark:text-neutral-100">{t('automation.title')}</h2>
                        <p className="text-sm text-neutral-500 dark:text-neutral-400 mt-0.5">{t('automation.subtitle')}</p>
                    </div>
                    {(
                        <div className="flex flex-wrap items-center gap-2">
                            <input ref={importRef} type="file" accept=".json,application/json" className="hidden" onChange={importFile} aria-label="Arquivo de automação" />
                            <button disabled={importing} onClick={()=>importRef.current?.click()} className="rounded-lg border px-3 py-2 text-sm font-medium disabled:opacity-50">{importing?'Importando…':'Importar'}</button>
                            <button onClick={() => { setAiError(null); setShowAi(true); }} className="ai-glow flex items-center gap-1.5 rounded-lg border border-purple-200 dark:border-purple-800 bg-purple-50 dark:bg-purple-900/30 px-3 py-2 text-sm font-medium text-purple-700 dark:text-purple-300 hover:bg-purple-100 dark:hover:bg-purple-900/50 transition">
                                <Sparkles className="h-4 w-4" /> {t('automation.ai_generate')}
                            </button>
                            <button onClick={() => setShowCreate(true)} className="flex items-center gap-1.5 rounded-lg bg-brand-600 px-3 py-2 text-sm font-medium text-white hover:bg-brand-700 transition">
                                <Plus className="h-4 w-4" /> {t('automation.new_automation')}
                            </button>
                        </div>
                    )}
                </div>
                <div className="rounded-xl border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-900">
                    <b>Como ativar:</b> crie ou edite o fluxo, escolha um gatilho, conecte as etapas e salve. Depois clique no botão de reprodução <Play className="mx-1 inline h-4 w-4" /> do cartão — ou em <b>Ativar</b> dentro do editor. Somente fluxos com estado <b>Ativo</b> processam eventos reais.
                </div>

                {flash.success && <div className="rounded-lg bg-green-50 dark:bg-green-900/30 text-green-800 dark:text-green-200 px-4 py-2 text-sm">{flash.success}</div>}

                {automations.length === 0 ? (
                    <div className="rounded-xl border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-900">
                        <EmptyState
                            icon={<Zap className="h-8 w-8" />}
                            title={t('automation.empty_title')}
                            description={t('automation.empty_description')}
                            action={{ label: t('automation.new_automation'), onClick: () => setShowCreate(true) }}
                        />
                    </div>
                ) : (
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
                        {automations.map(automation => {
                            const trigger = TRIGGER_META[automation.trigger_type] ?? social_triggers.find(tr => tr.value === automation.trigger_type);
                            const TriggerIcon = trigger?.Icon ?? Zap;
                            const stepCount = (automation.nodes ?? []).filter(n => n.type !== 'trigger').length;
                            return (
                                <div
                                    key={automation.id}
                                    className="group flex flex-col rounded-xl border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-900 p-5 transition hover:border-brand-300 hover:shadow-md dark:hover:border-brand-600"
                                >
                                    {/* Header */}
                                    <div className="flex items-start justify-between gap-3">
                                        <div className="flex min-w-0 items-center gap-2.5">
                                            <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-brand-50 dark:bg-brand-900/30">
                                                <Zap className="h-4 w-4 text-brand-500" />
                                            </span>
                                            <Link
                                                href={route('client.automations.edit', automation.uuid)}
                                                className="truncate font-semibold text-neutral-900 hover:text-brand-600 dark:text-neutral-100 dark:hover:text-brand-400"
                                                title={automation.name}
                                            >
                                                {automation.name}
                                            </Link>
                                        </div>
                                        <span className={`shrink-0 rounded-full px-2 py-0.5 text-xs font-medium ${STATUS_COLORS[automation.status] ?? ''}`}>
                                            {STATUS_LABEL_KEYS[automation.status] ? t(STATUS_LABEL_KEYS[automation.status]) : automation.status}
                                        </span>
                                    </div>

                                    {/* Trigger */}
                                    <div className="mt-4 flex items-center gap-2 text-sm text-neutral-600 dark:text-neutral-300">
                                        <TriggerIcon className="h-4 w-4 shrink-0 text-neutral-400" />
                                        <span className="truncate">{trigger ? (trigger.label ?? t(trigger.labelKey)) : t('automation.no_trigger')}</span>
                                    </div>

                                    {/* Stats */}
                                    <div className="mt-4 grid grid-cols-3 divide-x divide-neutral-100 rounded-lg bg-neutral-50 py-3 text-center dark:divide-neutral-800 dark:bg-neutral-800/50">
                                        <div className="px-1">
                                            <div className="text-base font-semibold text-neutral-900 dark:text-neutral-100">{automation.runs_count ?? 0}</div>
                                            <div className="mt-0.5 text-[11px] uppercase tracking-wide text-neutral-400">{t('automation.col_runs')}</div>
                                        </div>
                                        <div className="px-1">
                                            <div className="text-base font-semibold text-neutral-900 dark:text-neutral-100">{stepCount}</div>
                                            <div className="mt-0.5 text-[11px] uppercase tracking-wide text-neutral-400">{t('automation.steps')}</div>
                                        </div>
                                        <div className="px-1">
                                            <div className="flex items-center justify-center gap-1 text-sm font-semibold text-neutral-900 dark:text-neutral-100">
                                                <Clock className="h-3.5 w-3.5 text-neutral-400" />
                                                {formatDate(automation.updated_at)}
                                            </div>
                                            <div className="mt-0.5 text-[11px] uppercase tracking-wide text-neutral-400">{t('automation.updated')}</div>
                                        </div>
                                    </div>

                                    {/* Footer actions */}
                                    <div className="mt-4 flex items-center justify-between border-t border-neutral-100 pt-3 dark:border-neutral-800">
                                        <Link
                                            href={route('client.automations.edit', automation.uuid)}
                                            className="inline-flex items-center gap-1.5 text-sm font-medium text-neutral-600 hover:text-brand-600 dark:text-neutral-300 dark:hover:text-brand-400"
                                        >
                                            <Pencil className="h-3.5 w-3.5" /> {t('common.edit')}
                                        </Link>
                                        <div className="flex items-center gap-1">
                                            <a href={route('client.automations.export', automation.uuid)} className="rounded-md px-2 py-1.5 text-xs text-brand-600 hover:bg-neutral-100" aria-label={`Exportar ${automation.name}`}>Exportar</a>
                                            <Link href={route('client.automations.runs', automation.uuid)} className="rounded-md p-1.5 text-neutral-400 transition hover:bg-neutral-100 hover:text-brand-600 dark:hover:bg-neutral-800" title={t('automation.view_runs')}>
                                                <BarChart2 className="h-4 w-4" />
                                            </Link>
                                            <button onClick={() => toggleStatus(automation)} className="rounded-md p-1.5 text-neutral-400 transition hover:bg-neutral-100 hover:text-brand-600 dark:hover:bg-neutral-800" title={automation.status === 'active' ? t('automation.pause') : t('automation.activate')}>
                                                {automation.status === 'active' ? <Pause className="h-4 w-4" /> : <Play className="h-4 w-4" />}
                                            </button>
                                            <button onClick={() => handleDelete(automation)} className="rounded-md p-1.5 text-neutral-400 transition hover:bg-neutral-100 hover:text-red-500 dark:hover:bg-neutral-800" title={t('common.delete')}>
                                                <Trash2 className="h-4 w-4" />
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                )}
            </div>

            {showCreate && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
                    <div className="w-full max-w-sm rounded-xl bg-white dark:bg-neutral-900 p-6 shadow-xl space-y-4">
                        <h3 className="text-lg font-semibold text-neutral-900 dark:text-neutral-100">{t('automation.new_automation')}</h3>
                        <form onSubmit={handleCreate} className="space-y-3">
                            <div>
                                <label className="text-xs font-medium text-neutral-500 dark:text-neutral-400">{t('common.name')}</label>
                                <input type="text" value={data.name} onChange={e => setData('name', e.target.value)} required placeholder={t('automation.name_placeholder')} className="mt-1 w-full rounded border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 px-3 py-1.5 text-sm" />
                            </div>
                            <div className="flex gap-2 pt-2">
                                <button type="submit" disabled={processing} className="flex-1 rounded-lg bg-brand-600 py-2 text-sm font-medium text-white hover:bg-brand-700 disabled:opacity-60 transition">
                                    {processing ? t('automation.creating') : t('common.create')}
                                </button>
                                <button type="button" onClick={() => setShowCreate(false)} className="rounded-lg border border-neutral-300 dark:border-neutral-600 px-4 py-2 text-sm hover:bg-neutral-50 dark:hover:bg-neutral-800 transition">
                                    {t('common.cancel')}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {showAi && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
                    <div className="w-full max-w-lg rounded-xl bg-white dark:bg-neutral-900 p-6 shadow-xl space-y-4">
                        <div className="flex items-center gap-2.5">
                            <span className="flex h-9 w-9 items-center justify-center rounded-lg bg-purple-50 text-purple-600 dark:bg-purple-900/30"><Sparkles className="h-4 w-4" /></span>
                            <div>
                                <h3 className="text-lg font-semibold text-neutral-900 dark:text-neutral-100">{t('automation.ai_title')}</h3>
                                <p className="text-xs text-neutral-500">{t('automation.ai_subtitle')}</p>
                            </div>
                        </div>
                        <textarea
                            autoFocus rows={5} value={aiPrompt} onChange={e => setAiPrompt(e.target.value)} disabled={aiLoading}
                            placeholder={t('automation.ai_placeholder')}
                            className="w-full rounded-lg border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 px-3 py-2 text-sm resize-none focus:outline-none focus:ring-2 focus:ring-brand-500"
                        />
                        <div className="flex flex-wrap gap-2">
                            {['automation.ai_example_welcome', 'automation.ai_example_abandoned', 'automation.ai_example_faq'].map(k => (
                                <button key={k} disabled={aiLoading} onClick={() => setAiPrompt(t(k))} className="rounded-full border border-neutral-200 dark:border-neutral-700 px-3 py-1 text-xs text-neutral-600 dark:text-neutral-300 hover:bg-neutral-50 dark:hover:bg-neutral-800 transition">
                                    {t(k)}
                                </button>
                            ))}
                        </div>
                        {aiError && (
                            <div className="flex items-start gap-2 rounded-lg bg-red-50 dark:bg-red-900/30 px-3 py-2 text-sm text-red-700 dark:text-red-300">
                                <AlertTriangle className="h-4 w-4 shrink-0 mt-0.5" />{aiError}
                            </div>
                        )}
                        <p className="flex items-start gap-1.5 text-xs text-neutral-400">
                            <Sparkles className="h-3.5 w-3.5 shrink-0 mt-0.5" />{t('automation.ai_disclaimer')}
                        </p>
                        <div className="flex gap-2 pt-1">
                            <button disabled={aiLoading || !aiPrompt.trim()} onClick={handleGenerate} className="ai-glow flex flex-1 items-center justify-center gap-1.5 rounded-lg bg-purple-600 py-2 text-sm font-medium text-white hover:bg-purple-700 disabled:opacity-60 transition">
                                {aiLoading ? <><Loader2 className="h-4 w-4 animate-spin" /> {t('automation.ai_generating')}</> : <><Sparkles className="h-4 w-4" /> {t('automation.ai_generate')}</>}
                            </button>
                            <button type="button" disabled={aiLoading} onClick={() => setShowAi(false)} className="rounded-lg border border-neutral-300 dark:border-neutral-600 px-4 py-2 text-sm hover:bg-neutral-50 dark:hover:bg-neutral-800 transition">
                                {t('common.cancel')}
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </ClientLayout>
    );
}
