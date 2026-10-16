import { Head, Link } from '@inertiajs/react';
import ClientLayout from '@/Layouts/ClientLayout';
import AgendaPageHeader from '@/Components/Appointments/AgendaPageHeader';
import { CalendarCheck, CalendarDays, CheckCircle2, CircleX, Clock3, MessageSquare, Plus, Settings2, Stethoscope, Users } from 'lucide-react';

const statusLabel = { scheduled: 'Agendado', confirmed: 'Confirmado', completed: 'Concluído', cancelled: 'Cancelado', no_show: 'Não compareceu' };
const statCards = [
    ['today', 'Hoje', CalendarDays, 'text-blue-600 bg-blue-50 dark:bg-blue-950/30'],
    ['upcoming', 'Próximos', Clock3, 'text-violet-600 bg-violet-50 dark:bg-violet-950/30'],
    ['confirmed', 'Confirmados no mês', CalendarCheck, 'text-emerald-600 bg-emerald-50 dark:bg-emerald-950/30'],
    ['completed', 'Concluídos no mês', CheckCircle2, 'text-cyan-600 bg-cyan-50 dark:bg-cyan-950/30'],
    ['cancelled', 'Cancelados/faltas', CircleX, 'text-red-600 bg-red-50 dark:bg-red-950/30'],
];

function AppointmentRow({ item }) {
    return <div className="flex items-center gap-3 border-b border-neutral-100 py-3 last:border-0 dark:border-neutral-800"><span className="h-10 w-1 rounded-full" style={{ backgroundColor: item.service?.color || '#7c3aed' }} /><div className="min-w-0 flex-1"><p className="truncate text-sm font-semibold">{item.customer_name}</p><p className="truncate text-xs text-neutral-500">{item.service?.name} · {item.resource?.name}</p></div><div className="text-right"><p className="text-sm font-semibold tabular-nums">{new Date(item.starts_at).toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' })}</p><p className="text-xs text-neutral-500">{statusLabel[item.status]}</p></div></div>;
}

export default function AppointmentDashboard({ stats = {}, todayAppointments = [], nextAppointments = [], setup = {} }) {
    const setupItems = [
        [setup.services > 0, 'Cadastre os serviços oferecidos', 'client.appointments.services.index', Stethoscope],
        [setup.resources > 0, 'Cadastre profissionais e recursos', 'client.appointments.resources.index', Users],
        [setup.resources_with_hours > 0, 'Defina os horários de atendimento', 'client.appointments.availability.index', Clock3],
        [setup.channel_connected, 'Escolha o canal de confirmações', 'client.appointments.channels.index', MessageSquare],
    ];
    return <ClientLayout title="Agenda"><Head title="Agenda" /><div className="space-y-6 p-4 sm:p-6">
        <AgendaPageHeader title="Visão geral" description="Acompanhe os atendimentos e deixe toda a operação da agenda centralizada no ResponDesk." action="Novo agendamento" actionHref={route('client.appointments.calendar')} actionIcon={Plus} />
        <section className="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">{statCards.map(([key, label, Icon, tone]) => <div key={key} className="rounded-xl border border-neutral-200 bg-white p-4 dark:border-neutral-700 dark:bg-neutral-900"><div className={`flex h-10 w-10 items-center justify-center rounded-xl ${tone}`}><Icon className="h-5 w-5" /></div><p className="mt-4 text-2xl font-semibold tabular-nums">{stats[key] || 0}</p><p className="mt-1 text-xs text-neutral-500">{label}</p></div>)}</section>
        <div className="grid gap-6 xl:grid-cols-[1fr_1fr_22rem]">
            <section className="rounded-xl border border-neutral-200 bg-white p-5 dark:border-neutral-700 dark:bg-neutral-900"><div className="flex items-center justify-between"><h2 className="font-semibold">Agenda de hoje</h2><Link href={route('client.appointments.calendar')} className="text-xs font-semibold text-brand-600">Ver calendário</Link></div><div className="mt-3">{todayAppointments.length ? todayAppointments.map((item) => <AppointmentRow key={item.uuid} item={item} />) : <p className="py-10 text-center text-sm text-neutral-500">Nenhum atendimento para hoje.</p>}</div></section>
            <section className="rounded-xl border border-neutral-200 bg-white p-5 dark:border-neutral-700 dark:bg-neutral-900"><div className="flex items-center justify-between"><h2 className="font-semibold">Próximos atendimentos</h2><Link href={route('client.appointments.list')} className="text-xs font-semibold text-brand-600">Ver todos</Link></div><div className="mt-3">{nextAppointments.length ? nextAppointments.map((item) => <AppointmentRow key={item.uuid} item={item} />) : <p className="py-10 text-center text-sm text-neutral-500">Ainda não há próximos agendamentos.</p>}</div></section>
            <aside className="rounded-xl border border-neutral-200 bg-white p-5 dark:border-neutral-700 dark:bg-neutral-900"><h2 className="flex items-center gap-2 font-semibold"><Settings2 className="h-4 w-4 text-brand-600" />Configuração da agenda</h2><div className="mt-4 space-y-2">{setupItems.map(([done, label, href, Icon]) => <Link key={label} href={route(href)} className="flex min-h-12 items-center gap-3 rounded-xl border border-neutral-200 p-3 transition hover:border-brand-300 hover:bg-brand-50/40 dark:border-neutral-700 dark:hover:bg-brand-950/20"><span className={`flex h-8 w-8 items-center justify-center rounded-lg ${done ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'}`}><Icon className="h-4 w-4" /></span><span className="flex-1 text-xs font-medium">{label}</span><span className={`text-[10px] font-semibold ${done ? 'text-emerald-600' : 'text-amber-600'}`}>{done ? 'Pronto' : 'Pendente'}</span></Link>)}</div></aside>
        </div>
    </div></ClientLayout>;
}
