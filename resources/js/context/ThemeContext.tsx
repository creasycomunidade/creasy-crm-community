import { createContext, useCallback, useContext, useEffect, useMemo, useState } from 'react';
import { usePage } from '@inertiajs/react';

const STORAGE_KEY = 'theme';

export const ThemeContext = createContext({
    theme: 'light',
    setTheme: () => {},
});

function getInitialTheme(serverTheme, hasUser, stored) {
    // Always prefer localStorage so theme persists across navigations and isn't overwritten by server default
    if (stored === 'dark' || stored === 'light') return stored;
    if (hasUser && serverTheme) return serverTheme;
    return serverTheme || 'light';
}

export function ThemeProvider({ children }) {
    const page = usePage();
    const serverTheme = page.props.theme;
    const hasUser = !!page.props.auth?.user;

    const [theme, setThemeState] = useState(() => {
        if (typeof window === 'undefined') return serverTheme || 'light';
        const stored = window.localStorage.getItem(STORAGE_KEY);
        return getInitialTheme(serverTheme, hasUser, stored);
    });

    // Sync from server when user is present (e.g. after login or refresh).
    // Don't overwrite when user has an explicit localStorage preference that differs (e.g. just toggled to dark, or admin with no server persistence).
    useEffect(() => {
        if (!hasUser || !serverTheme || serverTheme === theme) return;
        const stored = typeof window !== 'undefined' ? window.localStorage.getItem(STORAGE_KEY) : null;
        if (stored === 'dark' || stored === 'light') {
            if (stored !== serverTheme) return; // keep user's choice when it conflicts with server
        }
        setThemeState(serverTheme);
    }, [hasUser, serverTheme, theme]);

    // Apply class to <html>
    useEffect(() => {
        document.documentElement.classList.toggle('dark', theme === 'dark');
    }, [theme]);

    const setTheme = useCallback((next) => {
        setThemeState((prev) => {
            const value = typeof next === 'function' ? next(prev) : (next === 'dark' || next === 'light' ? next : prev === 'dark' ? 'light' : 'dark');
            if (typeof window !== 'undefined') {
                window.localStorage.setItem(STORAGE_KEY, value);
            }
            document.documentElement.classList.toggle('dark', value === 'dark');
            return value;
        });
    }, []);

    const value = useMemo(() => ({ theme, setTheme }), [theme, setTheme]);

    return (
        <ThemeContext.Provider value={value}>
            {children}
        </ThemeContext.Provider>
    );
}

export function useTheme() {
    const ctx = useContext(ThemeContext);
    if (!ctx) throw new Error('useTheme must be used within ThemeProvider');
    return ctx;
}
