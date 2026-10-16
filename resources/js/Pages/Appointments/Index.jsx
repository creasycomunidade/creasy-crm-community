import { useMemo, useState } from 'react';
import { Head, router, useForm } from '@inertiajs/react';
import ClientLayout from '@/Layouts/ClientLayout';
import { CalendarDays, ChevronLeft, ChevronRight, Clock, List, Pencil, UserRound, X } from 'lucide-react';

const statusLabel = { scheduled: 'Agendado', confirmed: 'Confirmado', completed: 'Concluído', cancelled: 'Cancelado', no_show: 'Não compareceu' };
const reminderOptions = [
    [0, 'Sem lembrete'],
    [60, '1 hora antes'],
    [120, '2 horas antes'],
    [1440, '1 dia antes'],
    [2880, '2 dias antes'],
];

function localDateTime(value) {
    if (!value) return '';
    const date = new Date(value);
    const parts = new Intl.DateTimeFormat('sv-SE', {
        timeZone: 'America/Sao_Paulo', year: 'numeric', month: '2-digit', day: '2-digit',
        hour: '2-digit', minute: '2-digit', hour12: false,
    }).formatToParts(date).reduce((result, part) => ({ ...result, [part.type]: part.value }), {});
    return `${parts.year}-${parts.month}-${parts.day}T${parts.hour}:${parts.minute}`;
}

function AppointmentEditModal({ appointment, services, resources, onClose }) {
    const form = useForm({
        service_uuid: appointment.service?.uuid || '',
        resource_uuid: appointment.resource?.uuid || '',
        customer_name: appointment.customer_name || '',
        customer_email: appointment.customer_email || '',
        customer_phone: appointment.customer_phone || '',
        starts_at: localDateTime(appointment.starts_at),
        notes: appointment.notes || '',
        customer_reminder_minutes: appointment.customer_reminder_at ? 1440 : 0,
        professional_reminder_minutes: appointment.professional_reminder_at ? 120 : 0,
    });
    const save = event => {
        event.preventDefault();
        form.patch(route('client.appointments.update', appointment.uuid), {
            preserveScroll: true,
            onSuccess: onClose,
        });
    };
    return <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" onMouseDown={event => event.target === event.currentTarget && onClose()}>
        <form onSubmit={save} className="max-h-[90vh] w-full max-w-xl overflow-y-auto rounded-2xl bg-white p-6 shadow-2xl">
            <div className="mb-5 flex items-center justify-between"><div><h2 className="text-lg font-semibold">Editar agendamento</h2><p className="text-sm text-gray-500">Altere o dia, horário, serviço ou profissional.</p></div><button type="button" onClick={onClose} className="rounded-lg p-2 hover:bg-gray-100"><X className="h-5 w-5" /></button></div>
            <div className="grid gap-3">
                <select className="rounded-lg border p-2" value={form.data.service_uuid} onChange={event => form.setData('service_uuid', event.target.value)} required><option value="">Selecione o serviço</option>{services.map(item => <option key={item.uuid} value={item.uuid}>{item.name}</option>)}</select>
                <select className="rounded-lg border p-2" value={form.data.resource_uuid} onChange={event => form.setData('resource_uuid', event.target.value)} required><option value="">Selecione o profissional/recurso</option>{resources.map(item => <option key={item.uuid} value={item.uuid}>{item.name}</option>)}</select>
                <input className="rounded-lg border p-2" value={form.data.customer_name} onChange={event => form.setData('customer_name', event.target.value)} placeholder="Nome do cliente" required />
                <label className="text-xs font-medium text-gray-600">Nova data e horário<input type="datetime-local" className="mt-1 w-full rounded-lg border p-2" value={form.data.starts_at} onChange={event => form.setData('starts_at', event.target.value)} required /></label>
                <div className="grid gap-2 sm:grid-cols-2"><input type="email" className="rounded-lg border p-2" placeholder="E-mail do cliente" value={form.data.customer_email} onChange={event => form.setData('customer_email', event.target.value)} /><input className="rounded-lg border p-2" placeholder="Telefone" value={form.data.customer_phone} onChange={event => form.setData('customer_phone', event.target.value)} /></div>
                <textarea className="rounded-lg border p-2" rows="3" placeholder="Observações" value={form.data.notes} onChange={event => form.setData('notes', event.target.value)} />
                {Object.values(form.errors).map((error, index) => <p key={index} className="text-xs text-red-600">{error}</p>)}
                <div className="flex justify-end gap-2"><button type="button" onClick={onClose} className="rounded-lg border px-4 py-2">Cancelar</button><button disabled={form.processing} className="rounded-lg bg-violet-600 px-4 py-2 font-medium text-white disabled:opacity-50">{form.processing ? 'Salvando…' : 'Salvar alterações'}</button></div>
            </div>
        </form>
    </div>;
}

function CalendarView({ appointments, calendarMonth, onEdit }) {
    const monthStart = new Date(`${calendarMonth}-01T12:00:00`);
    const gridStart = new Date(monthStart);
    gridStart.setDate(1 - gridStart.getDay());
    const days = Array.from({ length: 42 }, (_, index) => {
        const value = new Date(gridStart);
        value.setDate(gridStart.getDate() + index);
        return value;
    });
    const byDay = useMemo(() => appointments.reduce((result, item) => {
        const key = new Date(item.starts_at).toLocaleDateString('sv-SE', { timeZone: 'America/Sao_Paulo' });
        (result[key] ||= []).push(item);
        return result;
    }, {}), [appointments]);

    return <div className="overflow-x-auto">
        <div className="min-w-[850px]">
            <div className="grid grid-cols-7 border-b bg-gray-50">{['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'].map(day => <div key={day} className="p-3 text-center text-xs font-semibold uppercase text-gray-500">{day}</div>)}</div>
            <div className="grid grid-cols-7">{days.map(day => {
                const key = day.toLocaleDateString('sv-SE');
                const outside = day.getMonth() !== monthStart.getMonth();
                return <div key={key} className={`min-h-32 border-b border-r p-2 ${outside ? 'bg-gray-50/70 text-gray-400' : ''}`}>
                    <div className="mb-2 text-xs font-semibold">{day.getDate()}</div>
                    <div className="space-y-1">{(byDay[key] || []).map(item => <button type="button" onClick={() => onEdit(item)} key={item.uuid} title={`Editar ${item.customer_name} — ${item.service?.name}`} className="block w-full rounded-md border-l-4 bg-violet-50 px-2 py-1 text-left text-[11px] hover:bg-violet-100" style={{ borderLeftColor: item.service?.color || '#7c3aed' }}>
                        <b>{new Date(item.starts_at).toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' })}</b> {item.customer_name}
                        <div className="truncate text-gray-500">{item.service?.name} · {item.resource?.name}</div>
                    </button>)}</div>
                </div>;
            })}</div>
        </div>
    </div>;
}

export default function AppointmentsIndex({ services = [], resources = [], appointments = [], calendarMonth }) {
    const [view, setView] = useState('calendar');
    const [editing, setEditing] = useState(null);
    const service = useForm({ name: '', duration_minutes: 30, buffer_minutes: 0, price: 0, color: '#7c3aed' });
    const resource = useForm({ name: '', email: '' });
    const booking = useForm({
        service_uuid: services[0]?.uuid || '', resource_uuid: resources[0]?.uuid || '',
        customer_name: '', customer_email: '', customer_phone: '', starts_at: '', notes: '',
        customer_reminder_minutes: 1440, professional_reminder_minutes: 120,
    });
    const submit = (form, routeName, reset = true) => event => {
        event.preventDefault();
        form.post(route(routeName), { preserveScroll: true, onSuccess: () => reset && form.reset() });
    };
    const moveMonth = amount => {
        const value = new Date(`${calendarMonth}-01T12:00:00`);
        value.setMonth(value.getMonth() + amount);
        router.get(route('client.appointments.index'), { month: `${value.getFullYear()}-${String(value.getMonth() + 1).padStart(2, '0')}` }, { preserveState: true, preserveScroll: true });
    };
    const monthTitle = new Date(`${calendarMonth}-01T12:00:00`).toLocaleDateString('pt-BR', { month: 'long', year: 'numeric' });

    return <ClientLayout title="Agendamentos">
        <Head title="Agendamentos" />
        {editing && <AppointmentEditModal appointment={editing} services={services} resources={resources} onClose={() => setEditing(null)} />}
        <div className="space-y-6 p-6">
            <div><h1 className="text-2xl font-semibold">Agendamentos</h1><p className="text-sm text-gray-500">Agenda para clínicas, consultórios, barbearias, serviços e reservas, com bloqueio de conflito e lembretes.</p></div>
            <div className="grid gap-6 xl:grid-cols-3">
                <form onSubmit={submit(service, 'client.appointments.services.store')} className="rounded-xl border bg-white p-5">
                    <h2 className="mb-4 font-semibold">Novo serviço</h2>
                    <div className="grid gap-3">
                        <input className="rounded-lg border p-2" placeholder="Consulta, corte, avaliação..." value={service.data.name} onChange={event => service.setData('name', event.target.value)} required />
                        <label className="text-xs text-gray-500">Duração (minutos)<input type="number" className="mt-1 w-full rounded-lg border p-2" value={service.data.duration_minutes} onChange={event => service.setData('duration_minutes', event.target.value)} /></label>
                        <label className="text-xs text-gray-500">Intervalo após o atendimento<input type="number" className="mt-1 w-full rounded-lg border p-2" value={service.data.buffer_minutes} onChange={event => service.setData('buffer_minutes', event.target.value)} /></label>
                        <label className="text-xs text-gray-500">Preço em BRL<input type="number" step="0.01" className="mt-1 w-full rounded-lg border p-2" value={service.data.price} onChange={event => service.setData('price', event.target.value)} /></label>
                        <label className="text-xs text-gray-500">Cor no calendário<input type="color" className="mt-1 h-10 w-full rounded-lg border p-1" value={service.data.color} onChange={event => service.setData('color', event.target.value)} /></label>
                        <button className="rounded-lg bg-violet-600 p-2 text-white">Criar serviço</button>
                    </div>
                </form>
                <form onSubmit={submit(resource, 'client.appointments.resources.store')} className="rounded-xl border bg-white p-5">
                    <h2 className="mb-4 font-semibold">Profissional ou recurso</h2>
                    <div className="grid gap-3">
                        <input className="rounded-lg border p-2" placeholder="Nome do profissional, sala ou equipamento" value={resource.data.name} onChange={event => resource.setData('name', event.target.value)} required />
                        <input type="email" className="rounded-lg border p-2" placeholder="E-mail para receber lembretes" value={resource.data.email} onChange={event => resource.setData('email', event.target.value)} />
                        <div className="rounded-lg bg-gray-50 p-3 text-xs text-gray-500">Fuso padrão: America/Sao_Paulo. A agenda impede sobreposição para o mesmo profissional ou recurso.</div>
                        <button className="rounded-lg bg-violet-600 p-2 text-white">Criar profissional/recurso</button>
                    </div>
                </form>
                <form onSubmit={submit(booking, 'client.appointments.store')} className="rounded-xl border bg-white p-5">
                    <h2 className="mb-4 font-semibold">Novo agendamento</h2>
                    <div className="grid gap-3">
                        <select className="rounded-lg border p-2" value={booking.data.service_uuid} onChange={event => booking.setData('service_uuid', event.target.value)} required><option value="">Selecione o serviço</option>{services.map(item => <option key={item.uuid} value={item.uuid}>{item.name}</option>)}</select>
                        <select className="rounded-lg border p-2" value={booking.data.resource_uuid} onChange={event => booking.setData('resource_uuid', event.target.value)} required><option value="">Selecione o profissional/recurso</option>{resources.map(item => <option key={item.uuid} value={item.uuid}>{item.name}</option>)}</select>
                        <input className="rounded-lg border p-2" placeholder="Nome do cliente" value={booking.data.customer_name} onChange={event => booking.setData('customer_name', event.target.value)} required />
                        <input type="datetime-local" className="rounded-lg border p-2" value={booking.data.starts_at} onChange={event => booking.setData('starts_at', event.target.value)} required />
                        <div className="grid gap-2 sm:grid-cols-2"><input type="email" className="rounded-lg border p-2" placeholder="E-mail do cliente" value={booking.data.customer_email} onChange={event => booking.setData('customer_email', event.target.value)} /><input className="rounded-lg border p-2" placeholder="Telefone" value={booking.data.customer_phone} onChange={event => booking.setData('customer_phone', event.target.value)} /></div>
                        <div className="grid gap-2 sm:grid-cols-2">
                            <label className="text-xs text-gray-500">Lembrar cliente<select className="mt-1 w-full rounded-lg border p-2" value={booking.data.customer_reminder_minutes} onChange={event => booking.setData('customer_reminder_minutes', Number(event.target.value))}>{reminderOptions.map(([value, label]) => <option key={value} value={value}>{label}</option>)}</select></label>
                            <label className="text-xs text-gray-500">Lembrar profissional<select className="mt-1 w-full rounded-lg border p-2" value={booking.data.professional_reminder_minutes} onChange={event => booking.setData('professional_reminder_minutes', Number(event.target.value))}>{reminderOptions.map(([value, label]) => <option key={value} value={value}>{label}</option>)}</select></label>
                        </div>
                        <div className="text-xs text-gray-500">Os lembretes são enviados por e-mail quando o SMTP da plataforma está configurado. O cliente e o profissional precisam ter e-mail cadastrado.</div>
                        <button className="rounded-lg bg-violet-600 p-2 text-white">Agendar</button>
                    </div>
                </form>
            </div>
            <section className="overflow-hidden rounded-xl border bg-white">
                <div className="flex flex-wrap items-center justify-between gap-3 border-b p-4">
                    <div className="flex items-center gap-2"><button className="rounded-lg border p-2" onClick={() => moveMonth(-1)}><ChevronLeft className="h-4 w-4" /></button><h2 className="min-w-44 text-center font-semibold capitalize">{monthTitle}</h2><button className="rounded-lg border p-2" onClick={() => moveMonth(1)}><ChevronRight className="h-4 w-4" /></button></div>
                    <div className="flex rounded-lg border p-1"><button onClick={() => setView('calendar')} className={`flex items-center gap-1 rounded px-3 py-1.5 text-sm ${view === 'calendar' ? 'bg-violet-600 text-white' : ''}`}><CalendarDays className="h-4 w-4" /> Calendário</button><button onClick={() => setView('list')} className={`flex items-center gap-1 rounded px-3 py-1.5 text-sm ${view === 'list' ? 'bg-violet-600 text-white' : ''}`}><List className="h-4 w-4" /> Lista</button></div>
                </div>
                {view === 'calendar' ? <CalendarView appointments={appointments} calendarMonth={calendarMonth} onEdit={setEditing} /> : <div className="divide-y">{appointments.length === 0 && <div className="p-8 text-center text-sm text-gray-500">Nenhum agendamento neste mês.</div>}{appointments.map(item => <div key={item.uuid} className="grid items-center gap-3 p-4 md:grid-cols-[1fr_1fr_1fr_auto_auto]">
                    <div className="flex gap-2"><CalendarDays className="h-4 w-4 text-violet-600" /><span><b>{item.customer_name}</b><small className="block text-gray-500">{item.service?.name}</small></span></div>
                    <div className="flex gap-2 text-sm"><Clock className="h-4 w-4" />{new Date(item.starts_at).toLocaleString('pt-BR')}</div>
                    <div className="flex gap-2 text-sm"><UserRound className="h-4 w-4" />{item.resource?.name}</div>
                    <select className="rounded-lg border p-2 text-sm" value={item.status} onChange={event => router.patch(route('client.appointments.status', item.uuid), { status: event.target.value }, { preserveScroll: true })}>{Object.entries(statusLabel).map(([value, label]) => <option key={value} value={value}>{label}</option>)}</select>
                    <button type="button" onClick={() => setEditing(item)} className="flex items-center gap-1 rounded-lg border px-3 py-2 text-sm hover:bg-gray-50"><Pencil className="h-4 w-4" /> Editar</button>
                </div>)}</div>}
            </section>
        </div>
    </ClientLayout>;
}
