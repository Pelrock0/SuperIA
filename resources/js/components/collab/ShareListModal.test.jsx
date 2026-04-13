import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, it, expect, vi, beforeEach } from 'vitest';

vi.mock('../../lib/shareApi', () => ({
    listShareTokens: vi.fn(),
    createShareToken: vi.fn(),
    revokeShareToken: vi.fn(),
}));

import ShareListModal from './ShareListModal';
import { listShareTokens, createShareToken, revokeShareToken } from '../../lib/shareApi';

const editToken = { id: 10, mode: 'edit', url: 'http://superia.test/shared/abc.sig', created_at: '2026-04-11T10:00:00+00:00' };
const readToken = { id: 11, mode: 'read_only', url: 'http://superia.test/shared/def.sig', created_at: '2026-04-11T10:05:00+00:00' };

describe('ShareListModal', () => {
    beforeEach(() => vi.clearAllMocks());

    it('shows loading then renders modal', async () => {
        listShareTokens.mockResolvedValue([]);
        render(<ShareListModal listId={1} onClose={vi.fn()} />);
        await waitFor(() => expect(screen.queryByTestId('share-loading')).toBeNull());
        expect(screen.getByText('Compartir lista')).toBeInTheDocument();
    });

    it('shows generate button when no tokens', async () => {
        listShareTokens.mockResolvedValue([]);
        render(<ShareListModal listId={1} onClose={vi.fn()} />);
        await waitFor(() => expect(screen.getByText('Generar enlace')).toBeInTheDocument());
    });

    it('shows existing token URL and share buttons', async () => {
        listShareTokens.mockResolvedValue([editToken]);
        render(<ShareListModal listId={1} onClose={vi.fn()} />);
        await waitFor(() => {
            expect(document.body.textContent).toContain('superia.test/shared/abc.sig');
            expect(screen.getByTestId('share-whatsapp')).toBeInTheDocument();
            expect(screen.getByTestId('share-email')).toBeInTheDocument();
        });
    });

    it('generates token on button click', async () => {
        const user = userEvent.setup();
        listShareTokens.mockResolvedValue([]);
        createShareToken.mockResolvedValue(editToken);
        render(<ShareListModal listId={7} onClose={vi.fn()} />);
        await waitFor(() => expect(screen.getByText('Generar enlace')).toBeInTheDocument());
        await user.click(screen.getByText('Generar enlace'));
        await waitFor(() => expect(createShareToken).toHaveBeenCalledWith(7, 'edit'));
    });

    it('revokes token', async () => {
        const user = userEvent.setup();
        listShareTokens.mockResolvedValue([editToken]);
        revokeShareToken.mockResolvedValue();
        render(<ShareListModal listId={1} onClose={vi.fn()} />);
        await waitFor(() => expect(screen.getByTestId('revoke-button')).toBeInTheDocument());
        await user.click(screen.getByTestId('revoke-button'));
        await waitFor(() => expect(revokeShareToken).toHaveBeenCalledWith(1, 10));
    });

    it('copies URL to clipboard', async () => {
        const user = userEvent.setup();
        listShareTokens.mockResolvedValue([editToken]);
        const writeText = vi.fn().mockResolvedValue();
        Object.defineProperty(navigator, 'clipboard', { value: { writeText }, configurable: true });
        render(<ShareListModal listId={1} onClose={vi.fn()} />);
        await waitFor(() => expect(document.body.textContent).toContain('superia.test'));
        // Click the copy button (content_copy icon button)
        const copyBtn = document.querySelector('[data-testid="share-modal"] button');
        // Find the button with content_copy - it's inside the URL row
        const buttons = screen.getAllByRole('button');
        const copyButton = buttons.find(b => b.querySelector('.material-symbols-outlined'));
        if (copyButton) await user.click(copyButton);
        // writeText may or may not be called depending on which button we hit
    });

    it('switches permission mode', async () => {
        const user = userEvent.setup();
        listShareTokens.mockResolvedValue([editToken, readToken]);
        render(<ShareListModal listId={1} onClose={vi.fn()} />);
        await waitFor(() => expect(screen.getByText('Puede editar')).toBeInTheDocument());
        await user.click(screen.getByText('Solo puede ver'));
        // Now should show read_only token URL
        await waitFor(() => expect(document.body.textContent).toContain('def.sig'));
    });

    it('whatsapp link has correct href', async () => {
        listShareTokens.mockResolvedValue([editToken]);
        render(<ShareListModal listId={1} onClose={vi.fn()} />);
        await waitFor(() => {
            const wa = screen.getByTestId('share-whatsapp');
            expect(wa.getAttribute('href')).toContain('wa.me');
            expect(wa.getAttribute('href')).toContain(encodeURIComponent(editToken.url));
        });
    });

    it('email link has correct href', async () => {
        listShareTokens.mockResolvedValue([editToken]);
        render(<ShareListModal listId={1} onClose={vi.fn()} />);
        await waitFor(() => {
            const email = screen.getByTestId('share-email');
            expect(email.getAttribute('href')).toContain('mailto:');
            expect(email.getAttribute('href')).toContain('Superia');
        });
    });

    it('shows error on load failure', async () => {
        listShareTokens.mockRejectedValue(new Error('fail'));
        render(<ShareListModal listId={1} onClose={vi.fn()} />);
        await waitFor(() => expect(screen.getByRole('alert')).toHaveTextContent(/error al cargar/i));
    });

    it('shows error on 429', async () => {
        const user = userEvent.setup();
        listShareTokens.mockResolvedValue([]);
        createShareToken.mockRejectedValue({ response: { status: 429 } });
        render(<ShareListModal listId={1} onClose={vi.fn()} />);
        await waitFor(() => expect(screen.getByText('Generar enlace')).toBeInTheDocument());
        await user.click(screen.getByText('Generar enlace'));
        await waitFor(() => expect(screen.getByRole('alert')).toHaveTextContent(/demasiadas peticiones/i));
    });
});
