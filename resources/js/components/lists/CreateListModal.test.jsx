import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import CreateListModal from './CreateListModal';

describe('CreateListModal', () => {
    const mockClose = vi.fn();
    const mockSubmit = vi.fn();

    beforeEach(() => vi.clearAllMocks());

    function renderModal(error = '') {
        return render(
            <CreateListModal onClose={mockClose} onSubmit={mockSubmit} error={error} />
        );
    }

    it('renders form with name, emoji, and category fields', () => {
        renderModal();
        expect(screen.getByText('Nueva lista')).toBeInTheDocument();
        expect(screen.getByLabelText(/nombre/i)).toBeInTheDocument();
        expect(screen.getByLabelText(/categoria/i)).toBeInTheDocument();
        expect(screen.getByRole('button', { name: /crear lista/i })).toBeInTheDocument();
    });

    it('submits with name only', async () => {
        const user = userEvent.setup();
        mockSubmit.mockResolvedValueOnce(true);

        renderModal();

        await user.type(screen.getByLabelText(/nombre/i), 'Mi lista');
        await user.click(screen.getByRole('button', { name: /crear lista/i }));

        expect(mockSubmit).toHaveBeenCalledWith({ name: 'Mi lista' });
    });

    it('submits with all fields', async () => {
        const user = userEvent.setup();
        mockSubmit.mockResolvedValueOnce(true);

        renderModal();

        await user.type(screen.getByLabelText(/nombre/i), 'Compra');
        await user.click(screen.getByLabelText('🛒'));
        await user.selectOptions(screen.getByLabelText(/categoria/i), 'supermercado');
        await user.click(screen.getByRole('button', { name: /crear lista/i }));

        expect(mockSubmit).toHaveBeenCalledWith({
            name: 'Compra',
            emoji: '🛒',
            category: 'supermercado',
        });
    });

    it('shows error message when provided', () => {
        renderModal('Has alcanzado el limite');
        expect(screen.getByRole('alert')).toHaveTextContent('Has alcanzado el limite');
    });

    it('calls onClose when cancel clicked', async () => {
        const user = userEvent.setup();
        renderModal();

        await user.click(screen.getByRole('button', { name: /cancelar/i }));
        expect(mockClose).toHaveBeenCalledOnce();
    });

    it('shows loading state during submit', async () => {
        const user = userEvent.setup();
        mockSubmit.mockImplementation(() => new Promise(() => {}));

        renderModal();

        await user.type(screen.getByLabelText(/nombre/i), 'Test');
        await user.click(screen.getByRole('button', { name: /crear lista/i }));

        expect(screen.getByRole('button', { name: /creando/i })).toBeDisabled();
    });
});
