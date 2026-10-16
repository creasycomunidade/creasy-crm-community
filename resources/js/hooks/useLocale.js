import { usePage, router } from '@inertiajs/react';
import { applyLocaleToDocument } from '@/i18n';

/**
 * Returns active locale, isRtl, locales list, and setLocale(code).
 * setLocale applies the language client-side immediately (i18next loads the
 * dictionary from /i18n/{code} if it isn't cached yet), then persists the
 * preference (user or session) in the background. LocaleSync reconciles
 * document dir/lang and the i18n store when the Inertia response lands.
 */
export function useLocale() {
    const props = usePage().props;
    const locale = props.i18n?.locale ?? props.locale ?? 'pt';
    const isRtl = props.i18n?.isRtl ?? false;
    const locales = props.i18n?.locales ?? [];
    const rtlLocales = props.rtlLocales ?? ['ar'];

    const setLocale = (code) => {
        if (code === locale) return;
        applyLocaleToDocument(code, rtlLocales);
        router.put(route('locale.update'), { locale: code }, { preserveScroll: true });
    };

    return { locale, isRtl, locales, setLocale };
}
