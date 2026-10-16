import { describe, it, expect } from 'vitest';
import i18n, { initI18n } from '../i18n';

describe('Portuguese translation bootstrap', () => {
    it('renders login text synchronously without an HTTP request', () => {
        document.documentElement.lang = 'pt-BR';
        initI18n();
        expect(i18n.isInitialized).toBe(true);
        expect(i18n.t('auth.log_in')).toBe('Entrar');
        expect(i18n.t('auth.email')).not.toBe('auth.email');
        expect(i18n.t('auth.login_subtitle')).toBe('Digite suas credenciais para continuar');
        expect(i18n.services.backendConnector.state['pt|translation']).not.toBe(1);
    });

    it('accepts the flat customized translations supplied by Laravel', () => {
        i18n.addResourceBundle('pt', 'translation', { 'auth.log_in': 'Acessar minha conta' }, true, true);
        expect(i18n.t('auth.log_in')).toBe('Acessar minha conta');
    });

    it('uses the server route so language requests retain the installation subdirectory', () => {
        const previousRoute = globalThis.route;
        globalThis.route = (name, params) => `https://example.test/crm/public/i18n/${params.locale}`;
        try {
            expect(i18n.options.backend.loadPath(['es'])).toBe('https://example.test/crm/public/i18n/es');
        } finally {
            globalThis.route = previousRoute;
        }
    });
});
