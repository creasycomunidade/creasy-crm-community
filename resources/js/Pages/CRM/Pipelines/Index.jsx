import { Head, Link, router } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState } from 'react';
import axios from 'axios';
import {
    BriefcaseBusiness, ChevronDown, CircleDollarSign, Edit3,
    GripVertical, MoreHorizontal, Plus, Trash2, UserRound, X,
} from 'lucide-react';
import { toast } from 'sonner';
import ClientLayout from '@/Layouts/ClientLayout';

const DEFAULT_COLOR = '#7c3aed';

function money(cents, currency = 'BRL') {
    return new Intl.NumberFormat('pt-BR', {
        style: 'currency',
        currency: currency || 'BRL',
    }).format((Number(cents) || 0) / 100);
}

function Modal({ title, children, onClose, wide = false }) {
    return (
        <div className="fixed inset-0 z-[100] flex items-center justify-center bg-neutral-950/45 p-4 backdrop-blur-sm" onMouseDown={onClose}>
            <div
                className={`w-full ${wide ? 'max-w-xl' : 'max-w-md'} max-h-[90vh] overflow-y-auto rounded-2xl border border-neutral-200 bg-white p-5 shadow-2xl dark:border-neutral-700 dark:bg-neutral-900`}
                onMouseDown={(event) => event.stopPropagation()}
            >
                <div className="mb-5 flex items-center justify-between gap-3">
                    <h3 className="text-lg font-semibold text-neutral-900 dark:text-white">{title}</h3>
                    <button type="button" onClick={onClose} className="rounded-lg p-1.5 text-neutral-400 hover:bg-neutral-100 dark:hover:bg-neutral-800">
                        <X className="h-4 w-4" />
                    </button>
                </div>
                {children}
            </div>
        </div>
    );
}

function Field({ label, children }) {
    return (
        <label className="block">
            <span className="mb-1.5 block text-xs font-medium text-neutral-600 dark:text-neutral-300">{label}</span>
            {children}
        </label>
    );
}

const inputClass = 'w-full rounded-xl border border-neutral-300 bg-white px-3 py-2.5 text-sm text-neutral-900 outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-500/15 dark:border-neutral-700 dark:bg-neutral-800 dark:text-white';

function ColorField({ value, onChange }) {
    return (
        <div className="flex items-center gap-2">
            <input type="color" value={value} onChange={(event) => onChange(event.target.value)} className="h-10 w-12 cursor-pointer rounded-lg border border-neutral-300 bg-white p-1 dark:border-neutral-700 dark:bg-neutral-800" />
            <input value={value} onChange={(event) => onChange(event.target.value)} className={inputClass} maxLength={7} />
        </div>
    );
}

function ContactAvatar({ contact }) {
    const name = contact?.full_name || contact?.phone_e164 || contact?.email || '?';
    return contact?.avatar_url ? (
        <img src={contact.avatar_url} alt="" className="h-9 w-9 rounded-full object-cover" />
    ) : (
        <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-brand-100 text-xs font-bold text-brand-700 dark:bg-brand-900/40 dark:text-brand-200">
            {name.slice(0, 2).toUpperCase()}
        </span>
    );
}

function DealCard({ deal, onEdit, onDelete, onDragStart }) {
    const contact = deal.contact || {};
    return (
        <article
            draggable
            onDragStart={(event) => onDragStart(event, deal)}
            className="group cursor-grab rounded-xl border border-neutral-200 bg-white p-3 shadow-sm transition hover:-translate-y-0.5 hover:border-brand-300 hover:shadow-md active:cursor-grabbing dark:border-neutral-700 dark:bg-neutral-900 dark:hover:border-brand-700"
        >
            <div className="mb-2 flex items-start justify-between gap-2">
                <div className="flex min-w-0 items-center gap-2">
                    <ContactAvatar contact={contact} />
                    <div className="min-w-0">
                        <button type="button" onClick={() => onEdit(deal)} className="block max-w-full truncate text-left text-sm font-semibold text-neutral-900 hover:text-brand-600 hover:underline dark:text-white dark:hover:text-brand-300">{deal.title}</button>
                        <p className="truncate text-[11px] text-neutral-500">{contact.full_name || contact.phone_e164 || contact.email}</p>
                    </div>
                </div>
                <GripVertical className="h-4 w-4 shrink-0 text-neutral-300 group-hover:text-neutral-500" />
            </div>

            {Number(deal.value_cents) > 0 && (
                <div className="mb-2 flex items-center gap-1 text-xs font-semibold text-emerald-600 dark:text-emerald-400">
                    <CircleDollarSign className="h-3.5 w-3.5" />
                    {money(deal.value_cents, deal.currency)}
                </div>
            )}

            <div className="flex items-center justify-between border-t border-neutral-100 pt-2 dark:border-neutral-800">
                <span className="flex min-w-0 items-center gap-1 text-[11px] text-neutral-500">
                    <UserRound className="h-3 w-3" />
                    <span className="truncate">{deal.assigned_user?.name || 'Sem responsável'}</span>
                </span>
                <span className="flex items-center gap-0.5 opacity-0 transition group-hover:opacity-100">
                    <button type="button" onClick={() => onEdit(deal)} title="Editar" className="rounded p-1 text-neutral-400 hover:bg-neutral-100 hover:text-brand-600 dark:hover:bg-neutral-800">
                        <Edit3 className="h-3.5 w-3.5" />
                    </button>
                    <button type="button" onClick={() => onDelete(deal)} title="Remover" className="rounded p-1 text-neutral-400 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-950/30">
                        <Trash2 className="h-3.5 w-3.5" />
                    </button>
                </span>
            </div>
        </article>
    );
}

export default function PipelineIndex({
    pipelines: initialPipelines = [],
    pipeline: initialPipeline,
    contacts = [],
    teamMembers = [],
    currency = 'BRL',
    prefillContactId = null,
    prefillAlreadyAdded = false,
}) {
    const [pipelines, setPipelines] = useState(initialPipelines);
    const [board, setBoard] = useState(initialPipeline);
    const [showPipelineMenu, setShowPipelineMenu] = useState(false);
    const [pipelineModal, setPipelineModal] = useState(null);
    const [stageModal, setStageModal] = useState(null);
    const [dealModal, setDealModal] = useState(null);
    const [saving, setSaving] = useState(false);
    const [dragOverStage, setDragOverStage] = useState(null);
    const prefillConsumed = useRef(false);

    useEffect(() => {
        // Inertia reuses this page component while switching pipelines.
        // eslint-disable-next-line react-hooks/set-state-in-effect
        setPipelines(initialPipelines);
        setBoard(initialPipeline);
    }, [initialPipelines, initialPipeline]);

    useEffect(() => {
        if (!prefillContactId || prefillConsumed.current || !initialPipeline?.stages?.length) return;
        prefillConsumed.current = true;
        if (prefillAlreadyAdded) {
            toast.info('Este contato já está neste Kanban.');
            return;
        }

        const contact = contacts.find((item) => Number(item.id) === Number(prefillContactId));
        // eslint-disable-next-line react-hooks/set-state-in-effect
        setDealModal({
            stage_id: initialPipeline.stages[0].id,
            contact_id: prefillContactId,
            title: contact?.full_name || contact?.phone_e164 || contact?.email || '',
            value: '',
            assigned_user_id: '',
            notes: '',
        });
    }, [contacts, initialPipeline, prefillAlreadyAdded, prefillContactId]);

    const existingContactIds = useMemo(
        () => new Set((board?.stages || []).flatMap((stage) => stage.deals || []).map((deal) => Number(deal.contact_id))),
        [board],
    );

    const boardTotal = useMemo(
        () => (board?.stages || []).flatMap((stage) => stage.deals || []).reduce((total, deal) => total + Number(deal.value_cents || 0), 0),
        [board],
    );

    const visitPipeline = (uuid) => {
        setShowPipelineMenu(false);
        router.get(route('client.crm.pipelines.index'), { pipeline: uuid }, { preserveState: false });
    };

    const savePipeline = (event) => {
        event.preventDefault();
        if (saving) return;
        setSaving(true);
        const data = { name: pipelineModal.name.trim(), color: pipelineModal.color };

        if (pipelineModal.uuid) {
            axios.put(route('client.crm.pipelines.update', pipelineModal.uuid), data)
                .then(({ data: response }) => {
                    setBoard((current) => ({ ...current, ...response.pipeline }));
                    setPipelines((items) => items.map((item) => item.uuid === response.pipeline.uuid ? response.pipeline : item));
                    setPipelineModal(null);
                    toast.success('Pipeline atualizado.');
                })
                .catch(showError)
                .finally(() => setSaving(false));
            return;
        }

        router.post(route('client.crm.pipelines.store'), data, {
            onError: (errors) => toast.error(Object.values(errors)[0] || 'Não foi possível criar o pipeline.'),
            onFinish: () => setSaving(false),
        });
    };

    const removePipeline = () => {
        if (!confirm(`Remover o pipeline “${board.name}” e todos os cartões dele?`)) return;
        router.delete(route('client.crm.pipelines.destroy', board.uuid), {
            onError: (errors) => toast.error(Object.values(errors)[0] || 'Não foi possível remover o pipeline.'),
        });
    };

    const saveStage = (event) => {
        event.preventDefault();
        if (saving) return;
        setSaving(true);
        const payload = { name: stageModal.name.trim(), color: stageModal.color, conversion_event: stageModal.conversion_event || null };
        const request = stageModal.uuid
            ? axios.put(route('client.crm.pipelines.stages.update', stageModal.uuid), payload)
            : axios.post(route('client.crm.pipelines.stages.store', board.uuid), payload);

        request.then(({ data }) => {
            setBoard((current) => ({
                ...current,
                stages: stageModal.uuid
                    ? current.stages.map((stage) => stage.uuid === data.stage.uuid ? { ...stage, ...data.stage } : stage)
                    : [...current.stages, { ...data.stage, deals: [] }],
            }));
            setStageModal(null);
            toast.success(stageModal.uuid ? 'Coluna atualizada.' : 'Coluna criada.');
        }).catch(showError).finally(() => setSaving(false));
    };

    const removeStage = (stage) => {
        const count = stage.deals?.length || 0;
        const warning = count > 0
            ? `Esta coluna possui ${count} cartão(ões). Eles serão movidos para outra coluna. Continuar?`
            : `Remover a coluna “${stage.name}”?`;
        if (!confirm(warning)) return;
        axios.delete(route('client.crm.pipelines.stages.destroy', stage.uuid))
            .then(() => router.reload({ only: ['pipeline', 'pipelines'] }))
            .catch(showError);
    };

    const saveDeal = (event) => {
        event.preventDefault();
        if (saving) return;
        setSaving(true);
        const payload = {
            stage_id: Number(dealModal.stage_id),
            contact_id: Number(dealModal.contact_id),
            title: dealModal.title,
            value: String(dealModal.value || '0').replace(',', '.'),
            assigned_user_id: dealModal.assigned_user_id ? Number(dealModal.assigned_user_id) : null,
            notes: dealModal.notes,
        };
        const request = dealModal.uuid
            ? axios.put(route('client.crm.pipelines.deals.update', dealModal.uuid), payload)
            : axios.post(route('client.crm.pipelines.deals.store', board.uuid), payload);

        request.then(({ data }) => {
            const saved = data.deal;
            setBoard((current) => ({
                ...current,
                stages: current.stages.map((stage) => {
                    if (dealModal.uuid) {
                        return { ...stage, deals: stage.deals.map((deal) => deal.uuid === saved.uuid ? saved : deal) };
                    }
                    return Number(stage.id) === Number(saved.stage_id)
                        ? { ...stage, deals: [...stage.deals, saved] }
                        : stage;
                }),
            }));
            setDealModal(null);
            toast.success(dealModal.uuid ? 'Oportunidade atualizada.' : 'Lead adicionado ao pipeline.');
        }).catch(showError).finally(() => setSaving(false));
    };

    const editDeal = (deal) => setDealModal({
        ...deal,
        value: ((Number(deal.value_cents) || 0) / 100).toFixed(2).replace('.', ','),
        assigned_user_id: deal.assigned_user_id || '',
    });

    const removeDeal = (deal) => {
        if (!confirm(`Remover “${deal.title}” deste pipeline?`)) return;
        axios.delete(route('client.crm.pipelines.deals.destroy', deal.uuid))
            .then(() => {
                setBoard((current) => ({
                    ...current,
                    stages: current.stages.map((stage) => ({ ...stage, deals: stage.deals.filter((item) => item.uuid !== deal.uuid) })),
                }));
                toast.success('Cartão removido.');
            })
            .catch(showError);
    };

    const startDrag = (event, deal) => {
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/plain', deal.uuid);
    };

    const dropDeal = (event, targetStage) => {
        event.preventDefault();
        setDragOverStage(null);
        const uuid = event.dataTransfer.getData('text/plain');
        let movingDeal = null;
        let originStageId = null;
        board.stages.forEach((stage) => {
            const found = stage.deals.find((deal) => deal.uuid === uuid);
            if (found) {
                movingDeal = found;
                originStageId = stage.id;
            }
        });
        if (!movingDeal || Number(originStageId) === Number(targetStage.id)) return;

        setBoard((current) => ({
            ...current,
            stages: current.stages.map((stage) => {
                if (Number(stage.id) === Number(originStageId)) {
                    return { ...stage, deals: stage.deals.filter((deal) => deal.uuid !== uuid) };
                }
                if (Number(stage.id) === Number(targetStage.id)) {
                    return { ...stage, deals: [...stage.deals, { ...movingDeal, stage_id: targetStage.id }] };
                }
                return stage;
            }),
        }));

        axios.patch(route('client.crm.pipelines.deals.move', uuid), { stage_id: targetStage.id })
            .then(() => toast.success(`Movido para ${targetStage.name}.`))
            .catch((error) => {
                showError(error);
                router.reload({ only: ['pipeline'] });
            });
    };

    const newDeal = (stage) => setDealModal({
        stage_id: stage.id,
        contact_id: '',
        title: '',
        value: '',
        assigned_user_id: '',
        notes: '',
    });

    if (!board) {
        return <ClientLayout title="Pipeline"><div className="p-8 text-sm text-neutral-500">Preparando o pipeline…</div></ClientLayout>;
    }

    return (
        <ClientLayout title="Kanban">
            <Head title="Kanban" />
            <div className="flex min-h-0 flex-1 flex-col">
                <header className="border-b border-neutral-200 bg-white px-5 py-4 dark:border-neutral-800 dark:bg-neutral-950">
                    <div className="flex flex-wrap items-center justify-between gap-4">
                        <div>
                            <div className="flex items-center gap-2">
                                <BriefcaseBusiness className="h-5 w-5 text-brand-600" />
                                <h1 className="text-xl font-semibold text-neutral-900 dark:text-white">Kanban</h1>
                            </div>
                            <p className="mt-1 text-sm text-neutral-500">Organize leads e acompanhe cada etapa do atendimento e da venda.</p>
                        </div>
                        <button
                            type="button"
                            onClick={() => setPipelineModal({ name: '', color: DEFAULT_COLOR })}
                            className="inline-flex items-center gap-2 rounded-xl bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-brand-700"
                        >
                            <Plus className="h-4 w-4" /> Novo pipeline
                        </button>
                    </div>
                    <nav className="mt-4 flex gap-1">
                        <span className="rounded-lg bg-brand-50 px-3 py-1.5 text-sm font-semibold text-brand-700">Negócios</span>
                        <Link href={route('client.crm.tasks.index')} className="rounded-lg px-3 py-1.5 text-sm text-neutral-500 hover:bg-neutral-100 dark:hover:bg-neutral-800">Tarefas</Link>
                    </nav>

                    <div className="mt-4 flex flex-wrap items-center justify-between gap-3">
                        <div className="relative">
                            <button type="button" onClick={() => setShowPipelineMenu((value) => !value)} className="flex min-w-56 items-center justify-between gap-3 rounded-xl border border-neutral-300 bg-white px-3 py-2 text-sm font-medium dark:border-neutral-700 dark:bg-neutral-900">
                                <span className="flex min-w-0 items-center gap-2">
                                    <span className="h-2.5 w-2.5 shrink-0 rounded-full" style={{ backgroundColor: board.color }} />
                                    <span className="truncate">{board.name}</span>
                                </span>
                                <ChevronDown className="h-4 w-4 text-neutral-400" />
                            </button>
                            {showPipelineMenu && (
                                <div className="absolute left-0 top-full z-40 mt-2 w-72 rounded-xl border border-neutral-200 bg-white p-1.5 shadow-xl dark:border-neutral-700 dark:bg-neutral-900">
                                    {pipelines.map((item) => (
                                        <button key={item.uuid} type="button" onClick={() => visitPipeline(item.uuid)} className={`flex w-full items-center justify-between rounded-lg px-3 py-2 text-left text-sm ${item.uuid === board.uuid ? 'bg-brand-50 text-brand-700 dark:bg-brand-950/40 dark:text-brand-300' : 'hover:bg-neutral-50 dark:hover:bg-neutral-800'}`}>
                                            <span className="flex min-w-0 items-center gap-2">
                                                <span className="h-2.5 w-2.5 shrink-0 rounded-full" style={{ backgroundColor: item.color }} />
                                                <span className="truncate">{item.name}</span>
                                            </span>
                                            <span className="text-xs text-neutral-400">{item.deals_count || 0}</span>
                                        </button>
                                    ))}
                                </div>
                            )}
                        </div>

                        <div className="flex flex-wrap items-center gap-2">
                            <span className="rounded-xl bg-neutral-100 px-3 py-2 text-xs text-neutral-600 dark:bg-neutral-900 dark:text-neutral-300">
                                {(board.stages || []).reduce((total, stage) => total + stage.deals.length, 0)} oportunidades
                            </span>
                            <span className="rounded-xl bg-emerald-50 px-3 py-2 text-xs font-semibold text-emerald-700 dark:bg-emerald-950/30 dark:text-emerald-300">
                                {money(boardTotal, currency)}
                            </span>
                            <button type="button" onClick={() => setPipelineModal({ ...board })} className="rounded-xl border border-neutral-300 p-2 text-neutral-500 hover:text-brand-600 dark:border-neutral-700">
                                <Edit3 className="h-4 w-4" />
                            </button>
                            <button type="button" onClick={removePipeline} className="rounded-xl border border-neutral-300 p-2 text-neutral-500 hover:border-red-300 hover:text-red-600 dark:border-neutral-700">
                                <Trash2 className="h-4 w-4" />
                            </button>
                        </div>
                    </div>
                </header>

                <div className="flex-1 overflow-x-auto bg-neutral-50/70 p-4 dark:bg-neutral-950">
                    <div className="flex min-h-[calc(100vh-250px)] items-start gap-4">
                        {board.stages.map((stage) => {
                            const stageTotal = stage.deals.reduce((total, deal) => total + Number(deal.value_cents || 0), 0);
                            return (
                                <section
                                    key={stage.uuid}
                                    onDragOver={(event) => { event.preventDefault(); setDragOverStage(stage.uuid); }}
                                    onDragLeave={(event) => { if (!event.currentTarget.contains(event.relatedTarget)) setDragOverStage(null); }}
                                    onDrop={(event) => dropDeal(event, stage)}
                                    className={`w-[310px] shrink-0 rounded-2xl border bg-neutral-100/80 p-2.5 transition dark:bg-neutral-900/70 ${dragOverStage === stage.uuid ? 'border-brand-400 ring-2 ring-brand-400/20' : 'border-neutral-200 dark:border-neutral-800'}`}
                                >
                                    <div className="mb-3 px-1">
                                        <div className="flex items-center justify-between gap-2">
                                            <div className="flex min-w-0 items-center gap-2">
                                                <span className="h-3 w-3 shrink-0 rounded-full shadow-sm" style={{ backgroundColor: stage.color }} />
                                                <h2 className="truncate text-sm font-semibold text-neutral-800 dark:text-neutral-100">{stage.name}</h2>
                                                <span className="rounded-full bg-white px-2 py-0.5 text-[10px] font-semibold text-neutral-500 shadow-sm dark:bg-neutral-800">{stage.deals.length}</span>
                                            </div>
                                            <div className="flex items-center">
                                                <button type="button" onClick={() => setStageModal({ ...stage })} className="rounded p-1 text-neutral-400 hover:bg-white hover:text-brand-600 dark:hover:bg-neutral-800">
                                                    <MoreHorizontal className="h-4 w-4" />
                                                </button>
                                                <button type="button" onClick={() => removeStage(stage)} className="rounded p-1 text-neutral-400 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-950/30">
                                                    <Trash2 className="h-3.5 w-3.5" />
                                                </button>
                                            </div>
                                        </div>
                                        <p className="mt-1.5 text-[11px] font-medium text-neutral-500">{money(stageTotal, currency)}</p>
                                    </div>

                                    <div className="space-y-2">
                                        {stage.deals.map((deal) => (
                                            <DealCard key={deal.uuid} deal={deal} onEdit={editDeal} onDelete={removeDeal} onDragStart={startDrag} />
                                        ))}
                                        {stage.deals.length === 0 && (
                                            <div className="rounded-xl border border-dashed border-neutral-300 px-3 py-7 text-center text-xs text-neutral-400 dark:border-neutral-700">
                                                Arraste um cartão para cá
                                            </div>
                                        )}
                                    </div>

                                    <button type="button" onClick={() => newDeal(stage)} className="mt-2 flex w-full items-center justify-center gap-1.5 rounded-xl px-3 py-2 text-xs font-medium text-neutral-500 transition hover:bg-white hover:text-brand-600 dark:hover:bg-neutral-800">
                                        <Plus className="h-3.5 w-3.5" /> Adicionar lead
                                    </button>
                                </section>
                            );
                        })}

                        <button
                            type="button"
                            onClick={() => setStageModal({ name: '', color: '#8b5cf6' })}
                            className="flex w-64 shrink-0 items-center justify-center gap-2 rounded-2xl border border-dashed border-neutral-300 px-4 py-5 text-sm font-medium text-neutral-500 transition hover:border-brand-400 hover:bg-brand-50 hover:text-brand-700 dark:border-neutral-700 dark:hover:bg-brand-950/20"
                        >
                            <Plus className="h-4 w-4" /> Nova coluna
                        </button>
                    </div>
                </div>
            </div>

            {pipelineModal && (
                <Modal title={pipelineModal.uuid ? 'Editar pipeline' : 'Novo pipeline'} onClose={() => setPipelineModal(null)}>
                    <form onSubmit={savePipeline} className="space-y-4">
                        <Field label="Nome do pipeline">
                            <input autoFocus required value={pipelineModal.name} onChange={(event) => setPipelineModal({ ...pipelineModal, name: event.target.value })} className={inputClass} placeholder="Ex.: Vendas, Pós-venda, Imóveis" />
                        </Field>
                        <Field label="Cor de identificação">
                            <ColorField value={pipelineModal.color} onChange={(color) => setPipelineModal({ ...pipelineModal, color })} />
                        </Field>
                        <button disabled={saving} className="w-full rounded-xl bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-700 disabled:opacity-50">
                            {saving ? 'Salvando…' : 'Salvar pipeline'}
                        </button>
                    </form>
                </Modal>
            )}

            {stageModal && (
                <Modal title={stageModal.uuid ? 'Editar coluna' : 'Nova coluna'} onClose={() => setStageModal(null)}>
                    <form onSubmit={saveStage} className="space-y-4">
                        <Field label="Nome da coluna">
                            <input autoFocus required value={stageModal.name} onChange={(event) => setStageModal({ ...stageModal, name: event.target.value })} className={inputClass} placeholder="Ex.: Qualificação" />
                        </Field>
                        <Field label="Cor">
                            <ColorField value={stageModal.color} onChange={(color) => setStageModal({ ...stageModal, color })} />
                        </Field>
                        <Field label="Enviar conversão aos anúncios ao entrar nesta coluna">
                            <select value={stageModal.conversion_event || ''} onChange={(event) => setStageModal({ ...stageModal, conversion_event: event.target.value || null })} className={inputClass}>
                                <option value="">Não enviar</option>
                                <option value="Lead">Lead</option>
                                <option value="QualifiedLead">Lead qualificado</option>
                                <option value="Purchase">Venda/Conversão</option>
                            </select>
                        </Field>
                        <button disabled={saving} className="w-full rounded-xl bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-700 disabled:opacity-50">
                            {saving ? 'Salvando…' : 'Salvar coluna'}
                        </button>
                    </form>
                </Modal>
            )}

            {dealModal && (
                <Modal title={dealModal.uuid ? 'Editar oportunidade' : 'Adicionar lead'} onClose={() => setDealModal(null)} wide>
                    <form onSubmit={saveDeal} className="space-y-4">
                        {!dealModal.uuid && (
                            <Field label="Contato">
                                <select required value={dealModal.contact_id} onChange={(event) => {
                                    const selected = contacts.find((contact) => Number(contact.id) === Number(event.target.value));
                                    setDealModal({ ...dealModal, contact_id: event.target.value, title: dealModal.title || selected?.full_name || selected?.phone_e164 || selected?.email || '' });
                                }} className={inputClass}>
                                    <option value="">Selecione um contato</option>
                                    {contacts.map((contact) => (
                                        <option key={contact.id} value={contact.id} disabled={existingContactIds.has(Number(contact.id))}>
                                            {contact.full_name || contact.phone_e164 || contact.email || `Contato ${contact.id}`}{existingContactIds.has(Number(contact.id)) ? ' — já está no pipeline' : ''}
                                        </option>
                                    ))}
                                </select>
                            </Field>
                        )}
                        <Field label="Título da oportunidade">
                            <input required value={dealModal.title} onChange={(event) => setDealModal({ ...dealModal, title: event.target.value })} className={inputClass} placeholder="Ex.: Venda de plano empresarial" />
                        </Field>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <Field label="Valor estimado (R$)">
                                <input inputMode="decimal" value={dealModal.value} onChange={(event) => setDealModal({ ...dealModal, value: event.target.value })} className={inputClass} placeholder="0,00" />
                            </Field>
                            <Field label="Responsável">
                                <select value={dealModal.assigned_user_id} onChange={(event) => setDealModal({ ...dealModal, assigned_user_id: event.target.value })} className={inputClass}>
                                    <option value="">Sem responsável</option>
                                    {teamMembers.map((member) => <option key={member.id} value={member.id}>{member.name}</option>)}
                                </select>
                            </Field>
                        </div>
                        <Field label="Observações">
                            <textarea rows={4} value={dealModal.notes || ''} onChange={(event) => setDealModal({ ...dealModal, notes: event.target.value })} className={`${inputClass} resize-none`} placeholder="Contexto, próximos passos, necessidades do cliente…" />
                        </Field>
                        <button disabled={saving} className="w-full rounded-xl bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-700 disabled:opacity-50">
                            {saving ? 'Salvando…' : dealModal.uuid ? 'Salvar alterações' : 'Adicionar ao pipeline'}
                        </button>
                    </form>
                </Modal>
            )}
        </ClientLayout>
    );
}

function showError(error) {
    const errors = error?.response?.data?.errors;
    const message = errors ? Object.values(errors).flat()[0] : error?.response?.data?.message;
    toast.error(message || 'Não foi possível concluir a operação.');
}
