import { Head, usePage } from '@inertiajs/react';

/**
 * Reusable SEO <Head> for public marketing pages.
 *
 * Renders the page title, meta description/keywords, canonical URL,
 * Open Graph + Twitter card tags, and optional JSON-LD structured data.
 *
 * Props:
 *  - title        string  document <title> + og:title
 *  - description  string  meta description + og:description
 *  - keywords     string  comma-separated meta keywords (optional)
 *  - image        string  absolute og:image URL (optional)
 *  - canonical    string  canonical URL (optional; falls back to current URL)
 *  - jsonLd       object|array  one or more JSON-LD graphs (optional)
 */
export default function SeoHead({ title, description, keywords, image, canonical, jsonLd }) {
    const appName = usePage().props.branding?.app_name || 'CREASY CRM';
    const fullTitle = title || appName;
    const origin = typeof window !== 'undefined' ? window.location.origin : '';
    const url =
        canonical ||
        (typeof window !== 'undefined' ? window.location.href.split(/[?#]/)[0] : undefined);

    // Fall back to the brand icon when a page doesn't supply its own og:image.
    // A page-provided `image` is assumed wide (2:1), so it gets a large card;
    // the square brand fallback uses the standard summary card.
    const ogImage = image || usePage().props.branding?.logo_url || `${origin}/creasy-community-icon.svg`;
    const twitterCard = image ? 'summary_large_image' : 'summary';

    const graphs = jsonLd ? (Array.isArray(jsonLd) ? jsonLd : [jsonLd]) : [];
    const nonce =
        typeof document !== 'undefined'
            ? document.querySelector('meta[name="csp-nonce"]')?.content
            : undefined;

    return (
        <Head>
            <title>{fullTitle}</title>
            {description && <meta name="description" content={description} head-key="description" />}
            {keywords && <meta name="keywords" content={keywords} head-key="keywords" />}
            {url && <link rel="canonical" href={url} head-key="canonical" />}

            {/* Open Graph */}
            <meta property="og:site_name" content={appName} head-key="og:site_name" />
            <meta property="og:type" content="website" head-key="og:type" />
            <meta property="og:title" content={fullTitle} head-key="og:title" />
            {description && <meta property="og:description" content={description} head-key="og:description" />}
            {url && <meta property="og:url" content={url} head-key="og:url" />}
            <meta property="og:image" content={ogImage} head-key="og:image" />

            {/* Twitter */}
            <meta name="twitter:card" content={twitterCard} head-key="twitter:card" />
            <meta name="twitter:title" content={fullTitle} head-key="twitter:title" />
            {description && <meta name="twitter:description" content={description} head-key="twitter:description" />}
            <meta name="twitter:image" content={ogImage} head-key="twitter:image" />

            {/* Structured data */}
            {graphs.map((graph, i) => (
                <script
                    key={`ld-${i}`}
                    type="application/ld+json"
                    nonce={nonce}
                    // eslint-disable-next-line react/no-danger
                    dangerouslySetInnerHTML={{
                        __html: JSON.stringify(graph)
                            .replaceAll('<', '\\u003c')
                            .replaceAll('>', '\\u003e')
                            .replaceAll('&', '\\u0026'),
                    }}
                />
            ))}
        </Head>
    );
}
