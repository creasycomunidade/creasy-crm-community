import { useMemo } from 'react';
import { ResponsiveContainer, LineChart, Line } from 'recharts';

export default function KpiCard({ label, value, unit = '', delta, sparkline = [], sparkKey = 'v', trend = 'up' }) {
    const deltaPositive = delta >= 0;
    const goodIsUp = trend === 'up';
    const isGood = goodIsUp ? deltaPositive : !deltaPositive;

    const color = delta === undefined || delta === null
        ? 'text-gray-500 dark:text-gray-400'
        : isGood ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400';

    const sparkColor = delta === undefined || delta === null ? '#6b7280' : isGood ? '#10b981' : '#ef4444';

    const formatted = useMemo(() => {
        if (value === null || value === undefined) return '—';
        if (typeof value === 'number') {
            if (value >= 1_000_000) return (value / 1_000_000).toFixed(1) + 'M';
            if (value >= 1_000) return (value / 1_000).toFixed(1) + 'K';
            return value.toLocaleString();
        }
        return value;
    }, [value]);

    return (
        <div className="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-4 flex flex-col gap-2">
            <p className="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">{label}</p>
            <div className="flex items-end justify-between gap-2">
                <div>
                    <span className="text-2xl font-bold text-gray-900 dark:text-white">{formatted}</span>
                    {unit && <span className="ml-1 text-sm text-gray-500 dark:text-gray-400">{unit}</span>}
                    {delta !== undefined && delta !== null && (
                        <span className={`ml-2 text-xs font-medium ${color}`}>
                            {deltaPositive ? '↑' : '↓'} {Math.abs(delta).toFixed(1)}%
                        </span>
                    )}
                </div>
                {sparkline.length > 1 && (
                    <div className="w-20 h-10 shrink-0">
                        <ResponsiveContainer width="100%" height="100%">
                            <LineChart data={sparkline}>
                                <Line
                                    type="monotone"
                                    dataKey={sparkKey}
                                    stroke={sparkColor}
                                    dot={false}
                                    strokeWidth={2}
                                />
                            </LineChart>
                        </ResponsiveContainer>
                    </div>
                )}
            </div>
        </div>
    );
}
