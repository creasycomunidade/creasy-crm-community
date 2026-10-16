/**
 * Premium button with soft borders, subtle shadow, and smooth transitions.
 * Variants: primary, secondary, ghost, danger, outline
 */
const variantClasses = {
    primary:
        'bg-brand-600 text-white border-transparent shadow-soft hover:bg-brand-700 hover:shadow-lg hover:shadow-brand-600/20 active:shadow-inner dark:bg-brand-600 dark:hover:bg-brand-500 dark:text-white',
    secondary:
        'bg-neutral-100 text-neutral-800 border-neutral-200 hover:bg-neutral-200 active:bg-neutral-300 dark:bg-neutral-800 dark:text-neutral-200 dark:border-neutral-700 dark:hover:bg-neutral-700 dark:active:bg-neutral-600',
    ghost:
        'bg-transparent text-neutral-700 border-transparent hover:bg-neutral-100 active:bg-neutral-200 dark:text-neutral-300 dark:hover:bg-neutral-800 dark:active:bg-neutral-700',
    danger:
        'bg-coral-500 text-white border-transparent shadow-soft hover:bg-coral-600 hover:shadow-soft-md active:shadow-inner dark:bg-coral-600 dark:hover:bg-coral-500',
    outline:
        'bg-transparent text-neutral-700 border-neutral-300 hover:bg-neutral-50 active:bg-neutral-100 dark:text-neutral-300 dark:border-neutral-600 dark:hover:bg-neutral-800 dark:active:bg-neutral-700',
};

const sizeClasses = {
    sm: 'px-3 py-1.5 text-sm rounded-soft',
    md: 'px-4 py-2 text-sm rounded-soft',
    lg: 'px-5 py-2.5 text-base rounded-soft-lg',
};

export default function Button({
    type = 'button',
    variant = 'primary',
    size = 'md',
    disabled = false,
    className = '',
    children,
    ...props
}) {
    return (
        <button
            type={type}
            disabled={disabled}
            className={[
                'inline-flex items-center justify-center font-medium border transition-all duration-150 ease-smooth focus:outline-none focus:ring-2 focus:ring-brand-500/30 focus:ring-offset-1 disabled:opacity-50 disabled:pointer-events-none',
                variantClasses[variant] ?? variantClasses.primary,
                sizeClasses[size] ?? sizeClasses.md,
                className,
            ].join(' ')}
            {...props}
        >
            {children}
        </button>
    );
}
