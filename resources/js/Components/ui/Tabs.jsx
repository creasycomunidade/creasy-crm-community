import { useState } from 'react';
import { useTranslation } from 'react-i18next';

/**
 * Tabs with soft underline indicator and smooth transition.
 */
export default function Tabs({ tabs = [], defaultIndex = 0, onChange, children }) {
    const { t } = useTranslation();
    const [activeIndex, setActiveIndex] = useState(defaultIndex);
    const current = tabs[activeIndex];

    const handleSelect = (index) => {
        setActiveIndex(index);
        onChange?.(index, tabs[index]);
    };

    return (
        <div className="w-full">
            <div className="border-b border-neutral-200 dark:border-neutral-700">
                <nav className="-mb-px flex gap-6" aria-label={t('common.tabs')}>
                    {tabs.map((tab, index) => {
                        const isActive = index === activeIndex;
                        const label = typeof tab === 'object' ? tab.label : tab;
                        const key = typeof tab === 'object' ? tab.key ?? index : index;
                        return (
                            <button
                                key={key}
                                type="button"
                                onClick={() => handleSelect(index)}
                                className={[
                                    'whitespace-nowrap border-b-2 py-3 text-sm font-medium transition duration-150',
                                    isActive
                                        ? 'border-brand-500 text-brand-600 dark:text-brand-400'
                                        : 'border-transparent text-neutral-500 dark:text-neutral-400 hover:border-neutral-300 dark:hover:border-neutral-600 hover:text-neutral-700 dark:hover:text-neutral-200',
                                ].join(' ')}
                            >
                                {label}
                            </button>
                        );
                    })}
                </nav>
            </div>
            {children ? (
                <div className="py-4">{children}</div>
            ) : (
                current && typeof current === 'object' && current.content && (
                    <div className="py-4">{current.content}</div>
                )
            )}
        </div>
    );
}

Tabs.Panel = function TabsPanel({ index, activeIndex, children }) {
    if (index !== activeIndex) return null;
    return <div>{children}</div>;
};
