import { render, screen, cleanup } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import Channels from '@/Pages/Appointments/Channels';

vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    Link: ({ href, children }) => <a href={href}>{children}</a>,
    useForm: (data) => ({ data, errors: {}, processing: false, setData: vi.fn(), put: vi.fn() }),
}));
vi.mock('@/Layouts/ClientLayout', () => ({ default: ({ children }) => <main>{children}</main> }));
afterEach(cleanup);

describe('Appointment channel settings', () => {
    it('renders the empty channel notice and internal reminders without Google Calendar', () => {
        render(<Channels settings={{}} channels={[]} />);
        expect(screen.getByText(/Nenhum canal ativo e permitido/)).toBeInTheDocument();
        expect(screen.getByRole('button', { name: 'Salvar configurações' })).toBeEnabled();
        expect(screen.getByText('Enviar lembretes')).toBeInTheDocument();
        expect(screen.queryByText(/Google Agenda/)).not.toBeInTheDocument();
    });
    it('renders a selected channel without the empty notice', () => {
        render(<Channels settings={{ channel_account_id: 1 }} channels={[{ id: 1, channel: 'whatsapp', display_name: 'Atendimento' }]} />);
        expect(screen.getByRole('option', { name: /Atendimento/ })).toBeInTheDocument();
        expect(screen.queryByText(/Nenhum canal ativo e permitido/)).not.toBeInTheDocument();
    });
});
