import { render, screen, fireEvent, cleanup } from '@testing-library/react';
import { afterEach, expect, it, vi } from 'vitest';
import Files from '@/Pages/client/WorkspaceFiles/Index';
import MarkdownDocument from '@/Components/MarkdownDocument';
const { patch } = vi.hoisted(() => ({ patch: vi.fn() }));
vi.mock('@/Layouts/ClientLayout', () => ({ default: ({ children }) => <main>{children}</main> }));
vi.mock('@inertiajs/react', () => ({ Head: () => null, usePage: () => ({props:{}}), router: {patch, on: () => () => {}} }));
afterEach(() => {cleanup();vi.clearAllMocks();});
const file={uuid:'document',name:'Guia.md',extension:'md',editable:true,content:'# Guia\n\nTexto **importante**',updated_at:'2026-09-23',size:42};
it('edits inside the preview and preserves the draft when viewing formatted Markdown', () => {
 render(<Files files={[file]} />);
 expect(screen.getByRole('heading',{name:'Guia'})).toBeTruthy();
 fireEvent.click(screen.getByRole('button',{name:'Editar'}));
 expect(screen.queryByRole('dialog')).toBeNull();
 fireEvent.change(screen.getByLabelText('Conteúdo do arquivo'),{target:{value:'# Revisado'}});
 fireEvent.click(screen.getByRole('button',{name:'Prévia formatada'}));
 expect(screen.getByRole('heading',{name:'Revisado'})).toBeTruthy();
 fireEvent.click(screen.getByRole('button',{name:'Voltar à edição'}));
 expect(screen.getByLabelText('Conteúdo do arquivo').value).toBe('# Revisado');
 fireEvent.click(screen.getByRole('button',{name:'Salvar'}));
 expect(patch).toHaveBeenCalledWith(expect.any(String),expect.objectContaining({content:'# Revisado'}),expect.any(Object));
});
it('renders code literally and supports tables without executing embedded HTML', () => {
 const {container}=render(<MarkdownDocument content={'# Título\n\n```sql\nSELECT * FROM users;\n```\n\n| Nome |\n| --- |\n| Ana |\n\n<script>alert(1)</script>\n\n[link](javascript:alert)'} />);
 expect(container.querySelector('pre code').textContent).toContain('SELECT *');
 expect(screen.getByRole('table')).toBeTruthy();
 expect(container.querySelector('script')).toBeNull();
 expect(container.querySelector('a').getAttribute('href')).not.toContain('javascript:');
});
