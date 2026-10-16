import { Head, Link, useForm, usePage } from '@inertiajs/react';
import ClientLayout from '@/Layouts/ClientLayout';
import EmptyState from '@/Components/EmptyState';
import { Plus, BookOpen, FileText, Database, X } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';

export default function AiKnowledgeBasesIndex({ knowledgeBases }) {
    const { t } = useTranslation();
    const { props } = usePage();
    const flash = props.flash ?? {};
    const [showCreate, setShowCreate] = useState(false);

    const { data, setData, post, processing, reset, errors } = useForm({ name: '' });

    const handleCreate = (e) => {
        e.preventDefault();
        post(route('client.ai.knowledge-bases.store'), { onSuccess: () => { reset(); setShowCreate(false); } });
    };

    const totalDocs = knowledgeBases.reduce((s, kb) => s + (kb.documents_count ?? 0), 0);
    const activeCount = knowledgeBases.filter(kb => kb.status === 'active').length;

    return (
        <ClientLayout title={t('ai.kb_title')}>
            <Head title={`${t('ai.kb_title')} · AI`} />
            <div className="space-y-6">
                {/* Header */}
                <div className="flex items-start justify-between gap-4">
                    <div>
                        <h2 className="text-xl font-semibold text-neutral-900 dark:text-neutral-100">{t('ai.kb_title')}</h2>
                        <p className="text-sm text-neutral-500 dark:text-neutral-400 mt-0.5">{t('ai.kb_subtitle')}</p>
                    </div>
                    {(
                        <button
                            onClick={() => setShowCreate(true)}
                            className="flex items-center gap-1.5 rounded-lg bg-brand-600 px-3 py-2 text-sm font-medium text-white hover:bg-brand-700 transition shrink-0"
                        >
                            <Plus className="h-4 w-4" /> {t('ai.new_kb')}
                        </button>
                    )}
                </div>

                {/* Stats */}
                {knowledgeBases.length > 0 && (
                    <div className="grid grid-cols-3 gap-3">
                        {[
                            { label: t('ai.kb_title'), value: knowledgeBases.length, icon: Database },
                            { label: t('common.active'), value: activeCount, icon: BookOpen },
                            { label: t('ai.total_documents'), value: totalDocs, icon: FileText },
                        ].map(({ label, value, icon: Icon }) => (
                            <div key={label} className="rounded-xl border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-900 px-4 py-3 flex items-center gap-3">
                                <div className="rounded-lg bg-brand-50 dark:bg-brand-900/20 p-2">
                                    <Icon className="h-4 w-4 text-brand-600 dark:text-brand-400" />
                                </div>
                                <div>
                                    <p className="text-lg font-semibold text-neutral-900 dark:text-neutral-100 leading-none">{value}</p>
                                    <p className="text-xs text-neutral-500 dark:text-neutral-400 mt-0.5">{label}</p>
                                </div>
                            </div>
                        ))}
                    </div>
                )}

                {flash.success && (
                    <div className="rounded-lg bg-green-50 dark:bg-green-900/30 border border-green-200 dark:border-green-800 text-green-800 dark:text-green-200 px-4 py-2.5 text-sm">
                        {flash.success}
                    </div>
                )}

                {/* Grid */}
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    {knowledgeBases.map(kb => (
                        <Link
                            key={kb.id}
                            href={route('client.ai.knowledge-bases.show', kb.uuid)}
                            className="group rounded-xl border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-900 p-5 block hover:border-brand-300 dark:hover:border-brand-700 hover:shadow-md transition-all"
                        >
                            <div className="flex items-start justify-between gap-2 mb-3">
                                <div className="rounded-lg bg-brand-50 dark:bg-brand-900/20 p-2 group-hover:bg-brand-100 dark:group-hover:bg-brand-900/40 transition">
                                    <BookOpen className="h-5 w-5 text-brand-600 dark:text-brand-400" />
                                </div>
                                <span className={`rounded-full px-2 py-0.5 text-xs font-medium ${
                                    kb.status === 'active'
                                        ? 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300'
                                        : 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/40 dark:text-yellow-300'
                                }`}>
                                    {kb.status === 'active' ? t('common.active') : t('ai.kb_status_draft')}
                                </span>
                            </div>
                            <h3 className="font-semibold text-sm text-neutral-900 dark:text-neutral-100 mb-1 truncate">{kb.name}</h3>
                            <div className="flex items-center gap-1.5 text-xs text-neutral-500 dark:text-neutral-400">
                                <FileText className="h-3.5 w-3.5 shrink-0" />
                                <span>{t('ai.document_count', { count: kb.documents_count ?? 0 })}</span>
                            </div>
                        </Link>
                    ))}

                    {knowledgeBases.length === 0 && (
                        <div className="col-span-3">
                            <EmptyState
                                icon={<BookOpen className="h-8 w-8" />}
                                title={t('ai.kb_empty_title')}
                                description={t('ai.kb_empty_description')}
                                action={{ label: t('ai.new_kb'), onClick: () => setShowCreate(true) }}
                            />
                        </div>
                    )}
                </div>
            </div>

            {/* Create Modal */}
            {showCreate && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4">
                    <div className="w-full max-w-sm rounded-2xl bg-white dark:bg-neutral-900 shadow-2xl">
                        <div className="flex items-center justify-between px-6 pt-5 pb-4 border-b border-neutral-100 dark:border-neutral-800">
                            <h3 className="text-base font-semibold text-neutral-900 dark:text-neutral-100">{t('ai.new_kb')}</h3>
                            <button onClick={() => setShowCreate(false)} className="text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-200 transition">
                                <X className="h-4 w-4" />
                            </button>
                        </div>
                        <form onSubmit={handleCreate} className="px-6 py-4 space-y-4">
                            <div>
                                <label className="block text-xs font-medium text-neutral-700 dark:text-neutral-300 mb-1">{t('common.name')}</label>
                                <input
                                    type="text"
                                    value={data.name}
                                    onChange={e => setData('name', e.target.value)}
                                    required
                                    autoFocus
                                    placeholder={t('ai.kb_name_placeholder')}
                                    className="w-full rounded-lg border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 px-3 py-2 text-sm text-neutral-900 dark:text-neutral-100 placeholder-neutral-400 focus:outline-none focus:ring-2 focus:ring-brand-500 transition"
                                />
                                {errors.name && <p className="mt-1 text-xs text-red-500">{errors.name}</p>}
                            </div>
                            <div className="flex gap-2 pt-1 pb-2">
                                <button
                                    type="submit"
                                    disabled={processing}
                                    className="flex-1 rounded-lg bg-brand-600 py-2 text-sm font-medium text-white hover:bg-brand-700 disabled:opacity-60 transition"
                                >
                                    {processing ? t('ai.creating') : t('ai.create_kb')}
                                </button>
                                <button
                                    type="button"
                                    onClick={() => setShowCreate(false)}
                                    className="rounded-lg border border-neutral-300 dark:border-neutral-600 px-4 py-2 text-sm text-neutral-700 dark:text-neutral-300 hover:bg-neutral-50 dark:hover:bg-neutral-800 transition"
                                >
                                    {t('common.cancel')}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </ClientLayout>
    );
}
