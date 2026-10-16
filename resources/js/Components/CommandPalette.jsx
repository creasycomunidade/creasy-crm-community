import { useState, useEffect, useRef, useCallback } from 'react';
import { router } from '@inertiajs/react';
import { Search, Loader2, FileText, Users, Building2, Package, CreditCard, LayoutDashboard, User, Settings, X } from 'lucide-react';
import { useTranslation } from 'react-i18next';

const ICON_MAP = {
    LayoutDashboard, Users, Building2, Package, CreditCard, FileText, User, Settings,
};

export default function CommandPalette({ searchRoute }) {
    const { t } = useTranslation();
    const [open, setOpen]         = useState(false);
    const [query, setQuery]       = useState('');
    const [results, setResults]   = useState([]);
    const [loading, setLoading]   = useState(false);
    const [selected, setSelected] = useState(0);
    const inputRef = useRef(null);
    const abortRef = useRef(null);

    // Open on Cmd+K / Ctrl+K
    useEffect(() => {
        const handler = (e) => {
            if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
                e.preventDefault();
                setOpen(v => !v);
            }
            if (e.key === 'Escape') setOpen(false);
        };
        window.addEventListener('keydown', handler);
        return () => window.removeEventListener('keydown', handler);
    }, []);

    // Focus input on open
    useEffect(() => {
        if (open) {
            setTimeout(() => inputRef.current?.focus(), 50);
            setQuery('');
            setResults([]);
            setSelected(0);
        }
    }, [open]);

    // Debounced search
    useEffect(() => {
        if (! open || query.length < 2) { setResults([]); return; }

        const timer = setTimeout(() => {
            abortRef.current?.abort();
            const controller = new AbortController();
            abortRef.current = controller;
            setLoading(true);
            fetch(`${searchRoute}?q=${encodeURIComponent(query)}`, { signal: controller.signal })
                .then(r => r.json())
                .then(data => { setResults(data.results ?? []); setSelected(0); })
                .catch(() => {})
                .finally(() => setLoading(false));
        }, 200);

        return () => clearTimeout(timer);
    }, [query, open, searchRoute]);

    const navigate = useCallback((result) => {
        setOpen(false);
        router.visit(result.href);
    }, []);

    // Keyboard navigation
    const handleKeyDown = (e) => {
        if (e.key === 'ArrowDown') { e.preventDefault(); setSelected(s => Math.min(s + 1, results.length - 1)); }
        if (e.key === 'ArrowUp')   { e.preventDefault(); setSelected(s => Math.max(s - 1, 0)); }
        if (e.key === 'Enter' && results[selected]) navigate(results[selected]);
    };

    if (! open) return null;

    return (
        <div className="fixed inset-0 z-50 flex items-start justify-center pt-[10vh] px-4">
            <div className="absolute inset-0 bg-black/40 backdrop-blur-sm" onClick={() => setOpen(false)} />
            <div className="relative w-full max-w-lg rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-700 shadow-2xl overflow-hidden">
                {/* Input */}
                <div className="flex items-center gap-3 px-4 py-3 border-b border-neutral-100 dark:border-neutral-800">
                    <Search className="h-4 w-4 text-neutral-400 flex-shrink-0" />
                    <input
                        ref={inputRef}
                        type="text"
                        value={query}
                        onChange={e => setQuery(e.target.value)}
                        onKeyDown={handleKeyDown}
                        placeholder={t('ui.search_placeholder')}
                        className="flex-1 bg-transparent text-sm text-neutral-900 dark:text-neutral-100 placeholder-neutral-400 outline-none"
                    />
                    {loading && <Loader2 className="h-4 w-4 text-neutral-400 animate-spin" />}
                    {!loading && <kbd className="hidden sm:inline-flex text-xs text-neutral-400 border border-neutral-200 dark:border-neutral-700 rounded px-1.5 py-0.5">Esc</kbd>}
                </div>

                {/* Results */}
                {results.length > 0 && (
                    <ul className="max-h-72 overflow-y-auto py-2">
                        {results.map((r, i) => {
                            const Icon = ICON_MAP[r.icon] ?? FileText;
                            return (
                                <li key={i}>
                                    <button
                                        onClick={() => navigate(r)}
                                        className={`w-full flex items-center gap-3 px-4 py-2.5 text-left transition ${i === selected ? 'bg-brand-50 dark:bg-brand-900/20' : 'hover:bg-neutral-50 dark:hover:bg-neutral-800/50'}`}
                                    >
                                        <Icon className="h-4 w-4 text-neutral-400 flex-shrink-0" />
                                        <div className="flex-1 min-w-0">
                                            <span className="text-sm font-medium text-neutral-900 dark:text-neutral-100 truncate">{r.label}</span>
                                            {r.sub && <span className="block text-xs text-neutral-400 truncate">{r.sub}</span>}
                                        </div>
                                        <span className="text-xs text-neutral-400 capitalize">{r.type}</span>
                                    </button>
                                </li>
                            );
                        })}
                    </ul>
                )}

                {query.length >= 2 && !loading && results.length === 0 && (
                    <div className="px-4 py-8 text-center text-sm text-neutral-400">{t('ui.no_results_for', { query })}</div>
                )}

                {query.length < 2 && (
                    <div className="px-4 py-4 text-xs text-neutral-400">
                        {t('ui.type_to_search')}
                    </div>
                )}

                {/* Footer hint */}
                <div className="flex items-center gap-3 px-4 py-2 border-t border-neutral-100 dark:border-neutral-800 bg-neutral-50 dark:bg-neutral-900/60">
                    <span className="text-xs text-neutral-400 flex items-center gap-1">
                        <kbd className="border border-neutral-200 dark:border-neutral-700 rounded px-1 py-0.5">↑↓</kbd> {t('ui.cmd_navigate')}
                    </span>
                    <span className="text-xs text-neutral-400 flex items-center gap-1">
                        <kbd className="border border-neutral-200 dark:border-neutral-700 rounded px-1 py-0.5">↵</kbd> {t('ui.cmd_open')}
                    </span>
                    <span className="ml-auto text-xs text-neutral-400">
                        <kbd className="border border-neutral-200 dark:border-neutral-700 rounded px-1 py-0.5">⌘K</kbd> {t('ui.cmd_close')}
                    </span>
                </div>
            </div>
        </div>
    );
}
