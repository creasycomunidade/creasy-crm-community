import { fireEvent, render, screen } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { router } from '@inertiajs/react';
import WorkspaceFiles from '@/Pages/client/WorkspaceFiles/Index';

vi.mock('@/Pages/client/WorkspaceFiles/MindMapPanel', () => ({ default: ({ map }) => <div data-testid="map-panel">Editor: {map.name}</div> }));
vi.mock('@/Layouts/ClientLayout', () => ({ default: ({ children }) => children }));
vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    usePage: () => ({ props: {} }),
    useForm: vi.fn(),
    router: { visit: vi.fn(), patch: vi.fn(), on: vi.fn(() => () => {}), reload: vi.fn() },
}));
const folders = [{ uuid: 'folder-1', name: 'Cliente A', parent_uuid: null }];
const maps = [{ uuid: 'map-1', name: 'Plano do cliente', kind: 'mind_map', folder_uuid: 'folder-1', url: '/map-editor?from_folder=folder-1' }];

describe('mind maps inside Files', () => {
    beforeEach(() => vi.clearAllMocks());
    it('opens the same map from the client folder', () => {
        render(<WorkspaceFiles folders={folders} mindMaps={maps} initialFolder="folder-1" canUseMindMaps />);
        fireEvent.click(screen.getByRole('button', { name: /Plano do cliente.*Abrir e editar/ }));
        expect(router.visit).not.toHaveBeenCalled();
        expect(screen.getByTestId('map-panel')).toHaveTextContent('Editor: Plano do cliente');
        expect(screen.getByRole('complementary', { name: 'Pastas e arquivos' })).toBeVisible();
        expect(screen.getByRole('button', { name: 'Mapa mental' })).toBeVisible();
    });
    it('removes only the folder link and never deletes the map', () => {
        vi.spyOn(window, 'confirm').mockReturnValue(true);
        render(<WorkspaceFiles folders={folders} mindMaps={maps} initialFolder="folder-1" canUseMindMaps />);
        fireEvent.click(screen.getByRole('button', { name: 'Remover vínculo de Plano do cliente' }));
        expect(router.patch).toHaveBeenCalledWith(expect.stringContaining('mind-maps.folder'), { folder_uuid: null }, expect.any(Object));
        window.confirm.mockRestore();
    });
});
