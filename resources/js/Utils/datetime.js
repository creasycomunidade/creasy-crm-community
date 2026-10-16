/**
 * Timezone-aware datetime helpers.
 *
 * The backend stores campaign `schedule_at` as UTC. Browsers' native
 * `<input type="datetime-local">` reads & writes wall-clock strings (no
 * timezone). These helpers bridge the two while honouring the campaign's
 * chosen IANA timezone (e.g. "America/Sao_Paulo") instead of the browser's.
 */

const DATETIME_LOCAL_RE = /^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2})(?::(\d{2}))?$/;

/**
 * Detect the browser's IANA timezone.
 */
export function browserTz() {
    try {
        return Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC';
    } catch {
        return 'UTC';
    }
}

/**
 * Compute the offset (ms) between UTC and `tz` at instant `utcMs`.
 * Positive when `tz` is ahead of UTC.
 */
function tzOffsetAt(utcMs, tz) {
    const d = new Date(utcMs);
    const parts = new Intl.DateTimeFormat('en-US', {
        timeZone: tz,
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        hour12: false,
    })
        .formatToParts(d)
        .reduce((acc, p) => {
            acc[p.type] = p.value;
            return acc;
        }, {});

    // Some browsers emit "24" for midnight when hour12=false.
    const hour = parts.hour === '24' ? 0 : parseInt(parts.hour, 10);
    const tzAsUtcMs = Date.UTC(
        parseInt(parts.year, 10),
        parseInt(parts.month, 10) - 1,
        parseInt(parts.day, 10),
        hour,
        parseInt(parts.minute, 10),
        parseInt(parts.second, 10),
    );
    return tzAsUtcMs - utcMs;
}

/**
 * Convert a wall-clock `<input type="datetime-local">` string (no tz info)
 * expressed in `tz` to a UTC ISO 8601 string suitable for sending to the
 * backend.
 *
 *   tzLocalToUtcIso('2026-05-04T15:30', 'America/Sao_Paulo')
 *     -> '2026-05-04T09:30:00.000Z'
 */
export function tzLocalToUtcIso(localStr, tz) {
    if (!localStr) return null;
    const m = DATETIME_LOCAL_RE.exec(localStr);
    if (!m) return null;

    const [, y, mo, d, h, mi, s] = m;

    // Step 1: pretend the wall-clock is already UTC to get a baseline ms.
    const naiveUtcMs = Date.UTC(+y, +mo - 1, +d, +h, +mi, +(s ?? 0));

    // Step 2: figure out tz's offset at that wall-clock moment.
    const offsetMs = tzOffsetAt(naiveUtcMs, tz || 'UTC');

    // Step 3: real UTC = naive - offset.
    return new Date(naiveUtcMs - offsetMs).toISOString();
}

/**
 * Convert a UTC ISO string back to a wall-clock `datetime-local` value
 * formatted in `tz`. Suitable for prefilling an `<input type="datetime-local">`.
 *
 *   utcToTzLocal('2026-05-04T09:30:00Z', 'America/Sao_Paulo')
 *     -> '2026-05-04T15:30'
 */
export function utcToTzLocal(utcStr, tz) {
    if (!utcStr) return '';

    const d = new Date(utcStr);
    if (Number.isNaN(d.getTime())) return '';

    const parts = new Intl.DateTimeFormat('en-US', {
        timeZone: tz || 'UTC',
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        hour12: false,
    })
        .formatToParts(d)
        .reduce((acc, p) => {
            acc[p.type] = p.value;
            return acc;
        }, {});

    const hour = parts.hour === '24' ? '00' : parts.hour;
    return `${parts.year}-${parts.month}-${parts.day}T${hour}:${parts.minute}`;
}

/**
 * Pretty-print a UTC ISO string in the given timezone, including the timezone abbreviation.
 *
 *   formatInTz('2026-05-04T09:30:00Z', 'America/Sao_Paulo')
 *     -> 'May 4, 2026, 3:30 PM GMT+6'
 */
export function formatInTz(utcStr, tz, options = {}) {
    if (!utcStr) return '';
    const d = new Date(utcStr);
    if (Number.isNaN(d.getTime())) return '';

    return new Intl.DateTimeFormat(undefined, {
        timeZone: tz || 'UTC',
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
        timeZoneName: 'short',
        ...options,
    }).format(d);
}

/**
 * Format only the time portion (HH:MM) in the given timezone.
 *
 *   formatTimeTz('2026-05-04T09:30:00Z', 'America/Sao_Paulo')
 *     -> '3:30 PM'
 */
export function formatTimeTz(utcStr, tz) {
    if (!utcStr) return '';
    const d = new Date(utcStr);
    if (Number.isNaN(d.getTime())) return '';
    return new Intl.DateTimeFormat(undefined, {
        timeZone: tz || 'UTC',
        hour: '2-digit',
        minute: '2-digit',
    }).format(d);
}

/**
 * Format only the date portion in the given timezone.
 *
 *   formatDateTz('2026-05-04T09:30:00Z', 'America/Sao_Paulo')
 *     -> 'May 4, 2026'
 */
export function formatDateTz(utcStr, tz) {
    if (!utcStr) return '';
    const d = new Date(utcStr);
    if (Number.isNaN(d.getTime())) return '';
    return new Intl.DateTimeFormat(undefined, {
        timeZone: tz || 'UTC',
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    }).format(d);
}

/**
 * Format a Unix timestamp (seconds) as a date in the given timezone.
 */
export function formatUnixTz(unixSec, tz) {
    if (!unixSec) return '';
    return formatDateTz(new Date(unixSec * 1000).toISOString(), tz);
}
