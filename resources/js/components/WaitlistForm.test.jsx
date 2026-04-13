import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import WaitlistForm from './WaitlistForm';

vi.mock('../lib/api', () => ({
    default: {
        post: vi.fn(),
    },
}));

import api from '../lib/api';

describe('WaitlistForm', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    it('renders form fields', () => {
        render(<WaitlistForm />);
        expect(screen.getByLabelText(/nombre/i)).toBeInTheDocument();
        expect(screen.getByLabelText(/correo/i)).toBeInTheDocument();
        expect(screen.getByRole('button', { name: /apuntarme/i })).toBeInTheDocument();
    });

    it('submits form successfully', async () => {
        const user = userEvent.setup();
        api.post.mockResolvedValueOnce({
            data: { message: 'Ok', position: 42 },
        });

        render(<WaitlistForm />);

        await user.type(screen.getByLabelText(/nombre/i), 'Juan');
        await user.type(screen.getByLabelText(/correo/i), 'juan@example.com');
        await user.click(screen.getByRole('button', { name: /apuntarme/i }));

        await waitFor(() => {
            expect(screen.getByTestId('waitlist-success')).toBeInTheDocument();
        });

        expect(screen.getByText(/42/)).toBeInTheDocument();
    });

    it('shows validation errors from server', async () => {
        const user = userEvent.setup();
        api.post.mockRejectedValueOnce({
            response: {
                status: 422,
                data: { errors: { email: ['El email no es válido'] } },
            },
        });

        render(<WaitlistForm />);

        await user.type(screen.getByLabelText(/nombre/i), 'Test');
        await user.type(screen.getByLabelText(/correo/i), 'bad@test.com');
        await user.click(screen.getByRole('button', { name: /apuntarme/i }));

        await waitFor(() => {
            expect(screen.getByText('El email no es válido')).toBeInTheDocument();
        });
    });

    it('shows rate limit error', async () => {
        const user = userEvent.setup();
        api.post.mockRejectedValueOnce({
            response: { status: 429 },
        });

        render(<WaitlistForm />);

        await user.type(screen.getByLabelText(/nombre/i), 'Test');
        await user.type(screen.getByLabelText(/correo/i), 'rate@test.com');
        await user.click(screen.getByRole('button', { name: /apuntarme/i }));

        await waitFor(() => {
            expect(screen.getByText(/límite de intentos/i)).toBeInTheDocument();
        });
    });

    it('shows generic error on network failure', async () => {
        const user = userEvent.setup();
        api.post.mockRejectedValueOnce({
            response: { status: 500 },
        });

        render(<WaitlistForm />);

        await user.type(screen.getByLabelText(/nombre/i), 'Test');
        await user.type(screen.getByLabelText(/correo/i), 'error@test.com');
        await user.click(screen.getByRole('button', { name: /apuntarme/i }));

        await waitFor(() => {
            expect(screen.getByText(/ha ocurrido un error/i)).toBeInTheDocument();
        });
    });

    it('sends companion when selected', async () => {
        const user = userEvent.setup();
        api.post.mockResolvedValueOnce({
            data: { message: 'Ok', position: 1 },
        });

        render(<WaitlistForm />);

        await user.type(screen.getByLabelText(/nombre/i), 'Ana');
        await user.type(screen.getByLabelText(/correo/i), 'ana@test.com');
        await user.click(screen.getByTestId('companion-familia'));
        await user.click(screen.getByRole('button', { name: /apuntarme/i }));

        await waitFor(() => {
            expect(api.post).toHaveBeenCalledWith('/waitlist', {
                name: 'Ana',
                email: 'ana@test.com',
                shopping_companion: 'familia',
            });
        });
    });

    it('does not send companion when not selected', async () => {
        const user = userEvent.setup();
        api.post.mockResolvedValueOnce({
            data: { message: 'Ok', position: 1 },
        });

        render(<WaitlistForm />);

        await user.type(screen.getByLabelText(/nombre/i), 'Pedro');
        await user.type(screen.getByLabelText(/correo/i), 'pedro@test.com');
        await user.click(screen.getByRole('button', { name: /apuntarme/i }));

        await waitFor(() => {
            expect(api.post).toHaveBeenCalledWith('/waitlist', {
                name: 'Pedro',
                email: 'pedro@test.com',
            });
        });
    });

    it('shows loading state while submitting', async () => {
        const user = userEvent.setup();
        let resolvePromise;
        api.post.mockImplementationOnce(() => new Promise((resolve) => { resolvePromise = resolve; }));

        render(<WaitlistForm />);

        await user.type(screen.getByLabelText(/nombre/i), 'Test');
        await user.type(screen.getByLabelText(/correo/i), 'loading@test.com');
        await user.click(screen.getByRole('button', { name: /apuntarme/i }));

        await waitFor(() => {
            expect(screen.getByText('Enviando...')).toBeInTheDocument();
        });

        resolvePromise({ data: { message: 'Ok', position: 1 } });

        await waitFor(() => {
            expect(screen.getByTestId('waitlist-success')).toBeInTheDocument();
        });
    });
});
