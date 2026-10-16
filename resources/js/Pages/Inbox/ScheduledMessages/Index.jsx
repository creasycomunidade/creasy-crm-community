import { Head, Link, router } from '@inertiajs/react';
import ClientLayout from '@/Layouts/ClientLayout';
import ScheduledMessageModal from '@/Components/Inbox/ScheduledMessageModal';
import { CalendarClock, CheckCircle2, CirclePause, Clock3, Pencil, Play, Plus, Repeat2, Search, Trash2, XCircle } from 'lucide-react';
import { useState } from 'react';
import { browserTz, formatInTz } from '@/Utils/datetime';

const statusMeta = {
    scheduled: { label: 'Agendada', tone: 'bg-blue-50 text-blue-700 dark:bg-blue-950/40 dark:text-blue-300' },
    processing: { label: 'Enviando', tone: 'bg-amber-50 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300' },
    paused: { label: 'Pausada', tone: 'bg-neutral-100 text-neutral-700 dark:bg-neutral-800 dark:text-neutral-300' },
    completed: { label: 'Concluída', tone: 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300' },
    failed: { label: 'Falhou', tone: 'bg-red-50 text-red-700 dark:bg-red-950/40 dark:text-red-300' },
    cancelled: { label: 'Cancelada', tone: 'bg-neutral-100 text-neutral-500 dark:bg-neutral-800 dark:text-neutral-400' },
};

const unitLabels = { once: 'Envio único', minute: 'minuto(s)', hour: 'hora(s)', day: 'dia(s)', week: 'semana(s)', month: 'mês(es)' };

function contactName(schedule) {
    const contact = schedule.conversation?.contact ?? {};
    return `${contact.first_name ?? ''} ${contact.last_name ?? ''}`.trim() || contact.phone_e164 || contact.email || 'Contato';
}

export default function ScheduledMessagesIndex({ schedules, counts = {}, filters = {} }) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [editingSchedule, setEditingSchedule] = useState(null);
    const timezone = browserTz() || 'America/Sao_Paulo';
    const status = filters.status ?? '';

    const filter = (nextStatus = status) => router.get(route('client.inbox.scheduled-messages.index'), {
        status: nextStatus || undefined,
        search: search || undefined,
    }, { preserveState: true, replace: true });

    const action = (name, uuid) => router.post(route(`client.inbox.scheduled-messages.${name}`, uuid), {}, { preserveScroll: true });
    const destroy = (uuid) => {
        if (confirm('Excluir definitivamente esta mensagem agendada?')) {
            router.delete(route('client.inbox.scheduled-messages.destroy', uuid), { preserveScroll: true });
        }
    };

    return (
        <ClientLayout title="Agendador de mensagens">
            <Head title="Agendador de mensagens" />
            {editingSchedule && <ScheduledMessageModal conversation={editingSchedule.conversation} schedule={editingSchedule} onClose={() => setEditingSchedule(null)} onSaved={() => router.reload({ only: ['schedules', 'counts'] })} />}
            <div className="mx-auto max-w-6xl space-y-5">
                <header className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h1 className="flex items-center gap-2 text-xl font-semibold text-neutral-900 dark:text-white">
                            <CalendarClock className="h-5 w-5 text-brand-600" /> Agendador de mensagens
                        </h1>
                        <p className="mt-1 text-sm text-neutral-500">Envie depois ou repita mensagens automaticamente na mesma conversa.</p>
                    </div>
                    <Link href={route('client.inbox.scheduled-messages.create')} className="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-brand-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2">
                        <Plus className="h-4 w-4" /> Nova mensagem
                    </Link>
                </header>

                <div className="grid grid-cols-2 gap-3 md:grid-cols-4">
                    {[
                        ['scheduled', 'Próximas', Clock3, (counts.scheduled ?? 0) + (counts.processing ?? 0)],
                        ['paused', 'Pausadas', CirclePause, counts.paused ?? 0],
                        ['completed', 'Concluídas', CheckCircle2, counts.completed ?? 0],
                        ['failed', 'Com falha', XCircle, counts.failed ?? 0],
                    ].map(([key, label, Icon, value]) => (
                        <button key={key} type="button" onClick={() => filter(status === key ? '' : key)} className={`min-h-20 rounded-xl border p-3 text-left transition focus:outline-none focus:ring-2 focus:ring-brand-500 ${status === key ? 'border-brand-500 bg-brand-50 dark:bg-brand-950/25' : 'border-neutral-200 bg-white hover:border-brand-300 dark:border-neutral-700 dark:bg-neutral-900'}`}>
                            <div className="flex items-center justify-between"><span className="text-xs font-medium text-neutral-500">{label}</span><Icon className="h-4 w-4 text-brand-600" /></div>
                            <strong className="mt-2 block text-xl text-neutral-900 dark:text-white">{value}</strong>
                        </button>
                    ))}
                </div>

                <form onSubmit={(event) => { event.preventDefault(); filter(); }} className="flex gap-2">
                    <label className="relative flex-1">
                        <span className="sr-only">Pesquisar agendamentos</span>
                        <Search className="pointer-events-none absolute left-3 top-3.5 h-4 w-4 text-neutral-400" />
                        <input value={search} onChange={(event) => setSearch(event.target.value)} placeholder="Pesquisar contato ou mensagem…" className="min-h-11 w-full rounded-xl border border-neutral-300 bg-white pl-10 pr-3 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 dark:border-neutral-700 dark:bg-neutral-900" />
                    </label>
                    <button className="min-h-11 rounded-xl border border-neutral-300 px-4 text-sm font-medium hover:bg-neutral-50 dark:border-neutral-700 dark:hover:bg-neutral-800">Pesquisar</button>
                </form>

                <div className="overflow-hidden rounded-xl border border-neutral-200 bg-white dark:border-neutral-700 dark:bg-neutral-900">
                    {schedules.data.length === 0 ? (
                        <div className="px-6 py-16 text-center"><CalendarClock className="mx-auto h-10 w-10 text-neutral-300" /><h2 className="mt-3 font-semibold">Nenhuma mensagem agendada</h2><p className="mt-1 text-sm text-neutral-500">Crie um envio único ou configure uma mensagem em loop.</p></div>
                    ) : (
                        <div className="divide-y divide-neutral-100 dark:divide-neutral-800">
                            {schedules.data.map((schedule) => {
                                const meta = statusMeta[schedule.status] ?? statusMeta.scheduled;
                                const recurring = schedule.recurrence_unit !== 'once';
                                return <article key={schedule.uuid} className="grid gap-4 p-4 md:grid-cols-[minmax(0,1fr)_220px_auto] md:items-center">
                                    <div className="min-w-0">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <h2 className="font-semibold text-neutral-900 dark:text-white">{contactName(schedule)}</h2>
                                            <span className={`rounded-full px-2 py-0.5 text-xs font-medium ${meta.tone}`}>{meta.label}</span>
                                            <span className="rounded-full bg-neutral-100 px-2 py-0.5 text-xs text-neutral-600 dark:bg-neutral-800 dark:text-neutral-300">{schedule.conversation?.channel_account?.channel ?? 'canal'}</span>
                                        </div>
                                        <p className="mt-1 line-clamp-2 text-sm text-neutral-600 dark:text-neutral-300">{schedule.body}</p>
                                        {schedule.last_error && <p className="mt-1 text-xs text-red-600">{schedule.last_error}</p>}
                                    </div>
                                    <div className="text-sm">
                                        <div className="flex items-center gap-2 text-neutral-700 dark:text-neutral-200"><Clock3 className="h-4 w-4 text-brand-600" /> {formatInTz(schedule.next_send_at, schedule.timezone || timezone)}</div>
                                        <div className="mt-1 flex items-center gap-2 text-xs text-neutral-500"><Repeat2 className="h-3.5 w-3.5" /> {recurring ? `A cada ${schedule.recurrence_interval} ${unitLabels[schedule.recurrence_unit]}` : unitLabels.once} · {schedule.occurrences_sent} enviado(s)</div>
                                    </div>
                                    <div className="flex items-center justify-end gap-1">
                                        {['scheduled', 'paused', 'failed'].includes(schedule.status) && <button type="button" onClick={() => setEditingSchedule(schedule)} title="Editar" aria-label="Editar mensagem agendada" className="flex h-11 w-11 items-center justify-center rounded-lg text-neutral-500 transition hover:bg-brand-50 hover:text-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500 dark:hover:bg-brand-950/30"><Pencil className="h-5 w-5" /></button>}
                                        {['scheduled', 'processing'].includes(schedule.status) && <button type="button" onClick={() => action('pause', schedule.uuid)} title="Pausar" aria-label="Pausar agendamento" className="flex h-11 w-11 items-center justify-center rounded-lg text-neutral-500 transition hover:bg-amber-50 hover:text-amber-700"><CirclePause className="h-5 w-5" /></button>}
                                        {['paused', 'failed'].includes(schedule.status) && <button type="button" onClick={() => action('resume', schedule.uuid)} title="Retomar" aria-label="Retomar agendamento" className="flex h-11 w-11 items-center justify-center rounded-lg text-neutral-500 transition hover:bg-emerald-50 hover:text-emerald-700"><Play className="h-5 w-5" /></button>}
                                        {schedule.status !== 'processing' && <button type="button" onClick={() => destroy(schedule.uuid)} title="Excluir" aria-label="Excluir mensagem agendada" className="flex h-11 w-11 items-center justify-center rounded-lg text-neutral-500 transition hover:bg-red-50 hover:text-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 dark:hover:bg-red-950/30"><Trash2 className="h-5 w-5" /></button>}
                                    </div>
                                </article>;
                            })}
                        </div>
                    )}
                </div>
            </div>
        </ClientLayout>
    );
}
