import { Head, Link, router } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import axios from 'axios';
import {
    CalendarClock, CheckSquare, FolderKanban, FolderPlus, Image, ListChecks,
    Maximize2, MoreHorizontal, Paperclip, Pencil, Plus, Trash2, UserRound, X,
} from 'lucide-react';
import { toast } from 'sonner';
import ClientLayout from '@/Layouts/ClientLayout';

const inputClass = 'w-full rounded-xl border border-neutral-300 bg-white px-3 py-2.5 text-sm text-neutral-900 outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/15 dark:border-neutral-700 dark:bg-neutral-800 dark:text-white';
const priorityLabel = { low: 'Baixa', normal: 'Normal', high: 'Alta', urgent: 'Urgente' };
const priorityClass = {
    low: 'bg-neutral-100 text-neutral-600',
    normal: 'bg-blue-50 text-blue-700',
    high: 'bg-amber-50 text-amber-700',
    urgent: 'bg-red-50 text-red-700',
};

function TaskModal({ task, boardUuid, columns, teamMembers, onClose, onSaved }) {
    const [form, setForm] = useState({
        column_id: task?.column_id ?? columns[0]?.id ?? '',
        title: task?.title ?? '',
        description: task?.description ?? '',
        assigned_user_id: task?.assigned_user_id ?? '',
        priority: task?.priority ?? 'normal',
        due_at: task?.due_at ? String(task.due_at).slice(0, 16) : '',
        checklist: task?.checklist?.map((item) => item.title) ?? [],
    });
    const [images, setImages] = useState([]);
    const [saving, setSaving] = useState(false);
    const [preview, setPreview] = useState(null);
    const localPreviews = useMemo(() => images.map((file) => ({ file, url: URL.createObjectURL(file) })), [images]);
    useEffect(() => () => localPreviews.forEach((item) => URL.revokeObjectURL(item.url)), [localPreviews]);
    useEffect(() => {
        if (!preview) return undefined;
        const closeOnEscape = (event) => event.key === 'Escape' && setPreview(null);
        window.addEventListener('keydown', closeOnEscape);
        return () => window.removeEventListener('keydown', closeOnEscape);
    }, [preview]);

    const addImages = (files) => {
        const accepted = [...files].filter((file) => file.type.startsWith('image/'));
        setImages((current) => [...current, ...accepted].slice(0, 10));
    };
    const handlePaste = (event) => {
        const clipboardImages = [...(event.clipboardData?.items ?? [])]
            .filter((item) => item.kind === 'file' && item.type.startsWith('image/'))
            .map((item) => item.getAsFile())
            .filter(Boolean);
        const imagesToAdd = clipboardImages.length > 0
            ? clipboardImages
            : [...(event.clipboardData?.files ?? [])].filter((file) => file.type.startsWith('image/'));
        if (imagesToAdd.length > 0) {
            event.preventDefault();
            addImages(imagesToAdd);
            toast.success(imagesToAdd.length === 1 ? 'Imagem colada na tarefa.' : `${imagesToAdd.length} imagens coladas na tarefa.`);
        }
    };
    const submit = (event) => {
        event.preventDefault();
        if (!form.title.trim() || saving) return;
        setSaving(true);
        const body = new FormData();
        body.append('board_uuid', boardUuid);
        Object.entries(form).forEach(([key, value]) => {
            if (key === 'checklist') value.forEach((item) => item.trim() && body.append('checklist[]', item.trim()));
            else if (value !== '' && value !== null) body.append(key, value);
        });
        images.forEach((file) => body.append('images[]', file));
        if (task?.uuid) body.append('_method', 'PUT');

        axios.post(task?.uuid ? route('client.crm.tasks.update', task.uuid) : route('client.crm.tasks.store'), body, {
            headers: { 'Content-Type': 'multipart/form-data', Accept: 'application/json' },
        }).then(({ data }) => {
            onSaved(data.task);
            toast.success(task?.uuid ? 'Tarefa atualizada.' : 'Tarefa criada.');
            onClose();
        }).catch(showError).finally(() => setSaving(false));
    };

    return (
        <div className="fixed inset-0 z-[100] flex items-center justify-center bg-neutral-950/50 p-4" onMouseDown={onClose}>
            <form onSubmit={submit} onPaste={handlePaste}
                className="max-h-[92vh] w-full max-w-2xl overflow-y-auto rounded-2xl bg-white p-5 shadow-2xl dark:bg-neutral-900"
                onMouseDown={(event) => event.stopPropagation()}>
                <div className="mb-5 flex items-center justify-between">
                    <h2 className="text-lg font-semibold">{task?.uuid ? 'Editar tarefa' : 'Nova tarefa'}</h2>
                    <button type="button" onClick={onClose} aria-label="Fechar tarefa" className="grid h-11 w-11 place-items-center rounded-xl hover:bg-neutral-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 dark:hover:bg-neutral-800"><X className="h-5 w-5" /></button>
                </div>
                <div className="grid gap-4 sm:grid-cols-2">
                    <label className="sm:col-span-2 text-xs font-medium">Título
                        <input className={`${inputClass} mt-1`} value={form.title} maxLength={180} required
                            onChange={(e) => setForm({ ...form, title: e.target.value })} />
                    </label>
                    <label className="text-xs font-medium">Coluna
                        <select className={`${inputClass} mt-1`} value={form.column_id} onChange={(e) => setForm({ ...form, column_id: e.target.value })}>
                            {columns.map((column) => <option key={column.id} value={column.id}>{column.name}</option>)}
                        </select>
                    </label>
                    <label className="text-xs font-medium">Responsável
                        <select className={`${inputClass} mt-1`} value={form.assigned_user_id} onChange={(e) => setForm({ ...form, assigned_user_id: e.target.value })}>
                            <option value="">Sem responsável</option>
                            {teamMembers.map((user) => <option key={user.id} value={user.id}>{user.name}</option>)}
                        </select>
                    </label>
                    <label className="text-xs font-medium">Prioridade
                        <select className={`${inputClass} mt-1`} value={form.priority} onChange={(e) => setForm({ ...form, priority: e.target.value })}>
                            {Object.entries(priorityLabel).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
                        </select>
                    </label>
                    <label className="text-xs font-medium">Prazo
                        <input type="datetime-local" className={`${inputClass} mt-1`} value={form.due_at} onChange={(e) => setForm({ ...form, due_at: e.target.value })} />
                    </label>
                    <label className="sm:col-span-2 text-xs font-medium">Descrição
                        <textarea className={`${inputClass} mt-1 min-h-24 resize-y`} value={form.description}
                            onChange={(e) => setForm({ ...form, description: e.target.value })} />
                    </label>
                </div>

                <div className="mt-5">
                    <div className="mb-2 flex items-center justify-between">
                        <span className="flex items-center gap-1.5 text-sm font-semibold"><ListChecks className="h-4 w-4" />Checklist</span>
                        <button type="button" onClick={() => setForm({ ...form, checklist: [...form.checklist, ''] })}
                            className="text-xs font-medium text-brand-600">+ Adicionar item</button>
                    </div>
                    <div className="space-y-2">
                        {form.checklist.map((item, index) => (
                            <div key={index} className="flex gap-2">
                                <input className={inputClass} value={item} placeholder="Descreva uma etapa…"
                                    onChange={(e) => setForm({ ...form, checklist: form.checklist.map((value, i) => i === index ? e.target.value : value) })} />
                                <button type="button" onClick={() => setForm({ ...form, checklist: form.checklist.filter((_, i) => i !== index) })}
                                    className="rounded-lg p-2 text-red-500 hover:bg-red-50"><Trash2 className="h-4 w-4" /></button>
                            </div>
                        ))}
                    </div>
                </div>

                <label className="mt-5 block cursor-pointer rounded-xl border-2 border-dashed border-neutral-300 p-4 text-center transition hover:border-brand-400 hover:bg-brand-50/40 focus-within:border-brand-500 dark:border-neutral-700 dark:hover:bg-brand-950/20">
                    <Image className="mx-auto mb-2 h-5 w-5 text-neutral-400" />
                    <span className="text-sm font-medium">Escolher imagens ou colar da área de transferência</span>
                    <span className="mt-1 block text-xs text-neutral-500">Use Ctrl+V em qualquer campo deste popup · PNG, JPG ou WebP — até 10 MB cada</span>
                    <input type="file" accept="image/*" multiple className="hidden" onChange={(e) => addImages(e.target.files ?? [])} />
                </label>
                {(task?.attachments?.length > 0 || images.length > 0) && (
                    <div className="mt-3 grid grid-cols-2 gap-2 sm:grid-cols-4">
                        {(task?.attachments ?? []).map((file, index) => <button type="button" key={`old-${index}`} onClick={() => setPreview({ url: file.url, name: file.filename || `Imagem ${index + 1}` })} className="group/image relative overflow-hidden rounded-lg focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500"><img src={file.url} alt={file.filename || `Imagem anexada ${index + 1}`} className="h-20 w-full object-cover transition duration-200 group-hover/image:scale-105" /><span className="absolute inset-0 grid place-items-center bg-black/0 text-white transition group-hover/image:bg-black/35"><Maximize2 className="h-5 w-5 opacity-0 transition group-hover/image:opacity-100" /></span></button>)}
                        {localPreviews.map(({ file, url }, index) => <button type="button" key={`${file.name}-${index}`} onClick={() => setPreview({ url, name: file.name })} className="group/image relative overflow-hidden rounded-lg focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500"><img src={url} alt={file.name || `Nova imagem ${index + 1}`} className="h-20 w-full object-cover transition duration-200 group-hover/image:scale-105" /><span className="absolute inset-0 grid place-items-center bg-black/0 text-white transition group-hover/image:bg-black/35"><Maximize2 className="h-5 w-5 opacity-0 transition group-hover/image:opacity-100" /></span></button>)}
                    </div>
                )}
                <div className="mt-6 flex justify-end gap-2">
                    <button type="button" onClick={onClose} className="rounded-xl border px-4 py-2 text-sm">Cancelar</button>
                    <button type="submit" disabled={saving} className="rounded-xl bg-brand-600 px-5 py-2 text-sm font-semibold text-white disabled:opacity-50">
                        {saving ? 'Salvando…' : 'Salvar tarefa'}
                    </button>
                </div>
                {preview && <div role="dialog" aria-modal="true" aria-label={`Visualização de ${preview.name}`} className="fixed inset-0 z-[130] flex items-center justify-center bg-black/85 p-3 sm:p-8" onMouseDown={(event) => { event.stopPropagation(); if (event.target === event.currentTarget) setPreview(null); }}><button type="button" onClick={() => setPreview(null)} aria-label="Fechar imagem ampliada" className="absolute right-4 top-4 grid h-11 w-11 place-items-center rounded-full bg-white/15 text-white transition hover:bg-white/25 focus:outline-none focus-visible:ring-2 focus-visible:ring-white"><X className="h-6 w-6" /></button><img src={preview.url} alt={preview.name} className="max-h-full max-w-full rounded-xl object-contain shadow-2xl" /></div>}
            </form>
        </div>
    );
}

function TaskCardView({ task, onEdit, onDelete, onDragStart, onToggle }) {
    const done = task.checklist?.filter((item) => item.completed).length ?? 0;
    const total = task.checklist?.length ?? 0;
    const contact = task.source_conversation?.contact;
    const contactName = contact ? ([contact.first_name, contact.last_name].filter(Boolean).join(' ') || contact.phone_e164) : null;
    return (
        <article draggable onDragStart={(event) => onDragStart(event, task)}
            className="group relative cursor-grab rounded-xl border border-neutral-200 bg-white p-3 shadow-sm transition hover:border-brand-300 hover:shadow-md active:scale-[0.995] dark:border-neutral-700 dark:bg-neutral-900">
            <button type="button" onClick={() => onEdit(task)} aria-label={`Abrir tarefa ${task.title}`}
                className="absolute inset-0 z-0 rounded-xl focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-2" />
            <div className="pointer-events-none relative z-10">
            <div className="flex items-start justify-between gap-2">
                <span className="text-left text-sm font-semibold text-neutral-900 group-hover:text-brand-600 dark:text-white dark:group-hover:text-brand-300">{task.title}</span>
                <button type="button" onClick={() => onEdit(task)} aria-label={`Editar tarefa ${task.title}`}
                    className="pointer-events-auto rounded p-1 text-neutral-400 hover:bg-neutral-100 dark:hover:bg-neutral-800"><MoreHorizontal className="h-4 w-4" /></button>
            </div>
            {task.description && <p className="mt-1 line-clamp-3 text-xs text-neutral-500">{task.description}</p>}
            {contactName && <p className="mt-2 text-[11px] font-medium text-brand-600">Conversa: {contactName}</p>}
            {total > 0 && (
                <div className="mt-3 space-y-1.5">
                    {task.checklist.slice(0, 4).map((item) => (
                        <label key={item.id} className="pointer-events-auto flex items-start gap-1.5 text-xs text-neutral-600">
                            <input type="checkbox" checked={item.completed} onChange={(e) => onToggle(task, item, e.target.checked)} />
                            <span className={item.completed ? 'line-through opacity-60' : ''}>{item.title}</span>
                        </label>
                    ))}
                    <div className="h-1.5 overflow-hidden rounded-full bg-neutral-100"><div className="h-full bg-emerald-500" style={{ width: `${Math.round(done / total * 100)}%` }} /></div>
                </div>
            )}
            <div className="mt-3 flex flex-wrap items-center gap-2 border-t border-neutral-100 pt-2 text-[11px] text-neutral-500">
                <span className={`rounded-full px-2 py-0.5 ${priorityClass[task.priority]}`}>{priorityLabel[task.priority]}</span>
                {task.due_at && <span className="flex items-center gap-1"><CalendarClock className="h-3 w-3" />{new Date(task.due_at).toLocaleDateString('pt-BR')}</span>}
                {(task.attachments?.length ?? 0) > 0 && <span className="flex items-center gap-1"><Paperclip className="h-3 w-3" />{task.attachments.length}</span>}
                {task.assigned_user?.name && <span className="ml-auto flex items-center gap-1"><UserRound className="h-3 w-3" />{task.assigned_user.name}</span>}
            </div>
            <button type="button" onClick={() => onDelete(task)} className="pointer-events-auto mt-2 hidden text-[11px] text-red-500 group-hover:block">Excluir tarefa</button>
            </div>
        </article>
    );
}

function TaskGroupModal({ group, onClose, onSaved }) {
    const [name, setName] = useState(group?.name ?? '');
    const [saving, setSaving] = useState(false);
    const submit = (event) => {
        event.preventDefault();
        if (!name.trim() || saving) return;
        setSaving(true);
        const request = group?.uuid
            ? axios.put(route('client.crm.tasks.boards.update', group.uuid), { name: name.trim() })
            : axios.post(route('client.crm.tasks.boards.store'), { name: name.trim() });
        request.then(({ data }) => {
            onSaved(data.board);
            toast.success(group?.uuid ? 'Grupo renomeado.' : 'Grupo criado com um Kanban próprio.');
            onClose();
        }).catch(showError).finally(() => setSaving(false));
    };

    return <div className="fixed inset-0 z-[110] flex items-center justify-center bg-neutral-950/55 p-4" onMouseDown={onClose}>
        <form onSubmit={submit} onMouseDown={(event) => event.stopPropagation()} className="w-full max-w-md rounded-2xl bg-white p-5 shadow-2xl dark:bg-neutral-900">
            <div className="flex items-start justify-between gap-3"><div><h2 className="text-lg font-semibold">{group?.uuid ? 'Renomear grupo' : 'Novo grupo de tarefas'}</h2><p className="mt-1 text-sm text-neutral-500">Cada grupo possui seu próprio Kanban, colunas e tarefas.</p></div><button type="button" onClick={onClose} aria-label="Fechar" className="grid h-11 w-11 shrink-0 place-items-center rounded-xl hover:bg-neutral-100 dark:hover:bg-neutral-800"><X className="h-5 w-5" /></button></div>
            <label className="mt-5 block text-sm font-medium">Nome do grupo<input autoFocus className={`${inputClass} mt-1`} value={name} onChange={(event) => setName(event.target.value)} maxLength={120} placeholder="Ex.: Marketing, Financeiro ou Projetos" required /></label>
            <div className="mt-6 flex justify-end gap-2"><button type="button" onClick={onClose} className="min-h-11 rounded-xl border px-4 text-sm">Cancelar</button><button type="submit" disabled={saving} className="min-h-11 rounded-xl bg-brand-600 px-5 text-sm font-semibold text-white disabled:opacity-50">{saving ? 'Salvando…' : group?.uuid ? 'Salvar nome' : 'Criar grupo'}</button></div>
        </form>
    </div>;
}

export default function TaskKanbanIndex({ board: initialBoard, boards: initialBoards = [], teamMembers = [] }) {
    const [board, setBoard] = useState(initialBoard);
    const [boards, setBoards] = useState(initialBoards);
    const [modal, setModal] = useState(null);
    const [groupModal, setGroupModal] = useState(null);
    const [dragOver, setDragOver] = useState(null);
    useEffect(() => setBoard(initialBoard), [initialBoard]);
    useEffect(() => setBoards(initialBoards), [initialBoards]);
    const columns = board?.columns ?? [];
    const taskCount = useMemo(() => columns.reduce((sum, column) => sum + (column.tasks?.length ?? 0), 0), [columns]);

    const saveLocal = (saved) => setBoard((current) => ({
        ...current,
        columns: current.columns.map((column) => {
            const without = (column.tasks ?? []).filter((task) => task.uuid !== saved.uuid);
            return Number(column.id) === Number(saved.column_id) ? { ...column, tasks: [...without, saved] } : { ...column, tasks: without };
        }),
    }));
    const move = (event, column) => {
        event.preventDefault();
        setDragOver(null);
        const uuid = event.dataTransfer.getData('text/plain');
        const task = columns.flatMap((item) => item.tasks ?? []).find((item) => item.uuid === uuid);
        if (!task || Number(task.column_id) === Number(column.id)) return;
        saveLocal({ ...task, column_id: column.id });
        axios.patch(route('client.crm.tasks.move', uuid), { column_id: column.id }).catch(() => router.reload());
    };
    const toggle = (task, item, completed) => {
        const updated = { ...task, checklist: task.checklist.map((current) => current.id === item.id ? { ...current, completed } : current) };
        saveLocal(updated);
        axios.patch(route('client.crm.tasks.checklist.toggle', { task: task.uuid, item: item.id }), { completed }).catch(() => router.reload());
    };
    const remove = (task) => {
        if (!confirm(`Excluir a tarefa “${task.title}”?`)) return;
        axios.delete(route('client.crm.tasks.destroy', task.uuid)).then(() => {
            setBoard((current) => ({ ...current, columns: current.columns.map((column) => ({ ...column, tasks: column.tasks.filter((item) => item.uuid !== task.uuid) })) }));
        }).catch(showError);
    };
    const addColumn = () => {
        const name = window.prompt('Nome da nova coluna:');
        if (!name?.trim()) return;
        axios.post(route('client.crm.tasks.columns.store'), { name: name.trim(), color: '#7c3aed', board_uuid: board.uuid })
            .then(({ data }) => setBoard((current) => ({ ...current, columns: [...current.columns, { ...data.column, tasks: [] }] })))
            .catch(showError);
    };
    const selectBoard = (uuid) => router.get(route('client.crm.tasks.index'), { board: uuid }, { preserveScroll: false, preserveState: false });
    const saveGroup = (saved) => {
        setBoards((current) => current.some((item) => item.uuid === saved.uuid)
            ? current.map((item) => item.uuid === saved.uuid ? { ...item, ...saved } : item)
            : [...current, saved]);
        if (!saved.is_default && saved.uuid !== board.uuid) selectBoard(saved.uuid);
        else if (saved.uuid === board.uuid) setBoard((current) => ({ ...current, name: saved.name }));
    };
    const deleteGroup = () => {
        if (board.is_default || !confirm(`Excluir o grupo “${board.name}” e todas as tarefas dele?`)) return;
        axios.delete(route('client.crm.tasks.boards.destroy', board.uuid)).then(() => {
            toast.success('Grupo excluído.');
            selectBoard(boards.find((item) => item.is_default)?.uuid || boards[0]?.uuid);
        }).catch(showError);
    };
    const editColumn = (column) => {
        const name = window.prompt('Nome da coluna:', column.name);
        if (!name?.trim()) return;
        axios.put(route('client.crm.tasks.columns.update', column.uuid), { name: name.trim(), color: column.color })
            .then(({ data }) => setBoard((current) => ({ ...current, columns: current.columns.map((item) => item.uuid === column.uuid ? { ...item, ...data.column } : item) })))
            .catch(showError);
    };

    return (
        <ClientLayout title="Kanban">
            <Head title="Kanban de tarefas" />
            {modal && <TaskModal task={modal === 'new' ? null : modal} boardUuid={board.uuid} columns={columns} teamMembers={teamMembers} onClose={() => setModal(null)} onSaved={saveLocal} />}
            {groupModal && <TaskGroupModal group={groupModal === 'new' ? null : groupModal} onClose={() => setGroupModal(null)} onSaved={saveGroup} />}
            <div className="flex min-h-0 flex-1 flex-col">
                <header className="border-b border-neutral-200 bg-white px-5 py-4 dark:border-neutral-800 dark:bg-neutral-950">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <div className="min-w-0">
                            <h1 className="flex items-center gap-2 text-xl font-semibold"><CheckSquare className="h-5 w-5 text-brand-600" />Kanban</h1>
                            <p className="mt-1 text-sm text-neutral-500">Organize tarefas criadas manualmente ou a partir das conversas.</p>
                        </div>
                        <div className="flex flex-wrap gap-2">
                            <button type="button" onClick={addColumn} className="rounded-xl border px-3 py-2 text-sm">+ Coluna</button>
                            <button type="button" onClick={() => setModal('new')} className="rounded-xl bg-brand-600 px-4 py-2 text-sm font-semibold text-white"><Plus className="mr-1 inline h-4 w-4" />Nova tarefa</button>
                        </div>
                    </div>
                    <section className="mt-4 flex flex-col gap-3 rounded-xl border border-neutral-200 bg-neutral-50 p-3 dark:border-neutral-800 dark:bg-neutral-900 sm:flex-row sm:items-center">
                        <div className="flex min-w-0 flex-1 items-center gap-2"><span className="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-brand-100 text-brand-700 dark:bg-brand-950/40 dark:text-brand-300"><FolderKanban className="h-5 w-5" /></span><label className="min-w-0 flex-1 text-xs font-medium text-neutral-500">Grupo de tarefas<select value={board.uuid} onChange={(event) => selectBoard(event.target.value)} className="mt-1 min-h-11 w-full rounded-xl border-neutral-300 bg-white text-sm font-semibold text-neutral-900 dark:border-neutral-700 dark:bg-neutral-800 dark:text-white">{boards.map((item) => <option key={item.uuid} value={item.uuid}>{item.name}{item.is_default ? ' · Principal' : ''}</option>)}</select></label></div>
                        <div className="flex gap-2 sm:self-end"><button type="button" onClick={() => setGroupModal('new')} className="inline-flex min-h-11 items-center gap-2 rounded-xl border border-brand-200 bg-white px-3 text-sm font-semibold text-brand-700 hover:bg-brand-50 dark:border-brand-900 dark:bg-neutral-800 dark:text-brand-300"><FolderPlus className="h-4 w-4" />Novo grupo</button><button type="button" onClick={() => setGroupModal(board)} aria-label="Renomear grupo" className="grid h-11 w-11 place-items-center rounded-xl border bg-white text-neutral-600 hover:bg-neutral-100 dark:bg-neutral-800"><Pencil className="h-4 w-4" /></button>{!board.is_default && <button type="button" onClick={deleteGroup} aria-label="Excluir grupo" className="grid h-11 w-11 place-items-center rounded-xl border border-red-200 bg-white text-red-600 hover:bg-red-50 dark:bg-neutral-800"><Trash2 className="h-4 w-4" /></button>}</div>
                    </section>
                    <nav className="mt-4 flex gap-1">
                        <Link href={route('client.crm.pipelines.index')} className="rounded-lg px-3 py-1.5 text-sm text-neutral-500 hover:bg-neutral-100">Negócios</Link>
                        <span className="rounded-lg bg-brand-50 px-3 py-1.5 text-sm font-semibold text-brand-700">Tarefas ({taskCount})</span>
                    </nav>
                </header>
                <div className="flex flex-1 gap-4 overflow-x-auto p-5">
                    {columns.map((column) => (
                        <section key={column.uuid} onDragOver={(e) => { e.preventDefault(); setDragOver(column.id); }} onDrop={(e) => move(e, column)}
                            className={`flex w-80 shrink-0 flex-col rounded-2xl border bg-neutral-50 dark:bg-neutral-950 ${dragOver === column.id ? 'border-brand-500' : 'border-neutral-200 dark:border-neutral-800'}`}>
                            <div className="flex items-center justify-between border-b px-3 py-3">
                                <button type="button" onClick={() => editColumn(column)} className="flex items-center gap-2 text-sm font-semibold">
                                    <span className="h-2.5 w-2.5 rounded-full" style={{ backgroundColor: column.color }} />{column.name}
                                </button>
                                <span className="rounded-full bg-white px-2 py-0.5 text-xs text-neutral-500">{column.tasks?.length ?? 0}</span>
                            </div>
                            <div className="flex-1 space-y-3 overflow-y-auto p-3">
                                {(column.tasks ?? []).map((task) => <TaskCardView key={task.uuid} task={task} onEdit={setModal} onDelete={remove}
                                    onToggle={toggle} onDragStart={(event, item) => event.dataTransfer.setData('text/plain', item.uuid)} />)}
                            </div>
                        </section>
                    ))}
                </div>
            </div>
        </ClientLayout>
    );
}

function showError(error) {
    const errors = error?.response?.data?.errors;
    toast.error(errors ? Object.values(errors).flat()[0] : (error?.response?.data?.message || 'Não foi possível concluir a operação.'));
}
