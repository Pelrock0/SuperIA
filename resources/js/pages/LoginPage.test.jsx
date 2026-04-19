import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { MemoryRouter } from 'react-router-dom';
import LoginPage from './LoginPage';

const mockLogin = vi.fn();
const mockLoginWithPasskey = vi.fn();
const mockNavigate = vi.fn();

vi.mock('react-router-dom', async () => {
    const actual = await vi.importActual('react-router-dom');
    return {
        ...actual,
        useNavigate: () => mockNavigate,
    };
});

const mockRefreshUser = vi.fn(() => Promise.resolve());

vi.mock('../context/AuthContext', () => ({
    useAuth: () => ({
        login: mockLogin,
        loginWithPasskey: mockLoginWithPasskey,
        refreshUser: mockRefreshUser,
        isAuthenticated: false,
        isLoading: false,
    }),
}));

vi.mock('../lib/webauthnApi', () => ({
    isSupported: vi.fn(() => false),
    probeEnabled: vi.fn(() => Promise.resolve(false)),
    supportsConditionalMediation: vi.fn(() => Promise.resolve(false)),
    authenticateConditional: vi.fn(() => Promise.resolve(null)),
    markDeviceRegistered: vi.fn(),
}));

import * as webauthnApi from '../lib/webauthnApi';

describe('LoginPage', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    function renderPage() {
        return render(
            <MemoryRouter>
                <LoginPage />
            </MemoryRouter>
        );
    }

    it('renders login form', () => {
        renderPage();
        expect(screen.getByText('Bienvenido de nuevo')).toBeInTheDocument();
        expect(screen.getByLabelText(/email/i)).toBeInTheDocument();
        expect(screen.getByLabelText(/password/i)).toBeInTheDocument();
        expect(screen.getByRole('button', { name: /iniciar sesión/i })).toBeInTheDocument();
    });

    it('renders remember me and forgot password link', () => {
        renderPage();
        expect(screen.getByLabelText(/recuérdame/i)).toBeInTheDocument();
        expect(screen.getByText(/olvidé mi contraseña/i)).toBeInTheDocument();
    });

    it('calls login on submit with valid data', async () => {
        const user = userEvent.setup();
        mockLogin.mockResolvedValueOnce({ id: 1, name: 'Test' });

        renderPage();

        await user.type(screen.getByLabelText(/email/i), 'test@example.com');
        await user.type(screen.getByLabelText(/password/i), 'Password1');
        await user.click(screen.getByRole('button', { name: /iniciar sesión/i }));

        await waitFor(() => {
            expect(mockLogin).toHaveBeenCalledWith('test@example.com', 'Password1', false);
        });
    });

    it('shows error on login failure', async () => {
        const user = userEvent.setup();
        mockLogin.mockRejectedValueOnce({
            response: { data: { error: { message: 'Credenciales incorrectas.' } } },
        });

        renderPage();

        await user.type(screen.getByLabelText(/email/i), 'test@example.com');
        await user.type(screen.getByLabelText(/password/i), 'Wrong1');
        await user.click(screen.getByRole('button', { name: /iniciar sesión/i }));

        await waitFor(() => {
            expect(screen.getByRole('alert')).toHaveTextContent('Credenciales incorrectas.');
        });
    });

    it('shows loading state during submit', async () => {
        const user = userEvent.setup();
        mockLogin.mockImplementation(() => new Promise(() => {}));

        renderPage();

        await user.type(screen.getByLabelText(/email/i), 'test@example.com');
        await user.type(screen.getByLabelText(/password/i), 'Password1');
        await user.click(screen.getByRole('button', { name: /iniciar sesión/i }));

        expect(screen.getByText('Entrando...')).toBeInTheDocument();
    });

    it('shows generic error on unexpected failure', async () => {
        const user = userEvent.setup();
        mockLogin.mockRejectedValueOnce(new Error('Network error'));

        renderPage();

        await user.type(screen.getByLabelText(/email/i), 'test@example.com');
        await user.type(screen.getByLabelText(/password/i), 'Password1');
        await user.click(screen.getByRole('button', { name: /iniciar sesión/i }));

        await waitFor(() => {
            expect(screen.getByRole('alert')).toHaveTextContent('Ha ocurrido un error');
        });
    });

    it('renders waitlist note', () => {
        renderPage();
        expect(screen.getByText(/No tienes cuenta/)).toBeInTheDocument();
        expect(screen.getByText(/lista de espera/)).toBeInTheDocument();
    });

    describe('biometric CTA', () => {
        beforeEach(() => {
            webauthnApi.isSupported.mockReturnValue(false);
            webauthnApi.probeEnabled.mockResolvedValue(false);
        });

        it('does NOT render CTA when WebAuthn not supported', async () => {
            webauthnApi.isSupported.mockReturnValue(false);
            renderPage();
            await waitFor(() => expect(screen.queryByTestId('webauthn-section')).not.toBeInTheDocument());
            expect(screen.queryByTestId('webauthn-login-passkey')).not.toBeInTheDocument();
            expect(screen.queryByTestId('webauthn-login-email')).not.toBeInTheDocument();
        });

        it('does NOT render CTA when probeEnabled returns false', async () => {
            webauthnApi.isSupported.mockReturnValue(true);
            webauthnApi.probeEnabled.mockResolvedValue(false);
            renderPage();
            await waitFor(() => expect(webauthnApi.probeEnabled).toHaveBeenCalled());
            expect(screen.queryByTestId('webauthn-section')).not.toBeInTheDocument();
        });

        it('renders primary CTA above form when supported and enabled', async () => {
            webauthnApi.isSupported.mockReturnValue(true);
            webauthnApi.probeEnabled.mockResolvedValue(true);
            renderPage();
            const cta = await screen.findByTestId('webauthn-login-passkey');
            expect(cta).toHaveTextContent(/Entrar con biometría/i);
            expect(screen.getByText(/o con email/i)).toBeInTheDocument();
        });

        it('clicking CTA invokes loginWithPasskey(null) and navigates', async () => {
            const user = userEvent.setup();
            webauthnApi.isSupported.mockReturnValue(true);
            webauthnApi.probeEnabled.mockResolvedValue(true);
            mockLoginWithPasskey.mockResolvedValueOnce({ id: 1 });
            renderPage();
            const cta = await screen.findByTestId('webauthn-login-passkey');
            await user.click(cta);
            await waitFor(() => expect(mockLoginWithPasskey).toHaveBeenCalledWith(null));
            expect(mockNavigate).toHaveBeenCalledWith('/app', { replace: true });
        });

        it('shows biometric error message on failure', async () => {
            const user = userEvent.setup();
            webauthnApi.isSupported.mockReturnValue(true);
            webauthnApi.probeEnabled.mockResolvedValue(true);
            mockLoginWithPasskey.mockRejectedValueOnce(new Error('Autenticación cancelada.'));
            renderPage();
            const cta = await screen.findByTestId('webauthn-login-passkey');
            await user.click(cta);
            await waitFor(() => {
                expect(screen.getByRole('alert')).toHaveTextContent(/cancelada/i);
            });
        });

        it('shows verifying state while passkey login pending', async () => {
            const user = userEvent.setup();
            webauthnApi.isSupported.mockReturnValue(true);
            webauthnApi.probeEnabled.mockResolvedValue(true);
            mockLoginWithPasskey.mockImplementation(() => new Promise(() => {}));
            renderPage();
            const cta = await screen.findByTestId('webauthn-login-passkey');
            await user.click(cta);
            expect(cta).toHaveTextContent(/Verificando/);
            expect(cta).toBeDisabled();
        });

        it('does NOT render removed email+biometric button', async () => {
            webauthnApi.isSupported.mockReturnValue(true);
            webauthnApi.probeEnabled.mockResolvedValue(true);
            renderPage();
            await screen.findByTestId('webauthn-section');
            expect(screen.queryByTestId('webauthn-login-email')).not.toBeInTheDocument();
        });

        it('falls back to idle state when probeEnabled rejects', async () => {
            webauthnApi.isSupported.mockReturnValue(true);
            webauthnApi.probeEnabled.mockRejectedValue(new Error('net'));
            renderPage();
            await waitFor(() => expect(webauthnApi.probeEnabled).toHaveBeenCalled());
            expect(screen.queryByTestId('webauthn-section')).not.toBeInTheDocument();
        });

        it('surfaces response error message from server on biometric failure', async () => {
            const user = userEvent.setup();
            webauthnApi.isSupported.mockReturnValue(true);
            webauthnApi.probeEnabled.mockResolvedValue(true);
            mockLoginWithPasskey.mockRejectedValueOnce({
                response: { data: { error: { message: 'Firma inválida.' } } },
            });
            renderPage();
            const cta = await screen.findByTestId('webauthn-login-passkey');
            await user.click(cta);
            await waitFor(() => {
                expect(screen.getByRole('alert')).toHaveTextContent(/Firma inválida/i);
            });
        });

        it('shows generic biometric error when err has no message or response', async () => {
            const user = userEvent.setup();
            webauthnApi.isSupported.mockReturnValue(true);
            webauthnApi.probeEnabled.mockResolvedValue(true);
            mockLoginWithPasskey.mockRejectedValueOnce({});
            renderPage();
            const cta = await screen.findByTestId('webauthn-login-passkey');
            await user.click(cta);
            await waitFor(() => {
                expect(screen.getByRole('alert')).toHaveTextContent(/Autenticación biométrica fallida/i);
            });
        });
    });
});
