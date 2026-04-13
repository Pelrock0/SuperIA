import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { MemoryRouter } from 'react-router-dom';
import ForgotPasswordPage from './ForgotPasswordPage';

vi.mock('../lib/api', () => ({
    default: {
        post: vi.fn(),
    },
    getToken: vi.fn(),
    setToken: vi.fn(),
    removeToken: vi.fn(),
}));

import api from '../lib/api';

describe('ForgotPasswordPage', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    function renderPage() {
        return render(
            <MemoryRouter>
                <ForgotPasswordPage />
            </MemoryRouter>
        );
    }

    it('renders forgot password form', () => {
        renderPage();
        expect(screen.getByText('Recuperar contraseña')).toBeInTheDocument();
        expect(screen.getByLabelText(/email/i)).toBeInTheDocument();
        expect(screen.getByRole('button', { name: /enviar enlace/i })).toBeInTheDocument();
    });

    it('shows success message on submit', async () => {
        const user = userEvent.setup();
        api.post.mockResolvedValueOnce({
            data: { data: { message: 'Si el email esta registrado, recibiras un enlace de recuperacion.' } },
        });

        renderPage();

        await user.type(screen.getByLabelText(/email/i), 'test@example.com');
        await user.click(screen.getByRole('button', { name: /enviar enlace/i }));

        await waitFor(() => {
            expect(screen.getByTestId('forgot-success')).toBeInTheDocument();
        });
    });

    it('shows success message even on API error', async () => {
        const user = userEvent.setup();
        api.post.mockRejectedValueOnce(new Error('Network error'));

        renderPage();

        await user.type(screen.getByLabelText(/email/i), 'test@example.com');
        await user.click(screen.getByRole('button', { name: /enviar enlace/i }));

        await waitFor(() => {
            expect(screen.getByTestId('forgot-success')).toBeInTheDocument();
        });
    });

    it('shows loading state during submit', async () => {
        const user = userEvent.setup();
        api.post.mockImplementation(() => new Promise(() => {}));

        renderPage();

        await user.type(screen.getByLabelText(/email/i), 'test@example.com');
        await user.click(screen.getByRole('button', { name: /enviar enlace/i }));

        expect(screen.getByRole('button')).toHaveTextContent('Enviando...');
    });

    it('shows back to login link', () => {
        renderPage();
        expect(screen.getByText(/volver a iniciar sesión/i)).toBeInTheDocument();
    });
});
