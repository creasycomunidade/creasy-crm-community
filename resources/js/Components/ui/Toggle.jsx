import { useState } from 'react';

/**
 * Toggle switch with smooth transition. Controlled when onChange is provided.
 */
export default function Toggle({
    checked,
    defaultChecked = false,
    onChange,
    disabled = false,
    label,
    ariaLabel,
    className = '',
}) {
    const [uncontrolled, setUncontrolled] = useState(defaultChecked);
    const isOn = onChange ? (checked ?? false) : uncontrolled;

    const handleClick = () => {
        if (disabled) return;
        const next = !isOn;
        if (onChange) onChange(next);
        else setUncontrolled(next);
    };

    return (
        <label className={`inline-flex items-center gap-3 cursor-pointer ${disabled ? 'opacity-50 cursor-not-allowed' : ''} ${className}`}>
            <button
                type="button"
                role="switch"
                aria-checked={isOn}
                aria-label={ariaLabel || label}
                disabled={disabled}
                onClick={handleClick}
                className={[
                    'relative inline-flex h-6 w-11 shrink-0 rounded-full border border-soft transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-brand-500/30 focus:ring-offset-1',
                    isOn ? 'bg-brand-500 border-brand-500' : 'bg-neutral-200 dark:bg-neutral-700 border-neutral-300 dark:border-neutral-600',
                ].join(' ')}
            >
                <span
                    className={[
                        'pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow-soft transition duration-200',
                        isOn ? 'translate-x-5' : 'translate-x-0.5',
                    ].join(' ')}
                />
            </button>
            {label && (
                <span className="text-sm font-medium text-neutral-700 dark:text-neutral-300">{label}</span>
            )}
        </label>
    );
}
