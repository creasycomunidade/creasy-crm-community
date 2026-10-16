import { useState } from 'react';
import { Clock, Globe, X } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import TimezonePicker from '@/Components/TimezonePicker';

export const DAYS = [
    { key: 'mon', labelKey: 'whatsapp.widget_day_mon' },
    { key: 'tue', labelKey: 'whatsapp.widget_day_tue' },
    { key: 'wed', labelKey: 'whatsapp.widget_day_wed' },
    { key: 'thu', labelKey: 'whatsapp.widget_day_thu' },
    { key: 'fri', labelKey: 'whatsapp.widget_day_fri' },
    { key: 'sat', labelKey: 'whatsapp.widget_day_sat' },
    { key: 'sun', labelKey: 'whatsapp.widget_day_sun' },
];

const DEFAULT_HOURS = Object.fromEntries(
    DAYS.map(d => [d.key, { enabled: ['mon','tue','wed','thu','fri'].includes(d.key), open: '09:00', close: '18:00' }])
);

export const DEFAULT_FORM = {
    name: '',
    display_phone: '',
    prefilled_message: '',
    greeting_message: '',
    agent_name: 'Support',
    agent_avatar_color: '#25D366',
    button_color: '#25D366',
    position: 'bottom_right',
    allowed_domains: [],
    working_hours_json: null,
};

export function widgetToForm(w) {
    return {
        name: w.name ?? '',
        display_phone: w.display_phone ?? '',
        prefilled_message: w.prefilled_message ?? '',
        greeting_message: w.greeting_message ?? '',
        agent_name: w.agent_name ?? 'Support',
        agent_avatar_color: w.agent_avatar_color ?? '#25D366',
        button_color: w.button_color ?? '#25D366',
        position: w.position ?? 'bottom_right',
        allowed_domains: w.allowed_domains ?? [],
        working_hours_json: w.working_hours_json ?? null,
    };
}

export const inputCls = (err) =>
    `w-full rounded-lg border ${err ? 'border-red-400 focus:ring-red-500' : 'border-neutral-300 dark:border-neutral-600 focus:ring-brand-500'} bg-white dark:bg-neutral-800 px-3 py-2 text-sm text-neutral-900 dark:text-neutral-100 placeholder-neutral-400 focus:outline-none focus:ring-2 transition`;

export function Field({ label, error, hint, children, className = '' }) {
    return (
        <div className={className}>
            <label className="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-1.5">{label}</label>
            {children}
            {hint && !error && <p className="mt-1.5 text-xs text-neutral-400">{hint}</p>}
            {error && <p className="mt-1.5 text-xs text-red-500">{error}</p>}
        </div>
    );
}

export function WidgetPreview({ data }) {
    const { t } = useTranslation();
    const posRight = data.position !== 'bottom_left';
    const color = data.button_color || '#25D366';
    const agentInitial = (data.agent_name || 'S')[0].toUpperCase();

    return (
        <div className="relative bg-[url('data:image/svg+xml,%3Csvg width=\'20\' height=\'20\' xmlns=\'http://www.w3.org/2000/svg\'%3E%3Crect width=\'20\' height=\'20\' fill=\'%23f8fafc\'/%3E%3Crect width=\'10\' height=\'10\' fill=\'%23f1f5f9\'/%3E%3Crect x=\'10\' y=\'10\' width=\'10\' height=\'10\' fill=\'%23f1f5f9\'/%3E%3C/svg%3E')] dark:bg-neutral-800 rounded-xl h-72 overflow-hidden border border-neutral-200 dark:border-neutral-700">
            <div className="absolute inset-0 flex items-center justify-center pointer-events-none">
                <span className="text-xs text-neutral-400 bg-white/70 dark:bg-neutral-900/70 px-3 py-1 rounded-full">{t('whatsapp.widget_live_preview')}</span>
            </div>
            <div className={`absolute bottom-5 ${posRight ? 'right-5' : 'left-5'}`}>
                {data.greeting_message && (
                    <div className={`absolute bottom-[68px] ${posRight ? 'right-0' : 'left-0'} w-56 bg-white dark:bg-neutral-700 rounded-2xl shadow-xl overflow-hidden`}
                        style={{ transformOrigin: posRight ? 'bottom right' : 'bottom left' }}>
                        <div className="px-3.5 py-3 flex items-center gap-2.5" style={{ background: color }}>
                            <div className="w-8 h-8 rounded-full flex items-center justify-center text-white text-sm font-bold flex-shrink-0"
                                style={{ background: data.agent_avatar_color || color, filter: 'brightness(.85)' }}>
                                {agentInitial}
                            </div>
                            <div>
                                <div className="text-white text-xs font-semibold leading-tight">{data.agent_name || t('whatsapp.widget_default_agent_name')}</div>
                                <div className="text-white/80 text-[11px] flex items-center gap-1">
                                    <span className="w-1.5 h-1.5 rounded-full bg-green-300 inline-block" />
                                    {t('whatsapp.widget_replies_instantly')}
                                </div>
                            </div>
                        </div>
                        <div className="px-3.5 py-3">
                            <div className="bg-neutral-100 dark:bg-neutral-600 rounded-[4px_12px_12px_12px] px-3 py-2 text-xs text-neutral-700 dark:text-neutral-100 leading-relaxed mb-3">
                                {data.greeting_message}
                            </div>
                            <div className="text-center text-xs font-semibold text-white rounded-lg py-2" style={{ background: color }}>
                                {t('whatsapp.widget_start_chat')}
                            </div>
                        </div>
                    </div>
                )}
                <div className="relative w-14 h-14">
                    <div className="absolute inset-0 rounded-full animate-ping opacity-30" style={{ background: color }} />
                    <div className="relative w-14 h-14 rounded-full flex items-center justify-center shadow-xl cursor-pointer hover:scale-110 transition-transform" style={{ background: color }}>
                        <svg xmlns="http://www.w3.org/2000/svg" width="26" height="26" fill="white" viewBox="0 0 24 24">
                            <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
                        </svg>
                    </div>
                </div>
            </div>
        </div>
    );
}

export function WorkingHoursEditor({ value, onChange }) {
    const { t } = useTranslation();
    const enabled = value?.enabled ?? false;
    const schedule = value?.schedule ?? DEFAULT_HOURS;
    const timezone = value?.timezone ?? Intl.DateTimeFormat().resolvedOptions().timeZone;

    const set = (patch) => onChange({ enabled, schedule, timezone, ...(value ?? {}), ...patch });
    const setDay = (key, patch) => set({ schedule: { ...schedule, [key]: { ...(schedule[key] ?? DEFAULT_HOURS[key]), ...patch } } });

    return (
        <div className="space-y-5">
            <div className="flex items-center justify-between p-4 rounded-xl bg-neutral-50 dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700">
                <div>
                    <p className="text-sm font-medium text-neutral-800 dark:text-neutral-200">{t('whatsapp.widget_working_hours')}</p>
                    <p className="text-xs text-neutral-500 mt-0.5">{t('whatsapp.widget_working_hours_hint')}</p>
                </div>
                <Toggle checked={enabled} onChange={v => set({ enabled: v })} />
            </div>

            {enabled && (
                <div className="space-y-4">
                    <Field label={t('whatsapp.widget_timezone')}>
                        <TimezonePicker
                            value={timezone}
                            onChange={tz => set({ timezone: tz })}
                        />
                    </Field>
                    <div className="rounded-xl border border-neutral-200 dark:border-neutral-700 overflow-hidden">
                        <div className="bg-neutral-50 dark:bg-neutral-800 px-4 py-2.5 border-b border-neutral-200 dark:border-neutral-700">
                            <p className="text-xs font-semibold text-neutral-500 uppercase tracking-wide">{t('whatsapp.widget_schedule')}</p>
                        </div>
                        <div className="divide-y divide-neutral-100 dark:divide-neutral-800">
                            {DAYS.map(d => {
                                const day = schedule[d.key] ?? DEFAULT_HOURS[d.key];
                                return (
                                    <div key={d.key} className="flex items-center gap-4 px-4 py-3">
                                        <Toggle checked={day.enabled} onChange={v => setDay(d.key, { enabled: v })} />
                                        <span className={`w-24 text-sm ${day.enabled ? 'text-neutral-800 dark:text-neutral-200 font-medium' : 'text-neutral-400'}`}>{t(d.labelKey)}</span>
                                        {day.enabled ? (
                                            <div className="flex items-center gap-2 ml-auto">
                                                <input type="time" value={day.open} onChange={e => setDay(d.key, { open: e.target.value })}
                                                    className="rounded-lg border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 px-2.5 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500" />
                                                <span className="text-neutral-400 text-sm">–</span>
                                                <input type="time" value={day.close} onChange={e => setDay(d.key, { close: e.target.value })}
                                                    className="rounded-lg border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 px-2.5 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500" />
                                            </div>
                                        ) : (
                                            <span className="ml-auto text-sm text-neutral-400">{t('whatsapp.widget_closed')}</span>
                                        )}
                                    </div>
                                );
                            })}
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}

export function DomainEditor({ value, onChange }) {
    const { t } = useTranslation();
    const [input, setInput] = useState('');
    const domains = value ?? [];

    const add = () => {
        const d = input.trim().toLowerCase().replace(/^https?:\/\//, '').split('/')[0];
        if (d && !domains.includes(d)) onChange([...domains, d]);
        setInput('');
    };

    return (
        <div className="space-y-4">
            <div className="p-4 rounded-xl bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 text-sm text-blue-800 dark:text-blue-200">
                <strong>{t('whatsapp.widget_domains_tip_label')}</strong> {t('whatsapp.widget_domains_tip')}
            </div>
            <Field label={t('whatsapp.widget_add_domain')}>
                <div className="flex gap-2">
                    <input type="text" value={input} onChange={e => setInput(e.target.value)}
                        onKeyDown={e => e.key === 'Enter' && (e.preventDefault(), add())}
                        placeholder="example.com"
                        className={inputCls() + ' flex-1'} />
                    <button type="button" onClick={add}
                        className="rounded-lg border border-neutral-300 dark:border-neutral-600 px-4 py-2 text-sm font-medium text-neutral-700 dark:text-neutral-300 hover:bg-neutral-50 dark:hover:bg-neutral-700 transition">
                        {t('common.add')}
                    </button>
                </div>
            </Field>
            {domains.length > 0 && (
                <div className="rounded-xl border border-neutral-200 dark:border-neutral-700 overflow-hidden">
                    <div className="bg-neutral-50 dark:bg-neutral-800 px-4 py-2.5 border-b border-neutral-200 dark:border-neutral-700">
                        <p className="text-xs font-semibold text-neutral-500 uppercase tracking-wide">{t('whatsapp.widget_allowed_domains_count', { count: domains.length })}</p>
                    </div>
                    <div className="divide-y divide-neutral-100 dark:divide-neutral-800">
                        {domains.map(d => (
                            <div key={d} className="flex items-center justify-between px-4 py-3">
                                <div className="flex items-center gap-2.5">
                                    <Globe className="h-4 w-4 text-neutral-400 flex-shrink-0" />
                                    <span className="text-sm text-neutral-800 dark:text-neutral-200 font-mono">{d}</span>
                                </div>
                                <button type="button" onClick={() => onChange(domains.filter(x => x !== d))}
                                    className="p-1 rounded text-neutral-400 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 transition">
                                    <X className="h-4 w-4" />
                                </button>
                            </div>
                        ))}
                    </div>
                </div>
            )}
        </div>
    );
}

export function Toggle({ checked, onChange }) {
    return (
        <button type="button" onClick={() => onChange(!checked)} role="switch" aria-checked={checked}
            className={`relative w-10 h-6 rounded-full transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 flex-shrink-0 ${checked ? 'bg-brand-600' : 'bg-neutral-300 dark:bg-neutral-600'}`}>
            <span className={`absolute top-1 left-1 w-4 h-4 bg-white rounded-full shadow transition-transform ${checked ? 'translate-x-4' : ''}`} />
        </button>
    );
}
