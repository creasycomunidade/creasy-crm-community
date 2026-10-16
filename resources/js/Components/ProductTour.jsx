import { useState, useEffect } from 'react';
import { router, usePage } from '@inertiajs/react';
import { X, ChevronRight, ChevronLeft, Check } from 'lucide-react';
import { useTranslation } from 'react-i18next';

const TOUR_STEPS = [
    {
        id:       'welcome',
        titleKey: 'ui.tour_welcome_title',
        descKey:  'ui.tour_welcome_desc',
        target:   null,
    },
    {
        id:       'inbox',
        titleKey: 'ui.tour_inbox_title',
        descKey:  'ui.tour_inbox_desc',
        target:   '[data-tour="inbox"]',
    },
    {
        id:       'campaigns',
        titleKey: 'ui.tour_campaigns_title',
        descKey:  'ui.tour_campaigns_desc',
        target:   '[data-tour="campaigns"]',
    },
    {
        id:       'automation',
        titleKey: 'ui.tour_automation_title',
        descKey:  'ui.tour_automation_desc',
        target:   '[data-tour="automation"]',
    },
    {
        id:       'ai',
        titleKey: 'ui.tour_ai_title',
        descKey:  'ui.tour_ai_desc',
        target:   '[data-tour="ai"]',
    },
    {
        id:       'done',
        titleKey: 'ui.tour_done_title',
        descKey:  'ui.tour_done_desc',
        target:   null,
    },
];

const STORAGE_KEY = 'product_tour_dismissed';

export default function ProductTour({ show = false }) {
    const { t } = useTranslation();
    const appName = (typeof window !== 'undefined' && window.APP_NAME) || import.meta.env.VITE_APP_NAME || 'the platform';
    const [step, setStep]         = useState(0);
    const [visible, setVisible]   = useState(false);

    useEffect(() => {
        if (show && ! localStorage.getItem(STORAGE_KEY)) {
            setVisible(true);
        }
    }, [show]);

    const dismiss = () => {
        localStorage.setItem(STORAGE_KEY, '1');
        setVisible(false);
    };

    const next = () => {
        if (step < TOUR_STEPS.length - 1) {
            setStep(s => s + 1);
        } else {
            dismiss();
        }
    };

    const prev = () => setStep(s => Math.max(0, s - 1));

    if (! visible) return null;

    const current = TOUR_STEPS[step];
    const isLast  = step === TOUR_STEPS.length - 1;

    return (
        <div className="fixed inset-0 z-50 flex items-end justify-center pb-8 px-4 pointer-events-none sm:items-center">
            {/* Soft backdrop */}
            <div className="absolute inset-0 bg-black/30 pointer-events-auto" onClick={dismiss} />

            <div className="relative pointer-events-auto w-full max-w-sm rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-700 shadow-2xl p-6 space-y-4">
                {/* Close */}
                <button
                    onClick={dismiss}
                    className="absolute top-4 right-4 p-1.5 rounded-full hover:bg-neutral-100 dark:hover:bg-neutral-800 text-neutral-400 hover:text-neutral-600 transition"
                    aria-label={t('ui.close_tour')}
                >
                    <X className="h-4 w-4" />
                </button>

                {/* Step indicator */}
                <div className="flex gap-1.5">
                    {TOUR_STEPS.map((_, i) => (
                        <div
                            key={i}
                            className={`h-1.5 flex-1 rounded-full transition-colors ${i <= step ? 'bg-brand-500' : 'bg-neutral-200 dark:bg-neutral-700'}`}
                        />
                    ))}
                </div>

                {/* Content */}
                <div>
                    <h3 className="text-base font-semibold text-neutral-900 dark:text-neutral-100">{t(current.titleKey, { appName })}</h3>
                    <p className="mt-1.5 text-sm text-neutral-600 dark:text-neutral-400 leading-relaxed">{t(current.descKey)}</p>
                </div>

                {/* Navigation */}
                <div className="flex items-center justify-between pt-2">
                    <button
                        onClick={prev}
                        disabled={step === 0}
                        className="flex items-center gap-1 text-sm text-neutral-500 hover:text-neutral-700 dark:hover:text-neutral-300 disabled:opacity-30 transition"
                    >
                        <ChevronLeft className="h-4 w-4" /> {t('common.back')}
                    </button>
                    <span className="text-xs text-neutral-400">{step + 1} / {TOUR_STEPS.length}</span>
                    <button
                        onClick={next}
                        className="flex items-center gap-1 text-sm font-medium text-brand-600 hover:text-brand-700 dark:text-brand-400 dark:hover:text-brand-300 transition"
                    >
                        {isLast ? (
                            <><Check className="h-4 w-4" /> {t('ui.done')}</>
                        ) : (
                            <>{t('common.next')} <ChevronRight className="h-4 w-4" /></>
                        )}
                    </button>
                </div>
            </div>
        </div>
    );
}
