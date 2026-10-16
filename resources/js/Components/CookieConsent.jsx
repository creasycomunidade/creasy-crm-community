import { useEffect, useState } from 'react';
import { Cookie, Settings2, X } from 'lucide-react';

const STORAGE_KEY = 'cookie_consent_v2';
const VERSION = 2;
const OPTIONAL = ['preferences', 'analytics', 'marketing'];

function readConsent() {
    try {
        const stored = JSON.parse(localStorage.getItem(STORAGE_KEY));
        return stored?.version === VERSION ? stored : null;
    } catch {
        return null;
    }
}

function publishConsent(value) {
    const consent = { version: VERSION, necessary: true, ...value, updated_at: new Date().toISOString() };
    localStorage.setItem(STORAGE_KEY, JSON.stringify(consent));
    window.respondeskCookieConsent = consent;
    window.dispatchEvent(new window.CustomEvent('cookie-consent-updated', { detail: consent }));
    return consent;
}

export default function CookieConsent() {
    const [consent, setConsent] = useState(null);
    const [open, setOpen] = useState(false);
    const [customizing, setCustomizing] = useState(false);
    const [choices, setChoices] = useState({ preferences: false, analytics: false, marketing: false });

    useEffect(() => {
        const stored = readConsent();
        setConsent(stored);
        setOpen(!stored);
        if (stored) {
            window.respondeskCookieConsent = stored;
            setChoices(Object.fromEntries(OPTIONAL.map(key => [key, Boolean(stored[key])])));
        }
    }, []);

    const save = value => {
        const next = publishConsent(value);
        setConsent(next);
        setChoices(Object.fromEntries(OPTIONAL.map(key => [key, Boolean(next[key])])));
        setOpen(false);
        setCustomizing(false);
    };

    if (!open) {
        return consent ? (
            <button type="button" onClick={() => { setOpen(true); setCustomizing(true); }} className="fixed bottom-4 left-4 z-40 flex h-9 w-9 items-center justify-center rounded-full border border-neutral-200 bg-white text-neutral-500 shadow-lg hover:text-brand-600 dark:border-neutral-700 dark:bg-neutral-900" aria-label="Alterar preferências de cookies" title="Preferências de cookies">
                <Cookie className="h-4 w-4" />
            </button>
        ) : null;
    }

    return (
        <div className="fixed inset-0 z-[100] flex items-end justify-center bg-neutral-950/30 p-3 sm:items-center" role="dialog" aria-modal="true" aria-labelledby="cookie-title">
            <section className="w-full max-w-xl rounded-2xl border border-neutral-200 bg-white p-5 shadow-2xl dark:border-neutral-700 dark:bg-neutral-900">
                <header className="flex items-start justify-between gap-4">
                    <div className="flex gap-3">
                        <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-600 dark:bg-brand-950"><Cookie className="h-5 w-5" /></span>
                        <div><h2 id="cookie-title" className="font-semibold text-neutral-900 dark:text-white">Sua privacidade importa</h2><p className="mt-1 text-sm leading-5 text-neutral-600 dark:text-neutral-300">Usamos cookies necessários para o funcionamento. Com sua autorização, também usamos preferências, análise e marketing.</p></div>
                    </div>
                    {consent && <button type="button" onClick={() => setOpen(false)} className="rounded-lg p-1.5 text-neutral-400 hover:bg-neutral-100 dark:hover:bg-neutral-800" aria-label="Fechar"><X className="h-4 w-4" /></button>}
                </header>

                {customizing && (
                    <div className="mt-4 space-y-2 border-y border-neutral-100 py-3 dark:border-neutral-800">
                        <div className="flex items-center justify-between rounded-xl bg-neutral-50 p-3 dark:bg-neutral-800"><div><p className="text-sm font-medium">Necessários</p><p className="text-xs text-neutral-500">Login, segurança e registro da escolha.</p></div><span className="text-xs font-semibold text-brand-600">Sempre ativos</span></div>
                        {[
                            ['preferences', 'Preferências', 'Idioma, tema e opções solicitadas.'],
                            ['analytics', 'Análise', 'Medição agregada para melhorar o serviço.'],
                            ['marketing', 'Marketing', 'Atribuição e personalização de campanhas.'],
                        ].map(([key, label, description]) => (
                            <label key={key} className="flex cursor-pointer items-center justify-between rounded-xl p-3 hover:bg-neutral-50 dark:hover:bg-neutral-800">
                                <span><span className="block text-sm font-medium">{label}</span><span className="text-xs text-neutral-500">{description}</span></span>
                                <input type="checkbox" checked={choices[key]} onChange={event => setChoices(current => ({ ...current, [key]: event.target.checked }))} className="h-5 w-5 rounded border-neutral-300 text-brand-600 focus:ring-brand-500" />
                            </label>
                        ))}
                    </div>
                )}

                <p className="mt-4 text-xs leading-5 text-neutral-500">Você pode mudar sua escolha quando quiser. Consulte a <a href="/p/cookies" className="font-medium text-brand-600 hover:underline">Política de Cookies</a> e a <a href="/p/privacy" className="font-medium text-brand-600 hover:underline">Política de Privacidade</a>.</p>
                <div className="mt-4 grid gap-2 sm:grid-cols-3">
                    <button type="button" onClick={() => save({ preferences: false, analytics: false, marketing: false })} className="rounded-xl border border-neutral-300 px-3 py-2.5 text-sm font-semibold text-neutral-700 hover:bg-neutral-50 dark:border-neutral-600 dark:text-neutral-200 dark:hover:bg-neutral-800">Rejeitar opcionais</button>
                    {!customizing ? <button type="button" onClick={() => setCustomizing(true)} className="inline-flex items-center justify-center gap-1.5 rounded-xl border border-brand-300 px-3 py-2.5 text-sm font-semibold text-brand-700 hover:bg-brand-50 dark:border-brand-700 dark:text-brand-300"><Settings2 className="h-4 w-4" /> Personalizar</button> : <button type="button" onClick={() => save(choices)} className="rounded-xl border border-brand-300 px-3 py-2.5 text-sm font-semibold text-brand-700 hover:bg-brand-50 dark:border-brand-700 dark:text-brand-300">Salvar escolhas</button>}
                    <button type="button" onClick={() => save({ preferences: true, analytics: true, marketing: true })} className="rounded-xl bg-brand-600 px-3 py-2.5 text-sm font-semibold text-white hover:bg-brand-700">Aceitar todos</button>
                </div>
            </section>
        </div>
    );
}
