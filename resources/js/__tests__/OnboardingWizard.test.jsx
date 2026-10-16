import { render, screen, cleanup } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import Wizard from '@/Pages/client/Onboarding/Wizard';

vi.mock('@/Layouts/ClientLayout', () => ({ default: ({ children }) => <main>{children}</main> }));
vi.mock('react-i18next', () => ({ useTranslation: () => ({ t: (key) => key }) }));

const destinations = {
    'client.inbox.setup': '/painel/inbox/setup',
    'client.contacts.index': '/painel/contacts',
    'client.inbox.index': '/painel/inbox',
};
const steps = ['verify_email', 'connect_first_channel', 'import_first_contacts', 'send_first_message'];

beforeEach(() => {
    vi.stubGlobal('route', (name) => {
        if (!destinations[name]) throw new Error(`Unknown route: ${name}`);
        return destinations[name];
    });
});
afterEach(() => { cleanup(); vi.unstubAllGlobals(); });

describe('Community onboarding', () => {
    it('renders all steps and links to channels, contacts and inbox', () => {
        render(<Wizard progress={{ steps: steps.map((key) => ({ key, label: key, completed: false })), is_complete: false }} />);
        expect(screen.getAllByRole('link').map((link) => link.getAttribute('href'))).toEqual(Object.values(destinations));
        expect(screen.getAllByRole('button', { name: 'onboarding.mark_done' })).toHaveLength(4);
    });
    it('renders completed steps without action links', () => {
        render(<Wizard progress={{ steps: steps.map((key) => ({ key, label: key, completed: true })), is_complete: true }} />);
        expect(screen.getByText('onboarding.complete_message')).toBeInTheDocument();
        expect(screen.queryByRole('link')).not.toBeInTheDocument();
    });
});
