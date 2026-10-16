import { Head } from '@inertiajs/react';
import axios from 'axios';
import { CalendarDays, CheckCircle2, Clock3, LoaderCircle, UserRound } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';

const inputClass = 'mt-1 min-h-11 w-full rounded-xl border border-neutral-300 bg-white px-3 py-2 text-base text-neutral-900 outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 sm:text-sm';
const today = () => new Date().toLocaleDateString('en-CA', { timeZone: 'America/Sao_Paulo' });
const futureDate = (days) => {
    const date = new Date();
    date.setDate(date.getDate() + Number(days || 90));
    return date.toLocaleDateString('en-CA', { timeZone: 'America/Sao_Paulo' });
};
const money = (value) => Number(value || 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });

export default function PublicBooking({ workspace, resources = [], services = [], selectedResourceUuid, bookingHorizonDays = 90, publicToken, embedded = false }) {
    const [form, setForm] = useState({
        resource_uuid: selectedResourceUuid || resources[0]?.uuid || '',
        service_uuid: '',
        date: '',
        time: '',
        customer_name: '',
        customer_phone: '',
        customer_email: '',
        notes: '',
        website: '',
    });
    const [slots, setSlots] = useState([]);
    const [loadingSlots, setLoadingSlots] = useState(false);
    const [submitting, setSubmitting] = useState(false);
    const [error, setError] = useState('');
    const [success, setSuccess] = useState(null);
    const selectedResource = resources.find((item) => item.uuid === form.resource_uuid);
    const availableServices = useMemo(() => {
        if (!selectedResource || !selectedResource.service_uuids?.length) return services;
        return services.filter((service) => selectedResource.service_uuids.includes(service.uuid));
    }, [selectedResource, services]);

    useEffect(() => {
        if (form.service_uuid && !availableServices.some((service) => service.uuid === form.service_uuid)) {
            setForm((current) => ({ ...current, service_uuid: '', time: '' }));
        }
    }, [availableServices, form.service_uuid]);

    useEffect(() => {
        if (!form.resource_uuid || !form.service_uuid || !form.date) {
            setSlots([]);
            return;
        }
        let active = true;
        setLoadingSlots(true);
        setError('');
        axios.get(route('appointments.public.slots', publicToken), { params: {
            resource_uuid: form.resource_uuid,
            service_uuid: form.service_uuid,
            date: form.date,
        } }).then(({ data }) => {
            if (active) setSlots(data.slots || []);
        }).catch((requestError) => {
            if (active) {
                setSlots([]);
                setError(requestError.response?.data?.message || 'Não foi possível consultar os horários.');
            }
        }).finally(() => active && setLoadingSlots(false));
        return () => { active = false; };
    }, [form.date, form.resource_uuid, form.service_uuid, publicToken]);

    const change = (key, value) => {
        setForm((current) => ({ ...current, [key]: value, ...(key === 'resource_uuid' || key === 'service_uuid' || key === 'date' ? { time: '' } : {}) }));
        setError('');
    };
    const submit = async (event) => {
        event.preventDefault();
        setSubmitting(true);
        setError('');
        try {
            const { data } = await axios.post(route('appointments.public.store', publicToken), form);
            setSuccess(data);
        } catch (requestError) {
            const validation = requestError.response?.data?.errors;
            setError(Object.values(validation || {}).flat()[0] || requestError.response?.data?.message || 'Não foi possível concluir o agendamento.');
        } finally {
            setSubmitting(false);
        }
    };

    if (success) {
        return <main className={`min-h-dvh bg-neutral-50 text-neutral-900 ${embedded ? 'p-4' : 'px-4 py-10 sm:py-16'}`}><Head title={`Agendamento confirmado - ${workspace.name}`} /><section className="mx-auto max-w-xl rounded-2xl border border-neutral-200 bg-white p-6 text-center shadow-sm sm:p-10"><CheckCircle2 className="mx-auto h-14 w-14 text-emerald-600" /><h1 className="mt-5 text-2xl font-bold">Agendamento confirmado</h1><p className="mt-2 text-neutral-600">Seu horário foi reservado para <strong>{success.starts_at}</strong>.</p><p className="mt-3 text-sm text-neutral-500">Código: <span className="font-semibold text-neutral-800">{success.reference}</span></p><button type="button" onClick={() => window.location.reload()} className="mt-7 min-h-11 rounded-xl bg-brand-600 px-5 text-sm font-semibold text-white transition hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2">Fazer outro agendamento</button></section></main>;
    }

    const closed = selectedResourceUuid && (!resources.length || !resources[0]?.booking_enabled);
    return <main className={`min-h-dvh bg-neutral-50 text-neutral-900 ${embedded ? 'p-3 sm:p-5' : 'px-4 py-8 sm:py-12'}`}><Head title={`Agendar - ${workspace.name}`} /><section className="mx-auto max-w-3xl overflow-hidden rounded-2xl border border-neutral-200 bg-white shadow-sm"><header className="border-b border-neutral-200 bg-gradient-to-br from-brand-700 to-brand-500 p-6 text-white sm:p-8"><p className="text-sm font-medium text-white/80">Agendamento online</p><h1 className="mt-1 text-2xl font-bold sm:text-3xl">{workspace.name}</h1><p className="mt-2 max-w-xl text-sm leading-6 text-white/85">Escolha o serviço, o profissional e o melhor horário para você.</p></header>
        {closed ? <div className="p-8 text-center sm:p-12"><CalendarDays className="mx-auto h-12 w-12 text-neutral-400" /><h2 className="mt-4 text-xl font-semibold">Agenda temporariamente fechada</h2><p className="mt-2 text-sm text-neutral-500">Este profissional não está recebendo novos agendamentos no momento.</p></div> : !resources.length || !services.length ? <div className="p-8 text-center sm:p-12"><CalendarDays className="mx-auto h-12 w-12 text-neutral-400" /><h2 className="mt-4 text-xl font-semibold">Agenda ainda indisponível</h2><p className="mt-2 text-sm text-neutral-500">A empresa ainda está configurando os profissionais, serviços e horários.</p></div> : <form onSubmit={submit} className="space-y-6 p-5 sm:p-8">
            <section aria-labelledby="booking-selection"><h2 id="booking-selection" className="flex items-center gap-2 text-base font-semibold"><UserRound className="h-5 w-5 text-brand-600" />Escolha seu atendimento</h2><div className="mt-4 grid gap-4 sm:grid-cols-2">
                <label className="text-sm font-medium">Profissional ou recurso<select required disabled={Boolean(selectedResourceUuid)} value={form.resource_uuid} onChange={(event) => change('resource_uuid', event.target.value)} className={`${inputClass} disabled:bg-neutral-100 disabled:text-neutral-600`}>{!form.resource_uuid && <option value="">Selecione</option>}{resources.map((resource) => <option key={resource.uuid} value={resource.uuid}>{resource.name}{resource.position ? ` — ${resource.position}` : ''}</option>)}</select></label>
                <label className="text-sm font-medium">Serviço<select required value={form.service_uuid} onChange={(event) => change('service_uuid', event.target.value)} className={inputClass}><option value="">Selecione</option>{availableServices.map((service) => <option key={service.uuid} value={service.uuid}>{service.name} · {service.duration_minutes} min{Number(service.price) > 0 ? ` · ${money(service.price)}` : ''}</option>)}</select></label>
            </div></section>
            <section aria-labelledby="booking-date"><h2 id="booking-date" className="flex items-center gap-2 text-base font-semibold"><CalendarDays className="h-5 w-5 text-brand-600" />Data e horário</h2><label className="mt-4 block max-w-xs text-sm font-medium">Data<input type="date" min={today()} max={futureDate(bookingHorizonDays)} required value={form.date} onChange={(event) => change('date', event.target.value)} className={inputClass} /></label><div className="mt-4"><p className="text-sm font-medium">Horários disponíveis</p>{loadingSlots ? <p className="mt-3 flex items-center gap-2 text-sm text-neutral-500"><LoaderCircle className="h-4 w-4 animate-spin" />Consultando horários…</p> : form.date && form.service_uuid ? slots.length ? <div className="mt-3 grid grid-cols-3 gap-2 sm:grid-cols-5">{slots.map((slot) => <button key={slot} type="button" onClick={() => change('time', slot)} aria-pressed={form.time === slot} className={`min-h-11 rounded-xl border px-3 text-sm font-semibold tabular-nums transition focus:outline-none focus:ring-2 focus:ring-brand-500 ${form.time === slot ? 'border-brand-600 bg-brand-600 text-white' : 'border-neutral-300 hover:border-brand-400 hover:bg-brand-50'}`}><Clock3 className="mr-1 inline h-4 w-4" />{slot}</button>)}</div> : <p className="mt-3 rounded-xl bg-neutral-100 p-4 text-sm text-neutral-600">Não há horários livres nesta data. Escolha outro dia.</p> : <p className="mt-3 text-sm text-neutral-500">Selecione o serviço e a data para ver os horários.</p>}</div></section>
            <section aria-labelledby="booking-contact"><h2 id="booking-contact" className="text-base font-semibold">Seus dados</h2><div className="mt-4 grid gap-4 sm:grid-cols-2"><label className="text-sm font-medium sm:col-span-2">Nome completo<input required value={form.customer_name} onChange={(event) => change('customer_name', event.target.value)} className={inputClass} autoComplete="name" /></label><label className="text-sm font-medium">Telefone com DDD<input type="tel" required value={form.customer_phone} onChange={(event) => change('customer_phone', event.target.value)} className={inputClass} autoComplete="tel" placeholder="(47) 99999-9999" /></label><label className="text-sm font-medium">E-mail <span className="font-normal text-neutral-500">(opcional)</span><input type="email" value={form.customer_email} onChange={(event) => change('customer_email', event.target.value)} className={inputClass} autoComplete="email" /></label><label className="hidden" aria-hidden="true">Website<input tabIndex="-1" autoComplete="off" value={form.website} onChange={(event) => change('website', event.target.value)} /></label><label className="text-sm font-medium sm:col-span-2">Observações <span className="font-normal text-neutral-500">(opcional)</span><textarea rows="3" value={form.notes} onChange={(event) => change('notes', event.target.value)} className={inputClass} /></label></div></section>
            {error && <p role="alert" className="rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-700">{error}</p>}
            <button disabled={submitting || !form.time} className="inline-flex min-h-12 w-full items-center justify-center gap-2 rounded-xl bg-brand-600 px-5 text-sm font-semibold text-white transition hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50">{submitting ? <><LoaderCircle className="h-4 w-4 animate-spin" />Confirmando…</> : 'Confirmar agendamento'}</button>
        </form>}</section><p className="mx-auto mt-4 max-w-3xl text-center text-xs text-neutral-500">Agendamento seguro fornecido por ResponDesk.</p></main>;
}
