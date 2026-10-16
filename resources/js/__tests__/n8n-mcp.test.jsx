import { useState } from 'react';
import { render, screen, fireEvent, cleanup } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import CapabilitiesIndex from '@/Pages/AI/Capabilities/Index';

const { submit } = vi.hoisted(() => ({ submit: vi.fn() }));
vi.mock('@/Layouts/ClientLayout', () => ({ default: ({ children }) => <div>{children}</div> }));
vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    router: { post: vi.fn(), delete: vi.fn() },
    useForm: initial => {
        const [data, set] = useState(initial);
        return {
            data, errors: {}, processing: false,
            setData: (key, value) => set(previous => ({ ...previous, [key]: value })),
            post: url => submit(url, data), put: url => submit(url, data),
        };
    },
}));
afterEach(() => { cleanup(); submit.mockClear(); });

const agent = { uuid: 'agent-1', name: 'Maestro', enabled: true };
describe('n8n MCP connection form', () => {
    it('requires an explicit agent and submits the n8n credentials and workflow configuration', () => {
        render(<CapabilitiesIndex agents={[agent]} />);
        fireEvent.click(screen.getByRole('button', { name: 'Conectar MCP' }));
        fireEvent.change(screen.getByLabelText('Tipo de conexão'), { target: { value: 'n8n' } });
        expect(screen.getByRole('button', { name: 'Salvar e testar' })).toBeDisabled();
        fireEvent.change(screen.getByLabelText('Nome *'), { target: { value: 'Aurora n8n' } });
        fireEvent.change(screen.getByLabelText(/Endereço do n8n/), { target: { value: 'https://wf.example.com' } });
        fireEvent.change(screen.getByLabelText(/Chave de API do n8n/), { target: { value: 'test-key' } });
        fireEvent.change(screen.getByLabelText(/URL de produção do webhook/), { target: { value: 'https://wf.example.com/webhook/ai' } });
        fireEvent.change(screen.getByLabelText(/Permissão/), { target: { value: 'approval_required' } });
        fireEvent.click(screen.getByLabelText('Maestro'));
        expect(screen.getByRole('button', { name: 'Salvar e testar' })).toBeEnabled();
        fireEvent.click(screen.getByRole('button', { name: 'Salvar e testar' }));
        expect(submit).toHaveBeenCalledWith('/client.ai.mcp-servers.store', expect.objectContaining({
            connection_type: 'n8n', api_key: 'test-key', agent_uuids: ['agent-1'],
            ai_webhook_url: 'https://wf.example.com/webhook/ai', permission_mode: 'approval_required',
        }));
    });

    it('keeps credentials empty on edit and allows removing the saved webhook token', () => {
        render(<CapabilitiesIndex agents={[agent]} servers={[{
            uuid: 'server-1', connection_type: 'n8n', name: 'n8n', endpoint_url: 'https://wf.example.com',
            enabled: true, status: 'connected', permission_mode: 'approval_required',
            has_credentials: true, has_webhook_token: true, agents: [agent],
            configuration: { ai_webhook_url: 'https://wf.example.com/webhook/ai' },
        }]} />);
        fireEvent.click(screen.getByRole('tab', { name: 'Servidores MCP' }));
        fireEvent.click(screen.getByRole('button', { name: 'Editar' }));
        expect(screen.getByLabelText('Tipo de conexão')).toBeDisabled();
        expect(screen.getByLabelText(/Chave de API do n8n/)).toHaveValue('');
        fireEvent.click(screen.getByLabelText('Remover o token salvo do webhook'));
        expect(screen.getByLabelText(/Token do webhook \(vazio/)).toBeDisabled();
        fireEvent.click(screen.getByRole('button', { name: 'Salvar conexão' }));
        expect(submit).toHaveBeenCalledWith(expect.any(String), expect.objectContaining({
            api_key: '', clear_webhook_token: true,
        }));
    });
});

it('finds native skills by Portuguese description without accents', () => {
    render(<CapabilitiesIndex skills={[
        {uuid:'one', name:'Revisar contrato', description:'Revisão jurídica de cláusulas', category:'Jurídico', agents:[], trigger_terms:[], enabled:true},
        {uuid:'two', name:'Analisar caixa', description:'Resumo financeiro', category:'Financeiro', agents:[], trigger_terms:[], enabled:true},
    ]} />)
    fireEvent.change(screen.getByLabelText('Pesquisar habilidades'), {target:{value:'juridica'}})
    expect(screen.getByText('Revisar contrato')).toBeTruthy()
    expect(screen.queryByText('Analisar caixa')).toBeNull()
})
