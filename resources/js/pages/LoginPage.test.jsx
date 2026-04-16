import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { MemoryRouter } from 'react-router-dom';
import LoginPage from './LoginPage';

const mockLogin = vi.fn();
const mockNavigate = vi.fn();

vi.mock('react-router-dom', async () => {
    const actual = await vi.importActual('react-router-dom');
    return {
        ...actual,
        useNavigate: () => mockNavigate,
    };
});

vi.mock('../context/AuthContext', () => ({
    useAuth: () => ({
        login: mockLogin,
        loginWithPasskey: vi.fn(),
        isAuthenticated: false,
        isLoading: false,
    }),
}));

vi.mock('../lib/webauthnApi', () => ({
    isSupported: vi.fn(() => false),
    probeEnabled: vi.fn(() => Promise.resolve(false)),
}));

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
});
