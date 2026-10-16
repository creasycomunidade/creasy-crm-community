import { Link } from '@inertiajs/react';

/**
 * Shared empty-state component.
 *
 * Props:
 *   icon        – React element (SVG / lucide icon).  Optional.
 *   title       – Heading text.
 *   description – Supporting paragraph text.  Optional.
 *   action      – { label, href?, onClick?, method? }  Optional primary CTA.
 *   secondaryAction – same shape, optional secondary CTA.
 */
export default function EmptyState({ icon, title, description, action, secondaryAction }) {
    return (
        <div className="flex flex-col items-center justify-center py-16 px-6 text-center">
            {icon && (
                <div className="mb-4 flex h-16 w-16 items-center justify-center rounded-2xl bg-neutral-100 dark:bg-neutral-800 text-neutral-400 dark:text-neutral-500">
                    {icon}
                </div>
            )}
            <h3 className="text-lg font-semibold text-neutral-900 dark:text-neutral-100 mb-1">
                {title}
            </h3>
            {description && (
                <p className="text-sm text-neutral-500 dark:text-neutral-400 max-w-sm mb-6">
                    {description}
                </p>
            )}
            {(action || secondaryAction) && (
                <div className="flex flex-wrap items-center justify-center gap-3">
                    {action && (
                        action.href ? (
                            <Link
                                href={action.href}
                                method={action.method}
                                className="inline-flex items-center gap-2 px-4 py-2.5 bg-brand-500 hover:bg-brand-600 text-white text-sm font-medium rounded-soft transition-colors shadow-soft"
                            >
                                {action.label}
                            </Link>
                        ) : (
                            <button
                                type="button"
                                onClick={action.onClick}
                                className="inline-flex items-center gap-2 px-4 py-2.5 bg-brand-500 hover:bg-brand-600 text-white text-sm font-medium rounded-soft transition-colors shadow-soft"
                            >
                                {action.label}
                            </button>
                        )
                    )}
                    {secondaryAction && (
                        secondaryAction.href ? (
                            <Link
                                href={secondaryAction.href}
                                className="inline-flex items-center gap-2 px-4 py-2.5 bg-neutral-100 dark:bg-neutral-800 hover:bg-neutral-200 dark:hover:bg-neutral-700 text-neutral-700 dark:text-neutral-200 text-sm font-medium rounded-soft transition-colors"
                            >
                                {secondaryAction.label}
                            </Link>
                        ) : (
                            <button
                                type="button"
                                onClick={secondaryAction.onClick}
                                className="inline-flex items-center gap-2 px-4 py-2.5 bg-neutral-100 dark:bg-neutral-800 hover:bg-neutral-200 dark:hover:bg-neutral-700 text-neutral-700 dark:text-neutral-200 text-sm font-medium rounded-soft transition-colors"
                            >
                                {secondaryAction.label}
                            </button>
                        )
                    )}
                </div>
            )}
        </div>
    );
}
