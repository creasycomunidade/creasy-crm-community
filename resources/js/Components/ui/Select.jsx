/**
 * Select with soft border and consistent styling.
 */
export default function Select({
    label,
    error,
    options = [],
    placeholder = 'Select...',
    className = '',
    id,
    ...props
}) {
    const selectId = id || props.name;
    return (
        <div className="w-full">
            {label && (
                <label
                    htmlFor={selectId}
                    className="mb-1.5 block text-sm font-medium text-neutral-700 dark:text-neutral-300"
                >
                    {label}
                </label>
            )}
            <select
                id={selectId}
                className={[
                    'w-full rounded-soft border border-soft border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 px-3 py-2 text-neutral-900 dark:text-neutral-100 shadow-inner transition duration-150 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20',
                    error && 'border-red-500 focus:border-red-500 focus:ring-red-500/20',
                    className,
                ].filter(Boolean).join(' ')}
                {...props}
            >
                {placeholder && (
                    <option value="">{placeholder}</option>
                )}
                {options.map((opt) =>
                    typeof opt === 'object' ? (
                        <option key={opt.value} value={opt.value}>
                            {opt.label}
                        </option>
                    ) : (
                        <option key={opt} value={opt}>{opt}</option>
                    )
                )}
            </select>
            {error && (
                <p className="mt-1.5 text-sm text-red-500 dark:text-red-400">{error}</p>
            )}
        </div>
    );
}
