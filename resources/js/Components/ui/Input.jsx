/**
 * Text input with soft border and subtle focus ring.
 */
export default function Input({
    type = 'text',
    label,
    error,
    hint,
    className = '',
    id,
    ...props
}) {
    const inputId = id || props.name;
    return (
        <div className="w-full">
            {label && (
                <label
                    htmlFor={inputId}
                    className="mb-1.5 block text-sm font-medium text-neutral-700 dark:text-neutral-300"
                >
                    {label}
                </label>
            )}
            <input
                id={inputId}
                type={type}
                className={[
                    'w-full rounded-soft border bg-white dark:bg-neutral-800 px-3 py-2 text-neutral-900 dark:text-neutral-100 shadow-inner transition duration-150 placeholder:text-neutral-400 dark:placeholder:text-neutral-500 focus:outline-none focus:ring-2',
                    // border-soft's color utility outranks border-red-500 in the
                    // compiled CSS, so it must be omitted entirely on error.
                    error
                        ? 'border-red-500 focus:border-red-500 focus:ring-red-500/20'
                        : 'border-soft border-neutral-300 dark:border-neutral-600 focus:border-brand-500 focus:ring-brand-500/20',
                    className,
                ].filter(Boolean).join(' ')}
                {...props}
            />
            {error && (
                <p className="mt-1.5 text-sm text-red-500 dark:text-red-400">{error}</p>
            )}
            {hint && !error && (
                <p className="mt-1.5 text-sm text-neutral-500 dark:text-neutral-400">{hint}</p>
            )}
        </div>
    );
}
