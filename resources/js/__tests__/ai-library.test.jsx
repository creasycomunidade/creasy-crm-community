import { render, screen, fireEvent, cleanup } from '@testing-library/react'
import { afterEach, it, expect, vi } from 'vitest'
import Library from '@/Pages/AI/Library/Index'
const { post, patch } = vi.hoisted(() => ({ post: vi.fn(), patch: vi.fn() }))
vi.mock('@/Layouts/ClientLayout', () => ({ default: ({ children }) => <main>{children}</main> }))
vi.mock('axios', () => ({ default: { post: vi.fn(), get: vi.fn() } }))
vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    Link: ({ children, href }) => <a href={href}>{children}</a>,
    usePage: () => ({ props: {} }),
    router: { post, patch },
}))
afterEach(() => {
    cleanup()
    vi.clearAllMocks()
})
it('shows projects and missions without the installation catalog', () => {
    render(<Library />)
    expect(screen.getByRole('heading', { name: 'Projetos e missões' })).toBeTruthy()
    expect(screen.getByRole('button', { name: 'Nova missão' })).toBeTruthy()
    expect(screen.queryByText('Instalar neste espaço')).toBeNull()
    expect(screen.getByText(/Esta organização não executa tarefas sozinha/)).toBeTruthy()
})
it('links a goal to a project using scoped server validation', () => {
    render(
        <Library
            projects={[{ id: 2, uuid: 'p', title: 'Projeto', mission_id: null }]}
            goals={[{ id: 1, uuid: 'g', title: 'Meta', project_id: null }]}
        />,
    )
    fireEvent.change(screen.getByLabelText('Meta'), { target: { value: '2' } })
    expect(patch).toHaveBeenCalledWith(expect.any(String), { project_id: '2' }, expect.any(Object))
})
