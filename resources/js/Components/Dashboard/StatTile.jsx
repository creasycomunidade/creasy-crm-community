import { Link } from '@inertiajs/react';
import { ResponsiveContainer, LineChart, Line } from 'recharts';
import { ArrowUpRight } from 'lucide-react';

function formatValue(v) {
    if (v === null || v === undefined) return '—';
    if (typeof v === 'number') {
        if (Math.abs(v) >= 1_000_000) return `${(v / 1_000_000).toFixed(1)}M`;
        if (Math.abs(v) >= 1_000) return `${(v / 1_000).toFixed(1)}K`;
        return v.toLocaleString();
    }
    return v;
}

/**
 * Compact KPI tile: icon, label, value, optional % delta, optional sparkline,
 * and an optional link that turns the whole card into a navigable target.
 *
 * @param {string} props.deltaGoodWhen 'up' (default) or 'down' — controls delta colour.
 */
export default function StatTile({
    label,
    value,
    icon: Icon,
    delta,
    deltaGoodWhen = 'up',
    sparkline = [],
    sparkKey = 'v',
    href,
    hint,
}) {
    const hasDelta = delta !== undefined && delta !== null && Number.isFinite(delta);
    const positive = delta >= 0;
    const good = deltaGoodWhen === 'up' ? positive : !positive;

    const deltaColor = !hasDelta
        ? 'text-neutral-400'
        : good
            ? 'text-emerald-600 dark:text-emerald-400'
            : 'text-red-600 dark:text-red-400';
    const sparkColor = !hasDelta ? '#9ca3af' : good ? '#10b981' : '#ef4444';

    const body = (
        <div className="relative flex h-full flex-col justify-between rounded-xl border border-neutral-200 bg-white p-4 shadow-soft transition hover:border-neutral-300 dark:border-neutral-700/50 dark:bg-neutral-800/70 dark:hover:border-neutral-600">
            <div className="flex items-start justify-between gap-2">
                <p className="truncate text-xs font-medium uppercase tracking-wide text-neutral-500 dark:text-neutral-400">
                    {label}
                </p>
                {Icon && (
                    <span className="flex h-8 w-8 shrink-0 items-center justify-center rounded-soft-lg bg-brand-50 text-brand-600 dark:bg-brand-900/30 dark:text-brand-400">
                        <Icon className="h-4 w-4" />
                    </span>
                )}
            </div>

            <div className="mt-2 flex items-end justify-between gap-2">
                <div className="min-w-0">
                    <p className="text-2xl font-bold tabular-nums text-neutral-900 dark:text-white">
                        {formatValue(value)}
                    </p>
                    <div className="mt-0.5 flex items-center gap-1.5">
                        {hasDelta && (
                            <span className={`text-xs font-medium ${deltaColor}`}>
                                {positive ? '↑' : '↓'} {Math.abs(delta).toFixed(1)}%
                            </span>
                        )}
                        {hint && <span className="truncate text-xs text-neutral-400">{hint}</span>}
                    </div>
                </div>

                {sparkline.length > 1 && (
                    <div className="h-9 w-16 shrink-0">
                        <ResponsiveContainer width="100%" height="100%">
                            <LineChart data={sparkline}>
                                <Line type="monotone" dataKey={sparkKey} stroke={sparkColor} dot={false} strokeWidth={2} />
                            </LineChart>
                        </ResponsiveContainer>
                    </div>
                )}
            </div>

            {href && (
                <ArrowUpRight className="absolute bottom-3 right-3 h-4 w-4 text-neutral-300 transition group-hover:text-brand-500 dark:text-neutral-600" />
            )}
        </div>
    );

    if (href) {
        return (
            <Link href={href} className="group block">
                {body}
            </Link>
        );
    }
    return body;
}
