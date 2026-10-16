import { cleanup, fireEvent, render, screen } from '@testing-library/react';
import { afterEach, expect, it, vi } from 'vitest';
import BulkImport from '@/Pages/Contacts/BulkImport';
vi.mock('@/Layouts/ClientLayout', () => ({ default: ({ children }) => <main>{children}</main> }));
vi.mock('react-i18next', () => ({ useTranslation: () => ({ t: key => key }), Trans: () => null }));
afterEach(cleanup);
it('pastes rows from a spreadsheet and keeps cells editable without a commercial grid', () => {
    render(<BulkImport tags={[]} segments={[]} />);
    fireEvent.paste(screen.getByLabelText('common.name 1'), { clipboardData: { getData: () => 'Ana\t+5511999999999\nBia\t+5511888888888' } });
    expect(screen.getByLabelText('common.name 1')).toHaveValue('Ana');
    expect(screen.getByLabelText('common.name 2')).toHaveValue('Bia');
    expect(screen.getByLabelText('contacts_page.phone_e164 2')).toHaveValue('+5511888888888');
    fireEvent.change(screen.getByLabelText('common.name 2'), { target: { value: 'Beatriz' } });
    expect(screen.getByLabelText('common.name 2')).toHaveValue('Beatriz');
    fireEvent.click(screen.getByRole('button', { name: 'Remover linha 1' }));
    expect(screen.getByLabelText('common.name 1')).toHaveValue('Beatriz');
});
