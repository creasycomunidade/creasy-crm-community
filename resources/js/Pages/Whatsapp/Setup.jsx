import { Head, router, usePage, Link, useForm } from '@inertiajs/react';
import ClientLayout from '@/Layouts/ClientLayout';
import { MessageSquare, Check, Link2, Phone, Webhook, Copy, Inbox, AlertTriangle, RefreshCw, Plus, Trash2, ShieldCheck, ShieldAlert } from 'lucide-react';
import { useState } from 'react';
import { useTranslation, Trans } from 'react-i18next';

const STATUS_LABEL_KEYS = {
    active:   'whatsapp.setup_status_active',
    inactive: 'whatsapp.setup_status_inactive',
    error:    'whatsapp.setup_status_error',
};

function CopyButton({ text }) {
    const { t } = useTranslation();
    const [copied, setCopied] = useState(false);
    const copy = () => {
        navigator.clipboard.writeText(text).then(() => {
            setCopied(true);
            setTimeout(() => setCopied(false), 2000);
        });
    };
    return (
        <button
            type="button"
            onClick={copy}
            title={t('whatsapp.setup_copy_to_clipboard')}
            className="shrink-0 rounded p-1 text-neutral-400 hover:text-brand-600 dark:hover:text-brand-400 transition"
        >
            {copied ? <Check className="h-4 w-4 text-green-500" /> : <Copy className="h-4 w-4" />}
        </button>
    );
}

function StatusBadge({ status }) {
    const { t } = useTranslation();
    const map = {
        active: 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300',
        inactive: 'bg-neutral-100 text-neutral-600 dark:bg-neutral-800 dark:text-neutral-400',
        error: 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300',
    };
    return (
        <span className={`inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ${map[status] ?? map.inactive}`}>
            {STATUS_LABEL_KEYS[status] ? t(STATUS_LABEL_KEYS[status]) : status}
        </span>
    );
}

function WabaCard({ waba, webhookUrl, webhookToken, activePhoneIds, onSyncTemplates }) {
    const { t } = useTranslation();
    const [confirmDelete, setConfirmDelete] = useState(false);
    const [deleting, setDeleting] = useState(false);
    const [syncingPhones, setSyncingPhones] = useState(false);
    const [showPhoneForm, setShowPhoneForm] = useState(false);
    const [verifyingPhone, setVerifyingPhone] = useState(null); // phone_number_id being verified
    const [verifyCode, setVerifyCode]         = useState('');
    const [sendingCode, setSendingCode]       = useState(false);
    const [submittingCode, setSubmittingCode] = useState(false);
    const phoneForm = useForm({ phone_number_id: '' });
    const phoneList = waba.phone_numbers ?? waba.phoneNumbers ?? [];
    const fullWebhookUrl = webhookToken ? `${webhookUrl}/${webhookToken}` : null;

    const handleDelete = () => {
        setDeleting(true);
        router.delete(route('client.whatsapp.setup.destroy', { waba: waba.id }), {
            preserveScroll: true,
            onFinish: () => { setDeleting(false); setConfirmDelete(false); },
        });
    };

    const syncPhones = () => {
        setSyncingPhones(true);
        router.post(route('client.whatsapp.setup.sync-phone-numbers', { waba: waba.id }), {}, {
            preserveScroll: true,
            onFinish: () => setSyncingPhones(false),
        });
    };

    const sendVerificationCode = (phoneNumberId) => {
        setSendingCode(true);
        router.post(route('client.whatsapp.setup.request-verification-code', { waba: waba.id }), { phone_number_id: phoneNumberId }, {
            preserveScroll: true,
            onSuccess: () => { setVerifyingPhone(phoneNumberId); setVerifyCode(''); },
            onFinish: () => setSendingCode(false),
        });
    };

    const submitVerifyCode = (phoneNumberId) => {
        setSubmittingCode(true);
        router.post(route('client.whatsapp.setup.verify-code', { waba: waba.id }), { phone_number_id: phoneNumberId, code: verifyCode }, {
            preserveScroll: true,
            onSuccess: () => { setVerifyingPhone(null); setVerifyCode(''); },
            onFinish: () => setSubmittingCode(false),
        });
    };

    const submitPhoneNumber = (e) => {
        e.preventDefault();
        phoneForm.post(route('client.whatsapp.setup.store-phone-number', { waba: waba.id }), {
            preserveScroll: true,
            onSuccess: () => { phoneForm.reset(); setShowPhoneForm(false); },
        });
    };

    return (
        <div className="rounded-xl border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-900 divide-y divide-neutral-100 dark:divide-neutral-800">
            {/* Header */}
            <div className="flex items-start justify-between gap-4 p-5">
                <div className="min-w-0">
                    <div className="flex items-center gap-2 flex-wrap">
                        <span className="font-semibold text-neutral-900 dark:text-neutral-100 text-sm">WABA</span>
                        <code className="font-mono text-xs text-neutral-500 dark:text-neutral-400">{waba.waba_id}</code>
                        <StatusBadge status={waba.status} />
                    </div>
                </div>
                <div className="flex items-center gap-2 shrink-0">
                    <button
                        type="button"
                        onClick={() => onSyncTemplates(waba)}
                        title={t('whatsapp.setup_sync_templates_tooltip')}
                        className="flex items-center gap-1.5 rounded-lg border border-neutral-300 dark:border-neutral-600 px-3 py-1.5 text-xs text-neutral-600 dark:text-neutral-400 hover:bg-neutral-50 dark:hover:bg-neutral-800 transition"
                    >
                        <RefreshCw className="h-3.5 w-3.5" /> {t('whatsapp.setup_templates')}
                    </button>
                    {confirmDelete ? (
                        <div className="flex items-center gap-2">
                            <button
                                type="button"
                                onClick={handleDelete}
                                disabled={deleting}
                                className="rounded-lg bg-red-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-red-700 disabled:opacity-60 transition"
                            >
                                {deleting ? t('whatsapp.setup_removing') : t('whatsapp.setup_confirm')}
                            </button>
                            <button
                                type="button"
                                onClick={() => setConfirmDelete(false)}
                                className="rounded-lg border border-neutral-300 dark:border-neutral-600 px-3 py-1.5 text-xs text-neutral-600 dark:text-neutral-400 hover:bg-neutral-50 dark:hover:bg-neutral-800 transition"
                            >
                                {t('common.cancel')}
                            </button>
                        </div>
                    ) : (
                        <button
                            type="button"
                            onClick={() => setConfirmDelete(true)}
                            className="rounded-lg border border-red-200 dark:border-red-800 p-1.5 text-red-500 hover:bg-red-50 dark:hover:bg-red-950/40 transition"
                            title={t('whatsapp.setup_remove_waba_tooltip')}
                        >
                            <Trash2 className="h-4 w-4" />
                        </button>
                    )}
                </div>
            </div>

            {/* Webhook */}
            {fullWebhookUrl && (
                <div className="px-5 py-3 space-y-2">
                    <p className="text-xs font-semibold text-neutral-500 dark:text-neutral-400 uppercase flex items-center gap-1.5">
                        <Webhook className="h-3.5 w-3.5" /> {t('whatsapp.setup_webhook')}
                    </p>
                    <div className="space-y-1.5">
                        <div className="flex items-center gap-2">
                            <Link2 className="h-3.5 w-3.5 text-neutral-400 shrink-0" />
                            <code className="flex-1 min-w-0 text-xs font-mono text-neutral-700 dark:text-neutral-300 break-all">{fullWebhookUrl}</code>
                            <CopyButton text={fullWebhookUrl} />
                        </div>
                        <div className="flex items-center gap-2">
                            <span className="text-xs text-neutral-400 shrink-0">{t('whatsapp.setup_token_label')}</span>
                            <code className="flex-1 min-w-0 text-xs font-mono text-neutral-700 dark:text-neutral-300 break-all">{webhookToken}</code>
                            <CopyButton text={webhookToken} />
                        </div>
                    </div>
                </div>
            )}

            {/* Phone numbers */}
            <div className="px-5 py-3 space-y-3">
                <div className="flex items-center justify-between">
                    <p className="text-xs font-semibold text-neutral-500 dark:text-neutral-400 uppercase flex items-center gap-1.5">
                        <Phone className="h-3.5 w-3.5" /> {t('whatsapp.setup_phone_numbers', { count: phoneList.length })}
                    </p>
                    <div className="flex items-center gap-2">
                        <button
                            type="button"
                            disabled={syncingPhones}
                            onClick={syncPhones}
                            className="flex items-center gap-1 text-xs text-brand-600 dark:text-brand-400 hover:underline disabled:opacity-60"
                        >
                            <RefreshCw className={`h-3 w-3 ${syncingPhones ? 'animate-spin' : ''}`} />
                            {syncingPhones ? t('whatsapp.setup_syncing') : t('whatsapp.setup_sync_from_meta')}
                        </button>
                        <button
                            type="button"
                            onClick={() => setShowPhoneForm(v => !v)}
                            className="flex items-center gap-1 text-xs text-brand-600 dark:text-brand-400 hover:underline"
                        >
                            <Plus className="h-3 w-3" /> {t('whatsapp.setup_add_manually')}
                        </button>
                    </div>
                </div>

                {showPhoneForm && (
                    <form onSubmit={submitPhoneNumber} className="flex items-end gap-2">
                        <div className="flex-1">
                            <input
                                type="text"
                                inputMode="numeric"
                                autoComplete="off"
                                value={phoneForm.data.phone_number_id}
                                onChange={e => phoneForm.setData('phone_number_id', e.target.value)}
                                placeholder="e.g. 106540573042353"
                                className="w-full rounded-lg border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 px-3 py-1.5 text-xs font-mono focus:outline-none focus:ring-2 focus:ring-brand-500"
                            />
                            {phoneForm.errors.phone_number_id && (
                                <p className="mt-1 text-xs text-red-500">{phoneForm.errors.phone_number_id}</p>
                            )}
                        </div>
                        <button
                            type="submit"
                            disabled={phoneForm.processing}
                            className="rounded-lg bg-brand-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-brand-700 disabled:opacity-60 transition shrink-0"
                        >
                            {phoneForm.processing ? t('whatsapp.setup_saving') : t('common.add')}
                        </button>
                        <button
                            type="button"
                            onClick={() => setShowPhoneForm(false)}
                            className="rounded-lg border border-neutral-300 dark:border-neutral-600 px-3 py-1.5 text-xs text-neutral-600 dark:text-neutral-400 hover:bg-neutral-50 dark:hover:bg-neutral-800 transition shrink-0"
                        >
                            {t('common.cancel')}
                        </button>
                    </form>
                )}

                {phoneList.length > 0 ? (
                    <ul className="space-y-2">
                        {phoneList.map((num, i) => {
                            const phoneId  = num.phone_number_id ?? num.id;
                            const isActive = phoneId && activePhoneIds.includes(String(phoneId));
                            const verStatus = num.code_verification_status;
                            const needsVerify = verStatus && verStatus !== 'VERIFIED';
                            return (
                                <li key={phoneId ?? i} className="rounded-lg bg-neutral-50 dark:bg-neutral-800 px-3 py-2 text-xs space-y-2">
                                    <div className="flex items-center gap-2">
                                        <Phone className="h-3.5 w-3.5 text-neutral-400 shrink-0" />
                                        <div className="flex-1 min-w-0">
                                            <div className="font-mono text-neutral-500 dark:text-neutral-400 truncate">{t('whatsapp.setup_phone_id', { id: phoneId })}</div>
                                            <div className="font-mono font-medium truncate">{num.display_phone ?? num.display_phone_number ?? '—'}</div>
                                            {num.verified_name && <div className="text-neutral-500 dark:text-neutral-400 truncate">{num.verified_name}</div>}
                                        </div>
                                        {num.quality_rating && <span className="text-neutral-400 shrink-0">{num.quality_rating}</span>}
                                        {verStatus === 'VERIFIED'
                                            ? <span className="flex items-center gap-1 text-green-600 dark:text-green-400 shrink-0"><ShieldCheck className="h-3.5 w-3.5" /> {t('whatsapp.setup_verified')}</span>
                                            : verStatus
                                                ? <span className="flex items-center gap-1 text-amber-600 dark:text-amber-400 shrink-0"><ShieldAlert className="h-3.5 w-3.5" /> {verStatus}</span>
                                                : null}
                                        {isActive
                                            ? <span className="flex items-center gap-1 font-medium text-green-600 dark:text-green-400 shrink-0"><Inbox className="h-3 w-3" /> {t('whatsapp.setup_inbox')}</span>
                                            : <span className="text-neutral-400 shrink-0">—</span>}
                                    </div>

                                    {needsVerify && verifyingPhone !== phoneId && (
                                        <div className="flex items-center gap-2 pt-1 border-t border-neutral-200 dark:border-neutral-700">
                                            <ShieldAlert className="h-3.5 w-3.5 text-amber-500 shrink-0" />
                                            <span className="text-amber-600 dark:text-amber-400 flex-1">{t('whatsapp.setup_phone_not_verified')}</span>
                                            <button
                                                type="button"
                                                disabled={sendingCode}
                                                onClick={() => sendVerificationCode(phoneId)}
                                                className="rounded bg-brand-600 px-2 py-1 text-white hover:bg-brand-700 disabled:opacity-60 transition shrink-0"
                                            >
                                                {sendingCode ? t('whatsapp.setup_sending') : t('whatsapp.setup_send_sms_code')}
                                            </button>
                                        </div>
                                    )}

                                    {needsVerify && verifyingPhone === phoneId && (
                                        <div className="flex items-center gap-2 pt-1 border-t border-neutral-200 dark:border-neutral-700">
                                            <input
                                                type="text"
                                                value={verifyCode}
                                                onChange={e => setVerifyCode(e.target.value)}
                                                placeholder={t('whatsapp.setup_enter_sms_code')}
                                                className="flex-1 rounded border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-900 px-2 py-1 text-xs font-mono"
                                            />
                                            <button
                                                type="button"
                                                disabled={submittingCode || !verifyCode}
                                                onClick={() => submitVerifyCode(phoneId)}
                                                className="rounded bg-green-600 px-2 py-1 text-white hover:bg-green-700 disabled:opacity-60 transition shrink-0"
                                            >
                                                {submittingCode ? t('whatsapp.setup_verifying') : t('whatsapp.setup_verify')}
                                            </button>
                                            <button type="button" onClick={() => setVerifyingPhone(null)} className="text-neutral-400 hover:text-neutral-600 shrink-0">{t('common.cancel')}</button>
                                        </div>
                                    )}
                                </li>
                            );
                        })}
                    </ul>
                ) : (
                    <p className="text-xs text-neutral-400 italic">{t('whatsapp.setup_no_phones')}</p>
                )}
            </div>

            {/* Confirm delete warning */}
            {confirmDelete && (
                <div className="px-5 py-3 flex items-start gap-2 text-xs text-red-600 dark:text-red-400">
                    <AlertTriangle className="h-4 w-4 mt-0.5 shrink-0" />
                    <span>{t('whatsapp.setup_delete_warning')}</span>
                </div>
            )}
        </div>
    );
}

export default function WhatsappSetup({ wabas, webhookUrl, webhookTokensByWaba, channelAccountPhoneIdsByWaba }) {
    const { t } = useTranslation();
    const { props } = usePage();
    const flash = props.flash ?? {};

    const [syncingTemplates, setSyncingTemplates] = useState(null);

    const syncTemplates = (waba) => {
        setSyncingTemplates(waba.id);
        router.post(route('client.whatsapp.templates.sync'), {}, {
            preserveScroll: true,
            onFinish: () => setSyncingTemplates(null),
        });
    };

    return (
        <ClientLayout title={t('whatsapp.setup_title')}>
            <Head title={t('whatsapp.setup_title')} />
            <div className="max-w-2xl space-y-6">
                <div className="flex items-start justify-between gap-4">
                    <div>
                        <h2 className="text-xl font-semibold text-neutral-900 dark:text-neutral-100 flex items-center gap-2">
                            <MessageSquare className="h-5 w-5 text-green-500" /> {t('whatsapp.setup_heading')}
                        </h2>
                        <p className="mt-1 text-sm text-neutral-500 dark:text-neutral-400">
                            {t('whatsapp.setup_subtitle')}
                        </p>
                    </div>
                </div>

                {flash.success && (
                    <div className="rounded-lg bg-green-50 dark:bg-green-900/30 text-green-800 dark:text-green-200 px-4 py-3 text-sm">
                        {flash.success}
                    </div>
                )}

                {/* WABA list */}
                {wabas.length > 0 ? (
                    <div className="space-y-4">
                        {wabas.map(waba => (
                            <WabaCard
                                key={waba.id}
                                waba={waba}
                                webhookUrl={webhookUrl}
                                webhookToken={webhookTokensByWaba?.[waba.id]}
                                activePhoneIds={channelAccountPhoneIdsByWaba?.[waba.id] ?? []}
                                onSyncTemplates={() => syncTemplates(waba)}
                                syncingTemplates={syncingTemplates === waba.id}
                            />
                        ))}
                    </div>
                ) : (
                    <div className="rounded-xl border border-dashed border-neutral-300 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-900 p-8 text-center">
                        <MessageSquare className="h-8 w-8 text-neutral-300 dark:text-neutral-600 mx-auto mb-3" />
                        <p className="text-sm font-medium text-neutral-600 dark:text-neutral-400">{t('whatsapp.setup_empty_title')}</p>
                        <p className="mt-1 text-xs text-neutral-400 dark:text-neutral-500">{t('whatsapp.setup_empty_description')}</p>
                    </div>
                )}

                {/* Webhook instructions */}
                {wabas.length > 0 && (
                    <details className="rounded-xl border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-900 p-5 text-sm open:space-y-3">
                        <summary className="cursor-pointer font-medium text-neutral-700 dark:text-neutral-300 flex items-center gap-2">
                            <Webhook className="h-4 w-4 text-neutral-400" /> {t('whatsapp.setup_webhook_instructions')}
                        </summary>
                        <ol className="list-decimal list-inside text-neutral-600 dark:text-neutral-400 space-y-1.5 mt-3">
                            <li>{t('whatsapp.setup_webhook_step_1')}</li>
                            <li><Trans i18nKey="whatsapp.setup_webhook_step_2">Paste the <strong>Callback URL</strong> from the WABA card above into "Callback URL".</Trans></li>
                            <li><Trans i18nKey="whatsapp.setup_webhook_step_3">Paste the <strong>Verify Token</strong> into "Verify token", then click <strong>Verify and Save</strong>.</Trans></li>
                            <li><Trans i18nKey="whatsapp.setup_webhook_step_4">Subscribe to <strong>messages</strong> and <strong>message_status_updates</strong>.</Trans></li>
                            <li>{t('whatsapp.setup_webhook_step_5')}</li>
                        </ol>
                    </details>
                )}

                {/* Quick nav */}
                {wabas.length > 0 && (
                    <div className="flex flex-wrap gap-4 text-sm">
                        <Link href={route('client.whatsapp.templates.index')} className="text-neutral-500 dark:text-neutral-400 hover:text-brand-600 dark:hover:text-brand-400">{t('whatsapp.setup_nav_templates')}</Link>
                        <Link href={route('client.whatsapp.auto-replies.index')} className="text-neutral-500 dark:text-neutral-400 hover:text-brand-600 dark:hover:text-brand-400">{t('whatsapp.setup_nav_auto_replies')}</Link>
                        <Link href={route('client.inbox.index')} className="text-neutral-500 dark:text-neutral-400 hover:text-brand-600 dark:hover:text-brand-400">{t('whatsapp.setup_nav_inbox')}</Link>
                    </div>
                )}
            </div>
        </ClientLayout>
    );
}
