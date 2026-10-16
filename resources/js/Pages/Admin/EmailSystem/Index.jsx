import { useState, useEffect } from 'react';
import AdminLayout from '@/Layouts/AdminLayout';
import { Button, Card, Modal, Tabs } from '@/Components/ui';
import { Head, router, useForm } from '@inertiajs/react';
import axios from 'axios';
import { Send, Settings, Pencil, Trash2, Mail, Server, Lock, User, CheckCircle2, XCircle, Zap } from 'lucide-react';
import { useTranslation } from 'react-i18next';

const ENCRYPTION_OPTIONS = [
    { value: 'tls', label: 'TLS' },
    { value: 'ssl', label: 'SSL' },
    { value: 'none', label: 'Nenhuma' },
];

export default function AdminEmailSystemIndex({ smtpConfigurations = [], emailTemplates = [], flash = {} }) {
    const { t } = useTranslation();
    const [addSmtpOpen, setAddSmtpOpen] = useState(false);
    const [editSmtp, setEditSmtp] = useState(null);
    const [editTemplate, setEditTemplate] = useState(null);
    const [testEmailOpen, setTestEmailOpen] = useState(false);
    const [testSending, setTestSending] = useState(false);
    const [testError, setTestError] = useState(null);
    const [testSuccess, setTestSuccess] = useState(false);

    return (
        <AdminLayout title={t('email_server.title')}>
            <Head title={`${t('email_server.title')} · Admin`} />
            <div className="space-y-6">
                <div className="flex items-start justify-between">
                    <div>
                        <h2 className="text-xl font-semibold text-neutral-900 dark:text-neutral-100">{t('email_server.title')}</h2>
                        <p className="mt-0.5 text-sm text-neutral-500 dark:text-neutral-400">
                            {t('email_server.subtitle')}
                        </p>
                    </div>
                </div>

                {flash?.success && (
                    <div className="flex items-center gap-2 rounded-lg bg-green-50 dark:bg-green-900/30 border border-green-200 dark:border-green-800 text-green-800 dark:text-green-200 px-4 py-3 text-sm">
                        <CheckCircle2 className="h-4 w-4 shrink-0" />
                        {flash.success}
                    </div>
                )}

                <Tabs
                    tabs={[
                        {
                            label: t('email_server.update_smtp'),
                            content: (
                                <div className="space-y-4">
                                    <div className="flex flex-wrap items-center justify-end gap-2">
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            onClick={() => {
                                                setTestEmailOpen(true);
                                                setTestError(null);
                                                setTestSuccess(false);
                                            }}
                                        >
                                            <Send className="h-4 w-4 mr-1.5" />
                                            {t('email_server.send_test')}
                                        </Button>
                                        <Button
                                            type="button"
                                            variant="primary"
                                            size="sm"
                                            onClick={() => {
                                                setEditSmtp(null);
                                                setAddSmtpOpen(true);
                                            }}
                                        >
                                            <Settings className="h-4 w-4 mr-1.5" />
                                            {t('email_system.add_configuration')}
                                        </Button>
                                    </div>

                                    <div className="space-y-3">
                                        {smtpConfigurations.length === 0 && (
                                            <Card>
                                                <Card.Body className="py-16 flex flex-col items-center gap-3 text-center">
                                                    <div className="h-12 w-12 rounded-full bg-neutral-100 dark:bg-neutral-800 flex items-center justify-center">
                                                        <Mail className="h-6 w-6 text-neutral-400 dark:text-neutral-500" />
                                                    </div>
                                                    <div>
                                                        <p className="text-sm font-medium text-neutral-700 dark:text-neutral-300">{t('email_system.no_smtp')}</p>
                                                        <p className="text-xs text-neutral-500 dark:text-neutral-400 mt-0.5">{t('email_system.no_smtp_desc')}</p>
                                                    </div>
                                                </Card.Body>
                                            </Card>
                                        )}
                                        {smtpConfigurations.map((c) => (
                                            <SmtpCard
                                                key={c.id}
                                                config={c}
                                                onEdit={() => { setEditSmtp(c); setAddSmtpOpen(true); }}
                                                onDelete={() => {
                                                    if (confirm(t('email_server.remove_confirm'))) {
                                                        router.delete(route('admin.smtp-configurations.destroy', c.id));
                                                    }
                                                }}
                                                onActivate={() => router.post(route('admin.smtp-configurations.activate', c.id))}
                                            />
                                        ))}
                                    </div>
                                </div>
                            ),
                        },
                        {
                            label: t('email_system.email_templates_tab'),
                            content: (
                                <div className="space-y-4">
                                    <div className="flex items-center justify-between">
                                        <p className="text-sm font-medium text-neutral-700 dark:text-neutral-300">
                                            {t('email_system.template_count', { count: emailTemplates.length })}
                                        </p>
                                    </div>
                                    <Card>
                                        <div className="overflow-x-auto">
                                            <table className="min-w-full text-sm">
                                                <thead>
                                                    <tr className="border-b border-neutral-200 dark:border-neutral-700 text-left">
                                                        <th className="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-neutral-500 dark:text-neutral-400">{t('email_system.col_template')}</th>
                                                        <th className="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-neutral-500 dark:text-neutral-400">{t('admin.col_key')}</th>
                                                        <th className="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-neutral-500 dark:text-neutral-400">{t('email_system.col_subject')}</th>
                                                        <th className="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-neutral-500 dark:text-neutral-400">{t('admin.col_status')}</th>
                                                        <th className="px-4 py-3"></th>
                                                    </tr>
                                                </thead>
                                                <tbody className="divide-y divide-neutral-100 dark:divide-neutral-800">
                                                    {emailTemplates.map((tmpl) => (
                                                        <tr key={tmpl.id} className="hover:bg-neutral-50 dark:hover:bg-neutral-800/50 transition-colors group">
                                                            <td className="px-4 py-3">
                                                                <p className="font-medium text-neutral-900 dark:text-neutral-100">{tmpl.name}</p>
                                                                {tmpl.description && (
                                                                    <p className="text-xs text-neutral-400 dark:text-neutral-500 mt-0.5">{tmpl.description}</p>
                                                                )}
                                                            </td>
                                                            <td className="px-4 py-3">
                                                                <span className="inline-flex items-center rounded bg-neutral-100 dark:bg-neutral-800 px-2 py-0.5 font-mono text-xs text-neutral-600 dark:text-neutral-400">
                                                                    {tmpl.slug}
                                                                </span>
                                                            </td>
                                                            <td className="px-4 py-3 text-neutral-500 dark:text-neutral-400 text-xs max-w-xs truncate">
                                                                {tmpl.subject || <span className="text-neutral-300 dark:text-neutral-600">—</span>}
                                                            </td>
                                                            <td className="px-4 py-3">
                                                                <span className={`inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium ${
                                                                    tmpl.enabled
                                                                        ? 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300'
                                                                        : 'bg-neutral-100 text-neutral-500 dark:bg-neutral-700 dark:text-neutral-400'
                                                                }`}>
                                                                    <span className={`h-1.5 w-1.5 rounded-full ${tmpl.enabled ? 'bg-green-500' : 'bg-neutral-400'}`} />
                                                                    {tmpl.enabled ? t('admin.active') : t('admin.inactive')}
                                                                </span>
                                                            </td>
                                                            <td className="px-4 py-3">
                                                                <Button
                                                                    variant="ghost"
                                                                    size="sm"
                                                                    className="opacity-0 group-hover:opacity-100 transition-opacity"
                                                                    onClick={() => setEditTemplate(tmpl)}
                                                                >
                                                                    <Pencil className="h-3.5 w-3.5 mr-1" />
                                                                    {t('common.edit')}
                                                                </Button>
                                                            </td>
                                                        </tr>
                                                    ))}
                                                </tbody>
                                            </table>
                                            {emailTemplates.length === 0 && (
                                                <div className="py-16 flex flex-col items-center gap-3 text-center">
                                                    <div className="h-12 w-12 rounded-full bg-neutral-100 dark:bg-neutral-800 flex items-center justify-center">
                                                        <Mail className="h-6 w-6 text-neutral-400 dark:text-neutral-500" />
                                                    </div>
                                                    <p className="text-sm text-neutral-500 dark:text-neutral-400">{t('email_system.no_templates')}</p>
                                                </div>
                                            )}
                                        </div>
                                    </Card>
                                </div>
                            ),
                        },
                    ]}
                    defaultIndex={0}
                />

                <AddOrEditSmtpModal
                    show={addSmtpOpen}
                    edit={editSmtp}
                    encryptionOptions={ENCRYPTION_OPTIONS}
                    onClose={() => { setAddSmtpOpen(false); setEditSmtp(null); }}
                    onSaved={() => { setAddSmtpOpen(false); setEditSmtp(null); router.reload(); }}
                />
                <TestEmailModal
                    show={testEmailOpen}
                    sending={testSending}
                    error={testError}
                    success={testSuccess}
                    onClose={() => { setTestEmailOpen(false); setTestError(null); setTestSuccess(false); }}
                    onSend={async (email) => {
                        setTestSending(true);
                        setTestError(null);
                        setTestSuccess(false);
                        try {
                            await axios.post(route('admin.smtp-configurations.test'), { email });
                            setTestSuccess(true);
                        } catch (e) {
                            setTestError(e?.response?.data?.message || e?.message || t('email_server.test_failed'));
                        } finally {
                            setTestSending(false);
                        }
                    }}
                />
                <EditTemplateModal
                    show={!!editTemplate}
                    template={editTemplate}
                    onClose={() => setEditTemplate(null)}
                    onSaved={() => { setEditTemplate(null); router.reload(); }}
                />
            </div>
        </AdminLayout>
    );
}

function SmtpCard({ config: c, onEdit, onDelete, onActivate }) {
    const { t } = useTranslation();
    return (
        <Card className={c.is_active ? 'ring-1 ring-green-500/30 dark:ring-green-500/20' : ''}>
            <Card.Body className="p-0">
                <div className="flex items-start justify-between gap-4 p-4 pb-3">
                    <div className="flex items-center gap-3 min-w-0">
                        <div className={`h-9 w-9 rounded-lg flex items-center justify-center shrink-0 ${
                            c.is_active
                                ? 'bg-green-100 dark:bg-green-900/40'
                                : 'bg-neutral-100 dark:bg-neutral-800'
                        }`}>
                            <Server className={`h-4 w-4 ${c.is_active ? 'text-green-600 dark:text-green-400' : 'text-neutral-500 dark:text-neutral-400'}`} />
                        </div>
                        <div className="min-w-0">
                            <div className="flex items-center gap-2 flex-wrap">
                                <p className="text-sm font-semibold text-neutral-900 dark:text-neutral-100 truncate">{c.summary}</p>
                                <span className={`inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium ${
                                    c.is_active
                                        ? 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300'
                                        : 'bg-neutral-100 text-neutral-500 dark:bg-neutral-700 dark:text-neutral-400'
                                }`}>
                                    <span className={`h-1.5 w-1.5 rounded-full ${c.is_active ? 'bg-green-500' : 'bg-neutral-400'}`} />
                                    {c.is_active ? t('admin.active') : t('admin.inactive')}
                                </span>
                            </div>
                        </div>
                    </div>
                    <div className="flex items-center gap-1 shrink-0">
                        <Button type="button" variant="ghost" size="sm" onClick={onEdit} title={t('common.edit')}>
                            <Pencil className="h-3.5 w-3.5" />
                        </Button>
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            className="text-red-500 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20"
                            onClick={onDelete}
                            title={t('common.delete')}
                        >
                            <Trash2 className="h-3.5 w-3.5" />
                        </Button>
                        {!c.is_active && (
                            <Button type="button" variant="outline" size="sm" onClick={onActivate} className="ml-1">
                                <Zap className="h-3.5 w-3.5 mr-1" />
                                {t('email_server.enable_smtp')}
                            </Button>
                        )}
                    </div>
                </div>

                <div className="grid grid-cols-2 sm:grid-cols-3 gap-px bg-neutral-100 dark:bg-neutral-800 border-t border-neutral-100 dark:border-neutral-800 rounded-b-lg overflow-hidden">
                    {[
                        { icon: Server, label: t('email_server.smtp_host'), value: c.host },
                        { icon: Settings, label: t('email_server.port'), value: c.port },
                        { icon: Lock, label: t('email_server.encryption'), value: c.encryption?.toUpperCase() },
                        { icon: User, label: t('email_server.username'), value: c.username },
                        { icon: Mail, label: t('email_server.from_email'), value: c.from_email },
                        { icon: Mail, label: t('email_server.from_name'), value: c.from_name },
                    ].map(({ icon: Icon, label, value }) => (
                        <div key={label} className="bg-white dark:bg-neutral-900 px-4 py-2.5">
                            <p className="text-xs text-neutral-400 dark:text-neutral-500 mb-0.5">{label}</p>
                            <p className="text-xs font-medium text-neutral-700 dark:text-neutral-300 truncate">{value}</p>
                        </div>
                    ))}
                </div>
            </Card.Body>
        </Card>
    );
}

function FormField({ label, children }) {
    return (
        <div>
            <label className="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-1">{label}</label>
            {children}
        </div>
    );
}

const inputCls = "w-full rounded-lg border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500/30 focus:border-brand-500 dark:focus:border-brand-400 transition-colors";

function AddOrEditSmtpModal({ show, edit, encryptionOptions, onClose, onSaved }) {
    const { t } = useTranslation();
    const isEdit = !!edit?.id;
    const { data, setData, post, put, processing } = useForm({
        host: edit?.host ?? 'smtp.gmail.com',
        port: edit?.port ?? 587,
        username: edit?.username ?? '',
        password: '',
        encryption: edit?.encryption ?? 'tls',
        from_email: edit?.from_email ?? '',
        from_name: edit?.from_name ?? '',
        activate: edit?.is_active ?? false,
    });

    const submit = (e) => {
        e.preventDefault();
        if (isEdit) {
            put(route('admin.smtp-configurations.update', edit.id), { onSuccess: () => onSaved(), preserveScroll: true });
        } else {
            post(route('admin.smtp-configurations.store'), { onSuccess: () => onSaved(), preserveScroll: true });
        }
    };

    return (
        <Modal show={show} onClose={onClose} maxWidth="2xl">
            <Modal.Header title={isEdit ? t('email_server.update_smtp') : t('email_server.add_smtp')} onClose={onClose} />
            <form onSubmit={submit}>
                <Modal.Body className="space-y-4">
                    <div className="grid grid-cols-2 gap-4">
                        <FormField label={`${t('email_server.smtp_host')} *`}>
                            <input type="text" value={data.host} onChange={(e) => setData('host', e.target.value)} required className={inputCls} placeholder={t('email_system.smtp_host_placeholder')} />
                        </FormField>
                        <FormField label={`${t('email_server.port')} *`}>
                            <input type="number" value={data.port} onChange={(e) => setData('port', parseInt(e.target.value, 10) || 587)} required min={1} max={65535} className={inputCls} />
                        </FormField>
                    </div>
                    <div className="grid grid-cols-2 gap-4">
                        <FormField label={`${t('email_server.username')} *`}>
                            <input type="text" value={data.username} onChange={(e) => setData('username', e.target.value)} required className={inputCls} placeholder={t('email_system.from_email_placeholder')} />
                        </FormField>
                        <FormField label={isEdit ? t('email_server.password') : `${t('email_server.password')} *`}>
                            <input type="password" value={data.password} onChange={(e) => setData('password', e.target.value)} placeholder={isEdit ? t('email_system.password_unchanged') : ''} required={!isEdit} className={inputCls} />
                        </FormField>
                    </div>
                    <FormField label={`${t('email_server.encryption')} *`}>
                        <select value={data.encryption} onChange={(e) => setData('encryption', e.target.value)} required className={inputCls}>
                            {encryptionOptions.map((o) => (
                                <option key={o.value} value={o.value}>{o.label}</option>
                            ))}
                        </select>
                    </FormField>
                    <div className="grid grid-cols-2 gap-4">
                        <FormField label={`${t('email_server.from_email')} *`}>
                            <input type="email" value={data.from_email} onChange={(e) => setData('from_email', e.target.value)} required className={inputCls} placeholder={t('email_system.from_email_placeholder')} />
                        </FormField>
                        <FormField label={`${t('email_server.from_name')} *`}>
                            <input type="text" value={data.from_name} onChange={(e) => setData('from_name', e.target.value)} required className={inputCls} placeholder={t('email_system.from_name_placeholder')} />
                        </FormField>
                    </div>
                    <label className="flex items-center gap-2.5 p-3 rounded-lg border border-neutral-200 dark:border-neutral-700 cursor-pointer hover:bg-neutral-50 dark:hover:bg-neutral-800/50 transition-colors">
                        <input
                            type="checkbox"
                            checked={data.activate}
                            onChange={(e) => setData('activate', e.target.checked)}
                            className="rounded border-neutral-300 dark:border-neutral-600 text-brand-500"
                        />
                        <div>
                            <p className="text-sm font-medium text-neutral-700 dark:text-neutral-300">{t('email_server.enable_smtp')}</p>
                            <p className="text-xs text-neutral-500 dark:text-neutral-400">{t('email_server.connected_to')}</p>
                        </div>
                    </label>
                </Modal.Body>
                <Modal.Footer>
                    <Button type="button" variant="outline" onClick={onClose}>{t('common.cancel')}</Button>
                    <Button type="submit" variant="primary" disabled={processing}>
                        {processing ? t('email_server.saving') : isEdit ? t('email_server.update_config') : t('email_server.save_config')}
                    </Button>
                </Modal.Footer>
            </form>
        </Modal>
    );
}

function TestEmailModal({ show, sending, error, success, onClose, onSend }) {
    const { t } = useTranslation();
    const [email, setEmail] = useState('');

    const handleSend = (e) => {
        e.preventDefault();
        onSend(email);
    };

    return (
        <Modal show={show} onClose={onClose}>
            <Modal.Header title={t('email_server.send_test_title')} onClose={onClose} />
            <form onSubmit={handleSend}>
                <Modal.Body className="space-y-4">
                    <p className="text-sm text-neutral-500 dark:text-neutral-400">
                        {t('email_server.send_test_desc')}
                    </p>
                    <FormField label={`${t('email_system.send_to_label')} *`}>
                        <input
                            type="email"
                            value={email}
                            onChange={(e) => setEmail(e.target.value)}
                            required
                            placeholder={t('email_system.recipient_placeholder')}
                            className={inputCls}
                        />
                    </FormField>
                    {error && (
                        <div className="flex items-start gap-2 rounded-lg border border-red-200 bg-red-50 dark:bg-red-900/20 dark:border-red-800 px-4 py-3 text-sm text-red-800 dark:text-red-200">
                            <XCircle className="h-4 w-4 shrink-0 mt-0.5" />
                            {error}
                        </div>
                    )}
                    {success && (
                        <div className="flex items-center gap-2 rounded-lg border border-green-200 bg-green-50 dark:bg-green-900/20 dark:border-green-800 px-4 py-3 text-sm text-green-800 dark:text-green-200">
                            <CheckCircle2 className="h-4 w-4 shrink-0" />
                            {t('email_system.test_sent_success')}
                        </div>
                    )}
                </Modal.Body>
                <Modal.Footer>
                    <Button type="button" variant="outline" onClick={onClose}>{t('common.cancel')}</Button>
                    <Button type="submit" variant="primary" disabled={sending}>
                        <Send className="h-4 w-4 mr-1.5" />
                        {sending ? t('email_server.sending') : t('email_server.send_test')}
                    </Button>
                </Modal.Footer>
            </form>
        </Modal>
    );
}

function EditTemplateModal({ show, template, onClose, onSaved }) {
    const { t } = useTranslation();
    const { data, setData, put, processing } = useForm({
        name: '',
        slug: '',
        subject: '',
        content: '',
        type: 'email',
        enabled: true,
    });
    const [testEmail, setTestEmail] = useState('');
    const [testSending, setTestSending] = useState(false);
    const [testError, setTestError] = useState(null);
    const [testSuccess, setTestSuccess] = useState(false);
    const [showTestInput, setShowTestInput] = useState(false);

    useEffect(() => {
        if (template) {
            setData({
                name: template.name,
                slug: template.slug,
                subject: template.subject ?? '',
                content: template.content ?? '',
                type: 'email',
                enabled: template.enabled ?? true,
            });
            setTestError(null);
            setTestSuccess(false);
            setShowTestInput(false);
        }
    }, [template?.id]);

    const handleSubmit = (e) => {
        e.preventDefault();
        put(route('admin.email-templates.update', template.id), {
            preserveScroll: true,
            onSuccess: () => onSaved(),
        });
    };

    const handleSendTest = async () => {
        if (!testEmail) return;
        setTestSending(true);
        setTestError(null);
        setTestSuccess(false);
        try {
            await axios.post(route('admin.email-templates.test', template.id), { email: testEmail });
            setTestSuccess(true);
        } catch (e) {
            setTestError(e?.response?.data?.message || e?.message || t('email_server.test_failed'));
        } finally {
            setTestSending(false);
        }
    };

    if (!template) return null;

    const placeholders = template.placeholders ?? [];

    return (
        <Modal show={show} onClose={onClose} maxWidth="6xl">
            <Modal.Header title={t('email_system.edit_template_title', { name: template.name })} onClose={onClose} />
            <form onSubmit={handleSubmit}>
                <Modal.Body className="p-0">
                    <div className="flex min-h-0 flex-col overflow-y-auto lg:flex-row lg:overflow-hidden" style={{ maxHeight: '75vh' }}>
                        {/* Left: form */}
                        <div className="min-w-0 flex-1 space-y-4 border-b border-neutral-200 p-4 dark:border-neutral-700 sm:p-6 lg:overflow-y-auto lg:border-b-0 lg:border-r">
                            {template.description && (
                                <p className="text-sm text-neutral-500 dark:text-neutral-400">{template.description}</p>
                            )}
                            <FormField label={t('email_system.template_key_label')}>
                                <input
                                    type="text"
                                    value={data.slug}
                                    readOnly
                                    className="w-full rounded-lg border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800/50 text-neutral-500 dark:text-neutral-400 px-3 py-2 text-sm font-mono cursor-not-allowed"
                                />
                            </FormField>
                            {placeholders.length > 0 && (
                                <div className="rounded-lg border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800/50 p-3 space-y-2">
                                    <p className="text-xs font-semibold text-neutral-600 dark:text-neutral-300">{t('email_system.available_variables')}</p>
                                    <div className="flex flex-wrap gap-1.5">
                                        {placeholders.map((p) => (
                                            <button
                                                key={p}
                                                type="button"
                                                title={t('email_system.click_to_copy')}
                                                onClick={() => navigator.clipboard?.writeText(`{{${p}}}`)}
                                                className="rounded-md bg-white dark:bg-neutral-700 border border-neutral-200 dark:border-neutral-600 px-2 py-0.5 font-mono text-xs text-neutral-700 dark:text-neutral-300 hover:bg-brand-50 hover:border-brand-300 dark:hover:bg-brand-900/40 transition-colors cursor-pointer"
                                            >
                                                {`{{${p}}}`}
                                            </button>
                                        ))}
                                    </div>
                                    <p className="text-xs text-neutral-400 dark:text-neutral-500">{t('email_system.click_to_copy')}.</p>
                                </div>
                            )}
                            <FormField label={`${t('email_system.col_subject')} *`}>
                                <input
                                    type="text"
                                    value={data.subject}
                                    onChange={(e) => setData('subject', e.target.value)}
                                    required
                                    placeholder={t('email_system.subject_placeholder')}
                                    className={inputCls}
                                />
                            </FormField>
                            <FormField label={`${t('email_system.html_body_label')} *`}>
                                <textarea
                                    value={data.content}
                                    onChange={(e) => setData('content', e.target.value)}
                                    required
                                    rows={14}
                                    className={`${inputCls} font-mono resize-none`}
                                    placeholder={t('email_system.body_placeholder')}
                                />
                            </FormField>
                            <label className="flex items-center gap-2.5 p-3 rounded-lg border border-neutral-200 dark:border-neutral-700 cursor-pointer hover:bg-neutral-50 dark:hover:bg-neutral-800/50 transition-colors">
                                <input
                                    type="checkbox"
                                    checked={data.enabled}
                                    onChange={(e) => setData('enabled', e.target.checked)}
                                    className="rounded border-neutral-300 dark:border-neutral-600 text-brand-500"
                                />
                                <div>
                                    <p className="text-sm font-medium text-neutral-700 dark:text-neutral-300">{t('admin.active')}</p>
                                    <p className="text-xs text-neutral-400 dark:text-neutral-500">{t('common.enabled')}</p>
                                </div>
                            </label>
                        </div>
                        {/* Right: live preview */}
                        <div className="flex min-h-[24rem] w-full shrink-0 flex-col overflow-hidden bg-neutral-50 dark:bg-neutral-900 lg:min-h-0 lg:w-80">
                            <div className="px-4 py-3 border-b border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-900">
                                <p className="text-xs font-semibold text-neutral-500 dark:text-neutral-400 uppercase tracking-wide">{t('email_system.email_preview')}</p>
                                {data.subject && (
                                    <p className="mt-1 text-xs text-neutral-600 dark:text-neutral-400 truncate">
                                        <span className="font-medium">{t('email_system.col_subject')}:</span> {data.subject}
                                    </p>
                                )}
                            </div>
                            <div className="flex-1 overflow-hidden">
                                <iframe
                                    srcDoc={data.content || '<p style="color:#aaa;font-family:sans-serif;padding:24px;font-size:14px">No content yet.</p>'}
                                    sandbox="allow-same-origin"
                                    title={t('email_system.email_preview')}
                                    className="w-full h-full border-0 bg-white"
                                    style={{ minHeight: '400px' }}
                                />
                            </div>
                        </div>
                    </div>
                </Modal.Body>
                <Modal.Footer>
                    <div className="flex items-center gap-2 mr-auto">
                        {!showTestInput ? (
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={() => { setShowTestInput(true); setTestError(null); setTestSuccess(false); }}
                            >
                                <Send className="h-3.5 w-3.5 mr-1.5" />
                                {t('email_server.send_test')}
                            </Button>
                        ) : (
                            <>
                                <input
                                    type="email"
                                    value={testEmail}
                                    onChange={(e) => setTestEmail(e.target.value)}
                                    placeholder={t('email_system.recipient_placeholder')}
                                    className="rounded-lg border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500/30 w-52"
                                />
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    disabled={testSending || !testEmail}
                                    onClick={handleSendTest}
                                >
                                    {testSending ? t('email_server.sending') : t('email_server.send_test')}
                                </Button>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    onClick={() => { setShowTestInput(false); setTestError(null); setTestSuccess(false); }}
                                >
                                    ✕
                                </Button>
                            </>
                        )}
                        {testSuccess && (
                            <span className="flex items-center gap-1 text-xs text-green-600 dark:text-green-400">
                                <CheckCircle2 className="h-3.5 w-3.5" /> {t('email_system.sent_label')}
                            </span>
                        )}
                        {testError && <span className="text-xs text-red-600 dark:text-red-400">{testError}</span>}
                    </div>
                    <Button type="button" variant="outline" onClick={onClose}>{t('common.cancel')}</Button>
                    <Button type="submit" variant="primary" disabled={processing}>{t('common.save')}</Button>
                </Modal.Footer>
            </form>
        </Modal>
    );
}
