import { Head, Link, useForm, router, usePage } from '@inertiajs/react';
import axios from 'axios';
import ClientLayout from '@/Layouts/ClientLayout';
import EmptyState from '@/Components/EmptyState';
import { ArrowLeft, Plus, RefreshCw, Trash2, Globe, FileText, Type, X, Upload, CheckCircle2, Clock, Zap, AlertCircle, HelpCircle, Trash, HardDrive, Search, LoaderCircle, ExternalLink, Unplug, Pencil, FilePenLine, RotateCcw, Eye, FileCode2, Heading1, Heading2, Heading3, Bold, Italic, Strikethrough, List, ListOrdered, ListChecks, Quote, Code2, Link2, Image, Table2, Minus } from 'lucide-react';
import { useState, useRef } from 'react';
import { useTranslation, Trans } from 'react-i18next';
import { sanitizeHtml } from '@/Utils/sanitizeHtml';

const SOURCE_TYPES = {
    url:     { icon: Globe,    labelKey: 'ai.source_url' },
    file:    { icon: Upload,   labelKey: 'ai.source_file' },
    text:    { icon: Type,     labelKey: 'ai.source_text' },
    sitemap: { icon: Globe,    labelKey: 'ai.source_sitemap' },
    faq:     { icon: HelpCircle, labelKey: 'ai.source_faq' },
    google_drive: { icon: HardDrive, label: 'Drive' },
};

const STATUS_CONFIG = {
    indexed:  { color: 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300',   icon: CheckCircle2 },
    pending:  { color: 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/40 dark:text-yellow-300', icon: Clock },
    indexing: { color: 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300',        icon: Zap },
    error:    { color: 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300',            icon: AlertCircle },
};

function EditorButton({ label, icon: Icon, onClick }) {
    return (
        <button
            type="button"
            onClick={onClick}
            title={label}
            aria-label={label}
            className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg text-neutral-600 transition hover:bg-neutral-200 hover:text-neutral-950 focus:outline-none focus:ring-2 focus:ring-brand-500 dark:text-neutral-300 dark:hover:bg-neutral-700 dark:hover:text-white"
        >
            <Icon className="h-4 w-4" aria-hidden="true" />
        </button>
    );
}

export default function AiKnowledgeBaseShow({ kb, googleDrive = {} }) {
    const { t } = useTranslation();
    const { props } = usePage();
    const flash = props.flash ?? {};
    const [showAdd, setShowAdd] = useState(false);
    const [dragOver, setDragOver] = useState(false);
    const [faqPairs, setFaqPairs] = useState([{ question: '', answer: '' }]);
    const fileRef = useRef();
    const markdownEditorRef = useRef(null);
    const [driveFiles, setDriveFiles] = useState([]);
    const [driveSearch, setDriveSearch] = useState('');
    const [driveLoading, setDriveLoading] = useState(false);
    const [driveError, setDriveError] = useState('');
    const [selectedDriveFiles, setSelectedDriveFiles] = useState([]);
    const [editingDocument, setEditingDocument] = useState(null);
    const [editDocument, setEditDocument] = useState({ title: '', source_ref: '', content: '', has_override: false, format: 'plain_text' });
    const [editLoading, setEditLoading] = useState(false);
    const [editSaving, setEditSaving] = useState(false);
    const [editError, setEditError] = useState('');
    const [editView, setEditView] = useState('preview');
    const [markdownPreview, setMarkdownPreview] = useState('');
    const [previewLoading, setPreviewLoading] = useState(false);

    const { data, setData, reset } = useForm({
        source_type: 'url',
        source_ref: '',
        title: '',
        file: null,
    });
    const [processing, setProcessing] = useState(false);

    const addFaqPair = () => setFaqPairs(p => [...p, { question: '', answer: '' }]);
    const removeFaqPair = (i) => setFaqPairs(p => p.filter((_, idx) => idx !== i));
    const updateFaqPair = (i, field, value) => setFaqPairs(p => p.map((pair, idx) => idx === i ? { ...pair, [field]: value } : pair));

    const handleAdd = (e) => {
        e.preventDefault();
        setProcessing(true);
        if (data.source_type === 'google_drive') {
            router.post(route('client.ai.knowledge-bases.google-drive.import', kb.uuid), {
                file_ids: selectedDriveFiles,
            }, {
                preserveScroll: true,
                onSuccess: () => {
                    reset();
                    setSelectedDriveFiles([]);
                    setShowAdd(false);
                    setProcessing(false);
                },
                onError: () => setProcessing(false),
            });
            return;
        }
        const formData = new FormData();
        formData.append('source_type', data.source_type);
        formData.append('title', data.title);
        if (data.source_type === 'faq') {
            formData.append('source_ref', JSON.stringify(faqPairs.filter(p => p.question.trim())));
        } else {
            formData.append('source_ref', data.source_ref);
        }
        if (data.file) formData.append('file', data.file);
        router.post(route('client.ai.knowledge-bases.documents.add', kb.uuid), formData, {
            preserveScroll: true,
            onSuccess: () => { reset(); setFaqPairs([{ question: '', answer: '' }]); setShowAdd(false); setProcessing(false); },
            onError: () => setProcessing(false),
        });
    };

    const loadDriveFiles = async () => {
        if (!googleDrive.connected) return;
        setDriveLoading(true);
        setDriveError('');
        try {
            const url = new URL(route('client.ai.google-drive.files'), window.location.origin);
            if (driveSearch.trim()) url.searchParams.set('q', driveSearch.trim());
            const response = await fetch(url.toString(), {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });
            const payload = await response.json();
            if (!response.ok) throw new Error(payload.message || 'Não foi possível carregar os arquivos.');
            setDriveFiles(payload.files || []);
        } catch (error) {
            setDriveError(error.message || 'Não foi possível carregar os arquivos do Google Drive.');
        } finally {
            setDriveLoading(false);
        }
    };

    const selectSourceType = type => {
        setData('source_type', type);
        if (type === 'google_drive' && googleDrive.connected && driveFiles.length === 0) {
            loadDriveFiles();
        }
    };

    const toggleDriveFile = fileId => {
        setSelectedDriveFiles(current => current.includes(fileId)
            ? current.filter(id => id !== fileId)
            : [...current, fileId]);
    };

    const handleDelete = (docId) => {
        if (confirm(t('ai.remove_document_confirm'))) {
            router.delete(route('client.ai.documents.destroy', docId), { preserveScroll: true });
        }
    };

    const handleReindex = (docId) => {
        router.post(route('client.ai.documents.reindex', docId), {}, { preserveScroll: true });
    };

    const requestPreview = async (content, format) => {
        if (format !== 'markdown') {
            setMarkdownPreview('');
            return;
        }
        setPreviewLoading(true);
        try {
            const { data: payload } = await axios.post(route('client.ai.markdown.preview'), { content });
            setMarkdownPreview(payload.html || '');
        } finally {
            setPreviewLoading(false);
        }
    };

    const openDocumentEditor = async doc => {
        setEditingDocument(doc);
        setEditDocument({ title: doc.title || '', source_ref: '', content: '', has_override: false, format: 'plain_text' });
        setEditLoading(true);
        setEditError('');
        setEditView('preview');
        setMarkdownPreview('');
        try {
            const response = await fetch(route('client.ai.documents.content', doc.uuid), {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });
            const payload = await response.json();
            if (!response.ok) throw new Error(payload.message || 'Não foi possível carregar o conteúdo.');
            setEditDocument(current => ({
                ...current,
                source_ref: payload.source_ref || '',
                content: payload.content || '',
                has_override: Boolean(payload.has_override),
                format: payload.format || 'plain_text',
            }));
            await requestPreview(payload.content || '', payload.format || 'plain_text');
        } catch (error) {
            setEditError(error.message || 'Não foi possível carregar o conteúdo deste documento.');
        } finally {
            setEditLoading(false);
        }
    };

    const showDocumentPreview = async () => {
        setEditView('preview');
        setEditError('');
        try {
            await requestPreview(editDocument.content, editDocument.format);
        } catch (error) {
            setEditError(error.response?.data?.message || 'Não foi possível gerar a visualização do documento.');
            setEditView('source');
        }
    };

    const applyMarkdown = (kind) => {
        const editor = markdownEditorRef.current;
        if (!editor) return;
        const start = editor.selectionStart;
        const end = editor.selectionEnd;
        const selected = editDocument.content.slice(start, end);
        const lineStart = editDocument.content.lastIndexOf('\n', Math.max(0, start - 1)) + 1;
        const lineEndIndex = editDocument.content.indexOf('\n', end);
        const lineEnd = lineEndIndex === -1 ? editDocument.content.length : lineEndIndex;
        const lineSelection = editDocument.content.slice(lineStart, lineEnd);
        let replaceStart = start;
        let replaceEnd = end;
        let replacement = selected;
        let selectionStart = start;
        let selectionEnd = end;

        const wrap = (before, after, placeholder) => {
            const value = selected || placeholder;
            replacement = `${before}${value}${after}`;
            selectionStart = start + before.length;
            selectionEnd = selectionStart + value.length;
        };
        const prefixLines = (prefix, placeholder = 'Novo item') => {
            replaceStart = lineStart;
            replaceEnd = lineEnd;
            const value = lineSelection || placeholder;
            replacement = value.split('\n').map((line, index) => `${typeof prefix === 'function' ? prefix(index) : prefix}${line}`).join('\n');
            selectionStart = replaceStart;
            selectionEnd = replaceStart + replacement.length;
        };

        if (kind === 'h1') prefixLines('# ', 'Título principal');
        if (kind === 'h2') prefixLines('## ', 'Título da seção');
        if (kind === 'h3') prefixLines('### ', 'Subtítulo');
        if (kind === 'bold') wrap('**', '**', 'texto em negrito');
        if (kind === 'italic') wrap('*', '*', 'texto em itálico');
        if (kind === 'strike') wrap('~~', '~~', 'texto riscado');
        if (kind === 'bullet') prefixLines('- ');
        if (kind === 'number') prefixLines(index => `${index + 1}. `);
        if (kind === 'check') prefixLines('- [ ] ');
        if (kind === 'quote') prefixLines('> ', 'Citação');
        if (kind === 'code') wrap(selected.includes('\n') ? '```\n' : '`', selected.includes('\n') ? '\n```' : '`', 'código');
        if (kind === 'link') wrap('[', '](https://)', 'texto do link');
        if (kind === 'image') wrap('![', '](https://)', 'descrição da imagem');
        if (kind === 'table') {
            replacement = '| Coluna 1 | Coluna 2 |\n| --- | --- |\n| Conteúdo | Conteúdo |';
            selectionEnd = start + replacement.length;
        }
        if (kind === 'rule') {
            replacement = '\n\n---\n\n';
            selectionEnd = start + replacement.length;
        }

        const nextContent = `${editDocument.content.slice(0, replaceStart)}${replacement}${editDocument.content.slice(replaceEnd)}`;
        setEditDocument(current => ({ ...current, content: nextContent }));
        window.requestAnimationFrame(() => {
            editor.focus();
            editor.setSelectionRange(selectionStart, selectionEnd);
        });
    };

    const saveDocument = event => {
        event.preventDefault();
        setEditSaving(true);
        setEditError('');
        router.patch(route('client.ai.documents.update', editingDocument.uuid), editDocument, {
            preserveScroll: true,
            onSuccess: () => setEditingDocument(null),
            onError: errors => setEditError(errors.content || errors.source_ref || errors.title || errors.document || 'Não foi possível salvar o documento.'),
            onFinish: () => setEditSaving(false),
        });
    };

    const restoreDocumentSource = () => {
        if (!confirm('Restaurar o conteúdo da fonte original? Suas alterações de texto serão substituídas na próxima indexação.')) return;
        setEditSaving(true);
        setEditError('');
        router.patch(route('client.ai.documents.update', editingDocument.uuid), {
            title: editDocument.title,
            source_ref: editDocument.source_ref,
            restore_source: true,
        }, {
            preserveScroll: true,
            onSuccess: () => setEditingDocument(null),
            onError: errors => setEditError(errors.source_ref || errors.title || errors.document || 'Não foi possível restaurar a fonte original.'),
            onFinish: () => setEditSaving(false),
        });
    };

    const handleDrop = (e) => {
        e.preventDefault();
        setDragOver(false);
        const file = e.dataTransfer.files[0];
        if (file) { setData('file', file); setData('source_type', 'file'); }
    };

    const totalTokens = kb.documents?.reduce((s, d) => s + (d.tokens ?? 0), 0) ?? 0;
    const indexedCount = kb.documents?.filter(d => d.status === 'indexed').length ?? 0;

    return (
        <ClientLayout title={kb.name}>
            <Head title={`${kb.name} · ${t('ai.kb_title')}`} />
            <div className="space-y-6">
                {/* Header */}
                <div className="flex items-center gap-3">
                    <Link href={route('client.ai.knowledge-bases.index')} className="rounded-lg p-1.5 text-neutral-400 hover:text-neutral-700 dark:hover:text-neutral-200 hover:bg-neutral-100 dark:hover:bg-neutral-800 transition">
                        <ArrowLeft className="h-4 w-4" />
                    </Link>
                    <div className="flex-1 min-w-0">
                        <h2 className="text-xl font-semibold text-neutral-900 dark:text-neutral-100 truncate">{kb.name}</h2>
                    </div>
                    {(
                        <button
                            onClick={() => setShowAdd(true)}
                            className="flex items-center gap-1.5 rounded-lg bg-brand-600 px-3 py-2 text-sm font-medium text-white hover:bg-brand-700 transition shrink-0"
                        >
                            <Plus className="h-4 w-4" /> {t('ai.add_document')}
                        </button>
                    )}
                </div>

                {flash.success && (
                    <div className="rounded-lg bg-green-50 dark:bg-green-900/30 border border-green-200 dark:border-green-800 text-green-800 dark:text-green-200 px-4 py-2.5 text-sm">
                        {flash.success}
                    </div>
                )}
                {flash.error && (
                    <div className="rounded-lg bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-800 text-red-800 dark:text-red-200 px-4 py-2.5 text-sm">
                        {flash.error}
                    </div>
                )}

                {/* Stats */}
                {(kb.documents?.length ?? 0) > 0 && (
                    <div className="grid grid-cols-3 gap-3">
                        {[
                            { label: t('ai.total_documents'), value: kb.documents?.length ?? 0 },
                            { label: t('ai.indexed'), value: indexedCount },
                            { label: t('ai.total_tokens'), value: totalTokens.toLocaleString() },
                        ].map(({ label, value }) => (
                            <div key={label} className="rounded-xl border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-900 px-4 py-3">
                                <p className="text-lg font-semibold text-neutral-900 dark:text-neutral-100 leading-none">{value}</p>
                                <p className="text-xs text-neutral-500 dark:text-neutral-400 mt-1">{label}</p>
                            </div>
                        ))}
                    </div>
                )}

                {/* Documents Table */}
                <div className="overflow-x-auto rounded-xl border border-neutral-200 bg-white dark:border-neutral-700 dark:bg-neutral-900">
                    {(kb.documents?.length ?? 0) > 0 ? (
                        <table className="min-w-full divide-y divide-neutral-100 dark:divide-neutral-800 text-sm">
                            <thead className="bg-neutral-50 dark:bg-neutral-800/60">
                                <tr>
                                    {[
                                        { key: 'document', label: t('ai.col_document') },
                                        { key: 'type', label: t('ai.col_type') },
                                        { key: 'status', label: t('ai.col_status') },
                                        { key: 'tokens', label: t('ai.col_tokens') },
                                        { key: 'actions', label: '' },
                                    ].map(h => (
                                        <th key={h.key} className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-neutral-400 dark:text-neutral-500">{h.label}</th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-neutral-100 dark:divide-neutral-800">
                                {kb.documents.map(doc => {
                                    const sourceDefinition = doc.external_provider === 'google_drive'
                                        ? SOURCE_TYPES.google_drive
                                        : (SOURCE_TYPES[doc.source_type] ?? SOURCE_TYPES.file);
                                    const { icon: TypeIcon, labelKey: typeLabelKey, label: typeLabel } = sourceDefinition;
                                    const { color, icon: StatusIcon } = STATUS_CONFIG[doc.status] ?? STATUS_CONFIG.pending;
                                    return (
                                        <tr key={doc.id} className="hover:bg-neutral-50 dark:hover:bg-neutral-800/40 transition">
                                            <td className="px-4 py-3 max-w-xs">
                                                <div className="flex items-center gap-1.5">
                                                    <p className="font-medium text-neutral-900 dark:text-neutral-100 truncate">{doc.title || doc.source_ref || '—'}</p>
                                                    {doc.external_provider === 'google_drive' && doc.source_metadata?.webViewLink && (
                                                        <a href={doc.source_metadata.webViewLink} target="_blank" rel="noreferrer" title="Abrir no Google Drive" className="shrink-0 text-neutral-400 hover:text-brand-600">
                                                            <ExternalLink className="h-3.5 w-3.5" />
                                                        </a>
                                                    )}
                                                </div>
                                                {doc.title && doc.source_ref && doc.external_provider !== 'google_drive' && (
                                                    <p className="text-xs text-neutral-400 dark:text-neutral-500 truncate mt-0.5">{doc.source_ref}</p>
                                                )}
                                                {doc.external_provider === 'google_drive' && (
                                                    <p className={`text-xs mt-0.5 ${doc.sync_error ? 'text-red-500' : 'text-neutral-400 dark:text-neutral-500'}`}>
                                                        {doc.sync_error
                                                            ? `Falha na sincronização: ${doc.sync_error}`
                                                            : doc.last_synced_at
                                                                ? `Sincronizado em ${new Date(doc.last_synced_at).toLocaleString('pt-BR')}`
                                                                : 'Aguardando a primeira sincronização'}
                                                    </p>
                                                )}
                                            </td>
                                            <td className="px-4 py-3">
                                                <span className="inline-flex items-center gap-1 rounded-md bg-neutral-100 dark:bg-neutral-800 px-2 py-0.5 text-xs text-neutral-600 dark:text-neutral-400">
                                                    <TypeIcon className="h-3 w-3" /> {typeLabel ?? t(typeLabelKey)}
                                                </span>
                                            </td>
                                            <td className="px-4 py-3">
                                                <span className={`inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium ${color}`}>
                                                    <StatusIcon className="h-3 w-3" /> {t(`ai.doc_status_${doc.status}`)}
                                                </span>
                                            </td>
                                            <td className="px-4 py-3 text-neutral-500 dark:text-neutral-400 tabular-nums">
                                                {(doc.tokens ?? 0).toLocaleString()}
                                            </td>
                                            <td className="px-4 py-3">
                                                <div className="flex items-center gap-1 justify-end">
                                                    <button
                                                        onClick={() => openDocumentEditor(doc)}
                                                        title="Editar documento"
                                                        className="rounded-md p-1.5 text-neutral-400 hover:text-brand-600 hover:bg-brand-50 dark:hover:bg-brand-900/20 transition"
                                                    >
                                                        <Pencil className="h-3.5 w-3.5" />
                                                    </button>
                                                    <button
                                                        onClick={() => handleReindex(doc.uuid)}
                                                        title={t('ai.reindex')}
                                                        className="rounded-md p-1.5 text-neutral-400 hover:text-brand-600 hover:bg-brand-50 dark:hover:bg-brand-900/20 transition"
                                                    >
                                                        <RefreshCw className="h-3.5 w-3.5" />
                                                    </button>
                                                    <button
                                                        onClick={() => handleDelete(doc.uuid)}
                                                        className="rounded-md p-1.5 text-neutral-400 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 transition"
                                                    >
                                                        <Trash2 className="h-3.5 w-3.5" />
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    ) : (
                        <EmptyState
                            icon={<FileText className="h-8 w-8" />}
                            title={t('ai.docs_empty_title')}
                            description={t('ai.docs_empty_description')}
                            action={{ label: t('ai.add_first_document'), onClick: () => setShowAdd(true) }}
                        />
                    )}
                </div>
            </div>

            {/* Add Document Modal */}
            {showAdd && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4">
                    <div className="w-full max-w-md rounded-2xl bg-white dark:bg-neutral-900 shadow-2xl">
                        <div className="flex items-center justify-between px-6 pt-5 pb-4 border-b border-neutral-100 dark:border-neutral-800">
                            <h3 className="text-base font-semibold text-neutral-900 dark:text-neutral-100">{t('ai.add_document')}</h3>
                            <button onClick={() => setShowAdd(false)} className="text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-200 transition">
                                <X className="h-4 w-4" />
                            </button>
                        </div>

                        <form onSubmit={handleAdd} className="px-6 py-4 space-y-4">
                            {/* Source Type Tabs */}
                            <div>
                                <label className="block text-xs font-medium text-neutral-700 dark:text-neutral-300 mb-2">{t('ai.source_type')}</label>
                                <div className="grid grid-cols-3 sm:grid-cols-6 gap-1 rounded-lg bg-neutral-100 dark:bg-neutral-800 p-1">
                                    {Object.entries(SOURCE_TYPES).map(([type, { icon: Icon, labelKey, label }]) => (
                                        <button
                                            key={type}
                                            type="button"
                                            onClick={() => selectSourceType(type)}
                                            className={`flex flex-col items-center gap-0.5 rounded-md py-1.5 px-1 text-xs font-medium transition ${
                                                data.source_type === type
                                                    ? 'bg-white dark:bg-neutral-700 text-brand-600 dark:text-brand-400 shadow-sm'
                                                    : 'text-neutral-500 dark:text-neutral-400 hover:text-neutral-700 dark:hover:text-neutral-200'
                                            }`}
                                        >
                                            <Icon className="h-3.5 w-3.5" />
                                            {label ?? t(labelKey)}
                                        </button>
                                    ))}
                                </div>
                            </div>

                            {/* Input Area */}
                            {data.source_type === 'google_drive' ? (
                                <div className="space-y-3">
                                    {!googleDrive.configured ? (
                                        <div className="rounded-lg border border-amber-200 bg-amber-50 dark:border-amber-800 dark:bg-amber-900/20 p-4 text-sm text-amber-900 dark:text-amber-200">
                                            O aplicativo Google precisa ser configurado pelo administrador antes de conectar uma conta.
                                        </div>
                                    ) : !googleDrive.connected ? (
                                        <div className="rounded-lg border border-neutral-200 dark:border-neutral-700 p-4 text-center">
                                            <HardDrive className="mx-auto h-8 w-8 text-brand-500" />
                                            <p className="mt-2 text-sm font-medium text-neutral-900 dark:text-neutral-100">Conecte seu Google Drive</p>
                                            <p className="mt-1 text-xs text-neutral-500 dark:text-neutral-400">A autorização pertence somente a este espaço de trabalho e fica armazenada de forma criptografada.</p>
                                            <a href={`${route('client.ai.google-drive.connect')}?kb=${encodeURIComponent(kb.uuid)}`} className="mt-3 inline-flex rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">
                                                Conectar com o Google
                                            </a>
                                        </div>
                                    ) : (
                                        <>
                                            <div className="flex items-center justify-between gap-3 rounded-lg border border-green-200 bg-green-50 dark:border-green-800 dark:bg-green-900/20 p-3">
                                                <div className="min-w-0">
                                                    <p className="text-xs font-medium text-green-800 dark:text-green-200">Google Drive conectado</p>
                                                    <p className="truncate text-xs text-green-700 dark:text-green-300">{googleDrive.email}</p>
                                                </div>
                                                <button type="button" onClick={() => confirm('Desconectar o Google Drive e remover as cópias indexadas deste espaço?') && router.delete(route('client.ai.google-drive.disconnect'), { preserveScroll: true })} className="inline-flex items-center gap-1 text-xs text-red-600 hover:text-red-700">
                                                    <Unplug className="h-3.5 w-3.5" /> Desconectar
                                                </button>
                                            </div>
                                            <div className="flex gap-2">
                                                <div className="relative flex-1">
                                                    <Search className="absolute left-2.5 top-2.5 h-4 w-4 text-neutral-400" />
                                                    <input value={driveSearch} onChange={event => setDriveSearch(event.target.value)} onKeyDown={event => { if (event.key === 'Enter') { event.preventDefault(); loadDriveFiles(); } }} placeholder="Pesquisar no Google Drive" className="w-full rounded-lg border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 py-2 pl-8 pr-3 text-sm text-neutral-900 dark:text-neutral-100" />
                                                </div>
                                                <button type="button" onClick={loadDriveFiles} disabled={driveLoading} className="rounded-lg border border-neutral-300 dark:border-neutral-600 px-3 text-sm text-neutral-700 dark:text-neutral-300 disabled:opacity-50">
                                                    {driveLoading ? <LoaderCircle className="h-4 w-4 animate-spin" /> : 'Buscar'}
                                                </button>
                                            </div>
                                            {driveError && <p className="rounded-lg bg-red-50 dark:bg-red-900/20 p-2 text-xs text-red-700 dark:text-red-300">{driveError}</p>}
                                            <div className="max-h-60 space-y-1 overflow-y-auto rounded-lg border border-neutral-200 dark:border-neutral-700 p-1">
                                                {driveLoading && driveFiles.length === 0 ? (
                                                    <div className="flex items-center justify-center gap-2 py-8 text-sm text-neutral-400"><LoaderCircle className="h-4 w-4 animate-spin" /> Carregando arquivos…</div>
                                                ) : driveFiles.length === 0 ? (
                                                    <p className="py-8 text-center text-sm text-neutral-400">Nenhum arquivo compatível encontrado.</p>
                                                ) : driveFiles.map(file => (
                                                    <label key={file.id} className="flex cursor-pointer items-center gap-3 rounded-md p-2 hover:bg-neutral-50 dark:hover:bg-neutral-800">
                                                        <input type="checkbox" checked={selectedDriveFiles.includes(file.id)} onChange={() => toggleDriveFile(file.id)} className="rounded border-neutral-300 text-brand-600 focus:ring-brand-500" />
                                                        <HardDrive className="h-4 w-4 shrink-0 text-brand-500" />
                                                        <span className="min-w-0 flex-1">
                                                            <span className="block truncate text-sm font-medium text-neutral-800 dark:text-neutral-200">{file.name}</span>
                                                            <span className="block text-[11px] text-neutral-400">Alterado em {new Date(file.modifiedTime).toLocaleString('pt-BR')}</span>
                                                        </span>
                                                    </label>
                                                ))}
                                            </div>
                                            <p className="text-xs text-neutral-500 dark:text-neutral-400">{selectedDriveFiles.length} arquivo(s) selecionado(s). Mudanças serão verificadas automaticamente a cada cinco minutos.</p>
                                        </>
                                    )}
                                </div>
                            ) : data.source_type === 'file' ? (
                                <div
                                    onDragOver={e => { e.preventDefault(); setDragOver(true); }}
                                    onDragLeave={() => setDragOver(false)}
                                    onDrop={handleDrop}
                                    onClick={() => fileRef.current?.click()}
                                    className={`relative cursor-pointer rounded-lg border-2 border-dashed p-6 text-center transition ${
                                        dragOver
                                            ? 'border-brand-500 bg-brand-50 dark:bg-brand-900/20'
                                            : 'border-neutral-300 dark:border-neutral-600 hover:border-brand-400 dark:hover:border-brand-600'
                                    }`}
                                >
                                    <input ref={fileRef} type="file" accept=".pdf,.txt,.md,.docx" className="hidden" onChange={e => setData('file', e.target.files[0])} />
                                    <Upload className="h-6 w-6 mx-auto mb-2 text-neutral-400" />
                                    {data.file ? (
                                        <p className="text-sm font-medium text-brand-600 dark:text-brand-400">{data.file.name}</p>
                                    ) : (
                                        <>
                                            <p className="text-sm text-neutral-600 dark:text-neutral-400"><Trans i18nKey="ai.drop_a_file_or_browse" components={{ 1: <span className="text-brand-600 dark:text-brand-400 font-medium" /> }} /></p>
                                            <p className="text-xs text-neutral-400 mt-1">PDF, TXT, MD, DOCX</p>
                                        </>
                                    )}
                                </div>
                            ) : data.source_type === 'text' ? (
                                <div>
                                    <label className="block text-xs font-medium text-neutral-700 dark:text-neutral-300 mb-1">{t('ai.text_content')}</label>
                                    <textarea
                                        value={data.source_ref}
                                        onChange={e => setData('source_ref', e.target.value)}
                                        rows={5}
                                        placeholder={t('ai.text_content_placeholder')}
                                        className="w-full rounded-lg border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 px-3 py-2 text-sm text-neutral-900 dark:text-neutral-100 placeholder-neutral-400 focus:outline-none focus:ring-2 focus:ring-brand-500 resize-none transition"
                                    />
                                </div>
                            ) : data.source_type === 'faq' ? (
                                <div className="space-y-2">
                                    <label className="block text-xs font-medium text-neutral-700 dark:text-neutral-300">{t('ai.questions_and_answers')}</label>
                                    <div className="space-y-3 max-h-52 overflow-y-auto pr-1">
                                        {faqPairs.map((pair, i) => (
                                            <div key={i} className="rounded-lg border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800/50 p-3 space-y-2">
                                                <div className="flex items-center gap-2">
                                                    <span className="text-xs font-semibold text-neutral-400 w-4 shrink-0">Q{i + 1}</span>
                                                    <input
                                                        type="text"
                                                        value={pair.question}
                                                        onChange={e => updateFaqPair(i, 'question', e.target.value)}
                                                        placeholder={t('ai.faq_question_placeholder')}
                                                        className="flex-1 rounded-md border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 px-2.5 py-1.5 text-xs text-neutral-900 dark:text-neutral-100 placeholder-neutral-400 focus:outline-none focus:ring-2 focus:ring-brand-500 transition"
                                                    />
                                                    {faqPairs.length > 1 && (
                                                        <button type="button" onClick={() => removeFaqPair(i)} className="text-neutral-300 hover:text-red-400 transition">
                                                            <Trash className="h-3.5 w-3.5" />
                                                        </button>
                                                    )}
                                                </div>
                                                <div className="flex items-start gap-2">
                                                    <span className="text-xs font-semibold text-neutral-400 w-4 shrink-0 mt-1.5">A</span>
                                                    <textarea
                                                        value={pair.answer}
                                                        onChange={e => updateFaqPair(i, 'answer', e.target.value)}
                                                        rows={2}
                                                        placeholder={t('ai.faq_answer_placeholder')}
                                                        className="flex-1 rounded-md border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 px-2.5 py-1.5 text-xs text-neutral-900 dark:text-neutral-100 placeholder-neutral-400 focus:outline-none focus:ring-2 focus:ring-brand-500 resize-none transition"
                                                    />
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                    <button
                                        type="button"
                                        onClick={addFaqPair}
                                        className="w-full rounded-lg border border-dashed border-neutral-300 dark:border-neutral-600 py-1.5 text-xs text-neutral-500 dark:text-neutral-400 hover:border-brand-400 hover:text-brand-600 dark:hover:text-brand-400 transition"
                                    >
                                        {t('ai.add_another_qa')}
                                    </button>
                                </div>
                            ) : (
                                <div>
                                    <label className="block text-xs font-medium text-neutral-700 dark:text-neutral-300 mb-1">
                                        {data.source_type === 'sitemap' ? t('ai.sitemap_url') : t('ai.source_url')}
                                    </label>
                                    <input
                                        type="url"
                                        value={data.source_ref}
                                        onChange={e => setData('source_ref', e.target.value)}
                                        placeholder={data.source_type === 'sitemap' ? 'https://example.com/sitemap.xml' : 'https://example.com'}
                                        className="w-full rounded-lg border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 px-3 py-2 text-sm text-neutral-900 dark:text-neutral-100 placeholder-neutral-400 focus:outline-none focus:ring-2 focus:ring-brand-500 transition"
                                    />
                                    {data.source_type === 'sitemap' && (
                                        <p className="mt-1 text-xs text-neutral-400">{t('ai.sitemap_hint')}</p>
                                    )}
                                </div>
                            )}

                            {data.source_type !== 'faq' && data.source_type !== 'google_drive' && (
                                <div>
                                    <label className="block text-xs font-medium text-neutral-700 dark:text-neutral-300 mb-1">{t('ai.title_label')} <span className="text-neutral-400 font-normal">({t('common.optional')})</span></label>
                                    <input
                                        type="text"
                                        value={data.title}
                                        onChange={e => setData('title', e.target.value)}
                                        placeholder={t('ai.title_placeholder')}
                                        className="w-full rounded-lg border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 px-3 py-2 text-sm text-neutral-900 dark:text-neutral-100 placeholder-neutral-400 focus:outline-none focus:ring-2 focus:ring-brand-500 transition"
                                    />
                                </div>
                            )}

                            <div className="flex gap-2 pt-1 pb-2">
                                <button
                                    type="submit"
                                    disabled={processing || (data.source_type === 'google_drive' && (!googleDrive.connected || selectedDriveFiles.length === 0))}
                                    className="flex-1 rounded-lg bg-brand-600 py-2 text-sm font-medium text-white hover:bg-brand-700 disabled:opacity-60 transition"
                                >
                                    {processing ? t('ai.adding') : t('ai.add_and_index')}
                                </button>
                                <button
                                    type="button"
                                    onClick={() => setShowAdd(false)}
                                    className="rounded-lg border border-neutral-300 dark:border-neutral-600 px-4 py-2 text-sm text-neutral-700 dark:text-neutral-300 hover:bg-neutral-50 dark:hover:bg-neutral-800 transition"
                                >
                                    {t('common.cancel')}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {editingDocument && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm" role="dialog" aria-modal="true" aria-labelledby="kb-document-edit-title">
                    <form onSubmit={saveDocument} className="flex max-h-[92dvh] w-full max-w-6xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl dark:bg-neutral-900">
                        <div className="flex items-start justify-between border-b border-neutral-200 px-6 py-5 dark:border-neutral-800">
                            <div className="flex min-w-0 gap-3">
                                <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-600 dark:bg-brand-900/30 dark:text-brand-300"><FilePenLine className="h-5 w-5" /></span>
                                <div><h3 id="kb-document-edit-title" className="font-semibold text-neutral-900 dark:text-neutral-100">Ver e editar documento</h3><p className="mt-1 text-sm text-neutral-500 dark:text-neutral-400">Este é o conteúdo que as IAs consultam. Ao salvar, ele será reindexado.</p></div>
                            </div>
                            <button type="button" onClick={() => setEditingDocument(null)} className="flex h-10 w-10 items-center justify-center rounded-lg hover:bg-neutral-100 dark:hover:bg-neutral-800" aria-label="Fechar"><X className="h-5 w-5" /></button>
                        </div>
                        <div className="overflow-y-auto px-6 py-5">
                            <label className="block text-sm font-medium text-neutral-800 dark:text-neutral-200">Título<input value={editDocument.title} onChange={event => setEditDocument(current => ({ ...current, title: event.target.value }))} onBlur={() => !editDocument.title.trim() && setEditError('Informe um título para o documento.')} className="mt-1.5 min-h-11 w-full rounded-lg border border-neutral-300 px-3 text-neutral-900 focus:border-brand-500 focus:ring-brand-500 dark:border-neutral-700 dark:bg-neutral-950 dark:text-neutral-100" /></label>
                            {!editingDocument.external_provider && ['url', 'sitemap'].includes(editingDocument.source_type) && (
                                <label className="mt-4 block text-sm font-medium text-neutral-800 dark:text-neutral-200">Endereço da fonte<input type="url" value={editDocument.source_ref} onChange={event => setEditDocument(current => ({ ...current, source_ref: event.target.value }))} className="mt-1.5 min-h-11 w-full rounded-lg border border-neutral-300 px-3 text-neutral-900 focus:border-brand-500 focus:ring-brand-500 dark:border-neutral-700 dark:bg-neutral-950 dark:text-neutral-100" /></label>
                            )}
                            {editLoading ? (
                                <div className="mt-5 flex min-h-72 items-center justify-center rounded-xl border border-neutral-200 bg-neutral-50 dark:border-neutral-800 dark:bg-neutral-950/50" role="status"><LoaderCircle className="mr-2 h-5 w-5 animate-spin text-brand-600" /><span className="text-sm text-neutral-600 dark:text-neutral-300">Carregando conteúdo indexado…</span></div>
                            ) : (
                                <div className="mt-4">
                                    <div className="flex flex-wrap items-end justify-between gap-3">
                                        <div>
                                            <div className="flex items-center gap-2 text-sm font-medium text-neutral-800 dark:text-neutral-200">
                                                <span>Conteúdo usado pela IA</span>
                                                {editDocument.format === 'markdown' && <span className="inline-flex items-center gap-1 rounded-full bg-brand-50 px-2 py-1 text-xs font-semibold text-brand-700 dark:bg-brand-950/40 dark:text-brand-300"><FileCode2 className="h-3.5 w-3.5" aria-hidden="true" />Markdown</span>}
                                            </div>
                                            <p className="mt-1 text-xs font-normal text-neutral-500 dark:text-neutral-400">
                                                {editDocument.format === 'markdown'
                                                    ? 'Quebras de linha, títulos, listas, links e blocos de código são preservados.'
                                                    : 'Edite o texto que será consultado pelas IAs.'}
                                            </p>
                                        </div>
                                        <div className="flex items-center gap-3">
                                            <span className="text-xs font-normal tabular-nums text-neutral-400">{editDocument.content.length.toLocaleString('pt-BR')} caracteres</span>
                                            <div className="inline-flex rounded-xl border border-neutral-300 bg-neutral-50 p-1 dark:border-neutral-700 dark:bg-neutral-950" role="tablist" aria-label="Modo do documento">
                                                <button type="button" role="tab" aria-selected={editView === 'source'} onClick={() => setEditView('source')} className={`inline-flex min-h-10 items-center gap-2 rounded-lg px-3 text-sm font-medium transition focus:outline-none focus:ring-2 focus:ring-brand-500 ${editView === 'source' ? 'bg-white text-brand-700 shadow-sm dark:bg-neutral-800 dark:text-brand-300' : 'text-neutral-600 hover:text-neutral-900 dark:text-neutral-400 dark:hover:text-neutral-100'}`}><Pencil className="h-4 w-4" aria-hidden="true" />Editar</button>
                                                <button type="button" role="tab" aria-selected={editView === 'preview'} onClick={showDocumentPreview} className={`inline-flex min-h-10 items-center gap-2 rounded-lg px-3 text-sm font-medium transition focus:outline-none focus:ring-2 focus:ring-brand-500 ${editView === 'preview' ? 'bg-white text-brand-700 shadow-sm dark:bg-neutral-800 dark:text-brand-300' : 'text-neutral-600 hover:text-neutral-900 dark:text-neutral-400 dark:hover:text-neutral-100'}`}><Eye className="h-4 w-4" aria-hidden="true" />Visualizar</button>
                                            </div>
                                        </div>
                                    </div>

                                    {editView === 'source' ? (
                                        <div className="mt-2 overflow-hidden rounded-xl border border-neutral-300 bg-white shadow-sm focus-within:border-brand-500 focus-within:ring-2 focus-within:ring-brand-500/20 dark:border-neutral-700 dark:bg-neutral-950">
                                            {editDocument.format === 'markdown' && (
                                                <div className="flex flex-wrap items-center gap-1 border-b border-neutral-200 bg-neutral-50 p-2 dark:border-neutral-800 dark:bg-neutral-900" role="toolbar" aria-label="Formatação do documento Markdown">
                                                    <EditorButton label="Título principal" icon={Heading1} onClick={() => applyMarkdown('h1')} />
                                                    <EditorButton label="Título da seção" icon={Heading2} onClick={() => applyMarkdown('h2')} />
                                                    <EditorButton label="Subtítulo" icon={Heading3} onClick={() => applyMarkdown('h3')} />
                                                    <span className="mx-1 h-6 w-px bg-neutral-300 dark:bg-neutral-700" aria-hidden="true" />
                                                    <EditorButton label="Negrito" icon={Bold} onClick={() => applyMarkdown('bold')} />
                                                    <EditorButton label="Itálico" icon={Italic} onClick={() => applyMarkdown('italic')} />
                                                    <EditorButton label="Tachado" icon={Strikethrough} onClick={() => applyMarkdown('strike')} />
                                                    <span className="mx-1 h-6 w-px bg-neutral-300 dark:bg-neutral-700" aria-hidden="true" />
                                                    <EditorButton label="Lista com marcadores" icon={List} onClick={() => applyMarkdown('bullet')} />
                                                    <EditorButton label="Lista numerada" icon={ListOrdered} onClick={() => applyMarkdown('number')} />
                                                    <EditorButton label="Lista de tarefas" icon={ListChecks} onClick={() => applyMarkdown('check')} />
                                                    <EditorButton label="Citação" icon={Quote} onClick={() => applyMarkdown('quote')} />
                                                    <EditorButton label="Código" icon={Code2} onClick={() => applyMarkdown('code')} />
                                                    <span className="mx-1 h-6 w-px bg-neutral-300 dark:bg-neutral-700" aria-hidden="true" />
                                                    <EditorButton label="Inserir link" icon={Link2} onClick={() => applyMarkdown('link')} />
                                                    <EditorButton label="Inserir imagem por endereço" icon={Image} onClick={() => applyMarkdown('image')} />
                                                    <EditorButton label="Inserir tabela" icon={Table2} onClick={() => applyMarkdown('table')} />
                                                    <EditorButton label="Linha divisória" icon={Minus} onClick={() => applyMarkdown('rule')} />
                                                </div>
                                            )}
                                            <label className="block">
                                                <span className="sr-only">Conteúdo do documento</span>
                                                <textarea
                                                    ref={markdownEditorRef}
                                                    value={editDocument.content}
                                                    onChange={event => setEditDocument(current => ({ ...current, content: event.target.value }))}
                                                    onKeyDown={event => {
                                                        if (!(event.ctrlKey || event.metaKey)) return;

                                                        const key = event.key.toLowerCase();
                                                        if (key === 'b' || key === 'i') {
                                                            event.preventDefault();
                                                            applyMarkdown(key === 'b' ? 'bold' : 'italic');
                                                        }
                                                    }}
                                                    rows={18}
                                                    spellCheck
                                                    placeholder="O documento ainda não possui texto indexado."
                                                    className={`min-h-96 w-full resize-y whitespace-pre-wrap border-0 p-5 text-base leading-7 text-neutral-900 focus:ring-0 dark:bg-neutral-950 dark:text-neutral-100 ${editDocument.format === 'markdown' ? 'font-mono sm:text-sm' : ''}`}
                                                />
                                            </label>
                                            {editDocument.format === 'markdown' && <p className="border-t border-neutral-200 px-4 py-2 text-xs text-neutral-500 dark:border-neutral-800 dark:text-neutral-400">Selecione um texto e use a barra para formatar. Atalhos como Ctrl+B, Ctrl+I e Ctrl+Z continuam disponíveis no navegador.</p>}
                                        </div>
                                    ) : (
                                        <div role="tabpanel" aria-label="Visualização formatada do documento" className="mt-2 min-h-96 overflow-auto rounded-xl border border-neutral-300 bg-white p-6 dark:border-neutral-700 dark:bg-neutral-950">
                                            {previewLoading ? (
                                                <div className="flex min-h-72 items-center justify-center text-sm text-neutral-500" role="status"><LoaderCircle className="mr-2 h-5 w-5 animate-spin motion-reduce:animate-none text-brand-600" aria-hidden="true" />Formatando Markdown…</div>
                                            ) : editDocument.format === 'markdown' && markdownPreview ? (
                                                <article
                                                    className="mx-auto max-w-4xl break-words text-base leading-7 text-neutral-800 dark:text-neutral-200 [&_a]:font-medium [&_a]:text-brand-700 [&_a]:underline [&_a]:underline-offset-2 dark:[&_a]:text-brand-300 [&_blockquote]:my-4 [&_blockquote]:border-l-4 [&_blockquote]:border-brand-300 [&_blockquote]:pl-4 [&_blockquote]:italic [&_code]:rounded [&_code]:bg-neutral-100 [&_code]:px-1.5 [&_code]:py-0.5 [&_code]:font-mono dark:[&_code]:bg-neutral-800 [&_h1]:mb-4 [&_h1]:mt-2 [&_h1]:text-3xl [&_h1]:font-bold [&_h2]:mb-3 [&_h2]:mt-7 [&_h2]:text-2xl [&_h2]:font-bold [&_h3]:mb-2 [&_h3]:mt-6 [&_h3]:text-xl [&_h3]:font-semibold [&_hr]:my-7 [&_li]:my-1 [&_ol]:my-4 [&_ol]:list-decimal [&_ol]:pl-6 [&_p]:my-4 [&_pre]:my-4 [&_pre]:overflow-x-auto [&_pre]:rounded-xl [&_pre]:bg-neutral-950 [&_pre]:p-4 [&_pre]:text-neutral-100 [&_pre_code]:bg-transparent [&_pre_code]:p-0 [&_strong]:font-bold [&_table]:my-4 [&_table]:w-full [&_table]:border-collapse [&_td]:border [&_td]:border-neutral-300 [&_td]:p-2 dark:[&_td]:border-neutral-700 [&_th]:border [&_th]:border-neutral-300 [&_th]:bg-neutral-100 [&_th]:p-2 [&_th]:text-left dark:[&_th]:border-neutral-700 dark:[&_th]:bg-neutral-800 [&_ul]:my-4 [&_ul]:list-disc [&_ul]:pl-6"
                                                    dangerouslySetInnerHTML={{ __html: sanitizeHtml(markdownPreview) }}
                                                />
                                            ) : editDocument.format !== 'markdown' && editDocument.content ? (
                                                <div className="mx-auto max-w-4xl whitespace-pre-wrap text-base leading-7 text-neutral-800 dark:text-neutral-200">{editDocument.content}</div>
                                            ) : (
                                                <p className="flex min-h-72 items-center justify-center text-sm text-neutral-500">O documento ainda não possui conteúdo para visualizar.</p>
                                            )}
                                        </div>
                                    )}
                                </div>
                            )}
                            {(editingDocument.source_type === 'file' || editingDocument.external_provider) && (
                                <div className="mt-3 rounded-lg border border-blue-200 bg-blue-50 px-3 py-2.5 text-xs leading-5 text-blue-800 dark:border-blue-900/60 dark:bg-blue-950/30 dark:text-blue-200">
                                    O arquivo original não será alterado. Esta edição cria uma versão de texto própria para as IAs deste espaço de trabalho.
                                </div>
                            )}
                            {editError && <p className="mt-3 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700 dark:border-red-900/60 dark:bg-red-950/30 dark:text-red-200" role="alert">{editError}</p>}
                        </div>
                        <div className="flex flex-col-reverse gap-2 border-t border-neutral-200 px-6 py-4 sm:flex-row sm:items-center sm:justify-between dark:border-neutral-800">
                            <div>{editDocument.has_override && <button type="button" onClick={restoreDocumentSource} disabled={editSaving} className="inline-flex min-h-11 items-center gap-2 rounded-lg px-3 text-sm font-medium text-neutral-600 hover:bg-neutral-100 disabled:opacity-50 dark:text-neutral-300 dark:hover:bg-neutral-800"><RotateCcw className="h-4 w-4" /> Restaurar conteúdo original</button>}</div>
                            <div className="flex justify-end gap-2"><button type="button" onClick={() => setEditingDocument(null)} disabled={editSaving} className="min-h-11 rounded-lg border border-neutral-300 px-4 text-sm font-medium dark:border-neutral-700">Cancelar</button><button disabled={editLoading || editSaving || !editDocument.title.trim() || !editDocument.content.trim()} className="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg bg-brand-600 px-4 text-sm font-semibold text-white hover:bg-brand-700 disabled:cursor-not-allowed disabled:opacity-50">{editSaving && <LoaderCircle className="h-4 w-4 animate-spin" />}{editSaving ? 'Salvando…' : 'Salvar e reindexar'}</button></div>
                        </div>
                    </form>
                </div>
            )}
        </ClientLayout>
    );
}
