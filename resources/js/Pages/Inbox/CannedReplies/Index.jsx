import { Head, router, usePage } from '@inertiajs/react';
import ClientLayout from '@/Layouts/ClientLayout';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Plus, Pencil, Trash2, X, Check } from 'lucide-react';

export default function CannedRepliesIndex({ cannedReplies }) {
    const { t } = useTranslation();
    const { flash } = usePage().props;
    const [showForm, setShowForm] = useState(false);
    const [editing, setEditing] = useState(null);
    const [form, setForm] = useState({ shortcut: '', body: '' });
    const [errors, setErrors] = useState({});

    const resetForm = () => {
        setForm({ shortcut: '', body: '' });
        setErrors({});
        setEditing(null);
        setShowForm(false);
    };

    const openCreate = () => {
        setEditing(null);
        setForm({ shortcut: '', body: '' });
        setErrors({});
        setShowForm(true);
    };

    const openEdit = (reply) => {
        setEditing(reply);
        setForm({ shortcut: reply.shortcut, body: reply.body });
        setErrors({});
        setShowForm(true);
    };

    const submit = (e) => {
        e.preventDefault();
        const url  = editing
            ? route('client.inbox.canned-replies.update', editing.id)
            : route('client.inbox.canned-replies.store');
        const method = editing ? 'put' : 'post';

        router[method](url, form, {
            preserveScroll: true,
            onSuccess: resetForm,
            onError: (err) => setErrors(err),
        });
    };

    const destroy = (reply) => {
        if (!confirm(t('inbox.canned_delete_confirm', { shortcut: reply.shortcut }))) return;
        router.delete(route('client.inbox.canned-replies.destroy', reply.id), { preserveScroll: true });
    };

    return (
        <ClientLayout title={t('inbox.canned_replies')}>
            <Head title={t('inbox.canned_replies')} />
            <div className="max-w-3xl mx-auto space-y-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-neutral-900 dark:text-neutral-100">{t('inbox.canned_replies')}</h1>
                        <p className="text-sm text-neutral-500 mt-1">{t('inbox.canned_replies_hint_1')} <code className="font-mono bg-neutral-100 dark:bg-neutral-800 px-1 rounded">/shortcut</code> {t('inbox.canned_replies_hint_2')}</p>
                    </div>
                    <button onClick={openCreate} className="flex items-center gap-1.5 rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700 transition">
                        <Plus className="h-4 w-4" /> {t('inbox.new_reply')}
                    </button>
                </div>

                {flash?.success && (
                    <div className="rounded-lg bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-700 dark:bg-green-900/20 dark:border-green-800 dark:text-green-300">
                        {flash.success}
                    </div>
                )}

                {/* Form */}
                {showForm && (
                    <div className="rounded-xl border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-900 p-5 shadow-sm">
                        <div className="flex items-center justify-between mb-4">
                            <h2 className="font-semibold text-neutral-900 dark:text-neutral-100">
                                {editing ? t('inbox.edit_canned_reply') : t('inbox.new_canned_reply')}
                            </h2>
                            <button onClick={resetForm} className="text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-200">
                                <X className="h-4 w-4" />
                            </button>
                        </div>
                        <form onSubmit={submit} className="space-y-4">
                            <div>
                                <label className="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-1">
                                    {t('inbox.shortcut')} <span className="text-neutral-400">{t('inbox.shortcut_hint')}</span>
                                </label>
                                <input
                                    value={form.shortcut}
                                    onChange={e => setForm(f => ({ ...f, shortcut: e.target.value }))}
                                    placeholder={t('inbox.shortcut_placeholder')}
                                    className="w-full rounded-lg border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-800 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500"
                                />
                                {errors.shortcut && <p className="text-red-500 text-xs mt-1">{errors.shortcut}</p>}
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-1">
                                    {t('inbox.body')} <span className="text-neutral-400">{t('inbox.body_hint', { token: '{{contact.first_name}}' })}</span>
                                </label>
                                <textarea
                                    rows={4}
                                    value={form.body}
                                    onChange={e => setForm(f => ({ ...f, body: e.target.value }))}
                                    placeholder={t('inbox.body_placeholder')}
                                    className="w-full rounded-lg border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-800 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500 resize-none"
                                />
                                {errors.body && <p className="text-red-500 text-xs mt-1">{errors.body}</p>}
                            </div>
                            <div className="flex gap-2 justify-end">
                                <button type="button" onClick={resetForm} className="rounded-lg border border-neutral-200 dark:border-neutral-700 px-4 py-2 text-sm hover:bg-neutral-50 dark:hover:bg-neutral-800 transition">
                                    {t('common.cancel')}
                                </button>
                                <button type="submit" className="flex items-center gap-1.5 rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700 transition">
                                    <Check className="h-4 w-4" /> {t('common.save')}
                                </button>
                            </div>
                        </form>
                    </div>
                )}

                {/* List */}
                <div className="rounded-xl border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-900 divide-y divide-neutral-100 dark:divide-neutral-800">
                    {cannedReplies.length === 0 ? (
                        <p className="p-8 text-center text-sm text-neutral-500">{t('inbox.no_canned_replies')}</p>
                    ) : (
                        cannedReplies.map(reply => (
                            <div key={reply.id} className="flex items-start gap-4 px-5 py-4">
                                <div className="flex-1 min-w-0">
                                    <span className="inline-block rounded bg-brand-50 dark:bg-brand-900/30 px-2 py-0.5 text-xs font-mono font-medium text-brand-700 dark:text-brand-300 mb-1">
                                        /{reply.shortcut}
                                    </span>
                                    <p className="text-sm text-neutral-700 dark:text-neutral-300 line-clamp-2">{reply.body}</p>
                                </div>
                                <div className="flex items-center gap-1.5 shrink-0">
                                    <button onClick={() => openEdit(reply)} className="rounded p-1.5 hover:bg-neutral-100 dark:hover:bg-neutral-800 text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-200 transition">
                                        <Pencil className="h-3.5 w-3.5" />
                                    </button>
                                    <button onClick={() => destroy(reply)} className="rounded p-1.5 hover:bg-red-50 dark:hover:bg-red-900/20 text-neutral-400 hover:text-red-500 transition">
                                        <Trash2 className="h-3.5 w-3.5" />
                                    </button>
                                </div>
                            </div>
                        ))
                    )}
                </div>
            </div>
        </ClientLayout>
    );
}
