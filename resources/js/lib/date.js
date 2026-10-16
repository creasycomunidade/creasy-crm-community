/**
 * Format a date/datetime string using the current user's timezone.
 *
 * Usage:
 *   import { formatDate, formatDateTime } from '@/lib/date';
 *   formatDate('2026-01-15T12:00:00Z', 'America/New_York') // "Jan 15, 2026"
 */

/**
 * Format a date value as a localized date string in the given timezone.
 * Falls back to UTC if timezone is not provided.
 */
export function formatDate(value, timezone = 'UTC', locale = undefined) {
    if (! value) return '—';
    try {
        return new Intl.DateTimeFormat(locale, {
            year:   'numeric',
            month:  'short',
            day:    'numeric',
            timeZone: timezone,
        }).format(new Date(value));
    } catch {
        return String(value);
    }
}

/**
 * Format a date value as a localized date + time string in the given timezone.
 */
export function formatDateTime(value, timezone = 'UTC', locale = undefined) {
    if (! value) return '—';
    try {
        return new Intl.DateTimeFormat(locale, {
            year:   'numeric',
            month:  'short',
            day:    'numeric',
            hour:   '2-digit',
            minute: '2-digit',
            timeZone: timezone,
        }).format(new Date(value));
    } catch {
        return String(value);
    }
}

/**
 * React hook that provides formatDate/formatDateTime bound to the current user's timezone.
 * Uses the `timezone` prop shared by Inertia (HandleInertiaRequests).
 *
 * Usage:
 *   const { fmt, fmtDt } = useDateFormat();
 *   fmtDt(conversation.created_at)
 */
export function useDateFormat() {
    const { timezone = 'UTC' } = usePage().props;
    return {
        fmt:   (v) => formatDate(v, timezone),
        fmtDt: (v) => formatDateTime(v, timezone),
    };
}
import { usePage } from '@inertiajs/react';
