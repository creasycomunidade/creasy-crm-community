/**
 * Titled panel used to frame a chart, table, or list on the dashboards.
 * `action` renders on the right of the header (e.g. a "View all" link).
 */
export default function WidgetCard({ title, subtitle, action, children, className = '', bodyClassName = '' }) {
    return (
        <div
            className={`flex flex-col rounded-xl border border-neutral-200 bg-white p-4 shadow-soft dark:border-neutral-700/50 dark:bg-neutral-800/70 sm:p-5 ${className}`}
        >
            {(title || action) && (
                <div className="mb-3 flex items-start justify-between gap-3">
                    <div className="min-w-0">
                        {title && (
                            <h3 className="text-sm font-semibold text-neutral-700 dark:text-neutral-200">{title}</h3>
                        )}
                        {subtitle && (
                            <p className="mt-0.5 text-xs text-neutral-500 dark:text-neutral-400">{subtitle}</p>
                        )}
                    </div>
                    {action && <div className="shrink-0">{action}</div>}
                </div>
            )}
            <div className={`flex-1 ${bodyClassName}`}>{children}</div>
        </div>
    );
}

/** Centered empty-state used inside WidgetCard bodies. */
export function EmptyState({ children, className = '' }) {
    return (
        <p className={`py-10 text-center text-sm text-neutral-400 dark:text-neutral-500 ${className}`}>
            {children}
        </p>
    );
}
