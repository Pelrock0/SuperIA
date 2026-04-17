import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import WebauthnCredentialsList from './WebauthnCredentialsList';

const mockIsSupported = vi.fn();
const mockList = vi.fn();
const mockRegister = vi.fn();
const mockRename = vi.fn();
const mockDelete = vi.fn();
const mockMarkDeviceRegistered = vi.fn();

vi.mock('../../lib/webauthnApi', () => ({
    isSupported: (...args) => mockIsSupported(...args),
    listCredentials: (...args) => mockList(...args),
    registerCredential: (...args) => mockRegister(...args),
    renameCredential: (...args) => mockRename(...args),
    deleteCredential: (...args) => mockDelete(...args),
    markDeviceRegistered: (...args) => mockMarkDeviceRegistered(...args),
}));

describe('WebauthnCredentialsList', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        mockIsSupported.mockReturnValue(true);
    });

    it('renders nothing when browser does not support WebAuthn', () => {
        mockIsSupported.mockReturnValue(false);
        const { container } = render(<WebauthnCredentialsList />);
        expect(container.firstChild).toBeNull();
    });

    it('renders nothing when backend returns 404 (feature disabled)', async () => {
        mockList.mockRejectedValueOnce({ response: { status: 404 } });
        const { container } = render(<WebauthnCredentialsList />);
        await waitFor(() => {
            expect(container.firstChild).toBeNull();
        });
    });

    it('shows empty state when no credentials', async () => {
        mockList.mockResolvedValueOnce([]);
        render(<WebauthnCredentialsList />);
        await screen.findByText(/no tienes dispositivos biométricos/i);
        expect(screen.getByTestId('webauthn-add-first')).toBeInTheDocument();
    });

    it('renders credential list with names', async () => {
        mockList.mockResolvedValueOnce([
            { id: 1, name: 'iPhone 14', transports: ['internal'], last_used_at: null, created_at: '2026-04-16T08:00:00Z' },
            { id: 2, name: 'Laptop', transports: ['internal'], last_used_at: '2026-04-15T10:00:00Z', created_at: '2026-04-10T08:00:00Z' },
        ]);

        render(<WebauthnCredentialsList />);
        await screen.findByText('iPhone 14');
        expect(screen.getByText('Laptop')).toBeInTheDocument();
        expect(screen.getByTestId('webauthn-add-another')).toBeInTheDocument();
    });

    it('adds a new credential from empty state', async () => {
        const user = userEvent.setup();
        mockList.mockResolvedValueOnce([]);
        mockRegister.mockResolvedValueOnce({ id: 1, name: 'Test' });
        mockList.mockResolvedValueOnce([{ id: 1, name: 'Test', transports: [], last_used_at: null, created_at: '2026-04-16T08:00:00Z' }]);

        render(<WebauthnCredentialsList />);
        const addBtn = await screen.findByTestId('webauthn-add-first');
        await user.click(addBtn);

        await waitFor(() => expect(mockRegister).toHaveBeenCalled());
        expect(mockMarkDeviceRegistered).toHaveBeenCalled();
    });

    it('displays error when register fails', async () => {
        const user = userEvent.setup();
        mockList.mockResolvedValueOnce([]);
        mockRegister.mockRejectedValueOnce(new Error('Registro cancelado.'));

        render(<WebauthnCredentialsList />);
        const addBtn = await screen.findByTestId('webauthn-add-first');
        await user.click(addBtn);

        await screen.findByText(/registro cancelado/i);
        expect(mockMarkDeviceRegistered).not.toHaveBeenCalled();
    });

    it('revokes a credential after confirm', async () => {
        const user = userEvent.setup();
        window.confirm = vi.fn(() => true);
        mockList.mockResolvedValueOnce([
            { id: 5, name: 'MyDevice', transports: [], last_used_at: null, created_at: '2026-04-16T08:00:00Z' },
        ]);
        mockDelete.mockResolvedValueOnce();
        mockList.mockResolvedValueOnce([]);

        render(<WebauthnCredentialsList />);
        const revokeBtn = await screen.findByTestId('webauthn-revoke-5');
        await user.click(revokeBtn);

        await waitFor(() => expect(mockDelete).toHaveBeenCalledWith(5));
    });

    it('does not revoke if confirm is cancelled', async () => {
        const user = userEvent.setup();
        window.confirm = vi.fn(() => false);
        mockList.mockResolvedValueOnce([
            { id: 5, name: 'MyDevice', transports: [], last_used_at: null, created_at: '2026-04-16T08:00:00Z' },
        ]);

        render(<WebauthnCredentialsList />);
        const revokeBtn = await screen.findByTestId('webauthn-revoke-5');
        await user.click(revokeBtn);

        expect(mockDelete).not.toHaveBeenCalled();
    });

    it('renames a credential', async () => {
        const user = userEvent.setup();
        mockList.mockResolvedValueOnce([
            { id: 5, name: 'Old', transports: [], last_used_at: null, created_at: '2026-04-16T08:00:00Z' },
        ]);
        mockRename.mockResolvedValueOnce({ id: 5, name: 'New' });
        mockList.mockResolvedValueOnce([
            { id: 5, name: 'New', transports: [], last_used_at: null, created_at: '2026-04-16T08:00:00Z' },
        ]);

        render(<WebauthnCredentialsList />);
        const renameBtn = await screen.findByRole('button', { name: /renombrar old/i });
        await user.click(renameBtn);

        const input = screen.getByLabelText(/nuevo nombre/i);
        await user.clear(input);
        await user.type(input, 'New');
        await user.click(screen.getByRole('button', { name: /guardar/i }));

        await waitFor(() => expect(mockRename).toHaveBeenCalledWith(5, 'New'));
    });

    it('rejects rename with empty name', async () => {
        const user = userEvent.setup();
        mockList.mockResolvedValueOnce([
            { id: 5, name: 'Old', transports: [], last_used_at: null, created_at: '2026-04-16T08:00:00Z' },
        ]);

        render(<WebauthnCredentialsList />);
        const renameBtn = await screen.findByRole('button', { name: /renombrar old/i });
        await user.click(renameBtn);

        const input = screen.getByLabelText(/nuevo nombre/i);
        await user.clear(input);
        await user.click(screen.getByRole('button', { name: /guardar/i }));

        await screen.findByText(/entre 1 y 50 caracteres/i);
        expect(mockRename).not.toHaveBeenCalled();
    });
});
