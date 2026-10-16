/**
 * Checkbox input matching the design system tokens used in Input.jsx.
 * Props: label, error, id, name, checked, onChange, className
 */
export default function Checkbox({ label, error, id, className = '', ...props }) {
    const inputId = id || props.name;

    return (
        <div className="flex items-start gap-2">
            <input
                id={inputId}
                type="checkbox"
                className={[
                    'mt-0.5 h-4 w-4 rounded-soft border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 text-brand-500 shadow-inner transition duration-150 focus:ring-2 focus:ring-brand-500/20 focus:ring-offset-0 dark:checked:bg-brand-500 checked:border-brand-500',
                    className,
                ].filter(Boolean).join(' ')}
                {...props}
            />
            {label && (
                <label
                    htmlFor={inputId}
                    className="select-none text-sm text-neutral-700 dark:text-neutral-300"
                >
                    {label}
                </label>
            )}
            {error && (
                <p className="mt-1 text-sm text-red-500 dark:text-red-400">{error}</p>
            )}
        </div>
    );
}
