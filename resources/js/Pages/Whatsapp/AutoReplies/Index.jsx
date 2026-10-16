import { Head, useForm, router, usePage } from '@inertiajs/react';
import ClientLayout from '@/Layouts/ClientLayout';
import EmptyState from '@/Components/EmptyState';
import { Plus, Trash2, ToggleLeft, ToggleRight, Zap, Pencil, X } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';

const TRIGGER_TYPES  = ['keyword', 'welcome', 'away', 'out_of_hours'];
const MATCH_MODES    = ['exact', 'contains', 'regex'];
const RESPONSE_KINDS = ['text', 'template', 'media'];
const MEDIA_TYPES    = ['image', 'video', 'document'];

const TRIGGER_LABEL_KEYS = {
    keyword:       'whatsapp.auto_replies_trigger_keyword',
    welcome:       'whatsapp.auto_replies_trigger_welcome',
    away:          'whatsapp.auto_replies_trigger_away',
    out_of_hours:  'whatsapp.auto_replies_trigger_out_of_hours',
};

const MATCH_MODE_LABEL_KEYS = {
    exact:    'whatsapp.auto_replies_match_exact',
    contains: 'whatsapp.auto_replies_match_contains',
    regex:    'whatsapp.auto_replies_match_regex',
};

const RESPONSE_KIND_LABEL_KEYS = {
    text:     'whatsapp.auto_replies_response_text',
    template: 'whatsapp.auto_replies_response_template',
    media:    'whatsapp.auto_replies_response_media',
};

const MEDIA_TYPE_LABEL_KEYS = {
    image:    'whatsapp.auto_replies_media_image',
    video:    'whatsapp.auto_replies_media_video',
    document: 'whatsapp.auto_replies_media_document',
};

const emptyForm = {
    channel_account_id: '',
    trigger_type:  'keyword',
    match_mode:    'contains',
    keywords:      [],
    response_kind: 'text',
    payload_json:  { text: '' },
    enabled:       true,
    priority:      0,
};

function RuleForm({ data, setData, errors, onSubmit, onCancel, processing, submitLabel, channelAccounts = [] }) {
    const { t } = useTranslation();
    return (
        <form onSubmit={onSubmit} className="space-y-3">
            <div>
                <label className="text-xs font-medium text-neutral-500 dark:text-neutral-400">Aplicar ao canal</label>
                <select value={data.channel_account_id} onChange={e => setData('channel_account_id', e.target.value)} className="mt-1 min-h-11 w-full rounded border border-neutral-300 bg-white px-3 text-sm dark:border-neutral-600 dark:bg-neutral-800">
                    <option value="">WhatsApp — regra geral</option>
                    {channelAccounts.map(account => <option key={account.id} value={account.id}>{account.channel === 'webchat' ? 'Chat do site' : 'WhatsApp'} — {account.display_name}</option>)}
                </select>
                {errors.channel_account_id && <p className="mt-1 text-xs text-red-500">{errors.channel_account_id}</p>}
            </div>
            {/* Trigger type */}
            <div>
                <label className="text-xs font-medium text-neutral-500 dark:text-neutral-400">{t('whatsapp.auto_replies_trigger_type')}</label>
                <select
                    value={data.trigger_type}
                    onChange={e => setData('trigger_type', e.target.value)}
                    className="mt-1 w-full rounded border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 px-3 py-1.5 text-sm"
                >
                    {TRIGGER_TYPES.map(tt => <option key={tt} value={tt}>{t(TRIGGER_LABEL_KEYS[tt] ?? '')}</option>)}
                </select>
            </div>

            {/* Keywords (only for keyword trigger) */}
            {data.trigger_type === 'keyword' && (
                <div>
                    <label className="text-xs font-medium text-neutral-500 dark:text-neutral-400">
                        {t('whatsapp.auto_replies_keywords')} <span className="text-neutral-400 font-normal">{t('whatsapp.auto_replies_keywords_hint')}</span>
                    </label>
                    <input
                        type="text"
                        value={(data.keywords ?? []).join(', ')}
                        onChange={e => setData('keywords', e.target.value.split(',').map(k => k.trim()).filter(Boolean))}
                        placeholder={t('whatsapp.auto_replies_keywords_placeholder')}
                        className="mt-1 w-full rounded border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 px-3 py-1.5 text-sm"
                    />
                    {errors.keywords && <p className="text-xs text-red-500 mt-1">{errors.keywords}</p>}

                    <label className="text-xs font-medium text-neutral-500 dark:text-neutral-400 mt-2 block">{t('whatsapp.auto_replies_match_mode')}</label>
                    <select
                        value={data.match_mode}
                        onChange={e => setData('match_mode', e.target.value)}
                        className="mt-1 w-full rounded border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 px-3 py-1.5 text-sm"
                    >
                        {MATCH_MODES.map(m => <option key={m} value={m}>{t(MATCH_MODE_LABEL_KEYS[m] ?? '')}</option>)}
                    </select>
                </div>
            )}

            {/* Response kind */}
            <div>
                <label className="text-xs font-medium text-neutral-500 dark:text-neutral-400">{t('whatsapp.auto_replies_response_type')}</label>
                <select
                    value={data.response_kind}
                    onChange={e => setData('response_kind', e.target.value)}
                    className="mt-1 w-full rounded border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 px-3 py-1.5 text-sm"
                >
                    {RESPONSE_KINDS.map(k => <option key={k} value={k}>{t(RESPONSE_KIND_LABEL_KEYS[k] ?? '')}</option>)}
                </select>
            </div>

            {/* Response content */}
            {data.response_kind === 'text' && (
                <div>
                    <label className="text-xs font-medium text-neutral-500 dark:text-neutral-400">{t('whatsapp.auto_replies_reply_text')}</label>
                    <textarea
                        value={data.payload_json?.text ?? ''}
                        onChange={e => setData('payload_json', { ...data.payload_json, text: e.target.value })}
                        rows={3}
                        placeholder={t('whatsapp.auto_replies_reply_text_placeholder')}
                        className="mt-1 w-full rounded border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 px-3 py-1.5 text-sm resize-none"
                    />
                    {errors.payload_json && <p className="text-xs text-red-500 mt-1">{errors.payload_json}</p>}
                </div>
            )}

            {data.response_kind === 'template' && (
                <div className="space-y-2">
                    <div>
                        <label className="text-xs font-medium text-neutral-500 dark:text-neutral-400">{t('whatsapp.auto_replies_template_name')}</label>
                        <input
                            type="text"
                            value={data.payload_json?.template_name ?? ''}
                            onChange={e => setData('payload_json', { ...data.payload_json, template_name: e.target.value })}
                            placeholder={t('whatsapp.auto_replies_template_name_placeholder')}
                            className="mt-1 w-full rounded border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 px-3 py-1.5 text-sm"
                        />
                    </div>
                    <div>
                        <label className="text-xs font-medium text-neutral-500 dark:text-neutral-400">{t('whatsapp.auto_replies_language_code')}</label>
                        <input
                            type="text"
                            value={data.payload_json?.language ?? 'en'}
                            onChange={e => setData('payload_json', { ...data.payload_json, language: e.target.value })}
                            placeholder="en"
                            className="mt-1 w-full rounded border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 px-3 py-1.5 text-sm"
                        />
                    </div>
                </div>
            )}

            {data.response_kind === 'media' && (
                <div className="space-y-2">
                    <div>
                        <label className="text-xs font-medium text-neutral-500 dark:text-neutral-400">{t('whatsapp.auto_replies_media_type')}</label>
                        <select
                            value={data.payload_json?.media_type ?? 'image'}
                            onChange={e => setData('payload_json', { ...data.payload_json, media_type: e.target.value })}
                            className="mt-1 w-full rounded border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 px-3 py-1.5 text-sm"
                        >
                            {MEDIA_TYPES.map(mt => <option key={mt} value={mt}>{t(MEDIA_TYPE_LABEL_KEYS[mt] ?? '')}</option>)}
                        </select>
                    </div>
                    <div>
                        <label className="text-xs font-medium text-neutral-500 dark:text-neutral-400">{t('whatsapp.auto_replies_media_id')}</label>
                        <input
                            type="text"
                            value={data.payload_json?.media_id ?? ''}
                            onChange={e => setData('payload_json', { ...data.payload_json, media_id: e.target.value })}
                            placeholder={t('whatsapp.auto_replies_media_id_placeholder')}
                            className="mt-1 w-full rounded border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 px-3 py-1.5 text-sm"
                        />
                    </div>
                    <div>
                        <label className="text-xs font-medium text-neutral-500 dark:text-neutral-400">{t('whatsapp.auto_replies_caption')}</label>
                        <input
                            type="text"
                            value={data.payload_json?.caption ?? ''}
                            onChange={e => setData('payload_json', { ...data.payload_json, caption: e.target.value })}
                            className="mt-1 w-full rounded border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 px-3 py-1.5 text-sm"
                        />
                    </div>
                </div>
            )}

            {/* Priority */}
            <div>
                <label className="text-xs font-medium text-neutral-500 dark:text-neutral-400">{t('whatsapp.auto_replies_priority')} <span className="font-normal">{t('whatsapp.auto_replies_priority_hint')}</span></label>
                <input
                    type="number"
                    min={0}
                    value={data.priority}
                    onChange={e => setData('priority', parseInt(e.target.value, 10) || 0)}
                    className="mt-1 w-full rounded border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 px-3 py-1.5 text-sm"
                />
            </div>

            {/* Enabled */}
            <label className="flex items-center gap-2 cursor-pointer select-none">
                <input
                    type="checkbox"
                    checked={data.enabled}
                    onChange={e => setData('enabled', e.target.checked)}
                    className="rounded border-neutral-300 dark:border-neutral-600 text-brand-600"
                />
                <span className="text-sm text-neutral-700 dark:text-neutral-300">{t('common.enabled')}</span>
            </label>

            <div className="flex gap-2 pt-2">
                <button
                    type="submit"
                    disabled={processing}
                    className="flex-1 rounded-lg bg-brand-600 py-2 text-sm font-medium text-white hover:bg-brand-700 disabled:opacity-60 transition"
                >
                    {processing ? t('whatsapp.auto_replies_saving') : (submitLabel ?? t('whatsapp.auto_replies_save_rule'))}
                </button>
                <button
                    type="button"
                    onClick={onCancel}
                    className="rounded-lg border border-neutral-300 dark:border-neutral-600 px-4 py-2 text-sm text-neutral-700 dark:text-neutral-300 hover:bg-neutral-50 dark:hover:bg-neutral-800 transition"
                >
                    {t('common.cancel')}
                </button>
            </div>
        </form>
    );
}

export default function WhatsappAutoRepliesIndex({ rules, channelAccounts = [] }) {
    const { t } = useTranslation();
    const { props } = usePage();
    const flash = props.flash ?? {};
    const [showCreate, setShowCreate] = useState(false);
    const [editingRule, setEditingRule] = useState(null);

    /* ── Create form ── */
    const createForm = useForm(emptyForm);

    const handleCreate = (e) => {
        e.preventDefault();
        createForm.post(route('client.whatsapp.auto-replies.store'), {
            onSuccess: () => { createForm.reset(); setShowCreate(false); },
        });
    };

    /* ── Edit form ── */
    const editForm = useForm(emptyForm);

    const openEdit = (rule) => {
        editForm.setData({
            channel_account_id: rule.channel_account_id ?? '',
            trigger_type:  rule.trigger_type,
            match_mode:    rule.match_mode,
            keywords:      rule.keywords ?? [],
            response_kind: rule.response_kind,
            payload_json:  rule.payload_json ?? { text: '' },
            enabled:       rule.enabled,
            priority:      rule.priority ?? 0,
        });
        setEditingRule(rule);
    };

    const handleEdit = (e) => {
        e.preventDefault();
        editForm.put(route('client.whatsapp.auto-replies.update', editingRule.id), {
            onSuccess: () => setEditingRule(null),
        });
    };

    /* ── Toggle / delete ── */
    const handleToggle = (rule) => {
        router.put(
            route('client.whatsapp.auto-replies.update', rule.id),
            { enabled: !rule.enabled },
            { preserveScroll: true },
        );
    };

    const handleDelete = (id) => {
        if (confirm(t('whatsapp.auto_replies_delete_confirm'))) {
            router.delete(route('client.whatsapp.auto-replies.destroy', id), { preserveScroll: true });
        }
    };

    return (
        <ClientLayout title={t('whatsapp.auto_replies_title')}>
            <Head title={t('whatsapp.auto_replies_head_title')} />
            <div className="space-y-5">
                <div className="flex items-center justify-between gap-3">
                    <div>
                        <h2 className="text-xl font-semibold text-neutral-900 dark:text-neutral-100">{t('whatsapp.auto_replies_title')}</h2>
                        <p className="text-sm text-neutral-500 dark:text-neutral-400 mt-0.5">
                            {t('whatsapp.auto_replies_subtitle')}
                        </p>
                    </div>
                    {(
                        <button
                            onClick={() => setShowCreate(true)}
                            className="flex items-center gap-1.5 rounded-lg bg-brand-600 px-3 py-2 text-sm font-medium text-white hover:bg-brand-700 transition"
                        >
                            <Plus className="h-4 w-4" /> {t('whatsapp.auto_replies_add_rule')}
                        </button>
                    )}
                </div>

                {flash.success && (
                    <div className="rounded-lg bg-green-50 dark:bg-green-900/30 text-green-800 dark:text-green-200 px-4 py-2 text-sm">
                        {flash.success}
                    </div>
                )}

                <div className="space-y-3">
                    {rules.map(rule => (
                        <div
                            key={rule.id}
                            className="rounded-xl border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-900 p-4 flex items-center gap-4"
                        >
                            <div className="flex-1 min-w-0">
                                <div className="flex items-center gap-2 flex-wrap">
                                    <span className="rounded-full bg-brand-50 px-2 py-0.5 text-xs font-medium text-brand-700 dark:bg-brand-900/30 dark:text-brand-300">
                                        {rule.channel_account_id ? (channelAccounts.find(a => a.id === rule.channel_account_id)?.display_name ?? 'Canal específico') : 'WhatsApp — regra geral'}
                                    </span>
                                    <span className={`rounded-full px-2 py-0.5 text-xs font-medium ${
                                        rule.trigger_type === 'keyword'
                                            ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300'
                                            : 'bg-purple-100 text-purple-700 dark:bg-purple-900/40 dark:text-purple-300'
                                    }`}>
                                        {TRIGGER_LABEL_KEYS[rule.trigger_type] ? t(TRIGGER_LABEL_KEYS[rule.trigger_type]) : rule.trigger_type}
                                    </span>
                                    {rule.keywords?.length > 0 && (
                                        <span className="text-sm text-neutral-600 dark:text-neutral-400">
                                            <strong>{rule.keywords.slice(0, 3).join(', ')}{rule.keywords.length > 3 ? '…' : ''}</strong>
                                            <span className="text-neutral-400 ml-1">({MATCH_MODE_LABEL_KEYS[rule.match_mode] ? t(MATCH_MODE_LABEL_KEYS[rule.match_mode]) : rule.match_mode})</span>
                                        </span>
                                    )}
                                    <span className={`rounded-full px-2 py-0.5 text-xs font-medium ${
                                        rule.response_kind === 'text'
                                            ? 'bg-neutral-100 text-neutral-600 dark:bg-neutral-700 dark:text-neutral-300'
                                            : 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300'
                                    }`}>
                                        → {RESPONSE_KIND_LABEL_KEYS[rule.response_kind] ? t(RESPONSE_KIND_LABEL_KEYS[rule.response_kind]) : rule.response_kind}
                                    </span>
                                    {rule.priority > 0 && (
                                        <span className="text-[11px] text-neutral-400">{t('whatsapp.auto_replies_priority_badge', { n: rule.priority })}</span>
                                    )}
                                </div>
                                {rule.payload_json?.text && (
                                    <p className="text-xs text-neutral-500 dark:text-neutral-400 mt-1 truncate">
                                        {rule.payload_json.text}
                                    </p>
                                )}
                                {rule.payload_json?.template_name && (
                                    <p className="text-xs text-neutral-500 dark:text-neutral-400 mt-1">
                                        {t('whatsapp.auto_replies_template_label')} <strong>{rule.payload_json.template_name}</strong> ({rule.payload_json.language ?? 'en'})
                                    </p>
                                )}
                            </div>
                            <div className="flex items-center gap-2 shrink-0">
                                <button
                                    type="button"
                                    onClick={() => openEdit(rule)}
                                    title={t('whatsapp.auto_replies_edit_tooltip')}
                                    className="text-neutral-400 hover:text-brand-600 transition"
                                >
                                    <Pencil className="h-4 w-4" />
                                </button>
                                <button
                                    type="button"
                                    onClick={() => handleToggle(rule)}
                                    title={rule.enabled ? t('whatsapp.auto_replies_disable') : t('whatsapp.auto_replies_enable')}
                                    className="text-neutral-400 hover:text-brand-600 transition"
                                >
                                    {rule.enabled
                                        ? <ToggleRight className="h-5 w-5 text-green-500" />
                                        : <ToggleLeft className="h-5 w-5" />
                                    }
                                </button>
                                <button
                                    type="button"
                                    onClick={() => handleDelete(rule.id)}
                                    title={t('whatsapp.auto_replies_delete_tooltip')}
                                    className="text-neutral-400 hover:text-red-500 transition"
                                >
                                    <Trash2 className="h-4 w-4" />
                                </button>
                            </div>
                        </div>
                    ))}

                    {rules.length === 0 && (
                        <EmptyState
                            icon={<Zap className="h-8 w-8" />}
                            title={t('whatsapp.auto_replies_empty_title')}
                            description={t('whatsapp.auto_replies_empty_description')}
                            action={{ label: t('whatsapp.auto_replies_add_rule'), onClick: () => setShowCreate(true) }}
                        />
                    )}
                </div>
            </div>

            {/* ── Create modal ── */}
            {showCreate && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
                    <div className="w-full max-w-md rounded-xl bg-white dark:bg-neutral-900 p-6 shadow-xl space-y-4 max-h-[90vh] overflow-y-auto">
                        <div className="flex items-center justify-between">
                            <h3 className="text-lg font-semibold text-neutral-900 dark:text-neutral-100">{t('whatsapp.auto_replies_new_rule')}</h3>
                            <button type="button" onClick={() => setShowCreate(false)} className="text-neutral-400 hover:text-neutral-600">
                                <X className="h-5 w-5" />
                            </button>
                        </div>
                        <RuleForm
                            data={createForm.data}
                            setData={createForm.setData}
                            errors={createForm.errors}
                            onSubmit={handleCreate}
                            onCancel={() => setShowCreate(false)}
                            processing={createForm.processing}
                            submitLabel={t('whatsapp.auto_replies_create_rule')}
                            channelAccounts={channelAccounts}
                        />
                    </div>
                </div>
            )}

            {/* ── Edit modal ── */}
            {editingRule && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
                    <div className="w-full max-w-md rounded-xl bg-white dark:bg-neutral-900 p-6 shadow-xl space-y-4 max-h-[90vh] overflow-y-auto">
                        <div className="flex items-center justify-between">
                            <h3 className="text-lg font-semibold text-neutral-900 dark:text-neutral-100">{t('whatsapp.auto_replies_edit_rule')}</h3>
                            <button type="button" onClick={() => setEditingRule(null)} className="text-neutral-400 hover:text-neutral-600">
                                <X className="h-5 w-5" />
                            </button>
                        </div>
                        <RuleForm
                            data={editForm.data}
                            setData={editForm.setData}
                            errors={editForm.errors}
                            onSubmit={handleEdit}
                            onCancel={() => setEditingRule(null)}
                            processing={editForm.processing}
                            submitLabel={t('whatsapp.auto_replies_save_changes')}
                            channelAccounts={channelAccounts}
                        />
                    </div>
                </div>
            )}
        </ClientLayout>
    );
}
