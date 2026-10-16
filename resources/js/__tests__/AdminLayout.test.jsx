import { render, screen, cleanup } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

const state = vi.hoisted(() => ({ permissions: ['manage_settings', 'view_settings'], missing: new Set() }));
vi.mock('@inertiajs/react', () => ({
    usePage: () => ({ props: { auth: { permissions: state.permissions } } }),
    Link: ({ children, ...props }) => <a {...props}>{children}</a>,
}));
vi.mock('react-i18next', () => ({ useTranslation: () => ({ t: key => key }) }));
vi.mock('sonner', () => ({ Toaster: () => null }));
vi.mock('@/Components/Sidebar', () => ({ default: ({ navItems, footer }) => <nav>{navItems.map(item => <a key={item.route} href={item.href}>{item.label}</a>)}{footer}</nav> }));
vi.mock('@/Components/Topbar', () => ({ default: () => null }));
vi.mock('@/Components/CommandPalette', () => ({ default: () => null }));
import AdminLayout from '@/Layouts/AdminLayout';

beforeEach(() => {
    state.permissions = ['manage_settings', 'view_settings'];
    state.missing = new Set();
    vi.stubGlobal('route', name => {
        if (!name) return { has: key => !state.missing.has(key), current: () => false };
        if (state.missing.has(name)) throw new Error(`Ziggy error: route '${name}' is not in the route list.`);
        return `/public/${name.replaceAll('.', '/')}`;
    });
});
afterEach(() => { cleanup(); vi.unstubAllGlobals(); });

describe('admin navigation with optional modules', () => {
    it('renders the dashboard when the license route is absent from the server', () => {
        state.missing.add('admin.creasy-license');
        render(<AdminLayout><h1>Painel administrativo</h1></AdminLayout>);
        expect(screen.getByRole('heading', { name: 'Painel administrativo' })).toBeTruthy();
        expect(screen.getByRole('link', { name: 'admin.dashboard' })).toBeTruthy();
        expect(screen.queryByRole('link', { name: 'Licença CREASY' })).toBeNull();
    });
    it('shows the license link only when its route and permission are available', () => {
        const { unmount } = render(<AdminLayout>Dashboard</AdminLayout>);
        expect(screen.getByRole('link', { name: 'Licença CREASY' })).toBeTruthy();
        unmount();
        state.permissions = [];
        render(<AdminLayout>Dashboard</AdminLayout>);
        expect(screen.queryByRole('link', { name: 'Licença CREASY' })).toBeNull();
    });
    it('also tolerates other unavailable module routes', () => {
        state.missing.add('admin.ai.index');
        render(<AdminLayout>Dashboard</AdminLayout>);
        expect(screen.queryByRole('link', { name: 'admin.nav.ai' })).toBeNull();
        expect(screen.getByRole('link', { name: 'admin.dashboard' })).toBeTruthy();
    });
});
