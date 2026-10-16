import { useState, useRef, useEffect, useMemo } from 'react';
import { Search, Clock, ChevronDown, X } from 'lucide-react';
import { useTranslation } from 'react-i18next';

const ALL_TIMEZONES = Intl.supportedValuesOf
    ? Intl.supportedValuesOf('timeZone')
    : ['UTC', 'America/Sao_Paulo', 'America/New_York', 'America/Los_Angeles', 'Europe/London',
       'Asia/Kolkata', 'Asia/Dubai', 'Asia/Bangkok', 'Asia/Tokyo', 'Australia/Sydney',
       'Pacific/Auckland', 'America/Chicago', 'America/Sao_Paulo', 'Europe/Paris',
       'Europe/Berlin', 'Africa/Cairo', 'Asia/Singapore', 'Asia/Shanghai'];

function getUtcOffset(tz) {
    try {
        const now = new Date();
        const parts = new Intl.DateTimeFormat('en', {
            timeZone: tz, timeZoneName: 'shortOffset',
        }).formatToParts(now);
        return parts.find(p => p.type === 'timeZoneName')?.value ?? '';
    } catch {
        return '';
    }
}

function getOffsetMinutes(tz) {
    try {
        const now = new Date();
        const local = new Date(now.toLocaleString('en-US', { timeZone: tz }));
        return Math.round((local - now) / 60000);
    } catch {
        return 0;
    }
}

export default function TimezonePicker({ value, onChange, className = '' }) {
    const { t } = useTranslation();
    const [open, setOpen] = useState(false);
    const [search, setSearch] = useState('');
    const containerRef = useRef(null);
    const searchRef = useRef(null);

    // Close on outside click
    useEffect(() => {
        const handler = (e) => {
            if (containerRef.current && !containerRef.current.contains(e.target)) {
                setOpen(false);
                setSearch('');
            }
        };
        document.addEventListener('mousedown', handler);
        return () => document.removeEventListener('mousedown', handler);
    }, []);

    // Focus search input when dropdown opens
    useEffect(() => {
        if (open) setTimeout(() => searchRef.current?.focus(), 50);
    }, [open]);

    const filtered = useMemo(() => {
        if (!search.trim()) return ALL_TIMEZONES;
        const q = search.toLowerCase().replace(/\s+/g, '_');
        return ALL_TIMEZONES.filter(tz =>
            tz.toLowerCase().includes(q) ||
            tz.replace(/_/g, ' ').toLowerCase().includes(search.toLowerCase())
        );
    }, [search]);

    const select = (tz) => {
        onChange(tz);
        setOpen(false);
        setSearch('');
    };

    const displayLabel = value
        ? `${value.replace(/_/g, ' ')} (${getUtcOffset(value)})`
        : 'Select timezone…';

    return (
        <div ref={containerRef} className={`relative ${className}`}>
            {/* Trigger */}
            <button
                type="button"
                onClick={() => setOpen(v => !v)}
                className="w-full flex items-center gap-2 rounded-lg border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 px-3 py-2 text-sm text-left focus:outline-none focus:ring-2 focus:ring-brand-500 transition"
            >
                <Clock className="h-3.5 w-3.5 shrink-0 text-neutral-400" />
                <span className="flex-1 truncate text-neutral-800 dark:text-neutral-200">{displayLabel}</span>
                <ChevronDown className={`h-3.5 w-3.5 shrink-0 text-neutral-400 transition-transform ${open ? 'rotate-180' : ''}`} />
            </button>

            {/* Dropdown */}
            {open && (
                <div className="absolute z-50 mt-1 w-full min-w-[260px] rounded-xl border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-900 shadow-xl overflow-hidden">
                    {/* Search */}
                    <div className="flex items-center gap-2 px-3 py-2.5 border-b border-neutral-100 dark:border-neutral-800">
                        <Search className="h-3.5 w-3.5 shrink-0 text-neutral-400" />
                        <input
                            ref={searchRef}
                            type="text"
                            value={search}
                            onChange={e => setSearch(e.target.value)}
                            placeholder={t('ui.timezone_search')}
                            className="flex-1 bg-transparent text-sm text-neutral-800 dark:text-neutral-200 placeholder-neutral-400 focus:outline-none"
                        />
                        {search && (
                            <button type="button" onClick={() => setSearch('')} className="text-neutral-400 hover:text-neutral-600">
                                <X className="h-3.5 w-3.5" />
                            </button>
                        )}
                    </div>

                    {/* List */}
                    <ul className="max-h-56 overflow-y-auto py-1">
                        {filtered.length === 0 && (
                            <li className="px-4 py-3 text-sm text-neutral-400 text-center">{t('ui.no_timezones')}</li>
                        )}
                        {filtered.map(tz => (
                            <li key={tz}>
                                <button
                                    type="button"
                                    onClick={() => select(tz)}
                                    className={`w-full flex items-center justify-between gap-3 px-4 py-2 text-sm text-left hover:bg-neutral-50 dark:hover:bg-neutral-800 transition ${
                                        tz === value ? 'bg-brand-50 dark:bg-brand-900/20 text-brand-600 dark:text-brand-400 font-medium' : 'text-neutral-700 dark:text-neutral-300'
                                    }`}
                                >
                                    <span className="truncate">{tz.replace(/_/g, ' ')}</span>
                                    <span className="shrink-0 text-xs text-neutral-400 font-mono">{getUtcOffset(tz)}</span>
                                </button>
                            </li>
                        ))}
                    </ul>
                </div>
            )}
        </div>
    );
}
