import { CalendarDays, Clock3, ImagePlus, Repeat2, Trash2, Upload } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';

const UNITS = [
    ['minute', 'Minutos'],
    ['hour', 'Horas'],
    ['day', 'Dias'],
    ['week', 'Semanas'],
    ['month', 'Meses'],
];

const fieldClass = 'mt-1 min-h-11 w-full rounded-xl border border-neutral-300 bg-white px-3 py-2 text-sm outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 dark:border-neutral-700 dark:bg-neutral-900';

function errorText(error) {
    return Array.isArray(error) ? error[0] : error;
}

function datePart(value) {
    return String(value || '').split('T')[0] || '';
}

function timePart(value, fallback = '') {
    return String(value || '').split('T')[1]?.slice(0, 5) || fallback;
}

function updateDateTime(value, part, next, fallbackTime = '09:00') {
    const date = part === 'date' ? next : datePart(value);
    const time = part === 'time' ? next : timePart(value, fallbackTime);
    return date ? `${date}T${time || fallbackTime}` : '';
}

export function ScheduledImageField({ image, onChange, error, existingUrl = '', existingName = '', removeExisting = false, onRemoveExisting = () => {} }) {
    const inputRef = useRef(null);
    const [clientError, setClientError] = useState('');
    const preview = useMemo(() => image ? URL.createObjectURL(image) : '', [image]);

    useEffect(() => {
        return () => {
            if (preview) URL.revokeObjectURL(preview);
        };
    }, [preview]);

    const selectImage = (file) => {
        setClientError('');
        if (!file) return;
        if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
            setClientError('Use uma imagem JPG, PNG ou WebP.');
            return;
        }
        if (file.size > 10 * 1024 * 1024) {
            setClientError('A imagem deve ter no máximo 10 MB.');
            return;
        }
        onChange(file);
    };

    const remove = () => {
        onChange(null);
        setClientError('');
        if (inputRef.current) inputRef.current.value = '';
    };

    return (
        <div className="mt-4">
            <span className="block text-sm font-medium">Imagem <span className="font-normal text-neutral-400">(opcional)</span></span>
            <input ref={inputRef} type="file" accept="image/jpeg,image/png,image/webp" className="sr-only" onChange={(event) => selectImage(event.target.files?.[0])} />
            {preview ? (
                <div className="mt-2 flex items-center gap-3 rounded-xl border border-neutral-200 bg-neutral-50 p-3 dark:border-neutral-700 dark:bg-neutral-800/60">
                    <img src={preview} alt="Pré-visualização da imagem agendada" className="h-20 w-20 shrink-0 rounded-lg object-cover" />
                    <div className="min-w-0 flex-1">
                        <p className="truncate text-sm font-medium">{image.name}</p>
                        <p className="mt-1 text-xs text-neutral-500">{(image.size / 1024 / 1024).toFixed(1)} MB · pronta para envio</p>
                        <button type="button" onClick={() => inputRef.current?.click()} className="mt-2 min-h-11 rounded-lg px-2 text-xs font-semibold text-brand-600 transition hover:bg-brand-50 focus:outline-none focus:ring-2 focus:ring-brand-500 dark:hover:bg-brand-950/30">Trocar imagem</button>
                    </div>
                    <button type="button" onClick={remove} aria-label="Remover imagem" className="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl text-red-600 transition hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-500 dark:hover:bg-red-950/30"><Trash2 className="h-4 w-4" /></button>
                </div>
            ) : existingUrl && !removeExisting ? (
                <div className="mt-2 flex items-center gap-3 rounded-xl border border-neutral-200 bg-neutral-50 p-3 dark:border-neutral-700 dark:bg-neutral-800/60">
                    <img src={existingUrl} alt="Imagem atualmente vinculada à mensagem" className="h-20 w-20 shrink-0 rounded-lg object-cover" />
                    <div className="min-w-0 flex-1">
                        <p className="truncate text-sm font-medium">{existingName || 'Imagem agendada'}</p>
                        <p className="mt-1 text-xs text-neutral-500">Imagem atual · será mantida se nenhuma alteração for feita</p>
                        <button type="button" onClick={() => inputRef.current?.click()} className="mt-2 min-h-11 rounded-lg px-2 text-xs font-semibold text-brand-600 transition hover:bg-brand-50 focus:outline-none focus:ring-2 focus:ring-brand-500 dark:hover:bg-brand-950/30">Trocar imagem</button>
                    </div>
                    <button type="button" onClick={onRemoveExisting} aria-label="Remover imagem atual" className="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl text-red-600 transition hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-500 dark:hover:bg-red-950/30"><Trash2 className="h-4 w-4" /></button>
                </div>
            ) : (
                <button type="button" onClick={() => inputRef.current?.click()} className="mt-2 flex min-h-24 w-full items-center justify-center gap-3 rounded-xl border border-dashed border-neutral-300 bg-neutral-50 px-4 text-left transition hover:border-brand-400 hover:bg-brand-50/50 focus:outline-none focus:ring-2 focus:ring-brand-500 dark:border-neutral-700 dark:bg-neutral-800/50 dark:hover:bg-brand-950/20">
                    <span className="flex h-11 w-11 items-center justify-center rounded-xl bg-brand-100 text-brand-700 dark:bg-brand-950/40 dark:text-brand-300"><ImagePlus className="h-5 w-5" /></span>
                    <span><b className="block text-sm">Adicionar imagem</b><small className="text-neutral-500">JPG, PNG ou WebP · até 10 MB</small></span>
                    <Upload className="ml-auto h-4 w-4 text-neutral-400" />
                </button>
            )}
            {(clientError || errorText(error)) && <p role="alert" className="mt-1 text-xs text-red-600">{clientError || errorText(error)}</p>}
        </div>
    );
}

export function ScheduledTimingFields({ form, update, errors = {}, minimumSendAt }) {
    const recurring = form.recurrence_unit !== 'once';
    const minimumDate = datePart(minimumSendAt);

    return (
        <>
            <div className="mt-4 grid gap-4 sm:grid-cols-2">
                <label className="text-sm font-medium">Dia do primeiro envio
                    <span className="relative block"><CalendarDays className="pointer-events-none absolute left-3 top-4 h-4 w-4 text-neutral-400" /><input required type="date" min={minimumDate} value={datePart(form.send_at)} onChange={(event) => update('send_at', updateDateTime(form.send_at, 'date', event.target.value))} className={`${fieldClass} pl-10`} /></span>
                </label>
                <label className="text-sm font-medium">Horário
                    <span className="relative block"><Clock3 className="pointer-events-none absolute left-3 top-4 h-4 w-4 text-neutral-400" /><input required type="time" step="300" min={datePart(form.send_at) === minimumDate ? timePart(minimumSendAt) : undefined} value={timePart(form.send_at, '09:00')} onChange={(event) => update('send_at', updateDateTime(form.send_at, 'time', event.target.value))} className={`${fieldClass} pl-10 tabular-nums`} /></span>
                </label>
                {errorText(errors.send_at) && <p role="alert" className="-mt-3 text-xs text-red-600 sm:col-span-2">{errorText(errors.send_at)}</p>}
            </div>

            <fieldset className="mt-5"><legend className="text-sm font-medium">Tipo de agendamento</legend>
                <div className="mt-2 grid gap-3 sm:grid-cols-2">
                    <label className={`flex min-h-16 cursor-pointer items-center gap-3 rounded-xl border p-3 transition ${!recurring ? 'border-brand-500 bg-brand-50 dark:bg-brand-950/25' : 'border-neutral-200 hover:border-brand-300 dark:border-neutral-700'}`}><input type="radio" checked={!recurring} onChange={() => update('recurrence_unit', 'once')} /><Clock3 className="h-5 w-5 text-brand-600" /><span><b className="block text-sm">Enviar uma vez</b><small className="text-neutral-500">Um único envio na data escolhida</small></span></label>
                    <label className={`flex min-h-16 cursor-pointer items-center gap-3 rounded-xl border p-3 transition ${recurring ? 'border-brand-500 bg-brand-50 dark:bg-brand-950/25' : 'border-neutral-200 hover:border-brand-300 dark:border-neutral-700'}`}><input type="radio" checked={recurring} onChange={() => update('recurrence_unit', 'day')} /><Repeat2 className="h-5 w-5 text-brand-600" /><span><b className="block text-sm">Repetir em loop</b><small className="text-neutral-500">Continua até o limite ou cancelamento</small></span></label>
                </div>
            </fieldset>

            {recurring && <div className="mt-5 rounded-xl bg-neutral-50 p-4 dark:bg-neutral-800/60">
                <label className="block text-sm font-medium">Repetir a cada</label>
                <div className="mt-2 grid gap-2 sm:grid-cols-[8rem_1fr]">
                    <input aria-label="Intervalo da repetição" required type="number" min="1" max="365" value={form.recurrence_interval} onChange={(event) => update('recurrence_interval', event.target.value)} className={`${fieldClass} mt-0 tabular-nums`} />
                    <div className="grid grid-cols-2 gap-2 sm:grid-cols-5" role="group" aria-label="Unidade da repetição">
                        {UNITS.map(([value, label]) => <button key={value} type="button" aria-pressed={form.recurrence_unit === value} onClick={() => update('recurrence_unit', value)} className={`min-h-11 rounded-xl border px-2 text-xs font-semibold transition focus:outline-none focus:ring-2 focus:ring-brand-500 ${form.recurrence_unit === value ? 'border-brand-500 bg-brand-600 text-white' : 'border-neutral-200 bg-white text-neutral-600 hover:border-brand-300 dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-300'}`}>{label}</button>)}
                    </div>
                </div>
                {errorText(errors.recurrence_interval) && <p role="alert" className="mt-1 text-xs text-red-600">{errorText(errors.recurrence_interval)}</p>}

                <div className="mt-4 grid gap-4 sm:grid-cols-2">
                    <label className="text-sm font-medium">Quantidade máxima <span className="font-normal text-neutral-400">(opcional)</span><input type="number" min="1" max="10000" value={form.max_occurrences} onChange={(event) => update('max_occurrences', event.target.value)} className={fieldClass} placeholder="Sem limite" /></label>
                    <div>
                        <span className="text-sm font-medium">Encerrar em <span className="font-normal text-neutral-400">(opcional)</span></span>
                        <div className="mt-1 grid grid-cols-[1fr_7rem] gap-2">
                            <input aria-label="Dia final" type="date" min={datePart(form.send_at)} value={datePart(form.ends_at)} onChange={(event) => update('ends_at', updateDateTime(form.ends_at, 'date', event.target.value, '23:55'))} className={`${fieldClass} mt-0`} />
                            <input aria-label="Horário final" type="time" step="300" disabled={!datePart(form.ends_at)} value={timePart(form.ends_at, '23:55')} onChange={(event) => update('ends_at', updateDateTime(form.ends_at, 'time', event.target.value, '23:55'))} className={`${fieldClass} mt-0 tabular-nums disabled:cursor-not-allowed disabled:opacity-50`} />
                        </div>
                    </div>
                </div>
                {errorText(errors.ends_at) && <p role="alert" className="mt-1 text-xs text-red-600">{errorText(errors.ends_at)}</p>}
                <p className="mt-3 text-xs text-neutral-500">Sem quantidade e sem data final, o loop continua até ser pausado ou cancelado.</p>
            </div>}
        </>
    );
}
