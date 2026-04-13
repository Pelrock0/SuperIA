import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { MemoryRouter } from 'react-router-dom';
import RegisterPage from './RegisterPage';

vi.mock('../lib/api', () => ({
    default: { get: vi.fn(), post: vi.fn() },
    getToken: vi.fn(), setToken: vi.fn(), removeToken: vi.fn(),
}));

import api from '../lib/api';

function renderWithToken(token = 'valid-token') {
    return render(<MemoryRouter initialEntries={[`/register?token=${token}`]}><RegisterPage /></MemoryRouter>);
}

function renderWithoutToken() {
    return render(<MemoryRouter initialEntries={['/register']}><RegisterPage /></MemoryRouter>);
}

describe('RegisterPage', () => {
    beforeEach(() => vi.clearAllMocks());

    it('shows error when no token provided', () => {
        renderWithoutToken();
        expect(screen.getByTestId('token-error')).toBeInTheDocument();
    });

    it('shows loading while validating token', () => {
        api.get.mockImplementation(() => new Promise(() => {}));
        renderWithToken();
        expect(screen.getByTestId('loading')).toBeInTheDocument();
    });

    it('shows error for invalid token', async () => {
        api.get.mockRejectedValueOnce({ response: { status: 404 } });
        renderWithToken('invalid');
        await waitFor(() => expect(screen.getByTestId('token-error')).toBeInTheDocument());
    });

    it('renders form with pre-filled email for valid token', async () => {
        api.get.mockResolvedValueOnce({ data: { data: { email: 'test@example.com', name: 'Test User' } } });
        renderWithToken();
        await waitFor(() => expect(screen.getByTestId('register-form')).toBeInTheDocument());
        const emailInput = screen.getByDisplayValue('test@example.com');
        expect(emailInput).toBeDisabled();
    });

    it('submits registration successfully', async () => {
        const user = userEvent.setup();
        api.get.mockResolvedValueOnce({ data: { data: { email: 'test@example.com', name: 'Test' } } });
        api.post.mockResolvedValueOnce({ data: { data: { message: 'Registro exitoso.' } } });
        renderWithToken();

        await waitFor(() => expect(screen.getByTestId('register-form')).toBeInTheDocument());

        const nameInput = screen.getByPlaceholderText('Tu nombre completo');
        await user.clear(nameInput);
        await user.type(nameInput, 'New User');
        const passwordInputs = screen.getAllByPlaceholderText('••••••••');
        await user.type(passwordInputs[0], 'Password1');
        await user.type(passwordInputs[1], 'Password1');
        await user.click(screen.getByLabelText(/política de privacidad/i));
        await user.click(screen.getByRole('button', { name: /crear mi cuenta/i }));

        await waitFor(() => expect(screen.getByTestId('register-success')).toBeInTheDocument());
    });

    it('shows validation errors on failure', async () => {
        const user = userEvent.setup();
        api.get.mockResolvedValueOnce({ data: { data: { email: 'test@example.com', name: '' } } });
        api.post.mockRejectedValueOnce({ response: { status: 422, data: { error: { message: 'Token invalido.' } } } });
        renderWithToken();

        await waitFor(() => expect(screen.getByTestId('register-form')).toBeInTheDocument());

        await user.type(screen.getByPlaceholderText('Tu nombre completo'), 'Test');
        const pwInputs = screen.getAllByPlaceholderText('••••••••');
        await user.type(pwInputs[0], 'Password1');
        await user.type(pwInputs[1], 'Password1');
        await user.click(screen.getByLabelText(/política de privacidad/i));
        await user.click(screen.getByRole('button', { name: /crear mi cuenta/i }));

        await waitFor(() => expect(screen.getByRole('alert')).toBeInTheDocument());
    });

    it('renders Stitch-aligned design elements', async () => {
        api.get.mockResolvedValueOnce({ data: { data: { email: 'test@example.com', name: 'Test' } } });
        renderWithToken();
        await waitFor(() => {
            expect(screen.getByText('¡Te hemos reservado tu plaza!')).toBeInTheDocument();
            expect(screen.getByText(/compra más inteligente/)).toBeInTheDocument();
        });
    });
});
