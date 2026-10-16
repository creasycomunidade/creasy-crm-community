import { usePage } from '@inertiajs/react';
import { useEffect } from 'react';
import i18n, { applyLocaleToDocument } from '@/i18n';

/**
 * Syncs Inertia shared props (locale, rtlLocales) to document dir/lang and i18n.
 * Every Inertia response shares the active locale's full dictionary
 * (i18n.translations); seed it into the i18n store before switching so the
 * language change applies instantly without waiting on the HTTP backend.
 * Renders nothing. Include once at app root so every page applies locale.
 */
export default function LocaleSync() {
    const { locale, rtlLocales = [], i18n: i18nProps } = usePage().props;
    const activeLocale = i18nProps?.locale ?? locale;
    const translations = i18nProps?.translations;

    useEffect(() => {
        if (!activeLocale) return;
        if (translations && typeof i18n.addResourceBundle === 'function') {
            i18n.addResourceBundle(activeLocale, 'translation', translations, true, true);
        }
        applyLocaleToDocument(activeLocale, rtlLocales);
    }, [activeLocale, translations, rtlLocales]);

    return null;
}
