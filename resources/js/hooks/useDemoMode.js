import { usePage } from '@inertiajs/react';

/**
 * True when the app runs in demo mode (config app.demo_mode, shared as the
 * `demo_mode` Inertia prop by HandleInertiaRequests).
 *
 * Demo mode is a fully-browsable showcase: every button stays clickable and all
 * data stays editable. Writes are NOT hidden — they are allowed through to the
 * server, blocked there by EnsureNotDemoMode (which returns a 403
 * { code: 'demo_mode' }), and surfaced to the user as a toast by the global
 * `router.on('invalid')` handler in app.jsx. Contact PII is still masked before
 * it reaches the browser.
 *
 * Do NOT use this hook to hide or disable action controls — that defeats the
 * "explore everything, then learn it's read-only on submit" experience. It
 * remains available only for read-only display decisions (e.g. showing an
 * informational note), should you ever need the flag in a component.
 */
export function useDemoMode() {
    return usePage().props.demo_mode === true;
}

export default useDemoMode;
