import { useState, useRef } from 'react';
import ClientLayout from '@/Layouts/ClientLayout';
import { Head, router, usePage } from '@inertiajs/react';
import { Image, Upload, Trash2, Copy, Check } from 'lucide-react';
import axios from 'axios';
import { useTranslation } from 'react-i18next';

function formatBytes(bytes) {
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
    if (bytes < 1024 * 1024 * 1024) return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
    return (bytes / (1024 * 1024 * 1024)).toFixed(2) + ' GB';
}

function StorageBar({ used, quota }) {
    const { t } = useTranslation();
    const percent = quota > 0 ? Math.min(100, Math.round((used / quota) * 100)) : 0;
    return (
        <div className="space-y-1">
            <div className="flex justify-between text-xs text-neutral-500 dark:text-neutral-400">
                <span>{formatBytes(used)} {t('media.storage_used')}</span>
                <span>{formatBytes(quota)} {t('media.storage_quota')}</span>
            </div>
            <div className="h-2 rounded-full bg-neutral-200 dark:bg-neutral-700 overflow-hidden">
                <div
                    className={`h-full rounded-full ${percent > 90 ? 'bg-coral-500' : percent > 70 ? 'bg-amber-500' : 'bg-brand-500'}`}
                    style={{ width: `${percent}%` }}
                />
            </div>
        </div>
    );
}

function MediaCard({ file, onDelete }) {
    const [copied, setCopied] = useState(false);
    const isImage = file.mime_type.startsWith('image/');

    const copy = () => {
        navigator.clipboard.writeText(file.url);
        setCopied(true);
        setTimeout(() => setCopied(false), 2000);
    };

    return (
        <div className="bg-white dark:bg-neutral-800 rounded-soft border border-neutral-200 dark:border-neutral-700 overflow-hidden group">
            <div className="aspect-square bg-neutral-100 dark:bg-neutral-700 flex items-center justify-center overflow-hidden relative">
                {isImage
                    ? <img src={file.url} alt={file.filename} className="w-full h-full object-cover" />
                    : <div className="flex flex-col items-center gap-2 text-neutral-400">
                        <Image className="h-8 w-8" />
                        <span className="text-xs">{file.mime_type}</span>
                    </div>
                }
                <div className="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center gap-2">
                    <button onClick={copy} className="p-2 bg-white rounded-soft text-neutral-700 hover:text-brand-600">
                        {copied ? <Check className="h-4 w-4 text-green-500" /> : <Copy className="h-4 w-4" />}
                    </button>
                    <button onClick={onDelete} className="p-2 bg-white rounded-soft text-neutral-700 hover:text-coral-600">
                        <Trash2 className="h-4 w-4" />
                    </button>
                </div>
            </div>
            <div className="p-2">
                <p className="text-xs font-medium text-neutral-700 dark:text-neutral-300 truncate">{file.filename}</p>
                <p className="text-xs text-neutral-400">{formatBytes(file.size_bytes)}</p>
            </div>
        </div>
    );
}

export default function MediaIndex({ files, usedBytes, quotaBytes }) {
    const { t } = useTranslation();
    const { flash } = usePage().props;
    const fileRef = useRef(null);
    const [uploading, setUploading] = useState(false);
    const [uploadError, setUploadError] = useState('');

    const handleUpload = async (e) => {
        const file = e.target.files?.[0];
        if (!file) return;
        setUploading(true);
        setUploadError('');

        try {
            const formData = new FormData();
            formData.append('file', file);
            await axios.post(route('client.media.store'), formData);
            router.reload();
        } catch (err) {
            const msg = err?.response?.data?.error ?? err?.response?.data?.message ?? 'Upload failed.';
            setUploadError(msg);
        } finally {
            setUploading(false);
            if (fileRef.current) fileRef.current.value = '';
        }
    };

    const handleDelete = async (id) => {
        if (!confirm(t('media.delete_confirm'))) return;
        try {
            await axios.delete(route('client.media.destroy', id));
            router.reload();
        } catch {
            // silently ignore
        }
    };

    return (
        <ClientLayout title={t('media.title')}>
            <Head title={t('media.title')} />
            <div className="space-y-6 max-w-5xl">
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-3">
                        <Image className="h-6 w-6 text-brand-500" />
                        <div>
                            <h1 className="text-2xl font-bold text-neutral-900 dark:text-white">{t('media.title')}</h1>
                            <p className="text-sm text-neutral-500 dark:text-neutral-400">{t('media.subtitle')}</p>
                        </div>
                    </div>
                    {(
                        <div>
                            <input ref={fileRef} type="file" className="hidden" onChange={handleUpload} />
                            <button
                                onClick={() => fileRef.current?.click()}
                                disabled={uploading}
                                className="inline-flex items-center gap-2 rounded-soft bg-brand-500 px-4 py-2 text-sm font-medium text-white hover:bg-brand-600 shadow-soft transition-all duration-150 disabled:opacity-50"
                            >
                                <Upload className="h-4 w-4" />
                                {uploading ? t('media.uploading') : t('media.upload_file')}
                            </button>
                        </div>
                    )}
                </div>

                <StorageBar used={usedBytes} quota={quotaBytes} />

                {uploadError && (
                    <div className="rounded-soft bg-coral-50 dark:bg-coral-900/20 text-coral-800 dark:text-coral-200 px-4 py-3 text-sm">{uploadError}</div>
                )}

                {files.data?.length === 0 && (
                    <div className="text-center py-16 text-neutral-400 dark:text-neutral-500">
                        <Image className="h-12 w-12 mx-auto mb-3 opacity-30" />
                        <p>{t('media.no_files')}</p>
                    </div>
                )}

                <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-3">
                    {files.data?.map(file => (
                        <MediaCard key={file.id} file={file} onDelete={() => handleDelete(file.id)} />
                    ))}
                </div>
            </div>
        </ClientLayout>
    );
}
