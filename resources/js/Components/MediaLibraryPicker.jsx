import { useCallback, useEffect, useState } from 'react';
import axios from 'axios';
import { Check, Film, Image as ImageIcon, Images, Loader2, Search } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Modal } from '@/Components/ui';

function formatBytes(bytes = 0) {
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

export default function MediaLibraryPicker({ onSelect, selectedUrls = [], disabled = false }) {
    const { t } = useTranslation();
    const [open, setOpen] = useState(false);
    const [files, setFiles] = useState([]);
    const [selected, setSelected] = useState([]);
    const [type, setType] = useState('all');
    const [search, setSearch] = useState('');
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState('');
    const [page, setPage] = useState(1);
    const [lastPage, setLastPage] = useState(1);

    const loadFiles = useCallback(async (targetPage = 1, append = false) => {
        setLoading(true);
        setError('');
        try {
            const response = await axios.get(route('client.media.library'), {
                params: { type, search: search.trim() || undefined, page: targetPage },
            });
            const received = response.data?.data ?? [];
            setFiles(current => append ? [...current, ...received] : received);
            setPage(response.data?.current_page ?? targetPage);
            setLastPage(response.data?.last_page ?? 1);
        } catch (requestError) {
            setError(requestError?.response?.data?.message ?? t('social.media_library_error'));
        } finally {
            setLoading(false);
        }
    }, [search, t, type]);

    useEffect(() => {
        if (!open) return;
        const timer = window.setTimeout(() => loadFiles(1, false), 250);
        return () => window.clearTimeout(timer);
    }, [loadFiles, open]);

    const openPicker = () => {
        setSelected([]);
        setOpen(true);
    };

    const toggle = (file) => {
        if (selectedUrls.includes(file.url)) return;
        setSelected(current => current.some(item => item.id === file.id)
            ? current.filter(item => item.id !== file.id)
            : [...current, file]);
    };

    const confirm = () => {
        onSelect?.(selected);
        setOpen(false);
        setSelected([]);
    };

    return (
        <>
            <button
                type="button"
                onClick={openPicker}
                disabled={disabled}
                className="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg border border-brand-200 bg-brand-50 px-3 py-2 text-xs font-semibold text-brand-700 transition hover:border-brand-300 hover:bg-brand-100 focus:outline-none focus:ring-2 focus:ring-brand-500/40 disabled:cursor-not-allowed disabled:opacity-50 dark:border-brand-800 dark:bg-brand-950/30 dark:text-brand-300 dark:hover:bg-brand-900/40"
            >
                <Images className="h-4 w-4" aria-hidden="true" />
                {t('social.choose_from_library')}
            </button>

            <Modal show={open} onClose={() => setOpen(false)} maxWidth="4xl">
                <Modal.Header title={t('social.media_library_title')} onClose={() => setOpen(false)} />
                <Modal.Body className="space-y-4">
                    <p className="text-sm text-neutral-500 dark:text-neutral-400">
                        {t('social.media_library_help')}
                    </p>

                    <div className="flex flex-col gap-3 sm:flex-row">
                        <div className="relative min-w-0 flex-1">
                            <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-neutral-400" aria-hidden="true" />
                            <label htmlFor="media-library-search" className="sr-only">{t('social.media_library_search')}</label>
                            <input
                                id="media-library-search"
                                type="search"
                                value={search}
                                onChange={event => setSearch(event.target.value)}
                                placeholder={t('social.media_library_search')}
                                className="min-h-11 w-full rounded-lg border border-neutral-300 bg-white py-2 pl-10 pr-3 text-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/30 dark:border-neutral-600 dark:bg-neutral-800"
                            />
                        </div>
                        <div className="grid grid-cols-3 gap-1 rounded-lg bg-neutral-100 p-1 dark:bg-neutral-800" aria-label={t('social.media_library_filter')}>
                            {[
                                ['all', Images, t('social.media_filter_all')],
                                ['image', ImageIcon, t('social.media_filter_images')],
                                ['video', Film, t('social.media_filter_videos')],
                            ].map(([value, Icon, label]) => (
                                <button
                                    key={value}
                                    type="button"
                                    onClick={() => setType(value)}
                                    aria-pressed={type === value}
                                    className={`flex min-h-11 items-center justify-center gap-1.5 rounded-md px-3 text-xs font-medium transition ${type === value ? 'bg-white text-brand-700 shadow-sm dark:bg-neutral-700 dark:text-brand-300' : 'text-neutral-500 hover:text-neutral-800 dark:text-neutral-400 dark:hover:text-neutral-100'}`}
                                >
                                    <Icon className="h-4 w-4" aria-hidden="true" /> {label}
                                </button>
                            ))}
                        </div>
                    </div>

                    <div className="min-h-72 max-h-[55vh] overflow-y-auto rounded-xl border border-neutral-200 p-3 dark:border-neutral-700">
                        {loading ? (
                            <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4" aria-label={t('common.loading')}>
                                {Array.from({ length: 8 }).map((_, index) => <div key={index} className="aspect-square animate-pulse rounded-lg bg-neutral-100 dark:bg-neutral-800" />)}
                            </div>
                        ) : error ? (
                            <div role="alert" className="flex min-h-64 items-center justify-center text-center text-sm text-red-600 dark:text-red-400">{error}</div>
                        ) : files.length === 0 ? (
                            <div className="flex min-h-64 flex-col items-center justify-center gap-2 text-center text-neutral-400">
                                <Images className="h-10 w-10" aria-hidden="true" />
                                <p className="text-sm">{t('social.media_library_empty')}</p>
                            </div>
                        ) : (
                            <div>
                              <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4">
                                {files.map(file => {
                                    const isVideo = file.mime_type?.startsWith('video/');
                                    const isSelected = selected.some(item => item.id === file.id);
                                    const alreadyAdded = selectedUrls.includes(file.url);
                                    return (
                                        <button
                                            key={file.id}
                                            type="button"
                                            onClick={() => toggle(file)}
                                            disabled={alreadyAdded}
                                            aria-pressed={isSelected}
                                            aria-label={`${file.filename}${alreadyAdded ? ` — ${t('social.media_already_added')}` : ''}`}
                                            className={`group relative overflow-hidden rounded-xl border-2 text-left transition focus:outline-none focus:ring-2 focus:ring-brand-500/40 ${isSelected ? 'border-brand-500 ring-2 ring-brand-500/20' : 'border-transparent hover:border-brand-300'} ${alreadyAdded ? 'cursor-not-allowed opacity-50' : ''}`}
                                        >
                                            <div className="relative aspect-square overflow-hidden bg-neutral-100 dark:bg-neutral-800">
                                                {isVideo ? (
                                                    <video src={file.url} preload="metadata" muted className="h-full w-full object-cover" />
                                                ) : (
                                                    <img src={file.url} alt="" loading="lazy" className="h-full w-full object-cover" />
                                                )}
                                                <span className="absolute left-2 top-2 rounded-full bg-black/65 p-1.5 text-white">
                                                    {isVideo ? <Film className="h-3.5 w-3.5" aria-hidden="true" /> : <ImageIcon className="h-3.5 w-3.5" aria-hidden="true" />}
                                                </span>
                                                {(isSelected || alreadyAdded) && (
                                                    <span className="absolute right-2 top-2 flex h-7 w-7 items-center justify-center rounded-full bg-brand-600 text-white shadow">
                                                        <Check className="h-4 w-4" aria-hidden="true" />
                                                    </span>
                                                )}
                                            </div>
                                            <div className="bg-white p-2 dark:bg-neutral-900">
                                                <p className="truncate text-xs font-medium text-neutral-800 dark:text-neutral-200">{file.filename}</p>
                                                <p className="mt-0.5 text-[11px] text-neutral-400">{alreadyAdded ? t('social.media_already_added') : formatBytes(file.size_bytes)}</p>
                                            </div>
                                        </button>
                                    );
                                })}
                              </div>
                              {page < lastPage && (
                                <button
                                    type="button"
                                    onClick={() => loadFiles(page + 1, true)}
                                    disabled={loading}
                                    className="mx-auto mt-4 flex min-h-11 items-center justify-center gap-2 rounded-lg border border-neutral-300 px-4 text-sm font-medium text-neutral-700 hover:bg-neutral-50 disabled:opacity-50 dark:border-neutral-600 dark:text-neutral-300 dark:hover:bg-neutral-800"
                                >
                                    {loading && <Loader2 className="h-4 w-4 animate-spin" aria-hidden="true" />}
                                    {t('social.media_library_load_more')}
                                </button>
                              )}
                            </div>
                        )}
                    </div>
                </Modal.Body>
                <Modal.Footer className="flex-col-reverse sm:flex-row sm:justify-between">
                    <span className="text-xs text-neutral-500 dark:text-neutral-400" aria-live="polite">
                        {t('social.media_selected_count', { count: selected.length })}
                    </span>
                    <div className="flex w-full gap-2 sm:w-auto">
                        <button type="button" onClick={() => setOpen(false)} className="min-h-11 flex-1 rounded-lg border border-neutral-300 px-4 text-sm font-medium text-neutral-700 hover:bg-neutral-50 dark:border-neutral-600 dark:text-neutral-300 dark:hover:bg-neutral-800 sm:flex-none">
                            {t('common.cancel')}
                        </button>
                        <button type="button" onClick={confirm} disabled={selected.length === 0 || loading} className="inline-flex min-h-11 flex-1 items-center justify-center gap-2 rounded-lg bg-brand-600 px-4 text-sm font-semibold text-white hover:bg-brand-700 disabled:cursor-not-allowed disabled:opacity-50 sm:flex-none">
                            {loading && <Loader2 className="h-4 w-4 animate-spin" aria-hidden="true" />}
                            {t('social.add_selected_media', { count: selected.length })}
                        </button>
                    </div>
                </Modal.Footer>
            </Modal>
        </>
    );
}
