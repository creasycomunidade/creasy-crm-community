import { useEffect, useRef, useState } from 'react';
import axios from 'axios';
import { useTranslation } from 'react-i18next';
import {
    AlertCircle,
    CheckCircle2,
    Code2,
    Eye,
    Layers,
    Loader2,
    Paintbrush,
    Sparkles,
    Variable,
} from 'lucide-react';
import { blocksToHtml, htmlToBlocks } from './blocks';
import { sanitizeHtml } from '@/Utils/sanitizeHtml';
import { EMAIL_TEMPLATES } from './templates';
import VisualCanvas from './VisualCanvas';

// ─── Shared styles ────────────────────────────────────────────────────────────

const inputClass =
    'w-full rounded-lg border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 px-3 py-2 text-sm focus:outline-none focus:border-brand-500 focus:ring-1 focus:ring-brand-500';

const btnBase =
    'inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-sm font-medium transition disabled:opacity-50';

// ─── Main EmailEditor ─────────────────────────────────────────────────────────

const TABS = [
    { id: 'templates', labelKey: 'email_editor.tab_templates', Icon: Paintbrush },
    { id: 'visual', labelKey: 'email_editor.tab_visual', Icon: Layers },
    { id: 'html', labelKey: 'email_editor.tab_html', Icon: Code2 },
    { id: 'ai', labelKey: 'email_editor.tab_ai', Icon: Sparkles },
];

export default function EmailEditor({
    subject,
    body,
    onSubjectChange,
    onBodyChange,
    contactTokens = [],
    campaignName = '',
}) {
    const { t } = useTranslation();
    const [activeTab, setActiveTab] = useState('templates');
    const [blocks, setBlocks] = useState(() => htmlToBlocks(body));
    const [showPreview, setShowPreview] = useState(false);
    const [justGenerated, setJustGenerated] = useState(false);
    const bodyRef = useRef(null);
    const lastBlocksHtml = useRef(body);

    // Sync blocks → body while editing visually
    useEffect(() => {
        if (activeTab !== 'visual') return;
        const html = blocksToHtml(blocks);
        if (html !== lastBlocksHtml.current) {
            lastBlocksHtml.current = html;
            onBodyChange(html);
        }
    }, [blocks, activeTab, onBodyChange]);

    // When switching to visual tab, re-parse HTML → blocks
    function handleTabChange(id) {
        if (id === 'visual' && activeTab !== 'visual') {
            setBlocks(htmlToBlocks(body));
            lastBlocksHtml.current = body;
        }
        setActiveTab(id);
        setJustGenerated(false);
    }

    function handleTemplateSelect(tpl) {
        onSubjectChange(tpl.subject);
        onBodyChange(tpl.body);
        setBlocks(htmlToBlocks(tpl.body));
        lastBlocksHtml.current = tpl.body;
        setActiveTab('visual');
    }

    function handleAiGenerated({ subject: s, body: b }) {
        onSubjectChange(s);
        onBodyChange(b);
        setBlocks(htmlToBlocks(b));
        lastBlocksHtml.current = b;
        setJustGenerated(true);
        setActiveTab('visual');
    }

    function insertTokenAtCursor(token) {
        const el = bodyRef.current;
        if (!el) return;
        const start = el.selectionStart;
        const end = el.selectionEnd;
        const newVal = body.slice(0, start) + token + body.slice(end);
        onBodyChange(newVal);
        requestAnimationFrame(() => {
            el.selectionStart = el.selectionEnd = start + token.length;
            el.focus();
        });
    }

    return (
        <div className="space-y-3">
            {/* Subject */}
            <div>
                <div className="mb-1 flex items-center justify-between gap-2">
                    <label className="text-sm font-medium text-neutral-700 dark:text-neutral-300">{t('email_editor.subject')}</label>
                    <div className="flex items-center gap-2">
                        <SubjectImprover subject={subject} body={body} onPick={onSubjectChange} />
                        <TokenPickerInline tokens={contactTokens} onPick={(token) => onSubjectChange(subject + token)} />
                    </div>
                </div>
                <input
                    type="text"
                    value={subject}
                    onChange={(e) => onSubjectChange(e.target.value)}
                    className={inputClass}
                    placeholder="Welcome, {{contact.first_name}}"
                />
            </div>

            {/* AI generated banner */}
            {justGenerated && (
                <div className="flex items-center gap-2 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-700 dark:border-emerald-800 dark:bg-emerald-950/30 dark:text-emerald-300">
                    <CheckCircle2 className="h-4 w-4 shrink-0" />
                    {t('email_editor.ai_applied')}
                </div>
            )}

            {/* Tab bar */}
            <div className="flex items-center gap-1 border-b border-neutral-200 dark:border-neutral-700">
                {TABS.map(({ id, labelKey, Icon }) => (
                    <button
                        key={id}
                        type="button"
                        onClick={() => handleTabChange(id)}
                        className={`-mb-px inline-flex items-center gap-1.5 border-b-2 px-3 py-2 text-sm font-medium transition ${
                            activeTab === id
                                ? 'border-brand-600 text-brand-600 dark:text-brand-400'
                                : 'border-transparent text-neutral-500 hover:text-neutral-700 dark:hover:text-neutral-300'
                        }`}
                    >
                        <Icon className="h-3.5 w-3.5" />
                        {t(labelKey)}
                    </button>
                ))}

                {/* Preview toggle (HTML/Visual tabs) */}
                {(activeTab === 'html' || activeTab === 'visual') && (
                    <button
                        type="button"
                        onClick={() => setShowPreview((v) => !v)}
                        className={`mb-1 ml-auto inline-flex items-center gap-1.5 rounded-lg px-3 py-2 text-sm font-medium transition ${
                            showPreview
                                ? 'bg-brand-50 text-brand-600 dark:bg-brand-900/20 dark:text-brand-400'
                                : 'text-neutral-500 hover:text-neutral-700 dark:hover:text-neutral-300'
                        }`}
                    >
                        <Eye className="h-3.5 w-3.5" />
                        {t('email_editor.preview')}
                    </button>
                )}
            </div>

            {/* Tab content */}
            <div className={showPreview && (activeTab === 'html' || activeTab === 'visual') ? 'grid grid-cols-2 gap-4' : ''}>
                <div>
                    {activeTab === 'templates' && <TemplatePicker onSelect={handleTemplateSelect} />}

                    {activeTab === 'visual' && (
                        <VisualCanvas blocks={blocks} onChange={setBlocks} tokens={contactTokens} />
                    )}

                    {activeTab === 'html' && (
                        <div>
                            <div className="mb-1 flex items-center justify-between gap-2">
                                <label className="text-sm font-medium text-neutral-700 dark:text-neutral-300">
                                    {t('email_editor.email_body')} <span className="font-normal text-neutral-400">(HTML)</span>
                                </label>
                                <TokenPickerInline tokens={contactTokens} onPick={insertTokenAtCursor} />
                            </div>
                            <textarea
                                ref={bodyRef}
                                rows={14}
                                value={body}
                                onChange={(e) => onBodyChange(e.target.value)}
                                placeholder="<p>Hi {{contact.first_name}},</p>"
                                className={`${inputClass} resize-y font-mono text-xs`}
                            />
                        </div>
                    )}

                    {activeTab === 'ai' && <AiGeneratePanel campaignName={campaignName} onGenerated={handleAiGenerated} />}
                </div>

                {/* Preview pane */}
                {showPreview && (activeTab === 'html' || activeTab === 'visual') && (
                    <div className="overflow-hidden rounded-xl border border-neutral-200 bg-white dark:border-neutral-700 dark:bg-neutral-900">
                        <div className="flex items-center gap-1.5 border-b border-neutral-200 px-3 py-2 text-xs font-medium text-neutral-500 dark:border-neutral-700 dark:text-neutral-400">
                            <Eye className="h-3.5 w-3.5" /> {t('email_editor.rendered_preview')}
                        </div>
                        <div
                            className="prose prose-sm max-h-[500px] max-w-none overflow-auto p-4 dark:prose-invert"
                            dangerouslySetInnerHTML={{ __html: sanitizeHtml(body) || `<p class="text-neutral-400">${t('email_editor.nothing_to_preview')}</p>` }}
                        />
                    </div>
                )}
            </div>
        </div>
    );
}

// ─── Template picker ──────────────────────────────────────────────────────────

function TemplatePicker({ onSelect }) {
    const { t } = useTranslation();
    const categories = [...new Set(EMAIL_TEMPLATES.map((tpl) => tpl.category))];
    const [activeCategory, setActiveCategory] = useState('all');

    const filtered =
        activeCategory === 'all' ? EMAIL_TEMPLATES : EMAIL_TEMPLATES.filter((t) => t.category === activeCategory);

    return (
        <div className="space-y-4">
            {/* Category filters */}
            <div className="flex flex-wrap gap-2">
                <button
                    type="button"
                    onClick={() => setActiveCategory('all')}
                    className={`rounded-full px-3 py-1 text-xs font-medium transition ${
                        activeCategory === 'all'
                            ? 'bg-brand-600 text-white'
                            : 'border border-neutral-300 text-neutral-600 hover:border-brand-400 dark:border-neutral-600 dark:text-neutral-400'
                    }`}
                >
                    {t('email_editor.category_all')}
                </button>
                {categories.map((cat) => (
                    <button
                        key={cat}
                        type="button"
                        onClick={() => setActiveCategory(cat)}
                        className={`rounded-full px-3 py-1 text-xs font-medium transition ${
                            activeCategory === cat
                                ? 'bg-brand-600 text-white'
                                : 'border border-neutral-300 text-neutral-600 hover:border-brand-400 dark:border-neutral-600 dark:text-neutral-400'
                        }`}
                    >
                        {cat}
                    </button>
                ))}
            </div>

            {/* Template grid */}
            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                {filtered.map((tpl) => (
                    <div
                        key={tpl.id}
                        className="group cursor-pointer overflow-hidden rounded-xl border border-neutral-200 transition hover:border-brand-400 dark:border-neutral-700"
                        onClick={() => onSelect(tpl)}
                    >
                        {/* Mini preview */}
                        <div className="relative h-32 overflow-hidden bg-neutral-50 p-3 dark:bg-neutral-800/50">
                            <div
                                className="pointer-events-none w-[182%] origin-top-left scale-[0.55]"
                                dangerouslySetInnerHTML={{ __html: sanitizeHtml(tpl.body) }}
                            />
                            <div className="absolute inset-0 bg-gradient-to-b from-transparent to-neutral-50 dark:to-neutral-800/80" />
                        </div>
                        <div className="flex items-center justify-between gap-2 px-3 py-2">
                            <div>
                                <div className="text-sm font-medium text-neutral-800 dark:text-neutral-200">{tpl.name}</div>
                                <div className="text-xs text-neutral-500">{tpl.category}</div>
                            </div>
                            <button
                                type="button"
                                onClick={(e) => {
                                    e.stopPropagation();
                                    onSelect(tpl);
                                }}
                                className="rounded-lg bg-brand-600 px-3 py-1.5 text-xs font-medium text-white opacity-0 transition group-hover:opacity-100"
                            >
                                {t('email_editor.use')}
                            </button>
                        </div>
                    </div>
                ))}
            </div>
        </div>
    );
}

// ─── AI Generate Panel ────────────────────────────────────────────────────────

function AiGeneratePanel({ campaignName, onGenerated }) {
    const { t } = useTranslation();
    const [prompt, setPrompt] = useState('');
    const [tone, setTone] = useState('professional');
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState(null);

    async function generate() {
        if (!prompt.trim()) return;
        setLoading(true);
        setError(null);
        try {
            const { data } = await axios.post(route('client.campaigns.generate-email'), {
                prompt,
                tone,
                campaign_name: campaignName,
            });
            onGenerated(data);
        } catch (e) {
            setError(e.response?.data?.error ?? t('email_editor.generation_failed'));
        } finally {
            setLoading(false);
        }
    }

    return (
        <div className="space-y-4">
            <div className="rounded-xl border border-brand-200 bg-brand-50 p-4 dark:border-brand-800 dark:bg-brand-950/30">
                <div className="mb-1 flex items-center gap-2 text-sm font-semibold text-brand-700 dark:text-brand-300">
                    <Sparkles className="h-4 w-4" /> {t('email_editor.ai_generator')}
                </div>
                <p className="text-xs text-brand-600 dark:text-brand-400">
                    {t('email_editor.ai_generator_desc')}
                </p>
            </div>

            <div>
                <label className="mb-1 block text-sm font-medium text-neutral-700 dark:text-neutral-300">
                    {t('email_editor.ai_prompt_label')}
                </label>
                <textarea
                    rows={4}
                    value={prompt}
                    onChange={(e) => setPrompt(e.target.value)}
                    placeholder={t('email_editor.ai_prompt_placeholder')}
                    className={`${inputClass} resize-none`}
                />
            </div>

            <div>
                <label className="mb-1 block text-sm font-medium text-neutral-700 dark:text-neutral-300">{t('email_editor.tone')}</label>
                <select value={tone} onChange={(e) => setTone(e.target.value)} className={inputClass}>
                    <option value="professional">{t('email_editor.tone_professional')}</option>
                    <option value="friendly">{t('email_editor.tone_friendly')}</option>
                    <option value="urgent">{t('email_editor.tone_urgent')}</option>
                    <option value="informative">{t('email_editor.tone_informative')}</option>
                </select>
            </div>

            {error && (
                <div className="flex items-start gap-2 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700 dark:border-red-800 dark:bg-red-950/30 dark:text-red-300">
                    <AlertCircle className="mt-0.5 h-4 w-4 shrink-0" />
                    {error}
                </div>
            )}

            <button
                type="button"
                disabled={loading || !prompt.trim()}
                onClick={generate}
                className={`${btnBase} ai-glow w-full justify-center bg-brand-600 text-white hover:bg-brand-700`}
            >
                {loading ? <Loader2 className="h-4 w-4 animate-spin" /> : <Sparkles className="h-4 w-4" />}
                {loading ? t('email_editor.generating') : t('email_editor.generate_email')}
            </button>

            <p className="text-center text-xs text-neutral-400">
                {t('email_editor.ai_footer_note')}
            </p>
        </div>
    );
}

// ─── Subject Improver ────────────────────────────────────────────────────────

function SubjectImprover({ subject, body, onPick }) {
    const { t } = useTranslation();
    const [open, setOpen] = useState(false);
    const [loading, setLoading] = useState(false);
    const [suggestions, setSuggestions] = useState([]);
    const [error, setError] = useState(null);

    async function fetchSuggestions() {
        if (!subject.trim()) return;
        setLoading(true);
        setError(null);
        setSuggestions([]);
        setOpen(true);
        try {
            const { data } = await axios.post(route('client.campaigns.improve-subject'), { subject, body });
            setSuggestions(data.suggestions ?? []);
        } catch (e) {
            setError(e.response?.data?.error ?? t('email_editor.suggestions_failed'));
        } finally {
            setLoading(false);
        }
    }

    function pick(s) {
        onPick(s);
        setOpen(false);
        setSuggestions([]);
    }

    return (
        <div className="relative">
            <button
                type="button"
                disabled={!subject.trim()}
                onClick={fetchSuggestions}
                title={t('email_editor.improve_title')}
                className="ai-glow inline-flex items-center gap-1 rounded-md border border-brand-300 bg-brand-50 px-2 py-1 text-xs font-medium text-brand-700 transition hover:bg-brand-100 disabled:opacity-40 dark:border-brand-700 dark:bg-brand-950/40 dark:text-brand-300 dark:hover:bg-brand-900/50"
            >
                {loading ? <Loader2 className="h-3 w-3 animate-spin" /> : <Sparkles className="h-3 w-3" />}
                {t('email_editor.improve')}
            </button>

            {open && (
                <div className="absolute right-0 z-40 mt-1 w-96 rounded-xl border border-neutral-200 bg-white shadow-xl dark:border-neutral-700 dark:bg-neutral-900">
                    <div className="flex items-center justify-between border-b border-neutral-100 px-4 py-3 dark:border-neutral-800">
                        <div className="flex items-center gap-2 text-sm font-semibold text-neutral-800 dark:text-neutral-200">
                            <Sparkles className="h-4 w-4 text-brand-500" />
                            {t('email_editor.subject_suggestions')}
                        </div>
                        <button
                            type="button"
                            onClick={() => setOpen(false)}
                            className="text-lg leading-none text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-300"
                        >
                            ×
                        </button>
                    </div>

                    <div className="space-y-2 p-3">
                        {loading && (
                            <div className="flex items-center justify-center gap-2 py-6 text-sm text-neutral-400">
                                <Loader2 className="h-4 w-4 animate-spin" />
                                {t('email_editor.generating_suggestions')}
                            </div>
                        )}

                        {error && (
                            <div className="flex items-start gap-2 rounded-lg border border-red-200 bg-red-50 p-3 text-xs text-red-700 dark:border-red-800 dark:bg-red-950/30 dark:text-red-300">
                                <AlertCircle className="mt-0.5 h-3.5 w-3.5 shrink-0" />
                                {error}
                            </div>
                        )}

                        {suggestions.map((s, i) => (
                            <button
                                key={i}
                                type="button"
                                onClick={() => pick(s)}
                                className="group w-full rounded-lg border border-neutral-200 px-3 py-2.5 text-left text-sm text-neutral-800 transition hover:border-brand-400 hover:bg-brand-50 dark:border-neutral-700 dark:text-neutral-200 dark:hover:bg-brand-900/20"
                            >
                                <div className="flex items-start justify-between gap-2">
                                    <span>{s}</span>
                                    <span className="mt-0.5 shrink-0 text-xs text-brand-600 opacity-0 transition group-hover:opacity-100 dark:text-brand-400">
                                        {t('email_editor.use')} →
                                    </span>
                                </div>
                                <div className="mt-1 text-xs text-neutral-400">{t('email_editor.char_count', { count: s.length })}</div>
                            </button>
                        ))}
                    </div>
                </div>
            )}
        </div>
    );
}

// ─── Inline Token Picker ──────────────────────────────────────────────────────

function TokenPickerInline({ tokens, onPick }) {
    const { t } = useTranslation();
    const [open, setOpen] = useState(false);
    if (!tokens || tokens.length === 0) return null;
    return (
        <div className="relative">
            <button
                type="button"
                onClick={() => setOpen((o) => !o)}
                className="inline-flex items-center gap-1 rounded-md border border-neutral-300 px-2 py-1 text-xs font-medium text-neutral-700 hover:bg-neutral-50 dark:border-neutral-600 dark:text-neutral-300 dark:hover:bg-neutral-800"
            >
                <Variable className="h-3 w-3" /> {t('email_editor.insert_variable')}
            </button>
            {open && (
                <div
                    className="absolute right-0 z-30 mt-1 w-56 rounded-lg border border-neutral-200 bg-white shadow-lg dark:border-neutral-700 dark:bg-neutral-800"
                    onMouseLeave={() => setOpen(false)}
                >
                    {tokens.map((token) => (
                        <button
                            key={token.key}
                            type="button"
                            onClick={() => {
                                onPick(token.key);
                                setOpen(false);
                            }}
                            className="flex w-full items-center justify-between px-3 py-1.5 text-left text-xs hover:bg-neutral-50 dark:hover:bg-neutral-700"
                        >
                            <span>{token.label}</span>
                            <span className="font-mono text-neutral-400">{token.key}</span>
                        </button>
                    ))}
                </div>
            )}
        </div>
    );
}
