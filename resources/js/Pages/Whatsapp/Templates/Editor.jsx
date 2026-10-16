import { Head, router, usePage } from '@inertiajs/react';
import ClientLayout from '@/Layouts/ClientLayout';
import {
    ArrowLeft, Plus, Trash2, Info, Upload, Image, FileVideo,
    FileText as FileTextIcon, ChevronUp, ChevronDown, Phone, Link as LinkIcon,
    MessageSquare, X,
} from 'lucide-react';
import { useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import axios from 'axios';
import TemplatePreview from '@/Components/TemplatePreview';

/* ─── Constants ─────────────────────────────────────────────────────────── */

const LANGUAGES = [
    ['en',    'whatsapp.templates_lang_en'],
    ['bn',    'whatsapp.templates_lang_bn'],
    ['ar',    'whatsapp.templates_lang_ar'],
    ['es',    'whatsapp.templates_lang_es'],
    ['fr',    'whatsapp.templates_lang_fr'],
    ['hi',    'whatsapp.templates_lang_hi'],
    ['id',    'whatsapp.templates_lang_id'],
    ['pt_BR', 'whatsapp.templates_lang_pt_br'],
    ['tr',    'whatsapp.templates_lang_tr'],
    ['zh_CN', 'whatsapp.templates_lang_zh_cn'],
];

const HEADER_FORMATS = ['TEXT', 'IMAGE', 'VIDEO', 'DOCUMENT'];

const CATEGORY_LABEL_KEYS = {
    MARKETING:      'whatsapp.templates_category_marketing',
    UTILITY:        'whatsapp.templates_category_utility',
    AUTHENTICATION: 'whatsapp.templates_category_authentication',
};

const SECTION_LABEL_KEYS = {
    HEADER:  'whatsapp.templates_section_header',
    BODY:    'whatsapp.templates_section_body',
    FOOTER:  'whatsapp.templates_section_footer',
    BUTTONS: 'whatsapp.templates_section_buttons',
};

const BTN_LIMITS = { QUICK_REPLY: 3, URL: 2, PHONE_NUMBER: 1 };
const BTN_TOTAL_MAX = 10;

/* ─── Helpers ────────────────────────────────────────────────────────────── */

function extractPlaceholders(text = '') {
    return [...new Set([...text.matchAll(/\{\{(\d+)\}\}/g)].map(m => m[1]))].sort((a, b) => a - b);
}

function emptyHeader() {
    return { type: 'HEADER', format: 'TEXT', text: '', example: {} };
}
function emptyBody() {
    return { type: 'BODY', text: '', example: {} };
}
function emptyFooter() {
    return { type: 'FOOTER', text: '' };
}
function emptyButtons() {
    return { type: 'BUTTONS', buttons: [] };
}

/* Rebuild the editor-only `*_text_map` fields from the positional example arrays
   stored on a saved template, so the example-value inputs are pre-filled when editing. */
function hydrateComponents(components) {
    return components.map(comp => {
        if (comp.type === 'BODY') {
            const phs = extractPlaceholders(comp.text ?? '');
            const row = comp.example?.body_text?.[0] ?? [];
            const map = {};
            phs.forEach((ph, i) => { map[ph] = row[i] ?? ''; });
            return { ...comp, example: { ...comp.example, body_text_map: map } };
        }
        if (comp.type === 'HEADER' && (comp.format ?? 'TEXT') === 'TEXT') {
            const phs = extractPlaceholders(comp.text ?? '');
            const row = comp.example?.header_text?.[0] ?? [];
            const map = {};
            phs.forEach((ph, i) => { map[ph] = row[i] ?? ''; });
            return { ...comp, example: { ...comp.example, header_text_map: map } };
        }
        return comp;
    });
}

/* ─── Sub-components ─────────────────────────────────────────────────────── */

function SectionCard({ title, onRemove, children }) {
    return (
        <div className="rounded-lg border border-neutral-200 dark:border-neutral-700 p-4 space-y-3">
            <div className="flex items-center justify-between">
                <span className="text-xs font-semibold tracking-wider uppercase text-neutral-500 dark:text-neutral-400">
                    {title}
                </span>
                {onRemove && (
                    <button type="button" onClick={onRemove} className="text-neutral-400 hover:text-red-500 transition">
                        <Trash2 className="h-4 w-4" />
                    </button>
                )}
            </div>
            {children}
        </div>
    );
}

function ExampleInputs({ label, placeholders, values, onChange }) {
    const { t } = useTranslation();
    if (placeholders.length === 0) return null;
    return (
        <div className="space-y-2 mt-2">
            <p className="text-xs font-medium text-neutral-500 dark:text-neutral-400">{t('whatsapp.templates_example_values', { section: label })}</p>
            {placeholders.map(n => (
                <div key={n} className="flex items-center gap-2">
                    <span className="text-xs font-mono text-neutral-400 w-10 shrink-0">{`{{${n}}}`}</span>
                    <input
                        type="text"
                        placeholder={t('whatsapp.templates_example_for', { token: `{{${n}}}` })}
                        value={values[n] ?? ''}
                        onChange={e => onChange(n, e.target.value)}
                        className="flex-1 rounded-lg border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 px-3 py-1.5 text-sm"
                    />
                </div>
            ))}
        </div>
    );
}

/* ─── HEADER Component ───────────────────────────────────────────────────── */

function HeaderBlock({ comp, onChange, onRemove }) {
    const { t } = useTranslation();
    const fileInputRef = useRef(null);
    const [uploading, setUploading] = useState(false);
    const [uploadError, setUploadError] = useState('');

    const format = comp.format ?? 'TEXT';
    const headerPhs = extractPlaceholders(comp.text ?? '');
    const headerExamples = comp.example?.header_text_map ?? {};

    const setFormat = (f) => onChange({ ...comp, format: f, text: '', example: {} });

    const handleFileChange = async (e) => {
        const file = e.target.files?.[0];
        if (!file) return;
        setUploadError('');
        setUploading(true);

        const formData = new FormData();
        formData.append('file', file);
        formData.append('_token', document.querySelector('meta[name="csrf-token"]')?.content ?? '');

        try {
            const { data } = await axios.post(route('client.whatsapp.templates.upload-media'), formData, {
                headers: { 'Content-Type': 'multipart/form-data' },
            });
            onChange({
                ...comp,
                format: data.format ?? format,
                example: { header_handle: [data.handle], _filename: file.name, _preview: URL.createObjectURL(file) },
            });
        } catch (err) {
            setUploadError(err.response?.data?.error ?? t('whatsapp.templates_upload_failed'));
        } finally {
            setUploading(false);
            e.target.value = '';
        }
    };

    const handleHeaderExampleChange = (n, val) => {
        const map = { ...(comp.example?.header_text_map ?? {}), [n]: val };
        const ordered = headerPhs.map(ph => map[ph] ?? '');
        onChange({ ...comp, example: { header_text_map: map, header_text: [ordered] } });
    };

    return (
        <SectionCard title={t('whatsapp.templates_section_header')} onRemove={onRemove}>
            {/* Format selector */}
            <div className="flex gap-1 flex-wrap">
                {HEADER_FORMATS.map(f => (
                    <button
                        key={f}
                        type="button"
                        onClick={() => setFormat(f)}
                        className={`px-3 py-1 rounded-full text-xs font-medium border transition ${
                            format === f
                                ? 'bg-brand-600 border-brand-600 text-white'
                                : 'border-neutral-300 dark:border-neutral-600 text-neutral-600 dark:text-neutral-400 hover:bg-neutral-50 dark:hover:bg-neutral-800'
                        }`}
                    >
                        {f === 'TEXT' && t('whatsapp.templates_format_text')}
                        {f === 'IMAGE' && <span className="flex items-center gap-1"><Image className="h-3 w-3" /> {t('whatsapp.templates_format_image')}</span>}
                        {f === 'VIDEO' && <span className="flex items-center gap-1"><FileVideo className="h-3 w-3" /> {t('whatsapp.templates_format_video')}</span>}
                        {f === 'DOCUMENT' && <span className="flex items-center gap-1"><FileTextIcon className="h-3 w-3" /> {t('whatsapp.templates_format_document')}</span>}
                    </button>
                ))}
            </div>

            {format === 'TEXT' ? (
                <>
                    <input
                        type="text"
                        value={comp.text ?? ''}
                        onChange={e => onChange({ ...comp, text: e.target.value })}
                        placeholder={t('whatsapp.templates_header_text_placeholder')}
                        maxLength={60}
                        className="w-full rounded-lg border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 px-3 py-2 text-sm"
                    />
                    <ExampleInputs
                        label={t('whatsapp.templates_section_header')}
                        placeholders={headerPhs}
                        values={headerExamples}
                        onChange={handleHeaderExampleChange}
                    />
                </>
            ) : (
                <div className="space-y-2">
                    {comp.example?.header_handle ? (
                        <div className="flex items-center gap-3 rounded-lg border border-neutral-200 dark:border-neutral-600 bg-neutral-50 dark:bg-neutral-800 px-3 py-2">
                            {format === 'IMAGE' && comp.example._preview ? (
                                <img src={comp.example._preview} alt="" className="h-12 w-12 object-cover rounded" />
                            ) : (
                                <div className="h-10 w-10 rounded bg-neutral-200 dark:bg-neutral-700 flex items-center justify-center text-neutral-500">
                                    {format === 'VIDEO' ? <FileVideo className="h-5 w-5" /> : <FileTextIcon className="h-5 w-5" />}
                                </div>
                            )}
                            <span className="flex-1 text-sm text-neutral-600 dark:text-neutral-400 truncate">
                                {comp.example._filename ?? t('whatsapp.templates_file_uploaded')}
                            </span>
                            <button
                                type="button"
                                onClick={() => { onChange({ ...comp, example: {} }); }}
                                className="text-xs text-red-500 hover:text-red-700"
                            >
                                <X className="h-4 w-4" />
                            </button>
                        </div>
                    ) : (
                        <>
                            <button
                                type="button"
                                disabled={uploading}
                                onClick={() => fileInputRef.current?.click()}
                                className="flex items-center gap-2 rounded-lg border border-dashed border-neutral-300 dark:border-neutral-600 px-4 py-3 text-sm text-neutral-600 dark:text-neutral-400 hover:bg-neutral-50 dark:hover:bg-neutral-800 w-full justify-center disabled:opacity-60 transition"
                            >
                                <Upload className="h-4 w-4" />
                                {uploading
                                    ? t('whatsapp.templates_uploading')
                                    : format === 'IMAGE'
                                        ? t('whatsapp.templates_upload_image')
                                        : format === 'VIDEO'
                                            ? t('whatsapp.templates_upload_video')
                                            : t('whatsapp.templates_upload_document')}
                            </button>
                            <input
                                ref={fileInputRef}
                                type="file"
                                className="hidden"
                                accept={format === 'IMAGE' ? 'image/jpeg,image/png' : format === 'VIDEO' ? 'video/mp4' : 'application/pdf'}
                                onChange={handleFileChange}
                            />
                        </>
                    )}
                    {uploadError && <p className="text-xs text-red-500">{uploadError}</p>}
                </div>
            )}
        </SectionCard>
    );
}

/* ─── BODY Component ─────────────────────────────────────────────────────── */

function BodyBlock({ comp, onChange }) {
    const { t } = useTranslation();
    const phs = extractPlaceholders(comp.text ?? '');
    const exampleMap = comp.example?.body_text_map ?? {};

    const handleExampleChange = (n, val) => {
        const map = { ...exampleMap, [n]: val };
        const row = phs.map(ph => map[ph] ?? '');
        onChange({ ...comp, example: { body_text_map: map, body_text: [row] } });
    };

    return (
        <SectionCard title={t('whatsapp.templates_section_body')}>
            <textarea
                value={comp.text ?? ''}
                onChange={e => onChange({ ...comp, text: e.target.value })}
                rows={4}
                placeholder={t('whatsapp.templates_body_placeholder', { p1: '{{1}}', p2: '{{2}}' })}
                className="w-full rounded-lg border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 px-3 py-2 text-sm resize-none"
            />
            {phs.length > 0 && (
                <div className="flex items-start gap-1.5 rounded-lg bg-blue-50 dark:bg-blue-900/30 px-3 py-2 text-xs text-blue-700 dark:text-blue-300">
                    <Info className="h-3.5 w-3.5 mt-0.5 shrink-0" />
                    {t('whatsapp.templates_example_required_hint')}
                </div>
            )}
            <ExampleInputs
                label={t('whatsapp.templates_section_body')}
                placeholders={phs}
                values={exampleMap}
                onChange={handleExampleChange}
            />
        </SectionCard>
    );
}

/* ─── FOOTER Component ───────────────────────────────────────────────────── */

function FooterBlock({ comp, onChange, onRemove }) {
    const { t } = useTranslation();
    return (
        <SectionCard title={t('whatsapp.templates_section_footer')} onRemove={onRemove}>
            <input
                type="text"
                value={comp.text ?? ''}
                onChange={e => onChange({ ...comp, text: e.target.value })}
                placeholder={t('whatsapp.templates_footer_text_placeholder')}
                maxLength={60}
                className="w-full rounded-lg border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 px-3 py-2 text-sm"
            />
        </SectionCard>
    );
}

/* ─── BUTTONS Component ──────────────────────────────────────────────────── */

const BTN_TYPE_ICON = {
    QUICK_REPLY:  <MessageSquare className="h-3 w-3" />,
    URL:          <LinkIcon className="h-3 w-3" />,
    PHONE_NUMBER: <Phone className="h-3 w-3" />,
};

const BTN_TYPE_LABEL_KEYS = { QUICK_REPLY: 'whatsapp.templates_btn_quick_reply', URL: 'whatsapp.templates_btn_url', PHONE_NUMBER: 'whatsapp.templates_btn_phone' };

function ButtonRow({ btn, idx, total, onChange, onRemove, onMove }) {
    const { t } = useTranslation();
    const phs = extractPlaceholders(btn.url ?? '');
    const exampleValues = btn.example ?? [];

    return (
        <div className="rounded-lg border border-neutral-200 dark:border-neutral-600 p-3 space-y-2">
            <div className="flex items-center gap-2">
                {/* Type chip */}
                <span className={`flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium
                    ${btn.type === 'QUICK_REPLY' ? 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300' :
                      btn.type === 'URL'          ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300' :
                                                   'bg-purple-100 text-purple-700 dark:bg-purple-900/40 dark:text-purple-300'}`}>
                    {BTN_TYPE_ICON[btn.type]} {t(BTN_TYPE_LABEL_KEYS[btn.type] ?? '')}
                </span>
                <div className="flex gap-1 ml-auto">
                    <button type="button" disabled={idx === 0}        onClick={() => onMove(idx, -1)} className="text-neutral-400 hover:text-neutral-600 disabled:opacity-30"><ChevronUp className="h-4 w-4" /></button>
                    <button type="button" disabled={idx === total - 1} onClick={() => onMove(idx, +1)} className="text-neutral-400 hover:text-neutral-600 disabled:opacity-30"><ChevronDown className="h-4 w-4" /></button>
                    <button type="button" onClick={onRemove} className="text-neutral-400 hover:text-red-500"><X className="h-4 w-4" /></button>
                </div>
            </div>

            {/* Button text (label) */}
            <div>
                <label className="block text-xs text-neutral-500 mb-1">{t('whatsapp.templates_button_label')} <span className="text-red-500">*</span> <span className="text-neutral-400">{t('whatsapp.templates_max_25_chars')}</span></label>
                <input
                    type="text"
                    value={btn.text ?? ''}
                    onChange={e => onChange({ ...btn, text: e.target.value })}
                    maxLength={25}
                    placeholder={t('whatsapp.templates_button_label_placeholder')}
                    className="w-full rounded-lg border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 px-3 py-1.5 text-sm"
                />
            </div>

            {btn.type === 'URL' && (
                <div className="space-y-2">
                    <div>
                        <label className="block text-xs text-neutral-500 mb-1">URL <span className="text-red-500">*</span> <span className="text-neutral-400">{t('whatsapp.templates_url_hint', { token: '{{1}}' })}</span></label>
                        <input
                            type="text"
                            value={btn.url ?? ''}
                            onChange={e => onChange({ ...btn, url: e.target.value })}
                            placeholder="https://example.com/order/{{1}}"
                            className="w-full rounded-lg border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 px-3 py-1.5 text-sm font-mono"
                        />
                    </div>
                    {phs.length > 0 && (
                        <div>
                            <label className="block text-xs text-neutral-500 mb-1">{t('whatsapp.templates_url_example_label', { token: '{{1}}' })}</label>
                            <input
                                type="text"
                                value={exampleValues[0] ?? ''}
                                onChange={e => onChange({ ...btn, example: [e.target.value] })}
                                placeholder="https://example.com/order/12345"
                                className="w-full rounded-lg border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 px-3 py-1.5 text-sm"
                            />
                        </div>
                    )}
                </div>
            )}

            {btn.type === 'PHONE_NUMBER' && (
                <div>
                    <label className="block text-xs text-neutral-500 mb-1">{t('whatsapp.templates_phone_number_label')} <span className="text-red-500">*</span> <span className="text-neutral-400">{t('whatsapp.templates_phone_hint')}</span></label>
                    <input
                        type="text"
                        value={btn.phone_number ?? ''}
                        onChange={e => onChange({ ...btn, phone_number: e.target.value })}
                        placeholder="+8801XXXXXXXXX"
                        className="w-full rounded-lg border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 px-3 py-1.5 text-sm font-mono"
                    />
                </div>
            )}
        </div>
    );
}

function ButtonsBlock({ comp, onChange, onRemove }) {
    const { t } = useTranslation();
    const buttons = comp.buttons ?? [];
    const typeCounts = buttons.reduce((acc, b) => { acc[b.type] = (acc[b.type] ?? 0) + 1; return acc; }, {});
    const totalUsed = buttons.length;

    const canAdd = (type) => totalUsed < BTN_TOTAL_MAX && (typeCounts[type] ?? 0) < BTN_LIMITS[type];

    const addButton = (type) => {
        if (!canAdd(type)) return;
        const newBtn = { type, text: '' };
        onChange({ ...comp, buttons: [...buttons, newBtn] });
    };

    const updateButton = (idx, updated) => {
        const next = [...buttons];
        next[idx] = updated;
        onChange({ ...comp, buttons: next });
    };

    const removeButton = (idx) => {
        onChange({ ...comp, buttons: buttons.filter((_, i) => i !== idx) });
    };

    const moveButton = (idx, dir) => {
        const next = [...buttons];
        const target = idx + dir;
        if (target < 0 || target >= next.length) return;
        [next[idx], next[target]] = [next[target], next[idx]];
        onChange({ ...comp, buttons: next });
    };

    return (
        <SectionCard title={t('whatsapp.templates_section_buttons')} onRemove={onRemove}>
            <div className="space-y-2">
                {buttons.map((btn, idx) => (
                    <ButtonRow
                        key={idx}
                        btn={btn}
                        idx={idx}
                        total={buttons.length}
                        onChange={(updated) => updateButton(idx, updated)}
                        onRemove={() => removeButton(idx)}
                        onMove={moveButton}
                    />
                ))}

                {/* Add button row */}
                <div className="flex flex-wrap gap-2 pt-1">
                    {Object.entries(BTN_TYPE_LABEL_KEYS).map(([type, labelKey]) => (
                        <button
                            key={type}
                            type="button"
                            disabled={!canAdd(type)}
                            onClick={() => addButton(type)}
                            className="flex items-center gap-1.5 rounded-lg border border-dashed border-neutral-300 dark:border-neutral-600 px-3 py-1.5 text-xs text-neutral-600 dark:text-neutral-400 hover:bg-neutral-50 dark:hover:bg-neutral-800 disabled:opacity-40 disabled:cursor-not-allowed transition"
                        >
                            <Plus className="h-3 w-3" /> {t(labelKey)}
                            {canAdd(type) && (
                                <span className="text-neutral-400">
                                    ({(typeCounts[type] ?? 0)}/{BTN_LIMITS[type]})
                                </span>
                            )}
                        </button>
                    ))}
                </div>

                <p className="text-xs text-neutral-400 text-right">{t('whatsapp.templates_buttons_used', { used: totalUsed, max: BTN_TOTAL_MAX })}</p>
            </div>
        </SectionCard>
    );
}

/* ─── Main Editor ────────────────────────────────────────────────────────── */

export default function WhatsappTemplateEditor({ template, phoneNumbers = [] }) {
    const { t } = useTranslation();
    const { props } = usePage();
    const flash = props.flash ?? {};

    const [name, setName]         = useState(template?.name ?? '');
    const [language, setLanguage] = useState(template?.language ?? 'en');
    const [category, setCategory] = useState(template?.category ?? 'MARKETING');
    const [phoneNumberId, setPhoneNumberId] = useState(template?.phone_number_id ?? (phoneNumbers[0]?.phone_number_id ?? ''));
    const [components, setComponents] = useState(() => {
        if (template?.components?.length) return hydrateComponents(template.components);
        return [emptyBody()];
    });
    const [processing, setProcessing] = useState(false);
    const [errors, setErrors] = useState({});

    const isEdit = Boolean(template?.id);

    const hasType = (type) => components.some(c => c.type === type);

    const addComponent = (type) => {
        if (hasType(type) && type !== 'BUTTONS') return;
        const makers = { HEADER: emptyHeader, FOOTER: emptyFooter, BUTTONS: emptyButtons };
        setComponents(prev => {
            const comp = makers[type]();
            // HEADER goes first, FOOTER second-to-last before BUTTONS
            if (type === 'HEADER') return [comp, ...prev];
            if (type === 'FOOTER') {
                const btnIdx = prev.findIndex(c => c.type === 'BUTTONS');
                return btnIdx >= 0
                    ? [...prev.slice(0, btnIdx), comp, ...prev.slice(btnIdx)]
                    : [...prev, comp];
            }
            return [...prev, comp];
        });
    };

    const updateComp = (idx, updated) => {
        setComponents(prev => { const n = [...prev]; n[idx] = updated; return n; });
    };

    const removeComp = (idx) => {
        setComponents(prev => prev.filter((_, i) => i !== idx));
    };

    const handleSubmit = async (e) => {
        e.preventDefault();
        setErrors({});
        setProcessing(true);

        // Build clean components payload for the server
        const payload = {
            name,
            language,
            category,
            phone_number_id: phoneNumberId || undefined,
            components: components.map(comp => {
                if (comp.type === 'BUTTONS') return comp;
                if (comp.type === 'HEADER') {
                    const { _filename, _preview, header_text_map, ...cleanExample } = comp.example ?? {};
                    return { ...comp, example: cleanExample };
                }
                if (comp.type === 'BODY') {
                    const { body_text_map, ...cleanExample } = comp.example ?? {};
                    return { ...comp, example: cleanExample };
                }
                return comp;
            }),
            _token: document.querySelector('meta[name="csrf-token"]')?.content ?? '',
        };

        try {
            if (isEdit) {
                await axios.put(route('client.whatsapp.templates.update', template.id), payload);
            } else {
                await axios.post(route('client.whatsapp.templates.store'), payload);
            }
            router.visit(route('client.whatsapp.templates.index'));
        } catch (err) {
            if (err.response?.status === 422) {
                setErrors(err.response.data.errors ?? {});
            } else {
                setErrors({ general: err.response?.data?.message ?? t('whatsapp.templates_unexpected_error') });
            }
        } finally {
            setProcessing(false);
        }
    };

    const addableTypes = ['HEADER', 'FOOTER', 'BUTTONS'].filter(type => !hasType(type));

    return (
        <ClientLayout title={t('whatsapp.templates_editor_title')}>
            <Head title={isEdit ? t('whatsapp.templates_edit_head_title') : t('whatsapp.templates_new_head_title')} />
            <div className="max-w-5xl space-y-6">
                <div className="flex items-center gap-3">
                    <a href={route('client.whatsapp.templates.index')} className="text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-200 transition">
                        <ArrowLeft className="h-5 w-5" />
                    </a>
                    <h2 className="text-xl font-semibold text-neutral-900 dark:text-neutral-100">
                        {isEdit ? t('whatsapp.templates_edit_heading') : t('whatsapp.templates_new_heading')}
                    </h2>
                </div>

                {flash.error && (
                    <div className="rounded-lg bg-red-50 dark:bg-red-900/30 text-red-800 dark:text-red-200 px-4 py-3 text-sm">
                        {flash.error}
                    </div>
                )}
                {errors.general && (
                    <div className="rounded-lg bg-red-50 dark:bg-red-900/30 text-red-800 dark:text-red-200 px-4 py-3 text-sm">
                        {errors.general}
                    </div>
                )}

                <div className="grid gap-6 lg:grid-cols-[1fr_340px] lg:items-start">
                <form onSubmit={handleSubmit} className="space-y-5 min-w-0">
                    {/* ── Basic Info ── */}
                    <div className="rounded-xl border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-900 p-5 space-y-4">
                        <h3 className="font-medium text-neutral-800 dark:text-neutral-200">{t('whatsapp.templates_basic_info')}</h3>

                        {phoneNumbers.length > 0 && (
                            <div>
                                <label className="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-1">
                                    {t('whatsapp.templates_whatsapp_number')} <span className="text-red-500">*</span>
                                </label>
                                <select
                                    value={phoneNumberId}
                                    onChange={e => setPhoneNumberId(e.target.value)}
                                    className="w-full rounded-lg border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 px-3 py-2 text-sm"
                                >
                                    {phoneNumbers.map(p => (
                                        <option key={p.phone_number_id} value={p.phone_number_id}>
                                            {p.verified_name ? `${p.verified_name} (${p.display_phone})` : p.display_phone}
                                        </option>
                                    ))}
                                </select>
                                <p className="mt-1 text-xs text-neutral-400">{t('whatsapp.templates_phone_select_hint')}</p>
                            </div>
                        )}

                        <div className="grid sm:grid-cols-2 gap-4">
                            <div>
                                <label className="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-1">
                                    {t('whatsapp.templates_template_name')} <span className="text-red-500">*</span>
                                </label>
                                <input
                                    type="text"
                                    value={name}
                                    onChange={e => setName(e.target.value.toLowerCase().replace(/[^a-z0-9_]/g, '_'))}
                                    placeholder="order_confirmation"
                                    disabled={isEdit}
                                    className="w-full rounded-lg border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 px-3 py-2 text-sm font-mono disabled:opacity-60 disabled:cursor-not-allowed"
                                />
                                <p className="mt-1 text-xs text-neutral-400">{isEdit ? t('whatsapp.templates_name_locked_hint') : t('whatsapp.templates_name_format_hint')}</p>
                                {errors.name && <p className="text-xs text-red-500 mt-1">{errors.name}</p>}
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-1">
                                    {t('whatsapp.templates_language')} <span className="text-red-500">*</span>
                                </label>
                                <select
                                    value={language}
                                    onChange={e => setLanguage(e.target.value)}
                                    disabled={isEdit}
                                    className="w-full rounded-lg border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 px-3 py-2 text-sm disabled:opacity-60 disabled:cursor-not-allowed"
                                >
                                    {!LANGUAGES.some(([v]) => v === language) && language && (
                                        <option value={language}>{language}</option>
                                    )}
                                    {LANGUAGES.map(([v, l]) => (
                                        <option key={v} value={v}>{t(l)}</option>
                                    ))}
                                </select>
                            </div>
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-1">
                                {t('whatsapp.templates_category')} <span className="text-red-500">*</span>
                            </label>
                            <div className="flex gap-3">
                                {['MARKETING', 'UTILITY', 'AUTHENTICATION'].map(cat => (
                                    <label key={cat} className="flex items-center gap-1.5 text-sm cursor-pointer">
                                        <input
                                            type="radio"
                                            value={cat}
                                            checked={category === cat}
                                            onChange={() => setCategory(cat)}
                                        />
                                        {t(CATEGORY_LABEL_KEYS[cat] ?? '')}
                                    </label>
                                ))}
                            </div>
                            {errors.category && <p className="text-xs text-red-500 mt-1">{errors.category}</p>}
                        </div>
                    </div>

                    {/* ── Components ── */}
                    <div className="rounded-xl border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-900 p-5 space-y-4">
                        <div className="flex items-center justify-between flex-wrap gap-2">
                            <h3 className="font-medium text-neutral-800 dark:text-neutral-200">{t('whatsapp.templates_components')}</h3>
                            {addableTypes.length > 0 && (
                                <div className="flex gap-2 flex-wrap">
                                    {addableTypes.map(type => (
                                        <button
                                            key={type}
                                            type="button"
                                            onClick={() => addComponent(type)}
                                            className="flex items-center gap-1 text-xs text-brand-600 hover:text-brand-700 dark:text-brand-400 border border-brand-200 dark:border-brand-700 rounded px-2 py-1"
                                        >
                                            <Plus className="h-3 w-3" /> {t(SECTION_LABEL_KEYS[type] ?? '')}
                                        </button>
                                    ))}
                                </div>
                            )}
                        </div>

                        {errors.components && (
                            <p className="text-xs text-red-500">{errors.components}</p>
                        )}

                        {components.map((comp, idx) => {
                            const commonProps = { comp, onChange: (u) => updateComp(idx, u) };
                            return (
                                <div key={idx}>
                                    {comp.type === 'HEADER'  && <HeaderBlock  {...commonProps} onRemove={() => removeComp(idx)} />}
                                    {comp.type === 'BODY'    && <BodyBlock    {...commonProps} />}
                                    {comp.type === 'FOOTER'  && <FooterBlock  {...commonProps} onRemove={() => removeComp(idx)} />}
                                    {comp.type === 'BUTTONS' && <ButtonsBlock {...commonProps} onRemove={() => removeComp(idx)} />}
                                </div>
                            );
                        })}
                    </div>

                    {/* ── Actions ── */}
                    <div className="flex gap-3">
                        <button
                            type="submit"
                            disabled={processing}
                            className="rounded-lg bg-brand-600 px-5 py-2 text-sm font-medium text-white hover:bg-brand-700 disabled:opacity-60 transition"
                        >
                            {processing ? t('whatsapp.templates_submitting') : isEdit ? t('whatsapp.templates_save_changes') : t('whatsapp.templates_submit')}
                        </button>
                        <a
                            href={route('client.whatsapp.templates.index')}
                            className="rounded-lg border border-neutral-300 dark:border-neutral-600 px-4 py-2 text-sm font-medium text-neutral-700 dark:text-neutral-300 hover:bg-neutral-50 dark:hover:bg-neutral-800 transition"
                        >
                            {t('common.cancel')}
                        </a>
                    </div>
                </form>

                {/* ── Live Preview ── */}
                <aside className="lg:sticky lg:top-4 space-y-2">
                    <h3 className="text-xs font-semibold uppercase tracking-wider text-neutral-500 dark:text-neutral-400">{t('whatsapp.templates_preview')}</h3>
                    <div className="rounded-xl border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-900 p-3">
                        <TemplatePreview components={components} />
                    </div>
                    <p className="text-xs text-neutral-400">{t('whatsapp.templates_preview_fallback_note', { token: '{{n}}' })}</p>
                </aside>
                </div>
            </div>
        </ClientLayout>
    );
}
