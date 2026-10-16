/**
 * Skeleton loading placeholder.
 *
 * Usage:
 *   <Skeleton className="h-4 w-48" />
 *   <Skeleton variant="circle" className="h-10 w-10" />
 *   <Skeleton variant="text" lines={3} />
 */
export default function Skeleton({ className = '', variant = 'rect', lines = 1, ...props }) {
    const base = 'animate-pulse bg-neutral-200 dark:bg-neutral-700 rounded';

    if (variant === 'circle') {
        return <div className={`${base} rounded-full ${className}`} {...props} />;
    }

    if (variant === 'text') {
        return (
            <div className={`space-y-2 ${className}`} {...props}>
                {Array.from({ length: lines }).map((_, i) => (
                    <div
                        key={i}
                        className={`${base} h-4 ${i === lines - 1 && lines > 1 ? 'w-3/4' : 'w-full'}`}
                    />
                ))}
            </div>
        );
    }

    return <div className={`${base} ${className}`} {...props} />;
}
