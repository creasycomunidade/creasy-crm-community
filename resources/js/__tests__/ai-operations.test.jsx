import { useState } from 'react'
import { render, screen, fireEvent, cleanup, waitFor } from '@testing-library/react'
import { afterEach, describe, expect, it, vi } from 'vitest'
import axios from 'axios'
import Operations from '@/Pages/AI/Operations/Index'
const { submit, mutation } = vi.hoisted(() => ({ submit: vi.fn(), mutation: vi.fn() }))
vi.mock('@/Layouts/ClientLayout', () => ({ default: ({ children }) => <main>{children}</main> }))
vi.mock('axios', () => ({ default: { post: vi.fn() } }))
vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    Link: ({ href, children, ...props }) => (
        <a href={href} {...props}>
            {children}
        </a>
    ),
    usePoll: vi.fn(),
    router: { post: mutation, patch: mutation, delete: mutation, reload: vi.fn() },
    useForm: (initial) => {
        const [data, set] = useState(initial)
        return {
            data,
            errors: {},
            processing: false,
            setData: (key, value) => set((previous) => ({ ...previous, [key]: value })),
            post: (url) => submit(url, data),
            put: (url) => submit(url, data),
            patch: (url) => submit(url, data),
        }
    },
}))
afterEach(() => {
    cleanup()
    vi.clearAllMocks()
})
const agents = [{ id: 1, uuid: 'agent-1', name: 'Maestro', enabled: true, provider: 'openai', model: 'test-model' }]
describe('AI operations center', () => {
    it('offers explicit execution mode and shows recorded steps', () => {
        render(<Operations agents={agents} runs={[{uuid: 'run', status: 'ok', summary: 'Continuará', execution_log: [{key: 'step', result: {message: 'Plano criado'}}]}]} />)
        fireEvent.click(screen.getByRole('button', { name: 'Novo Heartbeat' }))
        fireEvent.change(screen.getByLabelText('Nome'), {target: {value: 'Executar plano'}})
        fireEvent.change(screen.getByLabelText(/O que o agente deve acompanhar/), {target: {value: 'Crie o plano e confira'}})
        fireEvent.change(screen.getByLabelText(/Como o agente deve trabalhar/), {target: {value: 'execute'}})
        fireEvent.click(screen.getByRole('button', {name: 'Salvar Heartbeat'}))
        expect(submit).toHaveBeenCalledWith(expect.any(String), expect.objectContaining({action_mode: 'execute'}))
        fireEvent.click(screen.getByRole('button', {name: 'Histórico'}))
        expect(screen.getByText('Etapas executadas (1)')).toBeInTheDocument()
        expect(screen.getByText('Plano criado')).toBeInTheDocument()
        expect(screen.getByText('Em andamento')).toBeInTheDocument()
    })

    it('creates a paused heartbeat with explicit limits and a responsible agent', () => {
        render(<Operations agents={agents} />)
        fireEvent.click(screen.getByRole('button', { name: 'Novo Heartbeat' }))
        fireEvent.change(screen.getByLabelText('Nome'), { target: { value: 'Revisar metas' } })
        fireEvent.change(screen.getByLabelText(/O que o agente deve acompanhar/), {
            target: { value: 'Identifique riscos e bloqueios.' },
        })
        fireEvent.click(screen.getByRole('button', { name: 'Salvar Heartbeat' }))
        expect(submit).toHaveBeenCalledWith(
            '/client.ai.operations.heartbeats.store',
            expect.objectContaining({
                name: 'Revisar metas',
                agent_uuid: 'agent-1',
                enabled: false,
                daily_run_limit: 12,
                weekdays: [1, 2, 3, 4, 5],
                notification_policy: 'changes',
            }),
        )
    })
    it('saves selected event triggers and per-agent autonomy', () => {
        render(<Operations agents={agents} />)
        fireEvent.click(screen.getByRole('button', { name: 'Novo Heartbeat' }))
        fireEvent.change(screen.getByLabelText('Nome'), { target: { value: 'Monitorar' } })
        fireEvent.change(screen.getByLabelText(/O que o agente deve acompanhar/), {
            target: { value: 'Acompanhe eventos' },
        })
        fireEvent.click(screen.getByLabelText('Mensagem recebida'))
        fireEvent.click(screen.getByLabelText('Decisão de aprovação'))
        fireEvent.click(screen.getByRole('button', { name: 'Salvar Heartbeat' }))
        expect(submit).toHaveBeenCalledWith(
            expect.any(String),
            expect.objectContaining({ wake_triggers: ['inbound_message', 'approval_decision'] }),
        )
        fireEvent.click(screen.getByRole('button', { name: 'Agentes e modelos' }))
        fireEvent.click(screen.getByLabelText(/Autonomia para criar e ativar Heartbeats/))
        fireEvent.click(screen.getByRole('button', { name: 'Salvar limites' }))
        expect(submit).toHaveBeenCalledWith(expect.any(String), expect.objectContaining({ heartbeat_autonomy: true }))
    })
    it('submits a measured goal with selected tasks', () => {
        render(
            <Operations
                agents={agents}
                tasks={[{ uuid: 'task-1', title: 'Entregar proposta', status: 'completed' }]}
            />,
        )
        fireEvent.click(screen.getByRole('button', { name: 'Metas' }))
        fireEvent.click(screen.getByRole('button', { name: 'Nova meta' }))
        fireEvent.change(screen.getByLabelText('Resultado esperado'), { target: { value: 'Entregar propostas' } })
        fireEvent.click(screen.getByLabelText('Entregar proposta'))
        fireEvent.click(screen.getByRole('button', { name: 'Salvar meta' }))
        expect(submit).toHaveBeenCalledWith(
            '/client.ai.operations.goals.store',
            expect.objectContaining({ title: 'Entregar propostas', metric: 'completed_tasks', task_uuids: ['task-1'] }),
        )
    })
    it('shows measured progress and acknowledgements', () => {
        render(
            <Operations
                agents={agents}
                goals={[
                    {
                        uuid: 'g-1',
                        id: 1,
                        title: 'Entregas',
                        status: 'active',
                        measured_value: 3,
                        target_value: 10,
                        unit: 'tarefas',
                    },
                ]}
                runs={[
                    {
                        uuid: 'r-1',
                        status: 'attention',
                        summary: 'Há atraso',
                        attention: true,
                        heartbeat: { name: 'Monitor' },
                        input_tokens: 100,
                        output_tokens: 20,
                    },
                ]}
            />,
        )
        fireEvent.click(screen.getByRole('button', { name: 'Metas' }))
        expect(screen.getByRole('progressbar', { name: 'Progresso de Entregas' })).toHaveAttribute('value', '30')
        fireEvent.click(screen.getByRole('button', { name: 'Histórico' }))
        fireEvent.click(screen.getByRole('button', { name: 'Marcar como revisado' }))
        expect(mutation).toHaveBeenCalledWith(expect.stringContaining('runs.acknowledge'), {}, expect.any(Object))
    })
    it('previews relevant skills without calling an agent execution endpoint', async () => {
        axios.post.mockResolvedValue({
            data: { skills: [{ name: 'Monitor', description: 'Analisa riscos', version: 1, reason: 'trigger' }] },
        })
        render(<Operations agents={agents} />)
        fireEvent.click(screen.getByRole('button', { name: 'Habilidades' }))
        fireEvent.change(screen.getByLabelText('Exemplo de solicitação'), { target: { value: 'Monitorar metas' } })
        fireEvent.click(screen.getByRole('button', { name: 'Ver habilidades selecionadas' }))
        await waitFor(() => expect(screen.getByText('Analisa riscos')).toBeInTheDocument())
        expect(axios.post).toHaveBeenCalledWith('/client.ai.operations.skills.preview', {
            agent_uuid: 'agent-1',
            input: 'Monitorar metas',
        })
    })
    it('saves per-agent model limits', () => {
        render(<Operations agents={agents} />)
        fireEvent.click(screen.getByRole('button', { name: 'Agentes e modelos' }))
        fireEvent.change(screen.getByLabelText('Teto de tokens por resposta'), { target: { value: '1024' } })
        fireEvent.click(screen.getByRole('button', { name: 'Salvar limites' }))
        expect(submit).toHaveBeenCalledWith(
            expect.stringContaining('agents.settings'),
            expect.objectContaining({ max_output_tokens: 1024 }),
        )
    })
    it('disables creation when database setup is required', () => {
        render(<Operations agents={agents} setupRequired />)
        expect(screen.queryByRole('button', { name: 'Novo Heartbeat' })).not.toBeInTheDocument()
        expect(screen.getByText(/atualização do banco/)).toBeInTheDocument()
    })
})
