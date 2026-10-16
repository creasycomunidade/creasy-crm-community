import { useForm } from '@inertiajs/react';
import axios from 'axios';
import { CalendarPlus, Loader2, Search, UserRound, X } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';

const reminders = [[0, 'Sem lembrete'], [60, '1 hora antes'], [120, '2 horas antes'], [1440, '1 dia antes'], [2880, '2 dias antes']];
const input = 'mt-1 min-h-11 w-full rounded-xl border border-neutral-300 bg-white px-3 py-2 text-sm outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 dark:border-neutral-700 dark:bg-neutral-900';

function localParts(value, timezone) {
    if (!value) return { date: '', time: '' };
    const parts = new Intl.DateTimeFormat('sv-SE', { timeZone: timezone, year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit', hour12: false }).formatToParts(new Date(value)).reduce((all, part) => ({ ...all, [part.type]: part.value }), {});
    return { date: `${parts.year}-${parts.month}-${parts.day}`, time: `${parts.hour}:${parts.minute}` };
}

export default function AppointmentModal({ appointment = null, services = [], resources = [], timezone = 'America/Sao_Paulo', initialDate = '', initialTime = '', onClose }) {
    const existing = localParts(appointment?.starts_at, timezone);
    const form = useForm({
        service_uuid: appointment?.service?.uuid || services[0]?.uuid || '',
        resource_uuid: appointment?.resource?.uuid || resources[0]?.uuid || '',
        contact_uuid: appointment?.contact?.uuid || '',
        customer_name: appointment?.customer_name || '', customer_email: appointment?.customer_email || '', customer_phone: appointment?.customer_phone || '',
        starts_at: appointment ? `${existing.date}T${existing.time}` : '', notes: appointment?.notes || '',
        customer_reminder_minutes: appointment?.customer_reminder_at ? 1440 : 1440,
        professional_reminder_minutes: appointment?.professional_reminder_at ? 120 : 120,
    });
    const [date, setDate] = useState(existing.date || initialDate);
    const [time, setTime] = useState(existing.time || initialTime);
    const [slots, setSlots] = useState([]);
    const [loadingSlots, setLoadingSlots] = useState(false);
    const [contactResults, setContactResults] = useState([]);
    const [loadingContacts, setLoadingContacts] = useState(false);
    const [contactSearchOpen, setContactSearchOpen] = useState(false);
    const [selectedContact, setSelectedContact] = useState(appointment?.contact || null);
    const [minimumDate] = useState(() => new Date().toLocaleDateString('sv-SE'));
    const compatibleResources = useMemo(() => resources.filter((resource) => (resource.booking_enabled || resource.uuid === appointment?.resource?.uuid) && (!resource.services?.length || resource.services.some((service) => service.uuid === form.data.service_uuid))), [appointment?.resource?.uuid, resources, form.data.service_uuid]);

    useEffect(() => {
        if (!date || !form.data.resource_uuid || !form.data.service_uuid) { setSlots([]); return; }
        let active = true;
        setLoadingSlots(true);
        axios.get(route('client.appointments.slots'), { params: { date, resource_uuid: form.data.resource_uuid, service_uuid: form.data.service_uuid } })
            .then(({ data }) => active && setSlots(data.slots || []))
            .catch(() => active && setSlots([]))
            .finally(() => active && setLoadingSlots(false));
        return () => { active = false; };
    }, [date, form.data.resource_uuid, form.data.service_uuid]);

    useEffect(() => {
        const query = form.data.customer_name.trim();
        if (!contactSearchOpen || query.length < 2) {
            setContactResults([]);
            setLoadingContacts(false);
            return;
        }
        let active = true;
        setLoadingContacts(true);
        const timer = window.setTimeout(() => {
            axios.get(route('client.inbox.contacts.search'), { params: { q: query } })
                .then(({ data }) => active && setContactResults((data || []).slice(0, 8)))
                .catch(() => active && setContactResults([]))
                .finally(() => active && setLoadingContacts(false));
        }, 300);
        return () => { active = false; window.clearTimeout(timer); };
    }, [contactSearchOpen, form.data.customer_name]);

    const changeCustomerName = (value) => {
        form.setData({ ...form.data, customer_name: value, contact_uuid: '' });
        setSelectedContact(null);
        setContactSearchOpen(true);
    };
    const chooseContact = (contact) => {
        const name = [contact.first_name, contact.last_name].filter(Boolean).join(' ') || contact.phone_e164 || contact.email;
        form.setData({
            ...form.data,
            contact_uuid: contact.uuid,
            customer_name: name,
            customer_phone: contact.phone_e164 || '',
            customer_email: contact.email || '',
        });
        setSelectedContact(contact);
        setContactSearchOpen(false);
        setContactResults([]);
    };

    const submit = (event) => {
        event.preventDefault();
        form.transform((data) => ({ ...data, starts_at: date && time ? `${date}T${time}` : '' }));
        const options = { preserveScroll: true, onSuccess: onClose };
        appointment ? form.patch(route('client.appointments.update', appointment.uuid), options) : form.post(route('client.appointments.store'), options);
    };

    return <div className="fixed inset-0 z-[120] flex items-end justify-center bg-neutral-950/60 sm:items-center sm:p-4" onMouseDown={(event) => event.target === event.currentTarget && onClose()}>
        <section role="dialog" aria-modal="true" aria-labelledby="appointment-modal-title" className="flex max-h-[94vh] w-full max-w-2xl flex-col overflow-hidden rounded-t-2xl bg-white shadow-2xl dark:bg-neutral-900 sm:rounded-2xl">
            <header className="flex items-start justify-between border-b border-neutral-200 p-5 dark:border-neutral-700"><div><h2 id="appointment-modal-title" className="flex items-center gap-2 text-lg font-semibold"><CalendarPlus className="h-5 w-5 text-brand-600" />{appointment ? 'Editar agendamento' : 'Novo agendamento'}</h2><p className="mt-1 text-xs text-neutral-500">Horários ocupados e indisponíveis são bloqueados automaticamente.</p></div><button type="button" onClick={onClose} aria-label="Fechar" className="flex h-11 w-11 items-center justify-center rounded-xl hover:bg-neutral-100 focus:outline-none focus:ring-2 focus:ring-brand-500 dark:hover:bg-neutral-800"><X className="h-5 w-5" /></button></header>
            <form onSubmit={submit} className="min-h-0 space-y-4 overflow-y-auto p-5">
                <div className="grid gap-4 sm:grid-cols-2">
                    <label className="text-sm font-medium">Serviço *<select required value={form.data.service_uuid} onChange={(event) => { form.setData('service_uuid', event.target.value); setTime(''); }} className={input}><option value="">Selecione</option>{services.map((item) => <option key={item.uuid} value={item.uuid}>{item.name}</option>)}</select></label>
                    <label className="text-sm font-medium">Profissional ou recurso *<select required value={form.data.resource_uuid} onChange={(event) => { form.setData('resource_uuid', event.target.value); setTime(''); }} className={input}><option value="">Selecione</option>{compatibleResources.map((item) => <option key={item.uuid} value={item.uuid}>{item.name}{item.position ? ` — ${item.position}` : ''}</option>)}</select></label>
                </div>
                <div className="relative"><label htmlFor="appointment-customer-search" className="block text-sm font-medium">Cliente *<span className="relative mt-1 block"><Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-neutral-400" /><input id="appointment-customer-search" required autoComplete="off" value={form.data.customer_name} onFocus={() => setContactSearchOpen(true)} onBlur={() => window.setTimeout(() => setContactSearchOpen(false), 150)} onChange={(event) => changeCustomerName(event.target.value)} className={`${input} mt-0 pl-9 pr-10`} placeholder="Digite nome, telefone ou e-mail" role="combobox" aria-expanded={contactSearchOpen && form.data.customer_name.trim().length >= 2} aria-controls="appointment-contact-results" aria-autocomplete="list" />{loadingContacts && <Loader2 className="absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 animate-spin text-neutral-400" />}</span></label>
                    {contactSearchOpen && form.data.customer_name.trim().length >= 2 && <div id="appointment-contact-results" role="listbox" className="absolute z-30 mt-1 max-h-64 w-full overflow-y-auto rounded-xl border border-neutral-200 bg-white p-1 shadow-xl dark:border-neutral-700 dark:bg-neutral-900">{!loadingContacts && contactResults.length === 0 ? <p className="p-3 text-sm text-neutral-500">Nenhum cliente encontrado. Continue preenchendo para cadastrar um novo.</p> : contactResults.map((contact) => { const name = [contact.first_name, contact.last_name].filter(Boolean).join(' ') || contact.phone_e164 || contact.email; return <button key={contact.uuid} type="button" role="option" aria-selected={selectedContact?.uuid === contact.uuid} onClick={() => chooseContact(contact)} className="flex min-h-12 w-full items-center gap-3 rounded-lg px-3 py-2 text-left transition hover:bg-brand-50 focus:bg-brand-50 focus:outline-none dark:hover:bg-brand-950/30 dark:focus:bg-brand-950/30"><span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-brand-100 text-brand-700 dark:bg-brand-950/40 dark:text-brand-300"><UserRound className="h-4 w-4" /></span><span className="min-w-0"><span className="block truncate text-sm font-semibold">{name}</span><span className="block truncate text-xs text-neutral-500">{[contact.phone_e164, contact.email].filter(Boolean).join(' · ') || 'Sem telefone ou e-mail'}</span></span></button>; })}</div>}
                    {selectedContact && <p className="mt-1 text-xs text-emerald-600">Cliente existente selecionado e vinculado ao agendamento.</p>}
                </div>
                <div className="grid gap-4 sm:grid-cols-2"><label className="text-sm font-medium">Telefone<input type="tel" autoComplete="tel" value={form.data.customer_phone} onChange={(event) => form.setData('customer_phone', event.target.value)} className={input} placeholder="(47) 99999-9999" /></label><label className="text-sm font-medium">E-mail<input type="email" autoComplete="email" value={form.data.customer_email} onChange={(event) => form.setData('customer_email', event.target.value)} className={input} /></label></div>
                <div className="grid gap-4 sm:grid-cols-2"><label className="text-sm font-medium">Data *<input required type="date" min={minimumDate} value={date} onChange={(event) => { setDate(event.target.value); setTime(''); }} className={input} /></label><label className="text-sm font-medium">Horário *<input required type="time" step="300" value={time} onChange={(event) => setTime(event.target.value)} className={input} /></label></div>
                <div><span className="text-sm font-medium">Horários disponíveis</span><div className="mt-2 flex min-h-12 flex-wrap gap-2 rounded-xl bg-neutral-50 p-2 dark:bg-neutral-800/60">{loadingSlots ? <span className="inline-flex items-center gap-2 p-2 text-xs text-neutral-500"><Loader2 className="h-4 w-4 animate-spin" />Consultando agenda…</span> : slots.length ? slots.map((slot) => <button type="button" key={slot} onClick={() => setTime(slot)} className={`min-h-11 rounded-lg border px-3 text-sm font-semibold transition focus:outline-none focus:ring-2 focus:ring-brand-500 ${time === slot ? 'border-brand-600 bg-brand-600 text-white' : 'border-neutral-200 bg-white hover:border-brand-400 dark:border-neutral-700 dark:bg-neutral-900'}`}>{slot}</button>) : <span className="p-2 text-xs text-neutral-500">Selecione serviço, profissional e data para consultar os horários.</span>}</div></div>
                <div className="grid gap-4 sm:grid-cols-2"><label className="text-sm font-medium">Lembrar cliente<select value={form.data.customer_reminder_minutes} onChange={(event) => form.setData('customer_reminder_minutes', Number(event.target.value))} className={input}>{reminders.map(([value, label]) => <option key={value} value={value}>{label}</option>)}</select></label><label className="text-sm font-medium">Lembrar profissional<select value={form.data.professional_reminder_minutes} onChange={(event) => form.setData('professional_reminder_minutes', Number(event.target.value))} className={input}>{reminders.map(([value, label]) => <option key={value} value={value}>{label}</option>)}</select></label></div>
                <label className="block text-sm font-medium">Observações<textarea rows="3" value={form.data.notes} onChange={(event) => form.setData('notes', event.target.value)} className={`${input} resize-y`} /></label>
                {Object.values(form.errors).length > 0 && <div role="alert" className="rounded-xl border border-red-200 bg-red-50 p-3 text-xs text-red-700">{Object.values(form.errors).map((error) => <p key={error}>{error}</p>)}</div>}
                <footer className="flex flex-col-reverse gap-2 border-t border-neutral-200 pt-4 dark:border-neutral-700 sm:flex-row sm:justify-end"><button type="button" onClick={onClose} className="min-h-11 rounded-xl border border-neutral-300 px-4 text-sm font-medium dark:border-neutral-700">Cancelar</button><button disabled={form.processing || !date || !time} className="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-brand-600 px-5 text-sm font-semibold text-white transition hover:bg-brand-700 disabled:cursor-not-allowed disabled:opacity-50">{form.processing && <Loader2 className="h-4 w-4 animate-spin" />}{form.processing ? 'Salvando…' : 'Salvar agendamento'}</button></footer>
            </form>
        </section>
    </div>;
}
