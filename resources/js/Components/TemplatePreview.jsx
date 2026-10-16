import { Image as ImageIcon, FileVideo, FileText, Reply, ExternalLink, Phone } from 'lucide-react';
import { useTranslation } from 'react-i18next';

/* Renders a WhatsApp-style preview bubble from a template `components` array.
   Placeholders like {{1}} are replaced with their example values when available,
   otherwise the raw {{n}} token is shown. */

function extractPlaceholders(text = '') {
    return [...new Set([...text.matchAll(/\{\{(\d+)\}\}/g)].map(m => m[1]))].sort((a, b) => a - b);
}

/* Substitute {{n}} tokens using an ordered list of example values
   (Meta stores them positionally in example.*_text[0]). */
function substitute(text = '', examples = []) {
    if (!text) return text;
    const phs = extractPlaceholders(text);
    return text.replace(/\{\{(\d+)\}\}/g, (raw, n) => {
        const pos = phs.indexOf(n);
        const val = pos >= 0 ? examples[pos] : undefined;
        return val ? val : raw;
    });
}

const MEDIA_ICON = {
    IMAGE: <ImageIcon className="h-7 w-7" />,
    VIDEO: <FileVideo className="h-7 w-7" />,
    DOCUMENT: <FileText className="h-7 w-7" />,
};

const BTN_ICON = {
    QUICK_REPLY: <Reply className="h-3.5 w-3.5" />,
    URL: <ExternalLink className="h-3.5 w-3.5" />,
    PHONE_NUMBER: <Phone className="h-3.5 w-3.5" />,
};

function HeaderPreview({ comp }) {
    const format = comp.format ?? 'TEXT';

    if (format === 'TEXT') {
        const text = substitute(comp.text ?? '', comp.example?.header_text?.[0] ?? []);
        if (!text) return null;
        return <p className="font-semibold text-[13px] text-neutral-900 dark:text-neutral-100 mb-1">{text}</p>;
    }

    // Media header — show the uploaded preview if present, else a typed placeholder tile.
    const preview = comp.example?._preview;
    return (
        <div className="mb-1.5 -mx-2.5 -mt-1 overflow-hidden rounded-t-md">
            {format === 'IMAGE' && preview ? (
                <img src={preview} alt="" className="h-32 w-full object-cover" />
            ) : (
                <div className="flex h-24 w-full items-center justify-center bg-neutral-200 text-neutral-500 dark:bg-neutral-700 dark:text-neutral-400">
                    {MEDIA_ICON[format] ?? MEDIA_ICON.IMAGE}
                </div>
            )}
        </div>
    );
}

export default function TemplatePreview({ components = [], className = '' }) {
    const { t } = useTranslation();
    const header = components.find(c => c.type === 'HEADER');
    const body = components.find(c => c.type === 'BODY');
    const footer = components.find(c => c.type === 'FOOTER');
    const buttons = components.find(c => c.type === 'BUTTONS')?.buttons ?? [];

    const bodyText = substitute(body?.text ?? '', body?.example?.body_text?.[0] ?? []);

    const isEmpty = !header && !bodyText && !footer && buttons.length === 0;

    return (
        <div className={`rounded-lg bg-[#e5ddd5] dark:bg-neutral-800 p-3 ${className}`}>
            <div className="ml-auto max-w-[85%] rounded-md rounded-tr-none bg-[#dcf8c6] dark:bg-emerald-900/40 px-2.5 py-1.5 shadow-sm">
                {header && <HeaderPreview comp={header} />}

                {bodyText ? (
                    <p className="whitespace-pre-wrap break-words text-[13px] leading-snug text-neutral-800 dark:text-neutral-100">
                        {bodyText}
                    </p>
                ) : (
                    isEmpty && <p className="text-[13px] italic text-neutral-400">{t('whatsapp.templates_preview_placeholder')}</p>
                )}

                {footer?.text && (
                    <p className="mt-1 text-[11px] text-neutral-500 dark:text-neutral-400">{footer.text}</p>
                )}

                <span className="mt-0.5 block text-right text-[10px] text-neutral-400">12:00</span>
            </div>

            {buttons.length > 0 && (
                <div className="mt-1 ml-auto max-w-[85%] space-y-1">
                    {buttons.map((btn, i) => (
                        <div
                            key={i}
                            className="flex items-center justify-center gap-1.5 rounded-md bg-white dark:bg-neutral-700 px-2 py-1.5 text-[13px] font-medium text-[#00a5f4] dark:text-sky-300 shadow-sm"
                        >
                            {BTN_ICON[btn.type] ?? BTN_ICON.QUICK_REPLY}
                            <span className="truncate">{btn.text || t('whatsapp.templates_button_fallback')}</span>
                        </div>
                    ))}
                </div>
            )}
        </div>
    );
}
