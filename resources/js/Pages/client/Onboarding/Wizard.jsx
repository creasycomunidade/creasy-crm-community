import ClientLayout from '@/Layouts/ClientLayout';
import { Head, Link, router } from '@inertiajs/react';
import { CheckCircle, ArrowRight } from 'lucide-react';
import { useTranslation } from 'react-i18next';

const STEP_LINKS = {
    verify_email:                  null,
    connect_first_channel:         'client.inbox.setup',
    import_first_contacts:         'client.contacts.index',
    send_first_message:            'client.inbox.index',
};

export default function OnboardingWizard({ progress }) {
    const { t } = useTranslation();
    const { steps, is_complete } = progress;

    const markComplete = (stepKey) => {
        fetch(route('client.onboarding.complete'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
            },
            body: JSON.stringify({ step: stepKey }),
        }).then(() => router.reload());
    };

    return (
        <ClientLayout title={t('onboarding.title')}>
            <Head title={t('onboarding.title')} />
            <div className="max-w-2xl space-y-6">
                <div>
                    <h1 className="text-2xl font-bold text-gray-900 dark:text-white">{t('onboarding.title')}</h1>
                    <p className="text-sm text-gray-500 dark:text-gray-400 mt-1">
                        {t('onboarding.subtitle')}
                    </p>
                </div>

                {is_complete && (
                    <div className="rounded-xl bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 p-4">
                        <div className="flex items-center gap-2 text-green-700 dark:text-green-400">
                            <CheckCircle className="h-5 w-5" />
                            <p className="font-semibold">{t('onboarding.complete_message')}</p>
                        </div>
                    </div>
                )}

                {/* Steps */}
                <div className="space-y-3">
                    {steps.map((step, i) => {
                        const linkRoute = STEP_LINKS[step.key];
                        const href = linkRoute ? route(linkRoute) : null;

                        return (
                            <div
                                key={step.key}
                                className={`flex items-center gap-4 p-4 rounded-xl border ${step.completed ? 'border-green-200 dark:border-green-800 bg-green-50 dark:bg-green-900/10' : 'border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800'}`}
                            >
                                <div className="shrink-0">
                                    {step.completed
                                        ? <CheckCircle className="h-6 w-6 text-green-500" />
                                        : <span className="flex h-6 w-6 items-center justify-center rounded-full border-2 border-gray-300 dark:border-gray-600 text-xs font-bold text-gray-400">{i + 1}</span>
                                    }
                                </div>
                                <div className="flex-1">
                                    <p className={`font-medium ${step.completed ? 'text-green-700 dark:text-green-400 line-through' : 'text-gray-900 dark:text-white'}`}>
                                        {step.label}
                                    </p>
                                </div>
                                {!step.completed && (
                                    <div className="flex items-center gap-2">
                                        {href && (
                                            <Link href={href} className="flex items-center gap-1 text-sm text-indigo-600 dark:text-indigo-400 hover:underline">
                                                {t('client.go') || 'Go'} <ArrowRight className="h-3.5 w-3.5" />
                                            </Link>
                                        )}
                                        <button
                                            onClick={() => markComplete(step.key)}
                                            className="text-xs text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 underline"
                                        >
                                            {t('onboarding.mark_done')}
                                        </button>
                                    </div>
                                )}
                            </div>
                        );
                    })}
                </div>
            </div>
        </ClientLayout>
    );
}
