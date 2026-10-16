import { useCallback, useEffect, useLayoutEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { useTranslation } from 'react-i18next';
import {
    AlignCenter,
    AlignLeft,
    AlignRight,
    Copy,
    GripVertical,
    Image as ImageIcon,
    Plus,
    Settings2,
    Trash2,
} from 'lucide-react';
import MediaUpload from '@/Components/MediaUpload';
import { BLOCK_TYPES, defaultBlock, isTextBlock, uid } from './blocks';
import InlineText, { EDITOR_CSS, SelectionToolbar } from './InlineText';

const fieldClass =
    'w-full rounded-lg border border-neutral-300 bg-white px-2.5 py-1.5 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-neutral-600 dark:bg-neutral-800';
const labelClass = 'mb-1 block text-xs font-medium text-neutral-500 dark:text-neutral-400';

const HEADING_CLASS = {
    1: 'text-3xl font-bold leading-tight',
    2: 'text-2xl font-bold leading-tight',
    3: 'text-xl font-semibold leading-snug',
};

// Translated names for the email blocks (the canonical type/Icon live in blocks.js).
const BLOCK_LABEL_KEYS = {
    heading: 'email_editor.block_heading',
    paragraph: 'email_editor.block_text',
    button: 'email_editor.block_button',
    image: 'email_editor.block_image',
    divider: 'email_editor.block_divider',
    spacer: 'email_editor.block_spacer',
};

// ─── Main canvas ──────────────────────────────────────────────────────────────

export default function VisualCanvas({ blocks, onChange, tokens = [] }) {
    const { t } = useTranslation();
    const canvasRef = useRef(null);
    const [selectedId, setSelectedId] = useState(null);
    const [focusId, setFocusId] = useState(null); // newly-added text block to autofocus
    const [settings, setSettings] = useState(null); // { id, anchorEl }
    const [dragIndex, setDragIndex] = useState(null);
    const [dropIndex, setDropIndex] = useState(null);

    const updateBlock = useCallback(
        (id, patch) => onChange(blocks.map((b) => (b.id === id ? { ...b, ...patch } : b))),
        [blocks, onChange],
    );

    const removeBlock = useCallback(
        (id) => {
            onChange(blocks.filter((b) => b.id !== id));
            setSelectedId((s) => (s === id ? null : s));
            setSettings((s) => (s && s.id === id ? null : s));
        },
        [blocks, onChange],
    );

    const duplicateBlock = useCallback(
        (id) => {
            const i = blocks.findIndex((b) => b.id === id);
            if (i < 0) return;
            const clone = { ...blocks[i], id: uid() };
            const next = [...blocks];
            next.splice(i + 1, 0, clone);
            onChange(next);
            setSelectedId(clone.id);
        },
        [blocks, onChange],
    );

    const insertBlock = useCallback(
        (type, at) => {
            const nb = defaultBlock(type);
            const next = [...blocks];
            next.splice(at, 0, nb);
            onChange(next);
            setSelectedId(nb.id);
            if (isTextBlock(type)) setFocusId(nb.id);
        },
        [blocks, onChange],
    );

    const move = useCallback(
        (from, to) => {
            if (from == null || to == null) return;
            const next = [...blocks];
            const [item] = next.splice(from, 1);
            const insertAt = from < to ? to - 1 : to;
            next.splice(Math.max(0, Math.min(insertAt, next.length)), 0, item);
            onChange(next);
        },
        [blocks, onChange],
    );

    function handleDragStart(e, index, rowRef) {
        setDragIndex(index);
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('text/plain', String(index));
        if (rowRef.current) e.dataTransfer.setDragImage(rowRef.current, 24, 16);
    }
    function handleDragOver(e, index, rowRef) {
        if (dragIndex == null) return;
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';
        const rect = rowRef.current.getBoundingClientRect();
        const after = e.clientY > rect.top + rect.height / 2;
        setDropIndex(after ? index + 1 : index);
    }
    function resetDrag() {
        setDragIndex(null);
        setDropIndex(null);
    }
    function handleDrop(e) {
        e.preventDefault();
        if (dragIndex != null && dropIndex != null) move(dragIndex, dropIndex);
        resetDrag();
    }

    const openSettings = useCallback((id, anchorEl) => {
        setSelectedId(id);
        setSettings({ id, anchorEl });
    }, []);

    const settingsBlock = settings ? blocks.find((b) => b.id === settings.id) : null;

    return (
        <div>
            <style>{EDITOR_CSS}</style>

            <div
                ref={canvasRef}
                data-ee-canvas=""
                onDragOver={(e) => dragIndex != null && e.preventDefault()}
                onDrop={handleDrop}
                onClick={(e) => {
                    // click on the bare canvas (not a block) deselects
                    if (e.target === e.currentTarget) setSelectedId(null);
                }}
                className="mx-auto max-w-[640px] rounded-xl border border-neutral-200 bg-white p-4 text-neutral-900 shadow-soft dark:border-neutral-700 sm:p-6"
            >
                {blocks.length === 0 && (
                    <div className="rounded-lg border-2 border-dashed border-neutral-300 p-10 text-center text-sm text-neutral-400">
                        {t('email_editor.empty_canvas')}
                    </div>
                )}

                {blocks.map((block, idx) => (
                    <div key={block.id}>
                        <InsertZone onPick={(type) => insertBlock(type, idx)} />
                        {dragIndex != null && dropIndex === idx && <DropLine />}
                        <BlockRow
                            block={block}
                            index={idx}
                            selected={selectedId === block.id}
                            dragging={dragIndex === idx}
                            autoFocus={focusId === block.id}
                            onSelect={setSelectedId}
                            onUpdate={updateBlock}
                            onRemove={removeBlock}
                            onDuplicate={duplicateBlock}
                            onOpenSettings={openSettings}
                            onDragStart={handleDragStart}
                            onDragOver={handleDragOver}
                            onDragEnd={resetDrag}
                        />
                    </div>
                ))}
                {dragIndex != null && dropIndex === blocks.length && <DropLine />}

                <AddBar
                    onAdd={(type) => insertBlock(type, blocks.length)}
                    onDragOver={(e) => {
                        if (dragIndex == null) return;
                        e.preventDefault();
                        setDropIndex(blocks.length);
                    }}
                />
            </div>

            <SelectionToolbar canvasRef={canvasRef} tokens={tokens} />

            {settings && settingsBlock && (
                <FloatingPanel anchorEl={settings.anchorEl} onClose={() => setSettings(null)}>
                    <div className="mb-3 flex items-center justify-between">
                        <span className="text-xs font-semibold uppercase tracking-wide text-neutral-400">
                            {t('email_editor.block_settings', { block: t(BLOCK_LABEL_KEYS[settingsBlock.type] ?? '', settingsBlock.type) })}
                        </span>
                        <button
                            type="button"
                            onClick={() => setSettings(null)}
                            className="text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-300"
                        >
                            <Plus className="h-4 w-4 rotate-45" />
                        </button>
                    </div>
                    <BlockSettings block={settingsBlock} onChange={(patch) => updateBlock(settingsBlock.id, patch)} />
                </FloatingPanel>
            )}
        </div>
    );
}

// ─── A single block row (WYSIWYG + hover controls) ────────────────────────────

function BlockRow({
    block,
    index,
    selected,
    dragging,
    autoFocus,
    onSelect,
    onUpdate,
    onRemove,
    onDuplicate,
    onOpenSettings,
    onDragStart,
    onDragOver,
    onDragEnd,
}) {
    const { t } = useTranslation();
    const rowRef = useRef(null);

    return (
        <div
            ref={rowRef}
            data-ee-block={block.type}
            onDragOver={(e) => onDragOver(e, index, rowRef)}
            onClick={(e) => {
                e.stopPropagation();
                onSelect(block.id);
            }}
            className={`group relative my-0.5 rounded-lg border px-3 py-1.5 transition ${
                dragging ? 'opacity-40' : ''
            } ${
                selected
                    ? 'border-brand-500 ring-1 ring-brand-500'
                    : 'border-transparent hover:border-brand-200'
            }`}
        >
            {/* Hover / selected toolbar */}
            <div
                className={`absolute -top-3 right-2 z-20 flex items-center gap-0.5 rounded-lg border border-neutral-200 bg-white px-0.5 py-0.5 shadow-soft-md transition dark:border-neutral-700 dark:bg-neutral-800 ${
                    selected ? 'opacity-100' : 'pointer-events-none opacity-0 group-hover:pointer-events-auto group-hover:opacity-100'
                }`}
            >
                <button
                    type="button"
                    draggable
                    onDragStart={(e) => onDragStart(e, index, rowRef)}
                    onDragEnd={onDragEnd}
                    onClick={(e) => e.stopPropagation()}
                    title={t('email_editor.drag_to_reorder')}
                    className="flex h-6 w-6 cursor-grab items-center justify-center rounded text-neutral-400 hover:bg-neutral-100 hover:text-neutral-700 active:cursor-grabbing dark:hover:bg-neutral-700"
                >
                    <GripVertical className="h-3.5 w-3.5" />
                </button>
                <RowBtn title={t('email_editor.settings')} onClick={(e) => onOpenSettings(block.id, e.currentTarget)}>
                    <Settings2 className="h-3.5 w-3.5" />
                </RowBtn>
                <RowBtn title={t('email_editor.duplicate')} onClick={() => onDuplicate(block.id)}>
                    <Copy className="h-3.5 w-3.5" />
                </RowBtn>
                <RowBtn title={t('common.delete')} danger onClick={() => onRemove(block.id)}>
                    <Trash2 className="h-3.5 w-3.5" />
                </RowBtn>
            </div>

            <BlockContent
                block={block}
                autoFocus={autoFocus}
                onChange={(patch) => onUpdate(block.id, patch)}
                onRequestSettings={(el) => onOpenSettings(block.id, el)}
            />
        </div>
    );
}

function RowBtn({ title, danger, onClick, children }) {
    return (
        <button
            type="button"
            title={title}
            onClick={(e) => {
                e.stopPropagation();
                onClick(e);
            }}
            className={`flex h-6 w-6 items-center justify-center rounded transition ${
                danger
                    ? 'text-neutral-400 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-950/40'
                    : 'text-neutral-400 hover:bg-neutral-100 hover:text-neutral-700 dark:hover:bg-neutral-700 dark:hover:text-neutral-200'
            }`}
        >
            {children}
        </button>
    );
}

// ─── WYSIWYG rendering for each block type ────────────────────────────────────

function BlockContent({ block, autoFocus, onChange, onRequestSettings }) {
    const { t } = useTranslation();
    switch (block.type) {
        case 'heading': {
            const Tag = `h${block.level || 2}`;
            return (
                <InlineText
                    as={Tag}
                    singleLine
                    autoFocus={autoFocus}
                    html={block.text}
                    placeholder={t('email_editor.block_heading')}
                    onChange={(val) => onChange({ text: val })}
                    className={HEADING_CLASS[block.level] || HEADING_CLASS[2]}
                    style={{ margin: 0, textAlign: block.align, color: block.color || '#111111' }}
                />
            );
        }
        case 'paragraph':
            return (
                <InlineText
                    as="div"
                    autoFocus={autoFocus}
                    html={block.text}
                    placeholder={t('email_editor.text_placeholder')}
                    onChange={(val) => onChange({ text: val })}
                    className="text-[15px] leading-relaxed text-neutral-700"
                    style={{ textAlign: block.align }}
                />
            );
        case 'button':
            return (
                <div style={{ textAlign: block.align || 'center' }}>
                    <span
                        className="inline-flex items-center rounded-md px-5 py-2.5 text-sm font-semibold shadow-sm"
                        style={{ background: block.color || '#2563eb', color: block.textColor || '#ffffff' }}
                    >
                        <InlineText
                            as="span"
                            singleLine
                            autoFocus={autoFocus}
                            html={block.text}
                            placeholder={t('email_editor.block_button')}
                            onChange={(val) => onChange({ text: val })}
                            style={{ minWidth: '3ch', textAlign: 'center' }}
                        />
                    </span>
                </div>
            );
        case 'image':
            return block.src ? (
                <div style={{ textAlign: block.align || 'center' }}>
                    <img
                        src={block.src}
                        alt={block.alt || ''}
                        style={{ width: block.width || undefined, maxWidth: '100%' }}
                        className="inline-block rounded"
                    />
                </div>
            ) : (
                <button
                    type="button"
                    onClick={(e) => {
                        e.stopPropagation();
                        onRequestSettings(e.currentTarget);
                    }}
                    className="flex w-full items-center justify-center gap-2 rounded-lg border-2 border-dashed border-neutral-300 py-8 text-sm text-neutral-400 transition hover:border-brand-400 hover:text-brand-600"
                >
                    <ImageIcon className="h-5 w-5" /> {t('email_editor.click_add_image')}
                </button>
            );
        case 'divider':
            return <hr className="border-0 border-t" style={{ borderTopColor: block.color || '#e5e7eb', borderTopWidth: 1 }} />;
        case 'spacer':
            return (
                <div
                    className="flex items-center justify-center rounded border border-dashed border-neutral-200 text-[10px] uppercase tracking-wide text-neutral-300"
                    style={{ height: Math.max(16, Math.min(block.height ?? 24, 120)) }}
                >
                    {t('email_editor.spacer_label', { height: block.height ?? 24 })}
                </div>
            );
        default:
            return null;
    }
}

// ─── Insert-between zone + drop indicator ─────────────────────────────────────

function DropLine() {
    return <div className="pointer-events-none mx-1 my-0.5 h-0.5 rounded-full bg-brand-500" />;
}

function InsertZone({ onPick }) {
    const { t } = useTranslation();
    const [open, setOpen] = useState(false);
    return (
        <div className="group/iz relative h-2" onMouseLeave={() => setOpen(false)}>
            <div className="pointer-events-none absolute inset-x-0 top-1/2 h-px -translate-y-1/2 bg-brand-300 opacity-0 transition group-hover/iz:opacity-100" />
            <button
                type="button"
                onClick={(e) => {
                    e.stopPropagation();
                    setOpen((o) => !o);
                }}
                title={t('email_editor.insert_block_here')}
                className="absolute left-1/2 top-1/2 z-10 flex h-5 w-5 -translate-x-1/2 -translate-y-1/2 items-center justify-center rounded-full bg-brand-500 text-white opacity-0 shadow transition hover:bg-brand-600 group-hover/iz:opacity-100"
            >
                <Plus className="h-3 w-3" />
            </button>
            {open && (
                <div
                    onClick={(e) => e.stopPropagation()}
                    className="absolute left-1/2 top-full z-30 mt-1 flex -translate-x-1/2 gap-0.5 rounded-lg border border-neutral-200 bg-white p-1 shadow-soft-lg dark:border-neutral-700 dark:bg-neutral-800"
                >
                    {BLOCK_TYPES.map(({ type, Icon }) => (
                        <button
                            key={type}
                            type="button"
                            title={t(BLOCK_LABEL_KEYS[type] ?? '', type)}
                            onClick={() => {
                                onPick(type);
                                setOpen(false);
                            }}
                            className="flex h-8 w-8 items-center justify-center rounded-md text-neutral-600 transition hover:bg-brand-50 hover:text-brand-600 dark:text-neutral-300 dark:hover:bg-neutral-700"
                        >
                            <Icon className="h-4 w-4" />
                        </button>
                    ))}
                </div>
            )}
        </div>
    );
}

// ─── Bottom add bar ───────────────────────────────────────────────────────────

function AddBar({ onAdd, onDragOver }) {
    const { t } = useTranslation();
    return (
        <div
            onDragOver={onDragOver}
            className="mt-3 flex flex-wrap gap-2 border-t border-dashed border-neutral-200 pt-3"
        >
            {BLOCK_TYPES.map(({ type, Icon }) => (
                <button
                    key={type}
                    type="button"
                    onClick={() => onAdd(type)}
                    className="inline-flex items-center gap-1.5 rounded-lg border border-dashed border-neutral-300 px-3 py-1.5 text-xs font-medium text-neutral-600 transition hover:border-brand-400 hover:text-brand-600"
                >
                    <Plus className="h-3.5 w-3.5" />
                    <Icon className="h-3.5 w-3.5" />
                    {t(BLOCK_LABEL_KEYS[type] ?? '', type)}
                </button>
            ))}
        </div>
    );
}

// ─── Settings popover (portal, anchored to the gear button) ───────────────────

function FloatingPanel({ anchorEl, onClose, children, width = 300 }) {
    const panelRef = useRef(null);
    const [pos, setPos] = useState(null);

    useLayoutEffect(() => {
        function place() {
            if (!anchorEl) return;
            const r = anchorEl.getBoundingClientRect();
            const spaceBelow = window.innerHeight - r.bottom - 16;
            const spaceAbove = r.top - 16;
            const placeBelow = spaceBelow >= 260 || spaceBelow >= spaceAbove;
            let left = r.right - width;
            left = Math.max(8, Math.min(left, window.innerWidth - width - 8));
            setPos({
                left,
                top: placeBelow ? r.bottom + 6 : r.top - 6,
                placeBelow,
                maxHeight: (placeBelow ? spaceBelow : spaceAbove) - 8,
            });
        }
        place();
        window.addEventListener('scroll', place, true);
        window.addEventListener('resize', place);
        return () => {
            window.removeEventListener('scroll', place, true);
            window.removeEventListener('resize', place);
        };
    }, [anchorEl, width]);

    useEffect(() => {
        function onDown(e) {
            if (panelRef.current?.contains(e.target)) return;
            if (anchorEl?.contains(e.target)) return;
            onClose();
        }
        function onKey(e) {
            if (e.key === 'Escape') onClose();
        }
        document.addEventListener('mousedown', onDown);
        document.addEventListener('keydown', onKey);
        return () => {
            document.removeEventListener('mousedown', onDown);
            document.removeEventListener('keydown', onKey);
        };
    }, [anchorEl, onClose]);

    if (!pos) return null;

    return createPortal(
        <div
            ref={panelRef}
            style={{
                position: 'fixed',
                top: pos.top,
                left: pos.left,
                width,
                maxHeight: pos.maxHeight,
                transform: pos.placeBelow ? 'none' : 'translateY(-100%)',
                overflowY: 'auto',
            }}
            className="z-[55] rounded-xl border border-neutral-200 bg-white p-3 shadow-soft-xl dark:border-neutral-700 dark:bg-neutral-800"
        >
            {children}
        </div>,
        document.body,
    );
}

// ─── Per-type settings fields ─────────────────────────────────────────────────

function AlignControl({ value = 'left', onChange }) {
    const opts = [
        ['left', AlignLeft],
        ['center', AlignCenter],
        ['right', AlignRight],
    ];
    return (
        <div className="inline-flex rounded-lg border border-neutral-300 p-0.5 dark:border-neutral-600">
            {opts.map(([v, Ico]) => (
                <button
                    key={v}
                    type="button"
                    onClick={() => onChange(v)}
                    className={`flex h-7 w-7 items-center justify-center rounded-md transition ${
                        value === v
                            ? 'bg-brand-100 text-brand-700 dark:bg-brand-900/40 dark:text-brand-300'
                            : 'text-neutral-500 hover:bg-neutral-100 dark:hover:bg-neutral-700'
                    }`}
                >
                    <Ico className="h-3.5 w-3.5" />
                </button>
            ))}
        </div>
    );
}

function ColorField({ label, value, onChange }) {
    return (
        <div>
            <label className={labelClass}>{label}</label>
            <div className="flex items-center gap-2">
                <input
                    type="color"
                    value={value || '#000000'}
                    onChange={(e) => onChange(e.target.value)}
                    className="h-8 w-10 shrink-0 cursor-pointer rounded border border-neutral-300 p-0.5 dark:border-neutral-600"
                />
                <input type="text" value={value || ''} onChange={(e) => onChange(e.target.value)} className={fieldClass} />
            </div>
        </div>
    );
}

function Row({ label, children }) {
    return (
        <div className="flex items-center justify-between gap-3">
            <span className={`${labelClass} mb-0`}>{label}</span>
            {children}
        </div>
    );
}

function BlockSettings({ block, onChange }) {
    const { t } = useTranslation();
    switch (block.type) {
        case 'heading':
            return (
                <div className="space-y-3">
                    <div>
                        <label className={labelClass}>{t('email_editor.field_level')}</label>
                        <div className="flex gap-1">
                            {[1, 2, 3].map((l) => (
                                <button
                                    key={l}
                                    type="button"
                                    onClick={() => onChange({ level: l })}
                                    className={`flex-1 rounded-md py-1 text-xs font-bold transition ${
                                        block.level === l
                                            ? 'bg-brand-100 text-brand-700 dark:bg-brand-900/40 dark:text-brand-300'
                                            : 'border border-neutral-300 text-neutral-500 hover:bg-neutral-100 dark:border-neutral-600 dark:hover:bg-neutral-700'
                                    }`}
                                >
                                    H{l}
                                </button>
                            ))}
                        </div>
                    </div>
                    <Row label={t('email_editor.field_alignment')}>
                        <AlignControl value={block.align} onChange={(align) => onChange({ align })} />
                    </Row>
                    <ColorField label={t('email_editor.field_text_color')} value={block.color} onChange={(color) => onChange({ color })} />
                </div>
            );
        case 'paragraph':
            return (
                <Row label={t('email_editor.field_alignment')}>
                    <AlignControl value={block.align} onChange={(align) => onChange({ align })} />
                </Row>
            );
        case 'button':
            return (
                <div className="space-y-3">
                    <div>
                        <label className={labelClass}>{t('email_editor.field_link_url')}</label>
                        <input
                            type="text"
                            value={block.url || ''}
                            onChange={(e) => onChange({ url: e.target.value })}
                            placeholder="https://example.com"
                            className={fieldClass}
                        />
                    </div>
                    <Row label={t('email_editor.field_alignment')}>
                        <AlignControl value={block.align} onChange={(align) => onChange({ align })} />
                    </Row>
                    <ColorField label={t('email_editor.field_background')} value={block.color} onChange={(color) => onChange({ color })} />
                    <ColorField label={t('email_editor.field_text_color')} value={block.textColor} onChange={(textColor) => onChange({ textColor })} />
                </div>
            );
        case 'image':
            return (
                <div className="space-y-3">
                    <MediaUpload
                        value={block.src}
                        onChange={(src) => onChange({ src })}
                        accept="image/*"
                        collection="email"
                        placeholder="https://…/image.jpg"
                    />
                    <div>
                        <label className={labelClass}>{t('email_editor.field_alt_text')}</label>
                        <input
                            type="text"
                            value={block.alt || ''}
                            onChange={(e) => onChange({ alt: e.target.value })}
                            placeholder={t('email_editor.field_alt_placeholder')}
                            className={fieldClass}
                        />
                    </div>
                    <div>
                        <label className={labelClass}>{t('email_editor.field_link_url_optional')}</label>
                        <input
                            type="text"
                            value={block.url || ''}
                            onChange={(e) => onChange({ url: e.target.value })}
                            placeholder="https://example.com"
                            className={fieldClass}
                        />
                    </div>
                    <div className="flex items-end gap-3">
                        <div className="flex-1">
                            <label className={labelClass}>{t('email_editor.field_width')}</label>
                            <input
                                type="text"
                                value={block.width || ''}
                                onChange={(e) => onChange({ width: e.target.value })}
                                placeholder={t('email_editor.field_width_placeholder')}
                                className={fieldClass}
                            />
                        </div>
                        <AlignControl value={block.align} onChange={(align) => onChange({ align })} />
                    </div>
                </div>
            );
        case 'divider':
            return <ColorField label={t('email_editor.field_line_color')} value={block.color} onChange={(color) => onChange({ color })} />;
        case 'spacer':
            return (
                <div>
                    <label className={labelClass}>{t('email_editor.field_height', { height: block.height ?? 24 })}</label>
                    <div className="flex items-center gap-3">
                        <input
                            type="range"
                            min={8}
                            max={120}
                            value={block.height ?? 24}
                            onChange={(e) => onChange({ height: parseInt(e.target.value) })}
                            className="flex-1 accent-brand-500"
                        />
                        <input
                            type="number"
                            min={8}
                            max={200}
                            value={block.height ?? 24}
                            onChange={(e) => onChange({ height: parseInt(e.target.value) || 24 })}
                            className={`${fieldClass} w-20`}
                        />
                    </div>
                </div>
            );
        default:
            return null;
    }
}
