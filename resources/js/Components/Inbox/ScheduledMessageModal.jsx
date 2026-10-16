import { ScheduledImageField, ScheduledTimingFields } from '@/Components/Inbox/ScheduledMessageFields';
import { utcToTzLocal } from '@/Utils/datetime';
import axios from 'axios';
import { CalendarPlus, Loader2, Pencil, X } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { toast } from 'sonner';

function futureLocalTime() {
    const date = new Date(Date.now() + 5 * 60 * 1000);
    date.setMinutes(Math.ceil(date.getMinutes() / 5) * 5, 0, 0);
    return new Date(date.getTime() - date.getTimezoneOffset() * 60000).toISOString().slice(0, 16);
}

export default function ScheduledMessageModal({ conversation, schedule = null, initialBody = '', onClose, onSaved }) {
    const editing = Boolean(schedule);
    const [minimumSendAt] = useState(futureLocalTime);
    const scheduleTimezone = schedule?.timezone || Intl.DateTimeFormat().resolvedOptions().timeZone || 'America/Sao_Paulo';
    const initialSendAt = useMemo(() => {
        const stored = utcToTzLocal(schedule?.next_send_at, scheduleTimezone);
        return stored && stored > minimumSendAt ? stored : minimumSendAt;
    }, [minimumSendAt, schedule?.next_send_at, scheduleTimezone]);
    const [form, setForm] = useState({
        body: schedule?.body ?? initialBody ?? '',
        image: null,
        remove_image: false,
        send_at: initialSendAt,
        recurrence_unit: schedule?.recurrence_unit || 'once',
        recurrence_interval: schedule?.recurrence_interval || 1,
        max_occurrences: schedule?.max_occurrences ?? '',
        ends_at: utcToTzLocal(schedule?.ends_at, scheduleTimezone),
    });
    const [saving, setSaving] = useState(false);
    const [errors, setErrors] = useState({});
    const bodyRef = useRef(null);
    const inputClass = 'mt-1 min-h-11 w-full rounded-xl border border-neutral-300 bg-white px-3 py-2 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 dark:border-neutral-700 dark:bg-neutral-900';

    useEffect(() => {
        const previousOverflow = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
        bodyRef.current?.focus();
        const closeOnEscape = (event) => event.key === 'Escape' && onClose();
        document.addEventListener('keydown', closeOnEscape);
        return () => {
            document.body.style.overflow = previousOverflow;
            document.removeEventListener('keydown', closeOnEscape);
        };
    }, [onClose]);

    const update = (key, value) => setForm((current) => ({ ...current, [key]: value }));
    const submit = async (event) => {
        event.preventDefault();
        if (saving) return;
        setSaving(true);
        setErrors({});
        try {
            const payload = new FormData();
            if (!editing) payload.append('conversation_uuid', conversation.uuid);
            if (editing) payload.append('_method', 'PATCH');
            Object.entries(form).forEach(([key, value]) => {
                if (value !== null && value !== undefined) payload.append(key, value === true ? '1' : value === false ? '0' : value);
            });
            const endpoint = editing
                ? route('client.inbox.scheduled-messages.update', schedule.uuid)
                : route('client.inbox.scheduled-messages.store');
            const response = await axios.post(endpoint, payload, { headers: { Accept: 'application/json' } });
            toast.success(response.data?.message || (editing ? 'Mensagem atualizada.' : 'Mensagem agendada.'));
            onSaved?.(response.data?.schedule);
            onClose();
        } catch (error) {
            const validation = error?.response?.data?.errors ?? {};
            setErrors(validation);
            toast.error(Object.values(validation).flat()[0] || error?.response?.data?.message || `Não foi possível ${editing ? 'atualizar' : 'agendar'} a mensagem.`);
        } finally {
            setSaving(false);
        }
    };

    const hasContent = form.body.trim() || form.image || (schedule?.media_url && !form.remove_image);

    return (
        <div className="fixed inset-0 z-[140] flex items-end justify-center bg-neutral-950/60 p-0 sm:items-center sm:p-4" onMouseDown={(event) => event.target === event.currentTarget && onClose()}>
            <section role="dialog" aria-modal="true" aria-labelledby="schedule-message-title" className="flex max-h-[94vh] w-full max-w-2xl flex-col overflow-hidden rounded-t-2xl bg-white shadow-2xl dark:bg-neutral-900 sm:rounded-2xl">
                <header className="flex items-start justify-between gap-4 border-b border-neutral-200 p-5 dark:border-neutral-700">
                    <div>
                        <h2 id="schedule-message-title" className="flex items-center gap-2 text-lg font-semibold">{editing ? <Pencil className="h-5 w-5 text-brand-600" /> : <CalendarPlus className="h-5 w-5 text-brand-600" />}{editing ? 'Editar mensagem agendada' : 'Agendar mensagem'}</h2>
                        <p className="mt-1 text-xs text-neutral-500">{editing ? 'Altere o conteúdo, a imagem, o horário ou a repetição.' : 'Será enviada para este cliente sem sair da conversa.'}</p>
                    </div>
                    <button type="button" onClick={onClose} aria-label="Fechar" className="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl text-neutral-500 transition hover:bg-neutral-100 focus:outline-none focus:ring-2 focus:ring-brand-500 dark:hover:bg-neutral-800"><X className="h-5 w-5" /></button>
                </header>

                <form onSubmit={submit} className="min-h-0 overflow-y-auto p-5">
                    <label className="block text-sm font-medium">Mensagem
                        <textarea ref={bodyRef} maxLength={4096} rows={5} value={form.body} onChange={(event) => update('body', event.target.value)} className={`${inputClass} resize-y`} placeholder="Digite a mensagem…" />
                        <span className="mt-1 flex justify-between text-xs"><span className="text-red-600">{Array.isArray(errors.body) ? errors.body[0] : errors.body}</span><span className="text-neutral-400">{form.body.length}/4096</span></span>
                    </label>
                    <ScheduledImageField
                        image={form.image}
                        onChange={(image) => setForm((current) => ({ ...current, image, remove_image: false }))}
                        error={errors.image}
                        existingUrl={schedule?.media_url}
                        existingName={schedule?.media_name}
                        removeExisting={form.remove_image}
                        onRemoveExisting={() => setForm((current) => ({ ...current, image: null, remove_image: true }))}
                    />

                    <ScheduledTimingFields form={form} update={update} errors={errors} minimumSendAt={minimumSendAt} />

                    <footer className="mt-5 flex flex-col-reverse gap-2 border-t border-neutral-200 pt-4 dark:border-neutral-700 sm:flex-row sm:justify-end">
                        <button type="button" onClick={onClose} className="min-h-11 rounded-xl border border-neutral-300 px-4 text-sm font-medium transition hover:bg-neutral-50 dark:border-neutral-700 dark:hover:bg-neutral-800">Cancelar</button>
                        <button disabled={saving || !hasContent} className="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-brand-600 px-5 text-sm font-semibold text-white transition hover:bg-brand-700 disabled:cursor-not-allowed disabled:opacity-50">{saving ? <Loader2 className="h-4 w-4 animate-spin" /> : editing ? <Pencil className="h-4 w-4" /> : <CalendarPlus className="h-4 w-4" />}{saving ? 'Salvando…' : editing ? 'Salvar alterações' : 'Agendar mensagem'}</button>
                    </footer>
                </form>
            </section>
        </div>
    );
}
