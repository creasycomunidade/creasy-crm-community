import { router } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';

const OPTIONS = [
    { value: 7, key: 'dashboard.range_7d', fallback: '7d' },
    { value: 30, key: 'dashboard.range_30d', fallback: '30d' },
    { value: 90, key: 'dashboard.range_90d', fallback: '90d' },
];

/**
 * Segmented control that re-fetches the dashboard for a different date window.
 * Performs an Inertia visit preserving scroll + component state.
 */
export default function RangeFilter({ value = 30, routeName, params = {} }) {
    const { t } = useTranslation();

    const change = (range) => {
        if (range === value || !routeName) return;
        router.get(route(routeName), { ...params, range }, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    return (
        <div
            role="group"
            aria-label={t('dashboard.date_range') || 'Date range'}
            className="inline-flex items-center gap-0.5 rounded-soft-lg border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-800/70 p-0.5 shadow-soft"
        >
            {OPTIONS.map((opt) => {
                const active = value === opt.value;
                return (
                    <button
                        key={opt.value}
                        type="button"
                        onClick={() => change(opt.value)}
                        aria-pressed={active}
                        className={[
                            'rounded-soft px-3 py-1.5 text-sm font-medium transition',
                            active
                                ? 'bg-brand-500 text-white shadow-soft'
                                : 'text-neutral-600 dark:text-neutral-300 hover:bg-neutral-100 dark:hover:bg-neutral-700/60',
                        ].join(' ')}
                    >
                        {t(opt.key) || opt.fallback}
                    </button>
                );
            })}
        </div>
    );
}
