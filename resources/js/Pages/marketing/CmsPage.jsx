import { Head, usePage } from '@inertiajs/react';
import { sanitizeHtml } from '@/Utils/sanitizeHtml';

export default function CmsPage({ page }) {
    const appName = usePage().props.branding?.app_name || 'CREASY CRM';
    return (
        <div className="min-h-screen bg-white dark:bg-neutral-950">
            <Head>
                <title>{page.meta_title ?? page.title}</title>
                {page.meta_description && <meta name="description" content={page.meta_description} />}
            </Head>

            <header className="sticky top-0 z-30 border-b border-neutral-200 dark:border-neutral-800 bg-white/80 dark:bg-neutral-950/80 backdrop-blur px-6 py-4">
                <a href="/" className="font-bold text-lg text-brand-600 dark:text-brand-400">
                    {appName}
                </a>
            </header>

            <main className="max-w-3xl mx-auto px-4 py-16">
                <h1 className="text-3xl font-bold text-neutral-900 dark:text-white mb-8">{page.title}</h1>
                <div
                    className="prose dark:prose-invert max-w-none"
                    dangerouslySetInnerHTML={{ __html: sanitizeHtml(page.content) }}
                />
            </main>
        </div>
    );
}
