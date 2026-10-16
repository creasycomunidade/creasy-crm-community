import { useState } from 'react';

/**
 * Simple tooltip on hover. Wraps trigger and shows content on hover/focus.
 */
export default function Tooltip({ content, children, position = 'top' }) {
    const [visible, setVisible] = useState(false);

    const positionClasses = {
        top: 'bottom-full left-1/2 -translate-x-1/2 mb-2',
        bottom: 'top-full left-1/2 -translate-x-1/2 mt-2',
        left: 'right-full top-1/2 -translate-y-1/2 mr-2',
        right: 'left-full top-1/2 -translate-y-1/2 ml-2',
    };

    return (
        <div
            className="relative inline-flex"
            onMouseEnter={() => setVisible(true)}
            onMouseLeave={() => setVisible(false)}
        >
            {children}
            {visible && (
                <div
                    role="tooltip"
                    className={[
                        'absolute z-50 px-2.5 py-1.5 text-xs font-medium text-white bg-neutral-800 rounded-soft shadow-soft-md whitespace-nowrap',
                        positionClasses[position],
                    ].join(' ')}
                >
                    {content}
                </div>
            )}
        </div>
    );
}
