import { Head, router } from '@inertiajs/react';
import ClientLayout from '@/Layouts/ClientLayout';
import AgendaPageHeader from '@/Components/Appointments/AgendaPageHeader';
import AppointmentModal from '@/Components/Appointments/AppointmentModal';
import { CalendarDays, ChevronLeft, ChevronRight, Plus } from 'lucide-react';
import { useMemo, useState } from 'react';

const weekNames = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];
const hours = Array.from({ length: 24 }, (_, hour) => hour);
const keyOf = (date) => date.toLocaleDateString('sv-SE');
const fromKey = (key) => new Date(`${key}T12:00:00`);
const addDays = (date, amount) => { const value = new Date(date); value.setDate(value.getDate() + amount); return value; };
const weekStart = (date) => addDays(date, -date.getDay());

function appointmentParts(appointment, timezone) {
    const parts = new Intl.DateTimeFormat('en-GB', { timeZone: timezone, hour: '2-digit', minute: '2-digit', hour12: false }).formatToParts(new Date(appointment.starts_at));
    return {
        hour: Number(parts.find((part) => part.type === 'hour')?.value || 0),
        time: `${parts.find((part) => part.type === 'hour')?.value}:${parts.find((part) => part.type === 'minute')?.value}`,
    };
}

function AppointmentButton({ appointment, timezone, onClick, compact = false }) {
    const { time } = appointmentParts(appointment, timezone);
    return <button type="button" onClick={() => onClick(appointment)} className={`relative z-10 block w-full rounded-lg border-l-4 bg-brand-50 text-left transition hover:bg-brand-100 focus:outline-none focus:ring-2 focus:ring-brand-500 dark:bg-brand-950/25 dark:hover:bg-brand-950/40 ${compact ? 'px-2 py-1 text-[11px]' : 'px-3 py-2 text-xs'}`} style={{ borderLeftColor: appointment.service?.color || '#7c3aed' }}><span className="font-bold tabular-nums">{time}</span> <span className="font-semibold">{appointment.customer_name}</span><span className="block truncate text-neutral-500">{appointment.service?.name} · {appointment.resource?.name}</span></button>;
}

export default function AppointmentCalendar({ services = [], resources = [], appointments = [], calendarMonth, calendarDate, calendarView = 'month', timezone = 'America/Sao_Paulo' }) {
    const [modal, setModal] = useState(null);
    const referenceDate = useMemo(() => fromKey(calendarDate || `${calendarMonth}-01`), [calendarDate, calendarMonth]);
    const byDay = useMemo(() => appointments.reduce((all, item) => { const key = new Date(item.starts_at).toLocaleDateString('sv-SE', { timeZone: timezone }); (all[key] ||= []).push(item); return all; }, {}), [appointments, timezone]);
    const monthDays = useMemo(() => { const first = new Date(referenceDate.getFullYear(), referenceDate.getMonth(), 1, 12); const start = addDays(first, -first.getDay()); return Array.from({ length: 42 }, (_, index) => addDays(start, index)); }, [referenceDate]);
    const weekDays = useMemo(() => Array.from({ length: 7 }, (_, index) => addDays(weekStart(referenceDate), index)), [referenceDate]);

    const navigate = (date, view = calendarView) => {
        setModal(null);
        router.get(route('client.appointments.calendar'), { view, date: keyOf(date) }, { preserveState: true, preserveScroll: true, replace: true });
    };
    const move = (amount) => {
        const value = new Date(referenceDate);
        if (calendarView === 'month') value.setMonth(value.getMonth() + amount);
        else value.setDate(value.getDate() + amount * (calendarView === 'week' ? 7 : 1));
        navigate(value);
    };
    const openNew = (date = keyOf(referenceDate), time = '') => setModal({ appointment: null, date, time });
    const openExisting = (appointment) => setModal({ appointment, date: '', time: '' });
    const periodTitle = calendarView === 'month'
        ? referenceDate.toLocaleDateString('pt-BR', { month: 'long', year: 'numeric' })
        : calendarView === 'week'
            ? `${weekDays[0].toLocaleDateString('pt-BR', { day: '2-digit', month: 'short' })} – ${weekDays[6].toLocaleDateString('pt-BR', { day: '2-digit', month: 'short', year: 'numeric' })}`
            : referenceDate.toLocaleDateString('pt-BR', { weekday: 'long', day: '2-digit', month: 'long', year: 'numeric' });
    const viewLabel = calendarView === 'month' ? 'Mês' : calendarView === 'week' ? 'Semana' : 'Dia';
    const action = <button type="button" onClick={() => openNew()} className="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-brand-600 px-4 text-sm font-semibold text-white transition hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500"><Plus className="h-4 w-4" />Novo agendamento</button>;

    return <ClientLayout title="Calendário"><Head title="Calendário da Agenda" />{modal && <AppointmentModal appointment={modal.appointment} initialDate={modal.date} initialTime={modal.time} services={services} resources={resources} timezone={timezone} onClose={() => setModal(null)} />}<div className="space-y-5 p-4 sm:p-6">
        <AgendaPageHeader title="Calendário" description="Alterne entre mês, semana e dia. Clique em uma data ou horário para criar um agendamento." action={action} />
        <section className="overflow-hidden rounded-xl border border-neutral-200 bg-white dark:border-neutral-700 dark:bg-neutral-900">
            <div className="flex flex-wrap items-center justify-between gap-3 border-b border-neutral-200 p-4 dark:border-neutral-700"><div className="flex items-center gap-2"><button type="button" onClick={() => move(-1)} aria-label={`${viewLabel} anterior`} className="flex h-11 w-11 items-center justify-center rounded-xl border border-neutral-200 transition hover:bg-neutral-50 focus:outline-none focus:ring-2 focus:ring-brand-500 dark:border-neutral-700 dark:hover:bg-neutral-800"><ChevronLeft className="h-4 w-4" /></button><h2 className="min-w-44 text-center font-semibold capitalize sm:min-w-64">{periodTitle}</h2><button type="button" onClick={() => move(1)} aria-label={`Próximo ${viewLabel.toLowerCase()}`} className="flex h-11 w-11 items-center justify-center rounded-xl border border-neutral-200 transition hover:bg-neutral-50 focus:outline-none focus:ring-2 focus:ring-brand-500 dark:border-neutral-700 dark:hover:bg-neutral-800"><ChevronRight className="h-4 w-4" /></button><button type="button" onClick={() => navigate(new Date())} className="hidden min-h-11 rounded-xl border border-neutral-200 px-3 text-xs font-semibold transition hover:bg-neutral-50 focus:outline-none focus:ring-2 focus:ring-brand-500 dark:border-neutral-700 dark:hover:bg-neutral-800 sm:block">Hoje</button></div>
                <div className="flex flex-wrap items-center gap-3"><div className="inline-flex rounded-xl border border-neutral-200 bg-neutral-50 p-1 dark:border-neutral-700 dark:bg-neutral-800" aria-label="Visualização do calendário">{[['month', 'Mês'], ['week', 'Semana'], ['day', 'Dia']].map(([view, label]) => <button key={view} type="button" onClick={() => navigate(referenceDate, view)} aria-pressed={calendarView === view} className={`min-h-9 rounded-lg px-3 text-xs font-semibold transition focus:outline-none focus:ring-2 focus:ring-brand-500 ${calendarView === view ? 'bg-white text-brand-700 shadow-sm dark:bg-neutral-900 dark:text-brand-300' : 'text-neutral-500 hover:text-neutral-800 dark:hover:text-neutral-200'}`}>{label}</button>)}</div><span className="flex items-center gap-2 text-xs text-neutral-500"><CalendarDays className="h-4 w-4" />{appointments.length} neste período</span></div></div>

            {calendarView === 'month' && <div className="overflow-x-auto"><div className="min-w-[900px]"><div className="grid grid-cols-7 border-b border-neutral-200 bg-neutral-50 dark:border-neutral-700 dark:bg-neutral-800/60">{weekNames.map((day) => <div key={day} className="p-3 text-center text-xs font-semibold uppercase text-neutral-500">{day}</div>)}</div><div className="grid grid-cols-7">{monthDays.map((day) => { const key = keyOf(day); const outside = day.getMonth() !== referenceDate.getMonth(); return <div key={key} className={`relative min-h-36 border-b border-r border-neutral-200 p-2 dark:border-neutral-700 ${outside ? 'bg-neutral-50/70 text-neutral-400 dark:bg-neutral-800/30' : ''}`}><button type="button" onClick={() => openNew(key)} aria-label={`Criar agendamento em ${day.toLocaleDateString('pt-BR')}`} className="absolute inset-0 z-0 rounded-none text-transparent outline-none transition hover:bg-brand-50/40 focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-brand-500 dark:hover:bg-brand-950/10">Criar agendamento</button><span className="relative z-10 mb-2 flex h-8 w-8 pointer-events-none items-center justify-center rounded-lg text-xs font-semibold">{day.getDate()}</span><div className="relative z-10 space-y-1">{(byDay[key] || []).slice(0, 4).map((item) => <AppointmentButton key={item.uuid} appointment={item} timezone={timezone} onClick={openExisting} compact />)}{(byDay[key] || []).length > 4 && <button type="button" onClick={() => navigate(day, 'day')} className="min-h-8 px-2 text-[10px] font-semibold text-brand-600 hover:underline">+{byDay[key].length - 4} outros</button>}</div></div>; })}</div></div></div>}

            {calendarView === 'week' && <div className="max-h-[70vh] overflow-auto"><div className="min-w-[1050px]"><div className="sticky top-0 z-20 grid grid-cols-[4.5rem_repeat(7,minmax(8rem,1fr))] border-b border-neutral-200 bg-white dark:border-neutral-700 dark:bg-neutral-900"><div className="border-r border-neutral-200 p-3 dark:border-neutral-700" />{weekDays.map((day) => <button key={keyOf(day)} type="button" onClick={() => openNew(keyOf(day))} className="min-h-14 border-r border-neutral-200 p-2 text-center transition hover:bg-brand-50 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-brand-500 dark:border-neutral-700 dark:hover:bg-brand-950/20"><span className="block text-[10px] font-semibold uppercase text-neutral-500">{weekNames[day.getDay()]}</span><span className="text-sm font-bold">{day.getDate()}</span></button>)}</div>{hours.map((hour) => <div key={hour} className="grid grid-cols-[4.5rem_repeat(7,minmax(8rem,1fr))]"><div className="border-b border-r border-neutral-200 px-2 py-3 text-right text-xs tabular-nums text-neutral-500 dark:border-neutral-700">{String(hour).padStart(2, '0')}:00</div>{weekDays.map((day) => { const key = keyOf(day); const items = (byDay[key] || []).filter((item) => appointmentParts(item, timezone).hour === hour); return <div key={`${key}-${hour}`} className="relative min-h-16 border-b border-r border-neutral-200 p-1 dark:border-neutral-700"><button type="button" onClick={() => openNew(key, `${String(hour).padStart(2, '0')}:00`)} aria-label={`Criar agendamento em ${day.toLocaleDateString('pt-BR')} às ${hour}:00`} className="absolute inset-0 z-0 text-transparent transition hover:bg-brand-50/50 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-brand-500 dark:hover:bg-brand-950/20">Criar</button><div className="relative z-10 space-y-1">{items.map((item) => <AppointmentButton key={item.uuid} appointment={item} timezone={timezone} onClick={openExisting} compact />)}</div></div>; })}</div>)}</div></div>}

            {calendarView === 'day' && <div className="max-h-[70vh] overflow-y-auto"><div className="divide-y divide-neutral-200 dark:divide-neutral-700">{hours.map((hour) => { const key = keyOf(referenceDate); const items = (byDay[key] || []).filter((item) => appointmentParts(item, timezone).hour === hour); return <div key={hour} className="grid grid-cols-[4.5rem_1fr]"><div className="border-r border-neutral-200 px-2 py-4 text-right text-xs tabular-nums text-neutral-500 dark:border-neutral-700">{String(hour).padStart(2, '0')}:00</div><div className="relative min-h-20 p-2"><button type="button" onClick={() => openNew(key, `${String(hour).padStart(2, '0')}:00`)} aria-label={`Criar agendamento às ${hour}:00`} className="absolute inset-0 z-0 text-left text-transparent transition hover:bg-brand-50/50 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-brand-500 dark:hover:bg-brand-950/20">Criar agendamento</button><div className="relative z-10 grid gap-2 md:grid-cols-2 xl:grid-cols-3">{items.map((item) => <AppointmentButton key={item.uuid} appointment={item} timezone={timezone} onClick={openExisting} />)}</div></div></div>; })}</div></div>}
        </section>
    </div></ClientLayout>;
}
