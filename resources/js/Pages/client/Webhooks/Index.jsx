import { useState } from 'react';
import ClientLayout from '@/Layouts/ClientLayout';
import { Head, router, useForm, usePage, Link } from '@inertiajs/react';
import { Webhook, Plus, Pencil, Trash2, RefreshCw, Play, Eye, ChevronRight, Check, X } from 'lucide-react';
import { formatInTz } from '@/Utils/datetime';
import { useTranslation } from 'react-i18next';

const EVENTS = ['contact.created', 'message.received', 'test.ping'];

function EndpointForm({ endpoint = null, onClose }) {
    const { t } = useTranslation();
    const { data, setData, post, put, processing, errors } = useForm({
        url: endpoint?.url ?? '',
        description: endpoint?.description ?? '',
        events: endpoint?.events ?? [],
        enabled: endpoint?.enabled ?? true,
    });

    const toggleEvent = (e) => {
        setData('events', data.events.includes(e)
            ? data.events.filter(x => x !== e)
            : [...data.events, e]
        );
    };

    const submit = (ev) => {
        ev.preventDefault();
        const payload = { ...data, events: data.events.length ? data.events : null };
        if (endpoint) {
            put(route('client.webhooks.update', endpoint.id), { data: payload, onSuccess: onClose });
        } else {
            post(route('client.webhooks.store'), { data: payload, onSuccess: onClose });
        }
    };

    return (
        <form onSubmit={submit} className="space-y-4">
            <div>
                <label className="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-1">{t('webhook.endpoint_url')}</label>
                <input
                    type="url"
                    value={data.url}
                    onChange={e => setData('url', e.target.value)}
                    className="w-full border border-neutral-300 dark:border-neutral-600 rounded-lg px-3 py-2 text-sm dark:bg-neutral-700 dark:text-white"
                    placeholder="https://your-app.com/webhooks"
                    required
                />
                {errors.url && <p className="text-coral-600 text-xs mt-1">{errors.url}</p>}
            </div>
            <div>
                <label className="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-1">{t('webhook.description')}</label>
                <input
                    type="text"
                    value={data.description}
                    onChange={e => setData('description', e.target.value)}
                    className="w-full border border-neutral-300 dark:border-neutral-600 rounded-lg px-3 py-2 text-sm dark:bg-neutral-700 dark:text-white"
                    placeholder={t('webhook.description_placeholder')}
                />
            </div>
            <div>
                <label className="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-2">{t('webhook.events_label')}</label>
                <div className="grid grid-cols-2 gap-2">
                    {EVENTS.map(ev => (
                        <label key={ev} className="flex items-center gap-2 text-sm text-neutral-700 dark:text-neutral-300 cursor-pointer">
                            <input
                                type="checkbox"
                                checked={data.events.includes(ev)}
                                onChange={() => toggleEvent(ev)}
                                className="rounded"
                            />
                            <code className="text-xs">{ev}</code>
                        </label>
                    ))}
                </div>
            </div>
            <label className="flex items-center gap-2 text-sm text-neutral-700 dark:text-neutral-300">
                <input type="checkbox" checked={data.enabled} onChange={e => setData('enabled', e.target.checked)} className="rounded" />
                {t('common.enabled')}
            </label>
            <div className="flex justify-end gap-2 pt-2">
                <button type="button" onClick={onClose} className="px-4 py-2 text-sm border border-neutral-300 dark:border-neutral-600 rounded-lg">{t('common.cancel')}</button>
                <button type="submit" disabled={processing} className="px-4 py-2 text-sm bg-brand-500 text-white rounded-soft hover:bg-brand-600 shadow-soft disabled:opacity-50 transition-all duration-150">
                    {endpoint ? t('webhook.update_endpoint') : t('webhook.create_endpoint')}
                </button>
            </div>
        </form>
    );
}

export default function WebhooksIndex({ endpoints }) {
    const { t } = useTranslation();
    const { flash, timezone } = usePage().props;
    const userTz = timezone || 'America/Sao_Paulo';
    const [showCreate, setShowCreate] = useState(false);
    const [editing, setEditing] = useState(null);
    const [revealedSecret, setRevealedSecret] = useState({});

    const handleDelete = (ep) => {
        if (!confirm(t('webhook.delete_confirm', { url: ep.url }))) return;
        router.delete(route('client.webhooks.destroy', ep.id));
    };

    const handleTest = (ep) => {
        router.post(route('client.webhooks.test', ep.id));
    };

    const handleRotateSecret = async (ep) => {
        if (!confirm(t('webhook.rotate_confirm'))) return;
        const res = await fetch(route('client.webhooks.rotate-secret', ep.id), {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content },
        });
        const json = await res.json();
        if (json.secret) {
            setRevealedSecret(s => ({ ...s, [ep.id]: json.secret }));
        }
    };

    return (
        <ClientLayout title={t('webhook.title')}>
            <Head title={t('webhook.title')} />
            <div className="space-y-6 max-w-4xl">
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-3">
                        <Webhook className="h-6 w-6 text-brand-600 dark:text-brand-400" />
                        <div>
                            <h1 className="text-xl font-bold text-neutral-900 dark:text-white">{t('webhook.endpoints_heading')}</h1>
                            <p className="text-sm text-neutral-500 dark:text-neutral-400">{t('webhook.endpoints_subtitle')}</p>
                        </div>
                    </div>
                    {(
                        <button onClick={() => setShowCreate(true)} className="flex items-center gap-2 px-4 py-2 bg-brand-500 text-white text-sm rounded-soft hover:bg-brand-600 shadow-soft transition-all duration-150">
                            <Plus className="h-4 w-4" /> {t('webhook.add_endpoint')}
                        </button>
                    )}
                </div>

                {flash?.success && (
                    <div className="rounded-lg bg-green-50 dark:bg-green-900/20 text-green-800 dark:text-green-200 px-4 py-3 text-sm">{flash.success}</div>
                )}
                {flash?.error && (
                    <div className="rounded-lg bg-coral-50 dark:bg-coral-900/20 text-coral-800 dark:text-coral-200 px-4 py-3 text-sm">{flash.error}</div>
                )}

                {showCreate && (
                    <div className="bg-white dark:bg-neutral-800 rounded-xl border border-neutral-200 dark:border-neutral-700 p-6">
                        <h2 className="text-base font-semibold mb-4 text-neutral-900 dark:text-white">{t('webhook.new_endpoint')}</h2>
                        <EndpointForm onClose={() => setShowCreate(false)} />
                    </div>
                )}

                <div className="space-y-3">
                    {endpoints.map(ep => (
                        <div key={ep.id} className="bg-white dark:bg-neutral-800 rounded-xl border border-neutral-200 dark:border-neutral-700 overflow-hidden">
                            <div className="p-4">
                                {editing?.id === ep.id ? (
                                    <>
                                        <EndpointForm endpoint={ep} onClose={() => setEditing(null)} />
                                    </>
                                ) : (
                                    <div className="flex items-start justify-between gap-4">
                                        <div className="flex-1 min-w-0">
                                            <div className="flex items-center gap-2">
                                                <span className={`h-2 w-2 rounded-full ${ep.enabled ? 'bg-green-500' : 'bg-neutral-400'}`} />
                                                <code className="text-sm font-medium text-neutral-900 dark:text-white truncate">{ep.url}</code>
                                            </div>
                                            {ep.description && <p className="text-xs text-neutral-500 dark:text-neutral-400 mt-1">{ep.description}</p>}
                                            {ep.events?.length > 0 && (
                                                <div className="flex flex-wrap gap-1 mt-2">
                                                    {ep.events.map(e => (
                                                        <code key={e} className="px-1.5 py-0.5 bg-neutral-100 dark:bg-neutral-700 text-xs rounded">{e}</code>
                                                    ))}
                                                </div>
                                            )}
                                            {!ep.events?.length && (
                                                <p className="text-xs text-neutral-400 dark:text-neutral-500 mt-1">{t('webhook.listening_all')}</p>
                                            )}

                                            {revealedSecret[ep.id] && (
                                                <div className="mt-2 p-2 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg">
                                                    <p className="text-xs text-amber-700 dark:text-amber-400 font-medium mb-1">{t('webhook.new_secret_notice')}</p>
                                                    <code className="text-xs font-mono text-neutral-900 dark:text-white break-all">{revealedSecret[ep.id]}</code>
                                                </div>
                                            )}
                                        </div>
                                        <div className="flex items-center gap-1 shrink-0">
                                            <button onClick={() => handleTest(ep)} className="p-1.5 text-neutral-400 hover:text-brand-600 dark:hover:text-brand-400 transition" title={t('webhook.send_test')}>
                                                <Play className="h-4 w-4" />
                                            </button>
                                            <button onClick={() => handleRotateSecret(ep)} className="p-1.5 text-neutral-400 hover:text-amber-600" title={t('webhook.rotate_secret')}>
                                                <RefreshCw className="h-4 w-4" />
                                            </button>
                                            <Link href={route('client.webhooks.deliveries', ep.id)} className="p-1.5 text-neutral-400 hover:text-neutral-700 dark:hover:text-neutral-200" title={t('webhook.view_deliveries')}>
                                                <Eye className="h-4 w-4" />
                                            </Link>
                                            <button onClick={() => setEditing(ep)} className="p-1.5 text-neutral-400 hover:text-brand-600 dark:hover:text-brand-400 transition" title={t('common.edit')}>
                                                <Pencil className="h-4 w-4" />
                                            </button>
                                            <button onClick={() => handleDelete(ep)} className="p-1.5 text-neutral-400 hover:text-coral-600" title={t('common.delete')}>
                                                <Trash2 className="h-4 w-4" />
                                            </button>
                                        </div>
                                    </div>
                                )}
                            </div>
                            {ep.recent_deliveries?.length > 0 && editing?.id !== ep.id && (
                                <div className="border-t border-neutral-100 dark:border-neutral-700 px-4 py-2">
                                    <p className="text-xs text-neutral-400 dark:text-neutral-500 mb-1.5">{t('webhook.recent_deliveries')}</p>
                                    <div className="space-y-1">
                                        {ep.recent_deliveries.map(d => (
                                            <div key={d.id} className="flex items-center gap-2 text-xs text-neutral-600 dark:text-neutral-400">
                                                {d.response_status >= 200 && d.response_status < 300
                                                    ? <Check className="h-3 w-3 text-green-500" />
                                                    : <X className="h-3 w-3 text-coral-500" />
                                                }
                                                <code>{d.event}</code>
                                                <span className="text-neutral-400">{d.response_status ?? '—'}</span>
                                                <span className="ml-auto text-neutral-400">{formatInTz(d.created_at, userTz)}</span>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            )}
                        </div>
                    ))}

                    {endpoints.length === 0 && (
                        <div className="text-center py-12 text-neutral-400 dark:text-neutral-500">
                            <Webhook className="h-10 w-10 mx-auto mb-3 opacity-30" />
                            <p>{t('webhook.no_endpoints')}</p>
                        </div>
                    )}
                </div>
            </div>
        </ClientLayout>
    );
}
