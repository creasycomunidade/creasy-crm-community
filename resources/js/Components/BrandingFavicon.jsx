import { usePage } from '@inertiajs/react';
import { useEffect } from 'react';

/**
 * Keeps the browser-tab favicon in sync with the admin-configured branding.
 *
 * The favicon <link> is rendered once in app.blade.php's <head>, which is NOT
 * part of the Inertia-managed body — so after an admin uploads a new favicon
 * (an Inertia POST, no full reload) the tab icon never updated until a hard
 * refresh. The server already shares `branding.favicon_url` on every response;
 * this applies it to the live <link> tags so the change shows immediately.
 * Renders nothing. Mount once at app root.
 */
export default function BrandingFavicon() {
    const branding = usePage().props?.branding;
    const faviconUrl = branding?.favicon_url;

    useEffect(() => {
        window.APP_NAME = branding?.app_name || 'CREASY CRM';
        const color = branding?.primary_color;
        if (!/^#[0-9a-f]{6}$/i.test(color || '')) return;
        const rgb = [1, 3, 5].map(offset => parseInt(color.slice(offset, offset + 2), 16));
        const shades = { 50: .96, 100: .90, 200: .78, 300: .60, 400: .38, 500: .18, 600: 0, 700: -.15, 800: -.30, 900: -.45, 950: -.65 };
        for (const [shade, amount] of Object.entries(shades)) {
            const mixed = rgb.map(value => Math.round(amount >= 0 ? value + (255 - value) * amount : value * (1 + amount)));
            document.documentElement.style.setProperty(`--brand-${shade}`, mixed.join(' '));
        }
        return () => Object.keys(shades).forEach(shade => document.documentElement.style.removeProperty(`--brand-${shade}`));
    }, [branding?.app_name, branding?.primary_color]);

    useEffect(() => {
        if (!faviconUrl) return;
        const selectors = ['link[rel="icon"]', 'link[rel="apple-touch-icon"]'];
        selectors.forEach((selector) => {
            let link = document.head.querySelector(selector);
            if (!link) {
                link = document.createElement('link');
                link.rel = selector.includes('apple') ? 'apple-touch-icon' : 'icon';
                document.head.appendChild(link);
            }
            // Drop the type/sizes hints from the SVG fallback so the browser
            // doesn't keep favoring the old default icon.
            link.removeAttribute('type');
            link.removeAttribute('sizes');
            link.href = faviconUrl;
        });
    }, [faviconUrl]);

    return null;
}
