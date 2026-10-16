import { useState, useRef, useCallback } from 'react';
import axios from 'axios';
import { Upload, Link, X, Image, FileText, Check, Loader2, AlertCircle } from 'lucide-react';
import { useTranslation } from 'react-i18next';

/**
 * MediaUpload — dual-mode media input component.
 *
 * Props:
 *   value        (string)   — current URL value
 *   onChange     (fn)       — called with the new URL string
 *   accept       (string)   — file accept attribute (default: "image/*")
 *   maxSizeMb    (number)   — client-side size guard in MB (default: 50)
 *   label        (string)   — optional label above the input
 *   placeholder  (string)   — URL input placeholder
 *   collection   (string)   — media collection name sent to the server
 *   disabled     (bool)
 *   className    (string)
 */
export default function MediaUpload({
    value = '',
    onChange,
    accept = 'image/*',
    maxSizeMb = 50,
    label,
    placeholder = 'https://',
    collection = 'default',
    disabled = false,
    className = '',
}) {
    const { t } = useTranslation();
    const [mode, setMode] = useState('upload'); // 'url' | 'upload'
    const [uploading, setUploading] = useState(false);
    const [error, setError] = useState(null);
    const [dragging, setDragging] = useState(false);
    const fileRef = useRef(null);

    const isImage = value && /\.(jpe?g|png|gif|webp|svg|avif)(\?.*)?$/i.test(value);

    const uploadFile = useCallback(async (file) => {
        if (!file) return;

        if (file.size > maxSizeMb * 1024 * 1024) {
            setError(t('ui.file_exceeds_limit', { max: maxSizeMb }));
            return;
        }

        setError(null);
        setUploading(true);

        try {
            const form = new FormData();
            form.append('file', file);
            form.append('collection', collection);

            const resp = await axios.post(route('client.media.store'), form, {
                headers: { 'Content-Type': 'multipart/form-data' },
            });

            onChange?.(resp.data.url);
            setMode('url');
        } catch (err) {
            const msg =
                err?.response?.data?.error ||
                err?.response?.data?.message ||
                t('ui.upload_failed_retry');
            setError(msg);
        } finally {
            setUploading(false);
        }
    }, [collection, maxSizeMb, onChange, t]);

    const handleFileChange = (e) => {
        const file = e.target.files?.[0];
        if (file) uploadFile(file);
        e.target.value = '';
    };

    const handleDrop = (e) => {
        e.preventDefault();
        setDragging(false);
        const file = e.dataTransfer.files?.[0];
        if (file) uploadFile(file);
    };

    const clear = () => {
        onChange?.('');
        setError(null);
    };

    return (
        <div className={`space-y-2 ${className}`}>
            {label && (
                <label className="block text-sm font-medium text-neutral-700 dark:text-neutral-300">
                    {label}
                </label>
            )}

            {/* Mode toggle */}
            <div className="flex rounded-soft border border-neutral-200 dark:border-neutral-700 overflow-hidden w-fit">
                <button
                    type="button"
                    onClick={() => setMode('url')}
                    className={`flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium transition-colors ${
                        mode === 'url'
                            ? 'bg-brand-500 text-white'
                            : 'bg-white dark:bg-neutral-800 text-neutral-600 dark:text-neutral-400 hover:bg-neutral-50 dark:hover:bg-neutral-700'
                    }`}
                >
                    <Link className="h-3.5 w-3.5" />
                    {t('ui.url')}
                </button>
                <button
                    type="button"
                    onClick={() => setMode('upload')}
                    className={`flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium transition-colors ${
                        mode === 'upload'
                            ? 'bg-brand-500 text-white'
                            : 'bg-white dark:bg-neutral-800 text-neutral-600 dark:text-neutral-400 hover:bg-neutral-50 dark:hover:bg-neutral-700'
                    }`}
                >
                    <Upload className="h-3.5 w-3.5" />
                    {t('ui.upload')}
                </button>
            </div>

            {/* URL mode */}
            {mode === 'url' && (
                <div className="relative">
                    <input
                        type="url"
                        value={value}
                        onChange={(e) => { setError(null); onChange?.(e.target.value); }}
                        placeholder={placeholder}
                        disabled={disabled}
                        className="w-full rounded-soft border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 text-sm px-3 py-2 pr-8 focus:outline-none focus:ring-2 focus:ring-brand-500/30 focus:border-brand-500 disabled:opacity-50"
                    />
                    {value && (
                        <button
                            type="button"
                            onClick={clear}
                            className="absolute right-2 top-1/2 -translate-y-1/2 text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-200"
                        >
                            <X className="h-4 w-4" />
                        </button>
                    )}
                </div>
            )}

            {/* Upload mode */}
            {mode === 'upload' && (
                <div
                    onDragOver={(e) => { e.preventDefault(); setDragging(true); }}
                    onDragLeave={() => setDragging(false)}
                    onDrop={handleDrop}
                    onClick={() => !uploading && fileRef.current?.click()}
                    className={`relative flex flex-col items-center justify-center gap-2 rounded-soft-lg border-2 border-dashed cursor-pointer transition-colors px-4 py-6
                        ${dragging
                            ? 'border-brand-500 bg-brand-50 dark:bg-brand-900/20'
                            : 'border-neutral-300 dark:border-neutral-600 bg-neutral-50 dark:bg-neutral-800/50 hover:border-brand-400 hover:bg-brand-50/50 dark:hover:bg-brand-900/10'
                        }
                        ${disabled ? 'opacity-50 cursor-not-allowed' : ''}
                    `}
                >
                    {uploading ? (
                        <>
                            <Loader2 className="h-6 w-6 text-brand-500 animate-spin" />
                            <span className="text-sm text-neutral-500 dark:text-neutral-400">{t('ui.uploading')}</span>
                        </>
                    ) : (
                        <>
                            <Upload className="h-6 w-6 text-neutral-400" />
                            <span className="text-sm text-neutral-500 dark:text-neutral-400 text-center">
                                {t('ui.drag_drop_or')} <span className="text-brand-600 dark:text-brand-400 font-medium">{t('ui.browse')}</span>
                            </span>
                            <span className="text-xs text-neutral-400">
                                {accept} · {t('ui.max_size_mb', { max: maxSizeMb })}
                            </span>
                        </>
                    )}
                    <input
                        ref={fileRef}
                        type="file"
                        accept={accept}
                        className="hidden"
                        onChange={handleFileChange}
                        disabled={disabled || uploading}
                    />
                </div>
            )}

            {/* Error message */}
            {error && (
                <div className="flex items-center gap-2 text-xs text-coral-600 dark:text-coral-400">
                    <AlertCircle className="h-3.5 w-3.5 shrink-0" />
                    {error}
                </div>
            )}

            {/* Preview */}
            {value && (
                <div className="flex items-center gap-3 p-2 rounded-soft bg-neutral-50 dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700">
                    {isImage ? (
                        <img src={value} alt="preview" className="h-10 w-10 rounded object-cover shrink-0" />
                    ) : (
                        <div className="h-10 w-10 rounded bg-neutral-200 dark:bg-neutral-700 flex items-center justify-center shrink-0">
                            <FileText className="h-5 w-5 text-neutral-500" />
                        </div>
                    )}
                    <span className="text-xs text-neutral-500 dark:text-neutral-400 truncate flex-1 min-w-0">{value}</span>
                    <button
                        type="button"
                        onClick={clear}
                        className="shrink-0 text-neutral-400 hover:text-coral-500 transition"
                    >
                        <X className="h-4 w-4" />
                    </button>
                </div>
            )}
        </div>
    );
}
