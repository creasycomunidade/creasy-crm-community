import i18n from 'i18next';
import { initReactI18next } from 'react-i18next';
import Backend from 'i18next-http-backend';
import portuguese from './locales/pt.json';

// Match the flat dictionaries delivered by Laravel, including custom overrides.
function flattenDictionary(dictionary, prefix = '') {
    return Object.fromEntries(Object.entries(dictionary).flatMap(([key, value]) => {
        const name = prefix ? `${prefix}.${key}` : key;
        if (Array.isArray(value)) return [];
        return value && typeof value === 'object'
            ? Object.entries(flattenDictionary(value, name))
            : [[name, value]];
    }));
}

/**
 * Initialize i18next with translations from GET /i18n/{locale} (served from resources/js/locales/{locale}.json).
 * Initial language from document.documentElement.lang (set by Blade).
 */
export function initI18n() {
    const initialLang = typeof document !== 'undefined'
        ? (document.documentElement.getAttribute('lang') || 'pt').split('-')[0]
        : 'pt';

    i18n
        .use(Backend)
        .use(initReactI18next)
        .init({
            lng: initialLang,
            initImmediate: false,
            partialBundledLanguages: true,
            resources: { pt: { translation: flattenDictionary(portuguese) } },
            fallbackLng: 'pt',
            ns: ['translation'],
            defaultNS: 'translation',
            backend: {
                loadPath: (lngs) => typeof route === 'function'
                    ? route('i18n.show', { locale: lngs[0] })
                    : `/i18n/${encodeURIComponent(lngs[0])}`,
                // The endpoint wraps the dictionary as {translation: {...}}. i18next
                // stores parse()'s return value AS the namespace bundle, so we must
                // unwrap here — returning the wrapper double-nests every key and all
                // lookups fall back to English (language switching appears broken).
                parse: (data) => {
                    const parsed = typeof data === 'string' ? JSON.parse(data) : data;
                    return parsed?.translation ?? parsed;
                },
            },
            interpolation: {
                escapeValue: false,
            },
            react: {
                useSuspense: false,
            },
        });

    return i18n;
}

/**
 * Apply RTL/LTR to document and sync i18n language. Call after locale change.
 */
export function applyLocaleToDocument(locale, rtlLocales = ['ar']) {
    const dir = rtlLocales.includes(locale) ? 'rtl' : 'ltr';
    if (typeof document !== 'undefined') {
        document.documentElement.setAttribute('dir', dir);
        document.documentElement.setAttribute('lang', locale === 'pt' ? 'pt-BR' : locale);
    }
    if (i18n.isInitialized) {
        i18n.changeLanguage(locale);
    }
}

export default i18n;
