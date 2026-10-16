import { Head, router, usePage } from '@inertiajs/react';
import ClientLayout from '@/Layouts/ClientLayout';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Plus, Pencil, Trash2, X, Check } from 'lucide-react';

const PRESET_COLORS = [
    '#6366f1', '#8b5cf6', '#ec4899', '#f43f5e',
    '#f97316', '#eab308', '#22c55e', '#14b8a6',
    '#06b6d4', '#3b82f6', '#64748b', '#1f2937',
];

export default function LabelsIndex({ labels }) {
    const { t } = useTranslation();
    const { flash } = usePage().props;
    const [showForm, setShowForm] = useState(false);
    const [editing, setEditing] = useState(null);
    const [form, setForm] = useState({ name: '', color: '#6366f1' });
    const [errors, setErrors] = useState({});

    const resetForm = () => {
        setForm({ name: '', color: '#6366f1' });
        setErrors({});
        setEditing(null);
        setShowForm(false);
    };

    const openCreate = () => {
        setEditing(null);
        setForm({ name: '', color: '#6366f1' });
        setErrors({});
        setShowForm(true);
    };

    const openEdit = (label) => {
        setEditing(label);
        setForm({ name: label.name, color: label.color });
        setErrors({});
        setShowForm(true);
    };

    const submit = (e) => {
        e.preventDefault();
        const url    = editing ? route('client.inbox.labels.update', editing.id) : route('client.inbox.labels.store');
        const method = editing ? 'put' : 'post';

        router[method](url, form, {
            preserveScroll: true,
            onSuccess: resetForm,
            onError: (err) => setErrors(err),
        });
    };

    const destroy = (label) => {
        if (!confirm(t('inbox.label_delete_confirm', { name: label.name }))) return;
        router.delete(route('client.inbox.labels.destroy', label.id), { preserveScroll: true });
    };

    return (
        <ClientLayout title={t('inbox.labels')}>
            <Head title={t('inbox.labels')} />
            <div className="max-w-3xl mx-auto space-y-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-neutral-900 dark:text-neutral-100">{t('inbox.labels')}</h1>
                        <p className="text-sm text-neutral-500 mt-1">{t('inbox.labels_subtitle')}</p>
                    </div>
                    <button onClick={openCreate} className="flex items-center gap-1.5 rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700 transition">
                        <Plus className="h-4 w-4" /> {t('inbox.new_label')}
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
                                {editing ? t('inbox.edit_label') : t('inbox.new_label')}
                            </h2>
                            <button onClick={resetForm} className="text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-200">
                                <X className="h-4 w-4" />
                            </button>
                        </div>
                        <form onSubmit={submit} className="space-y-4">
                            <div>
                                <label className="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-1">{t('common.name')}</label>
                                <input
                                    value={form.name}
                                    onChange={e => setForm(f => ({ ...f, name: e.target.value }))}
                                    placeholder={t('inbox.label_name_placeholder')}
                                    className="w-full rounded-lg border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-800 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500"
                                />
                                {errors.name && <p className="text-red-500 text-xs mt-1">{errors.name}</p>}
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-1">{t('inbox.color')}</label>
                                <div className="flex items-center gap-2 flex-wrap">
                                    {PRESET_COLORS.map(c => (
                                        <button
                                            key={c}
                                            type="button"
                                            onClick={() => setForm(f => ({ ...f, color: c }))}
                                            className={`h-6 w-6 rounded-full border-2 transition ${form.color === c ? 'border-neutral-800 dark:border-white scale-110' : 'border-transparent'}`}
                                            style={{ backgroundColor: c }}
                                        />
                                    ))}
                                    <input
                                        type="color"
                                        value={form.color}
                                        onChange={e => setForm(f => ({ ...f, color: e.target.value }))}
                                        className="h-6 w-8 rounded cursor-pointer border border-neutral-200 dark:border-neutral-700"
                                    />
                                </div>
                                {errors.color && <p className="text-red-500 text-xs mt-1">{errors.color}</p>}
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
                    {labels.length === 0 ? (
                        <p className="p-8 text-center text-sm text-neutral-500">{t('inbox.no_labels')}</p>
                    ) : (
                        labels.map(label => (
                            <div key={label.id} className="flex items-center gap-4 px-5 py-3">
                                <span className="h-4 w-4 rounded-full shrink-0" style={{ backgroundColor: label.color }} />
                                <span className="flex-1 text-sm font-medium text-neutral-800 dark:text-neutral-200">{label.name}</span>
                                <div className="flex items-center gap-1.5">
                                    <button onClick={() => openEdit(label)} className="rounded p-1.5 hover:bg-neutral-100 dark:hover:bg-neutral-800 text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-200 transition">
                                        <Pencil className="h-3.5 w-3.5" />
                                    </button>
                                    <button onClick={() => destroy(label)} className="rounded p-1.5 hover:bg-red-50 dark:hover:bg-red-900/20 text-neutral-400 hover:text-red-500 transition">
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
