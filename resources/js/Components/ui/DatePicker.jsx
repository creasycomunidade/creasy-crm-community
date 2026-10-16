import { useState, useRef, useEffect, useMemo, useCallback } from 'react';
import { Calendar, ChevronLeft, ChevronRight, X } from 'lucide-react';
import { useTranslation } from 'react-i18next';

/**
 * Custom calendar date picker — a drop-in replacement for the native
 * `<input type="date">` / `<input type="datetime-local">` controls so the UI
 * stays consistent across browsers and matches the design system.
 *
 * Value format mirrors the native inputs it replaces:
 *   mode="date"     → "yyyy-mm-dd"
 *   mode="datetime" → "yyyy-mm-ddThh:mm"
 *
 * Props:
 *   value       current value string (same format as the native input)
 *   onChange    (value: string) => void  — emits the same format ('' when cleared)
 *   mode        'date' (default) | 'datetime'
 *   min, max    optional bounds in "yyyy-mm-dd" (or datetime) format
 *   placeholder trigger placeholder text
 *   error       shows error styling when truthy
 *   disabled    disables the control
 *   className    applied to the root wrapper (controls width/layout)
 *   id, name, required, ...rest  forwarded to a hidden input for forms
 */

const pad2 = (n) => String(n).padStart(2, '0');

const DATE_RE = /^(\d{4})-(\d{2})-(\d{2})(?:T(\d{2}):(\d{2}))?/;

// Parse a value string into local date parts without UTC shifting.
function parseValue(str) {
    if (!str) return null;
    const m = DATE_RE.exec(str);
    if (!m) return null;
    const [, y, mo, d, h, mi] = m;
    return {
        year: +y,
        month: +mo - 1,
        day: +d,
        hour: h != null ? +h : 0,
        minute: mi != null ? +mi : 0,
    };
}

function partsToDate(p) {
    return new Date(p.year, p.month, p.day, p.hour || 0, p.minute || 0);
}

function formatValue(p, mode) {
    const date = `${p.year}-${pad2(p.month + 1)}-${pad2(p.day)}`;
    if (mode === 'datetime') return `${date}T${pad2(p.hour)}:${pad2(p.minute)}`;
    return date;
}

// Day-granularity number for min/max comparison (yyyymmdd).
function dayKey(year, month, day) {
    return year * 10000 + month * 100 + day;
}

export default function DatePicker({
    value,
    onChange,
    mode = 'date',
    min,
    max,
    placeholder,
    error = false,
    disabled = false,
    className = '',
    id,
    name,
    required = false,
    ...rest
}) {
    const { t, i18n } = useTranslation();
    const lang = i18n?.language || 'en';

    const [open, setOpen] = useState(false);
    const [view, setView] = useState('days'); // 'days' | 'months'
    const containerRef = useRef(null);
    const popoverRef = useRef(null);
    const [dropUp, setDropUp] = useState(false);

    const selected = useMemo(() => parseValue(value), [value]);

    // Month currently shown in the calendar.
    const [cursor, setCursor] = useState(() => {
        const base = selected || null;
        const now = new Date();
        return {
            year: base ? base.year : now.getFullYear(),
            month: base ? base.month : now.getMonth(),
        };
    });

    // Open the popover, jumping the calendar to the selected month (or today).
    const openPicker = () => {
        const now = new Date();
        setCursor({
            year: selected ? selected.year : now.getFullYear(),
            month: selected ? selected.month : now.getMonth(),
        });
        setView('days');
        setOpen(true);
    };

    const minKey = useMemo(() => {
        const p = parseValue(min);
        return p ? dayKey(p.year, p.month, p.day) : null;
    }, [min]);
    const maxKey = useMemo(() => {
        const p = parseValue(max);
        return p ? dayKey(p.year, p.month, p.day) : null;
    }, [max]);

    // Close on outside click.
    useEffect(() => {
        if (!open) return;
        const handler = (e) => {
            if (containerRef.current && !containerRef.current.contains(e.target)) {
                setOpen(false);
                setView('days');
            }
        };
        document.addEventListener('mousedown', handler);
        return () => document.removeEventListener('mousedown', handler);
    }, [open]);

    // Close on Escape.
    useEffect(() => {
        if (!open) return;
        const handler = (e) => {
            if (e.key === 'Escape') {
                setOpen(false);
                setView('days');
            }
        };
        document.addEventListener('keydown', handler);
        return () => document.removeEventListener('keydown', handler);
    }, [open]);

    // Flip the popover above the trigger when it would overflow the viewport.
    useEffect(() => {
        if (!open || !containerRef.current) return;
        const rect = containerRef.current.getBoundingClientRect();
        const estimated = mode === 'datetime' ? 430 : 360;
        setDropUp(rect.bottom + estimated > window.innerHeight && rect.top > estimated);
    }, [open, mode]);

    // Localized weekday narrow labels, week starting Sunday.
    const weekdays = useMemo(() => {
        const fmt = new Intl.DateTimeFormat(lang, { weekday: 'narrow' });
        // 2023-01-01 was a Sunday.
        return Array.from({ length: 7 }, (_, i) => fmt.format(new Date(2023, 0, 1 + i)));
    }, [lang]);

    const monthNames = useMemo(() => {
        const fmt = new Intl.DateTimeFormat(lang, { month: 'short' });
        return Array.from({ length: 12 }, (_, i) => fmt.format(new Date(2023, i, 1)));
    }, [lang]);

    const headerLabel = useMemo(
        () => new Intl.DateTimeFormat(lang, { month: 'long', year: 'numeric' })
            .format(new Date(cursor.year, cursor.month, 1)),
        [lang, cursor],
    );

    const triggerLabel = useMemo(() => {
        if (!selected) {
            return placeholder
                || (mode === 'datetime'
                    ? t('ui.date_select_datetime', 'Select date & time')
                    : t('ui.date_select', 'Select date'));
        }
        const opts = mode === 'datetime'
            ? { year: 'numeric', month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' }
            : { year: 'numeric', month: 'short', day: 'numeric' };
        return new Intl.DateTimeFormat(lang, opts).format(partsToDate(selected));
    }, [selected, mode, lang, placeholder, t]);

    // Build a 6-row grid of cells for the cursor month.
    const cells = useMemo(() => {
        const firstDow = new Date(cursor.year, cursor.month, 1).getDay();
        const start = new Date(cursor.year, cursor.month, 1 - firstDow);
        return Array.from({ length: 42 }, (_, i) => {
            const d = new Date(start.getFullYear(), start.getMonth(), start.getDate() + i);
            return {
                year: d.getFullYear(),
                month: d.getMonth(),
                day: d.getDate(),
                inMonth: d.getMonth() === cursor.month,
            };
        });
    }, [cursor]);

    const isDisabledDay = useCallback((c) => {
        const k = dayKey(c.year, c.month, c.day);
        if (minKey != null && k < minKey) return true;
        if (maxKey != null && k > maxKey) return true;
        return false;
    }, [minKey, maxKey]);

    const emit = useCallback((parts) => {
        if (parts == null) {
            onChange?.('');
            return;
        }
        onChange?.(formatValue(parts, mode));
    }, [onChange, mode]);

    const selectDay = (c) => {
        if (isDisabledDay(c)) return;
        const now = new Date();
        const next = {
            year: c.year,
            month: c.month,
            day: c.day,
            hour: selected ? selected.hour : now.getHours(),
            minute: selected ? selected.minute : 0,
        };
        emit(next);
        setCursor({ year: c.year, month: c.month });
        if (mode !== 'datetime') {
            setOpen(false);
            setView('days');
        }
    };

    const setTime = (hour, minute) => {
        const base = selected || (() => {
            const now = new Date();
            return { year: now.getFullYear(), month: now.getMonth(), day: now.getDate(), hour: 0, minute: 0 };
        })();
        emit({ ...base, hour, minute });
    };

    const goToToday = () => {
        const now = new Date();
        const c = { year: now.getFullYear(), month: now.getMonth(), day: now.getDate() };
        if (isDisabledDay(c)) return;
        selectDay(c);
        setCursor({ year: c.year, month: c.month });
    };

    const stepMonth = (delta) => {
        setCursor((c) => {
            const d = new Date(c.year, c.month + delta, 1);
            return { year: d.getFullYear(), month: d.getMonth() };
        });
    };

    const todayKey = useMemo(() => {
        const n = new Date();
        return dayKey(n.getFullYear(), n.getMonth(), n.getDate());
    }, []);

    const selectedKey = selected ? dayKey(selected.year, selected.month, selected.day) : null;

    const triggerClasses = [
        'w-full flex items-center gap-2 rounded-soft border bg-white dark:bg-neutral-800 px-3 py-2 text-sm text-left shadow-inner transition duration-150 focus:outline-none focus:ring-2',
        error
            ? 'border-red-500 focus:border-red-500 focus:ring-red-500/20'
            : 'border-soft border-neutral-300 dark:border-neutral-600 focus:border-brand-500 focus:ring-brand-500/20',
        disabled ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer',
    ].join(' ');

    return (
        <div ref={containerRef} className={`relative ${className}`}>
            {/* Hidden input keeps native form submission / validation working. */}
            <input type="hidden" id={id} name={name} value={value || ''} required={required} {...rest} />

            <button
                type="button"
                disabled={disabled}
                onClick={() => !disabled && (open ? setOpen(false) : openPicker())}
                className={triggerClasses}
                aria-haspopup="dialog"
                aria-expanded={open}
            >
                <Calendar className="h-4 w-4 shrink-0 text-neutral-400" />
                <span className={`flex-1 truncate ${selected ? 'text-neutral-900 dark:text-neutral-100' : 'text-neutral-400 dark:text-neutral-500'}`}>
                    {triggerLabel}
                </span>
                {selected && !disabled && (
                    <span
                        role="button"
                        tabIndex={-1}
                        onClick={(e) => { e.stopPropagation(); emit(null); }}
                        className="shrink-0 rounded p-0.5 text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-300"
                        aria-label={t('ui.date_clear', 'Clear')}
                    >
                        <X className="h-3.5 w-3.5" />
                    </span>
                )}
            </button>

            {open && (
                <div
                    ref={popoverRef}
                    className={`absolute z-50 w-[17rem] rounded-soft-lg border border-soft border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-900 p-3 shadow-soft-lg ${dropUp ? 'bottom-full mb-2' : 'top-full mt-2'} left-0 rtl:left-auto rtl:right-0`}
                    role="dialog"
                >
                    {/* Header */}
                    <div className="mb-2 flex items-center justify-between">
                        <button
                            type="button"
                            onClick={() => setView((v) => (v === 'days' ? 'months' : 'days'))}
                            className="rounded-soft px-2 py-1 text-sm font-semibold text-neutral-800 dark:text-neutral-100 hover:bg-neutral-100 dark:hover:bg-neutral-800 transition"
                        >
                            {view === 'days' ? headerLabel : cursor.year}
                        </button>
                        <div className="flex items-center gap-1">
                            <button
                                type="button"
                                onClick={() => (view === 'days' ? stepMonth(-1) : setCursor((c) => ({ ...c, year: c.year - 1 })))}
                                className="rounded-soft p-1 text-neutral-500 hover:bg-neutral-100 dark:hover:bg-neutral-800 transition"
                                aria-label={t('ui.date_prev', 'Previous')}
                            >
                                <ChevronLeft className="h-4 w-4 rtl:rotate-180" />
                            </button>
                            <button
                                type="button"
                                onClick={() => (view === 'days' ? stepMonth(1) : setCursor((c) => ({ ...c, year: c.year + 1 })))}
                                className="rounded-soft p-1 text-neutral-500 hover:bg-neutral-100 dark:hover:bg-neutral-800 transition"
                                aria-label={t('ui.date_next', 'Next')}
                            >
                                <ChevronRight className="h-4 w-4 rtl:rotate-180" />
                            </button>
                        </div>
                    </div>

                    {view === 'days' ? (
                        <>
                            <div className="grid grid-cols-7 gap-0.5">
                                {weekdays.map((w, i) => (
                                    <div key={i} className="py-1 text-center text-xs font-medium text-neutral-400">
                                        {w}
                                    </div>
                                ))}
                                {cells.map((c, i) => {
                                    const k = dayKey(c.year, c.month, c.day);
                                    const isSelected = k === selectedKey;
                                    const isToday = k === todayKey;
                                    const disabledDay = isDisabledDay(c);
                                    return (
                                        <button
                                            type="button"
                                            key={i}
                                            disabled={disabledDay}
                                            onClick={() => selectDay(c)}
                                            className={[
                                                'h-8 w-8 mx-auto flex items-center justify-center rounded-soft text-sm transition',
                                                isSelected
                                                    ? 'bg-brand-500 text-white font-semibold hover:bg-brand-600'
                                                    : disabledDay
                                                        ? 'text-neutral-300 dark:text-neutral-600 cursor-not-allowed'
                                                        : c.inMonth
                                                            ? 'text-neutral-700 dark:text-neutral-200 hover:bg-brand-50 dark:hover:bg-neutral-800'
                                                            : 'text-neutral-400 dark:text-neutral-500 hover:bg-neutral-100 dark:hover:bg-neutral-800',
                                                !isSelected && isToday ? 'ring-1 ring-brand-400 font-semibold' : '',
                                            ].join(' ')}
                                        >
                                            {c.day}
                                        </button>
                                    );
                                })}
                            </div>

                            {mode === 'datetime' && (
                                <div className="mt-3 flex items-center gap-2 border-t border-soft border-neutral-100 dark:border-neutral-800 pt-3">
                                    <span className="text-xs font-medium text-neutral-500 dark:text-neutral-400">
                                        {t('ui.date_time', 'Time')}
                                    </span>
                                    <select
                                        value={selected ? selected.hour : ''}
                                        onChange={(e) => setTime(+e.target.value, selected ? selected.minute : 0)}
                                        className="rounded-soft border border-soft border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 px-2 py-1 text-sm text-neutral-900 dark:text-neutral-100 focus:outline-none focus:ring-2 focus:ring-brand-500/20"
                                    >
                                        {!selected && <option value="">--</option>}
                                        {Array.from({ length: 24 }, (_, h) => (
                                            <option key={h} value={h}>{pad2(h)}</option>
                                        ))}
                                    </select>
                                    <span className="text-neutral-400">:</span>
                                    <select
                                        value={selected ? selected.minute : ''}
                                        onChange={(e) => setTime(selected ? selected.hour : new Date().getHours(), +e.target.value)}
                                        className="rounded-soft border border-soft border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 px-2 py-1 text-sm text-neutral-900 dark:text-neutral-100 focus:outline-none focus:ring-2 focus:ring-brand-500/20"
                                    >
                                        {!selected && <option value="">--</option>}
                                        {Array.from({ length: 60 }, (_, mi) => (
                                            <option key={mi} value={mi}>{pad2(mi)}</option>
                                        ))}
                                    </select>
                                </div>
                            )}
                        </>
                    ) : (
                        <div className="grid grid-cols-3 gap-1.5">
                            {monthNames.map((mName, i) => (
                                <button
                                    type="button"
                                    key={i}
                                    onClick={() => { setCursor((c) => ({ ...c, month: i })); setView('days'); }}
                                    className={[
                                        'rounded-soft py-2 text-sm transition',
                                        i === cursor.month
                                            ? 'bg-brand-500 text-white font-semibold'
                                            : 'text-neutral-700 dark:text-neutral-200 hover:bg-brand-50 dark:hover:bg-neutral-800',
                                    ].join(' ')}
                                >
                                    {mName}
                                </button>
                            ))}
                        </div>
                    )}

                    {/* Footer */}
                    <div className="mt-3 flex items-center justify-between border-t border-soft border-neutral-100 dark:border-neutral-800 pt-2">
                        <button
                            type="button"
                            onClick={() => emit(null)}
                            className="text-sm font-medium text-neutral-500 hover:text-neutral-700 dark:text-neutral-400 dark:hover:text-neutral-200 transition"
                        >
                            {t('ui.date_clear', 'Clear')}
                        </button>
                        <button
                            type="button"
                            onClick={goToToday}
                            className="text-sm font-medium text-brand-600 hover:text-brand-700 dark:text-brand-400 dark:hover:text-brand-300 transition"
                        >
                            {t('ui.date_today', 'Today')}
                        </button>
                    </div>
                </div>
            )}
        </div>
    );
}
