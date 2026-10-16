import { Component } from 'react';
import { router } from '@inertiajs/react';

/**
 * App-wide error boundary. A render/runtime error thrown anywhere in the React
 * tree (a page, a layout, a shared component) would otherwise unmount the whole
 * SPA and leave a blank white screen with no way back. This catches it, shows a
 * recoverable fallback card, and auto-resets on the next Inertia navigation so a
 * single bad page can't brick the entire admin/app.
 *
 * O fallback usa texto estático em português (sem i18n), pois a própria camada
 * de tradução pode ter causado o erro e não deve ser uma dependência aqui.
 */
export default class ErrorBoundary extends Component {
    constructor(props) {
        super(props);
        this.state = { hasError: false, error: null };
    }

    static getDerivedStateFromError(error) {
        return { hasError: true, error };
    }

    componentDidCatch(error, info) {
        // Surface it for debugging / error-tracking without killing the app.
        // eslint-disable-next-line no-console
        console.error('[ErrorBoundary] Uncaught render error:', error, info?.componentStack);
    }

    componentDidMount() {
        // Clear the error state when the user navigates elsewhere, so recovery
        // doesn't require a full page reload.
        this._removeListener = router.on('navigate', () => {
            if (this.state.hasError) {
                this.setState({ hasError: false, error: null });
            }
        });
    }

    componentWillUnmount() {
        this._removeListener?.();
    }

    render() {
        if (!this.state.hasError) {
            return this.props.children;
        }

        return (
            <div className="min-h-screen flex items-center justify-center bg-neutral-50 dark:bg-neutral-950 p-6">
                <div className="max-w-md w-full rounded-2xl border border-neutral-200 dark:border-neutral-800 bg-white dark:bg-neutral-900 p-8 text-center shadow-sm">
                    <div className="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-coral-100 dark:bg-coral-900/30">
                        <svg className="h-6 w-6 text-coral-600 dark:text-coral-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2">
                            <path strokeLinecap="round" strokeLinejoin="round" d="M12 9v3.75m0 3.75h.01M10.34 3.94l-8.4 14.55A1.5 1.5 0 003.24 21h17.52a1.5 1.5 0 001.3-2.51l-8.4-14.55a1.5 1.5 0 00-2.6 0z" />
                        </svg>
                    </div>
                    <h1 className="text-lg font-semibold text-neutral-900 dark:text-neutral-100">Ocorreu um erro</h1>
                    <p className="mt-2 text-sm text-neutral-500 dark:text-neutral-400">
                        Esta página encontrou um erro inesperado. Recarregue a página e, se o problema continuar, entre em contato com o suporte.
                    </p>
                    <div className="mt-6 flex items-center justify-center gap-3">
                        <button
                            type="button"
                            onClick={() => window.location.reload()}
                            className="rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500/30"
                        >
                            Recarregar página
                        </button>
                        <button
                            type="button"
                            onClick={() => window.history.back()}
                            className="rounded-lg border border-neutral-300 dark:border-neutral-700 px-4 py-2 text-sm font-medium text-neutral-700 dark:text-neutral-200 hover:bg-neutral-50 dark:hover:bg-neutral-800"
                        >
                            Voltar
                        </button>
                    </div>
                </div>
            </div>
        );
    }
}
