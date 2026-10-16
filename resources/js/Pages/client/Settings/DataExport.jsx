import ClientLayout from '@/Layouts/ClientLayout';
import { Head, useForm } from '@inertiajs/react';
import { Download, FileArchive, Shield } from 'lucide-react';
import { useTranslation } from 'react-i18next';

export default function DataExport({ status = null }) {
    const { t } = useTranslation();
    const { post, processing } = useForm();

    const requestExport = (e) => {
        e.preventDefault();
        post(route('client.settings.data-export.store'));
    };

    return (
        <ClientLayout title={t('data_export.title')}>
            <Head title={t('data_export.title')} />

            <div className="max-w-2xl space-y-6">
                <div>
                    <h1 className="text-xl font-semibold text-neutral-900 dark:text-neutral-100">{t('data_export.export_your_data')}</h1>
                    <p className="mt-1 text-sm text-neutral-500 dark:text-neutral-400">
                        {t('data_export.subtitle')}
                    </p>
                </div>

                {status && (
                    <div className="rounded-lg bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 px-4 py-3 text-sm text-green-800 dark:text-green-200">
                        {status}
                    </div>
                )}

                {/* What's included */}
                <div className="rounded-xl border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-800/50 p-6 space-y-4">
                    <div className="flex items-start gap-4">
                        <div className="mt-0.5 flex-shrink-0 rounded-lg bg-brand-100 dark:bg-brand-900/30 p-2.5">
                            <FileArchive className="h-5 w-5 text-brand-600 dark:text-brand-400" />
                        </div>
                        <div>
                            <h2 className="text-sm font-semibold text-neutral-900 dark:text-neutral-100">{t('data_export.whats_included')}</h2>
                            <ul className="mt-2 space-y-1 text-sm text-neutral-600 dark:text-neutral-400 list-disc list-inside">
                                <li>{t('data_export.contacts_segments')}</li>
                                <li>{t('data_export.conversations')}</li>
                                <li>{t('data_export.ai_runs')}</li>
                                <li>{t('data_export.campaigns')}</li>
                                <li>{t('data_export.automations')}</li>
                                <li>{t('data_export.audit_log')}</li>
                                <li>{t('data_export.social_posts')}</li>
                                <li>{t('data_export.manifest')}</li>
                            </ul>
                        </div>
                    </div>

                    <div className="flex items-start gap-4">
                        <div className="mt-0.5 flex-shrink-0 rounded-lg bg-neutral-100 dark:bg-neutral-700 p-2.5">
                            <Shield className="h-5 w-5 text-neutral-500 dark:text-neutral-400" />
                        </div>
                        <div>
                            <h2 className="text-sm font-semibold text-neutral-900 dark:text-neutral-100">{t('data_export.privacy_security')}</h2>
                            <p className="mt-1 text-sm text-neutral-600 dark:text-neutral-400">
                                {t('data_export.privacy_security_desc')}
                            </p>
                        </div>
                    </div>
                </div>

                <form onSubmit={requestExport}>
                    <button
                        type="submit"
                        disabled={processing}
                        className="inline-flex items-center gap-2 rounded-lg bg-brand-600 hover:bg-brand-700 disabled:opacity-60 px-5 py-2.5 text-sm font-medium text-white transition focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2"
                    >
                        <Download className="h-4 w-4" />
                        {processing ? t('data_export.requesting') : t('data_export.request_export')}
                    </button>
                </form>
            </div>
        </ClientLayout>
    );
}
