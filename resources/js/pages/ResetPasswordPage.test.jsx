import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { MemoryRouter } from 'react-router-dom';
import ResetPasswordPage from './ResetPasswordPage';

vi.mock('../lib/api', () => ({
    default: {
        post: vi.fn(),
    },
    getToken: vi.fn(),
    setToken: vi.fn(),
    removeToken: vi.fn(),
}));

import api from '../lib/api';

function renderWithParams(token = 'valid-token', email = 'test@example.com') {
    return render(
        <MemoryRouter initialEntries={[`/reset-password?token=${token}&email=${email}`]}>
            <ResetPasswordPage />
        </MemoryRouter>
    );
}

describe('ResetPasswordPage', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    it('shows error when token or email missing', () => {
        render(
            <MemoryRouter initialEntries={['/reset-password']}>
                <ResetPasswordPage />
            </MemoryRouter>
        );
        expect(screen.getByTestId('invalid-link')).toBeInTheDocument();
    });

    it('renders reset form with valid params', () => {
        renderWithParams();
        expect(screen.getByRole('heading', { name: /nueva contraseña/i })).toBeInTheDocument();
        expect(screen.getByTestId('reset-form')).toBeInTheDocument();
    });

    it('submits reset successfully', async () => {
        const user = userEvent.setup();
        api.post.mockResolvedValueOnce({
            data: { data: { message: 'Contraseña restablecida correctamente.' } },
        });

        renderWithParams();

        await user.type(screen.getByLabelText(/nueva contraseña/i), 'NewPassword1');
        await user.type(screen.getByLabelText(/confirmar contraseña/i), 'NewPassword1');
        await user.click(screen.getByRole('button', { name: /restablecer contraseña/i }));

        await waitFor(() => {
            expect(screen.getByTestId('reset-success')).toBeInTheDocument();
        });
    });

    it('shows error on failed reset', async () => {
        const user = userEvent.setup();
        api.post.mockRejectedValueOnce({
            response: {
                data: { error: { message: 'El enlace de restablecimiento es inválido o ha expirado.' } },
            },
        });

        renderWithParams();

        await user.type(screen.getByLabelText(/nueva contraseña/i), 'NewPassword1');
        await user.type(screen.getByLabelText(/confirmar contraseña/i), 'NewPassword1');
        await user.click(screen.getByRole('button', { name: /restablecer contraseña/i }));

        await waitFor(() => {
            expect(screen.getByRole('alert')).toBeInTheDocument();
        });
    });

    it('shows loading state during submit', async () => {
        const user = userEvent.setup();
        api.post.mockImplementation(() => new Promise(() => {}));

        renderWithParams();

        await user.type(screen.getByLabelText(/nueva contraseña/i), 'NewPassword1');
        await user.type(screen.getByLabelText(/confirmar contraseña/i), 'NewPassword1');
        await user.click(screen.getByRole('button', { name: /restablecer contraseña/i }));

        expect(screen.getByRole('button')).toHaveTextContent('Restableciendo...');
    });
});
