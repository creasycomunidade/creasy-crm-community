import { useState, useEffect, useRef, useCallback } from 'react';
import { createPortal } from 'react-dom';
import { router } from '@inertiajs/react';
import { Search, X } from 'lucide-react';
import { useTranslation } from 'react-i18next';

export default function GlobalSearch() {
    const { t } = useTranslation();
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState('');
    const [results, setResults] = useState([]);
    const [loading, setLoading] = useState(false);
    const [selectedIndex, setSelectedIndex] = useState(0);
    const inputRef = useRef(null);
    const debounceRef = useRef(null);

    useEffect(() => {
        const onKeyDown = (e) => {
            if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
                e.preventDefault();
                setOpen(v => !v);
            }
            if (e.key === 'Escape') {
                setOpen(false);
            }
        };
        window.addEventListener('keydown', onKeyDown);
        return () => window.removeEventListener('keydown', onKeyDown);
    }, []);

    useEffect(() => {
        if (open && inputRef.current) {
            inputRef.current.focus();
        }
        if (!open) {
            setQuery('');
            setResults([]);
        }
    }, [open]);

    const search = useCallback(async (q) => {
        if (q.length < 2) { setResults([]); return; }
        setLoading(true);
        try {
            const res = await fetch(route('client.search') + '?q=' + encodeURIComponent(q), {
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content },
                credentials: 'include',
            });
            const json = await res.json();
            setResults(json.results ?? []);
            setSelectedIndex(0);
        } catch {
            setResults([]);
        } finally {
            setLoading(false);
        }
    }, []);

    const handleChange = (e) => {
        const q = e.target.value;
        setQuery(q);
        clearTimeout(debounceRef.current);
        debounceRef.current = setTimeout(() => search(q), 200);
    };

    const handleKeyDown = (e) => {
        if (e.key === 'ArrowDown') { e.preventDefault(); setSelectedIndex(i => Math.min(i + 1, results.length - 1)); }
        if (e.key === 'ArrowUp') { e.preventDefault(); setSelectedIndex(i => Math.max(i - 1, 0)); }
        if (e.key === 'Enter' && results[selectedIndex]) {
            navigate(results[selectedIndex].href);
        }
    };

    const navigate = (href) => {
        setOpen(false);
        router.visit(href);
    };

    if (!open) {
        return (
            <button
                onClick={() => setOpen(true)}
                className="hidden sm:flex items-center gap-2 px-3 py-1.5 text-sm text-neutral-500 dark:text-neutral-400 border border-neutral-300 dark:border-neutral-600 rounded-soft hover:border-brand-400 dark:hover:border-brand-600 hover:text-neutral-700 dark:hover:text-neutral-200 transition-colors duration-150"
                title={t('ui.global_search_title')}
            >
                <Search className="h-3.5 w-3.5" />
                <span>{t('ui.search_short')}</span>
                <kbd className="text-xs bg-neutral-100 dark:bg-neutral-700 px-1.5 py-0.5 rounded">⌘K</kbd>
            </button>
        );
    }

    return createPortal(
        <div className="fixed inset-0 z-[200] flex items-start justify-center pt-20 px-4">
            <div className="fixed inset-0 bg-black/50" onClick={() => setOpen(false)} />
            <div className="relative w-full max-w-xl bg-white dark:bg-neutral-900 rounded-xl shadow-soft-xl border border-neutral-200 dark:border-neutral-700 overflow-hidden">
                <div className="flex items-center gap-3 px-4 py-3 border-b border-neutral-200 dark:border-neutral-700">
                    <Search className="h-4 w-4 text-neutral-400 shrink-0" />
                    <input
                        ref={inputRef}
                        type="text"
                        value={query}
                        onChange={handleChange}
                        onKeyDown={handleKeyDown}
                        placeholder={t('ui.global_search_placeholder')}
                        className="flex-1 bg-transparent text-sm text-neutral-900 dark:text-white placeholder-neutral-400 outline-none"
                    />
                    {query && (
                        <button onClick={() => { setQuery(''); setResults([]); inputRef.current?.focus(); }} className="text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-200">
                            <X className="h-4 w-4" />
                        </button>
                    )}
                    <button onClick={() => setOpen(false)} className="text-xs text-neutral-400 border border-neutral-300 dark:border-neutral-600 px-1.5 py-0.5 rounded">
                        Esc
                    </button>
                </div>

                {results.length > 0 && (
                    <ul className="py-2 max-h-80 overflow-y-auto">
                        {results.map((result, i) => (
                            <li key={result.href}>
                                <button
                                    onClick={() => navigate(result.href)}
                                    onMouseEnter={() => setSelectedIndex(i)}
                                    className={`w-full text-left flex items-center gap-3 px-4 py-2.5 text-sm transition duration-100 ${
                                        i === selectedIndex
                                            ? 'bg-brand-50 dark:bg-brand-900/30 text-brand-700 dark:text-brand-300'
                                            : 'text-neutral-700 dark:text-neutral-300 hover:bg-neutral-50 dark:hover:bg-neutral-800'
                                    }`}
                                >
                                    <span className="flex-1">{result.label}</span>
                                    <span className="text-xs text-neutral-400 capitalize">{result.type}</span>
                                </button>
                            </li>
                        ))}
                    </ul>
                )}

                {query.length >= 2 && !loading && results.length === 0 && (
                    <div className="px-4 py-6 text-center text-sm text-neutral-400 dark:text-neutral-500">
                        No results for "{query}"
                    </div>
                )}

                {loading && (
                    <div className="px-4 py-6 text-center text-sm text-neutral-400 dark:text-neutral-500">
                        Searching...
                    </div>
                )}

                <div className="px-4 py-2 border-t border-neutral-100 dark:border-neutral-800 flex items-center gap-3 text-xs text-neutral-400 dark:text-neutral-500">
                    <span>↑↓ navigate</span>
                    <span>↵ open</span>
                    <span>{t('ui.global_search_hint')}</span>
                </div>
            </div>
        </div>,
        document.body
    );
}
