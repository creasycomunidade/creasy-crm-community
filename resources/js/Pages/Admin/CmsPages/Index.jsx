import { useState } from 'react';
import AdminLayout from '@/Layouts/AdminLayout';
import { useForm, router } from '@inertiajs/react';
import { FileText, Plus, Pencil, Trash2 } from 'lucide-react';
import { useTranslation } from 'react-i18next';

function PageForm({ page = null, onClose }) {
    const { t } = useTranslation();
    const { data, setData, post, put, processing, errors } = useForm({
        slug: page?.slug ?? '',
        title: page?.title ?? '',
        content: page?.content ?? '',
        meta_title: page?.meta_title ?? '',
        meta_description: page?.meta_description ?? '',
        published: page?.published ?? true,
        layout: page?.layout ?? 'marketing',
    });

    const submit = (e) => {
        e.preventDefault();
        if (page) {
            put(route('admin.cms-pages.update', page.id), { onSuccess: onClose });
        } else {
            post(route('admin.cms-pages.store'), { onSuccess: onClose });
        }
    };

    return (
        <form onSubmit={submit} className="space-y-4">
            <div className="grid grid-cols-2 gap-4">
                <div>
                    <label className="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-1">{t('cms.col_slug')}</label>
                    <input type="text" value={data.slug} onChange={e => setData('slug', e.target.value)} className="w-full border border-neutral-300 dark:border-neutral-600 rounded-lg px-3 py-2 text-sm dark:bg-neutral-700 dark:text-white font-mono" placeholder={t('cms.slug_placeholder')} required />
                    {errors.slug && <p className="text-coral-600 text-xs mt-1">{errors.slug}</p>}
                </div>
                <div>
                    <label className="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-1">{t('cms.col_title')}</label>
                    <input type="text" value={data.title} onChange={e => setData('title', e.target.value)} className="w-full border border-neutral-300 dark:border-neutral-600 rounded-lg px-3 py-2 text-sm dark:bg-neutral-700 dark:text-white" required />
                </div>
                <div>
                    <label className="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-1">{t('cms.col_layout')}</label>
                    <select value={data.layout} onChange={e => setData('layout', e.target.value)} className="w-full border border-neutral-300 dark:border-neutral-600 rounded-lg px-3 py-2 text-sm dark:bg-neutral-700 dark:text-white">
                        <option value="marketing">{t('cms.layout_marketing')}</option>
                        <option value="legal">{t('cms.layout_legal')}</option>
                    </select>
                </div>
                <div className="flex items-center gap-2 pt-6">
                    <input type="checkbox" id="published" checked={data.published} onChange={e => setData('published', e.target.checked)} className="rounded" />
                    <label htmlFor="published" className="text-sm text-neutral-700 dark:text-neutral-300">{t('cms.published')}</label>
                </div>
            </div>
            <div>
                <label className="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-1">{t('cms.content_html')}</label>
                <textarea value={data.content} onChange={e => setData('content', e.target.value)} rows={10} className="w-full border border-neutral-300 dark:border-neutral-600 rounded-lg px-3 py-2 text-sm dark:bg-neutral-700 dark:text-white font-mono resize-y" />
            </div>
            <div className="grid grid-cols-2 gap-4">
                <div>
                    <label className="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-1">{t('cms.meta_title')}</label>
                    <input type="text" value={data.meta_title} onChange={e => setData('meta_title', e.target.value)} className="w-full border border-neutral-300 dark:border-neutral-600 rounded-lg px-3 py-2 text-sm dark:bg-neutral-700 dark:text-white" />
                </div>
                <div>
                    <label className="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-1">{t('cms.meta_description')}</label>
                    <input type="text" value={data.meta_description} onChange={e => setData('meta_description', e.target.value)} className="w-full border border-neutral-300 dark:border-neutral-600 rounded-lg px-3 py-2 text-sm dark:bg-neutral-700 dark:text-white" />
                </div>
            </div>
            <div className="flex justify-end gap-2">
                <button type="button" onClick={onClose} className="px-4 py-2 text-sm border border-neutral-300 dark:border-neutral-600 rounded-lg">{t('common.cancel')}</button>
                <button type="submit" disabled={processing} className="px-4 py-2 text-sm bg-brand-500 text-white rounded-soft hover:bg-brand-600 shadow-soft disabled:opacity-50 transition-all duration-150">
                    {page ? t('cms.update_page') : t('cms.create_page')}
                </button>
            </div>
        </form>
    );
}

export default function CmsPagesIndex({ pages }) {
    const { t } = useTranslation();
    const [showCreate, setShowCreate] = useState(false);
    const [editing, setEditing] = useState(null);

    return (
        <AdminLayout title={t('admin.cms_pages')}>
            <div className="max-w-5xl space-y-6">
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-3">
                        <FileText className="h-6 w-6 text-brand-600 dark:text-brand-400" />
                        <h1 className="text-xl font-bold text-neutral-900 dark:text-white">{t('cms.title')}</h1>
                    </div>
                    <button onClick={() => setShowCreate(true)} className="flex items-center gap-2 px-4 py-2 bg-brand-500 text-white text-sm rounded-soft hover:bg-brand-600 shadow-soft transition-all duration-150">
                        <Plus className="h-4 w-4" /> {t('cms.new_page')}
                    </button>
                </div>

                {showCreate && (
                    <div className="bg-white dark:bg-neutral-800 rounded-xl border border-neutral-200 dark:border-neutral-700 p-6">
                        <h2 className="text-base font-semibold mb-4 text-neutral-900 dark:text-white">{t('cms.new_page')}</h2>
                        <PageForm onClose={() => setShowCreate(false)} />
                    </div>
                )}

                <div className="overflow-x-auto bg-white dark:bg-neutral-800 rounded-xl border border-neutral-200 dark:border-neutral-700">
                    <table className="w-full text-sm">
                        <thead className="bg-neutral-50 dark:bg-neutral-700">
                            <tr>
                                <th className="px-4 py-3 text-left font-medium text-neutral-600 dark:text-neutral-300">{t('cms.col_slug')}</th>
                                <th className="px-4 py-3 text-left font-medium text-neutral-600 dark:text-neutral-300">{t('cms.col_title')}</th>
                                <th className="px-4 py-3 text-left font-medium text-neutral-600 dark:text-neutral-300">{t('cms.col_layout')}</th>
                                <th className="px-4 py-3 text-left font-medium text-neutral-600 dark:text-neutral-300">{t('cms.col_status')}</th>
                                <th className="px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-neutral-100 dark:divide-neutral-700">
                            {pages?.map(p => (
                                <tr key={p.id}>
                                    {editing?.id === p.id ? (
                                        <td colSpan={5} className="px-4 py-4">
                                            <PageForm page={p} onClose={() => setEditing(null)} />
                                        </td>
                                    ) : (
                                        <>
                                            <td className="px-4 py-3 font-mono text-brand-600 dark:text-brand-400 text-xs">/p/{p.slug}</td>
                                            <td className="px-4 py-3 font-medium text-neutral-900 dark:text-white">{p.title}</td>
                                            <td className="px-4 py-3 text-neutral-600 dark:text-neutral-400 capitalize">{p.layout}</td>
                                            <td className="px-4 py-3">
                                                <span className={`px-2 py-0.5 rounded-full text-xs font-medium ${p.published ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400' : 'bg-neutral-100 text-neutral-500'}`}>
                                                    {p.published ? t('cms.published') : t('cms.draft')}
                                                </span>
                                            </td>
                                            <td className="px-4 py-3">
                                                <div className="flex items-center gap-2 justify-end">
                                                    <button onClick={() => setEditing(p)} className="p-1 text-neutral-400 hover:text-brand-600 dark:hover:text-brand-400 transition"><Pencil className="h-4 w-4" /></button>
                                                    <button onClick={() => { if (confirm(t('cms.delete_confirm'))) router.delete(route('admin.cms-pages.destroy', p.id)); }} className="p-1 text-neutral-400 hover:text-coral-600"><Trash2 className="h-4 w-4" /></button>
                                                </div>
                                            </td>
                                        </>
                                    )}
                                </tr>
                            ))}
                            {!pages?.length && (
                                <tr><td colSpan={5} className="px-4 py-8 text-center text-neutral-400 dark:text-neutral-500">{t('cms.no_pages')}</td></tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </AdminLayout>
    );
}
