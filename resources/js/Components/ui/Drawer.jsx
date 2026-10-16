import { Transition } from '@headlessui/react';

/**
 * Drawer slides in from the right. Use for filters, cart, or secondary panels.
 */
export default function Drawer({
    show = false,
    onClose,
    title,
    children,
    width = 'md',
}) {
    const widthClass = {
        sm: 'max-w-sm',
        md: 'max-w-md',
        lg: 'max-w-lg',
        xl: 'max-w-xl',
    }[width];

    return (
        <Transition show={show}>
            <div className="fixed inset-0 z-50 overflow-hidden">
                <Transition.Child
                    enter="ease-out duration-250"
                    enterFrom="opacity-0"
                    enterTo="opacity-100"
                    leave="ease-in duration-200"
                    leaveFrom="opacity-100"
                    leaveTo="opacity-0"
                >
                    <div
                        className="absolute inset-0 bg-neutral-900/40 backdrop-blur-[2px]"
                        onClick={onClose}
                        aria-hidden="true"
                    />
                </Transition.Child>

                <div className="fixed inset-y-0 right-0 flex max-w-full pl-10">
                    <Transition.Child
                        enter="ease-out duration-250"
                        enterFrom="translate-x-full"
                        enterTo="translate-x-0"
                        leave="ease-in duration-200"
                        leaveFrom="translate-x-0"
                        leaveTo="translate-x-full"
                        className={`w-screen ${widthClass}`}
                    >
                        <div className="flex h-full flex-col bg-white dark:bg-neutral-900 shadow-soft-xl border-l border-soft border-neutral-200 dark:border-neutral-800 text-neutral-900 dark:text-neutral-100">
                            {title && (
                                <div className="flex items-center justify-between border-b border-soft border-neutral-200 dark:border-neutral-800 px-5 py-4">
                                    <h2 className="text-lg font-semibold text-neutral-900 dark:text-neutral-100">{title}</h2>
                                    <button
                                        type="button"
                                        onClick={onClose}
                                        className="rounded-soft p-1.5 text-neutral-400 hover:bg-neutral-100 hover:text-neutral-600 dark:hover:bg-neutral-800 dark:hover:text-neutral-300 transition duration-150"
                                        aria-label="Close"
                                    >
                                        <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                    </button>
                                </div>
                            )}
                            <div className="flex-1 overflow-y-auto p-5">{children}</div>
                        </div>
                    </Transition.Child>
                </div>
            </div>
        </Transition>
    );
}
