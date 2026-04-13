import { render, screen, waitFor, act } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';

vi.mock('../../lib/complementsApi', () => ({
    fetchComplements: vi.fn(),
}));

import ComplementaryChip from './ComplementaryChip';
import { fetchComplements } from '../../lib/complementsApi';

describe('ComplementaryChip', () => {
    beforeEach(() => vi.clearAllMocks());

    afterEach(() => {
        vi.useRealTimers();
    });

    it('renders nothing while loading', () => {
        fetchComplements.mockImplementation(() => new Promise(() => {}));

        const { container } = render(<ComplementaryChip productName="pasta" listId={1} />);

        expect(container.querySelector('[data-testid="complementary-chip"]')).toBeNull();
    });

    it('renders nothing when no suggestions returned', async () => {
        fetchComplements.mockResolvedValue({ suggestions: [], ai_fallback_used: false, ai_limit_reached: false });

        const { container } = render(<ComplementaryChip productName="pasta" listId={1} />);

        await waitFor(() => expect(fetchComplements).toHaveBeenCalled());
        expect(container.querySelector('[data-testid="complementary-chip"]')).toBeNull();
    });

    it('renders suggestions after load', async () => {
        fetchComplements.mockResolvedValue({
            suggestions: [
                { nombre: 'Tomate frito', unidad_tipica: 'ud', categoria: 'conservas' },
                { nombre: 'Queso rallado', unidad_tipica: 'g', categoria: 'lacteos_huevos' },
            ],
            ai_fallback_used: false,
            ai_limit_reached: false,
        });

        render(<ComplementaryChip productName="pasta" listId={5} />);

        await waitFor(() => {
            expect(screen.getByTestId('complementary-chip')).toBeInTheDocument();
            expect(screen.getByText('Tomate frito')).toBeInTheDocument();
            expect(screen.getByText('Queso rallado')).toBeInTheDocument();
        });
        expect(fetchComplements).toHaveBeenCalledWith('pasta', 5);
    });

    it('calls onAccept and hides on accept click', async () => {
        const user = userEvent.setup();
        const onAccept = vi.fn();
        const tomate = { nombre: 'Tomate frito', unidad_tipica: 'ud', categoria: 'conservas' };
        fetchComplements.mockResolvedValue({
            suggestions: [tomate],
            ai_fallback_used: false,
            ai_limit_reached: false,
        });

        render(<ComplementaryChip productName="pasta" listId={1} onAccept={onAccept} />);

        await waitFor(() => screen.getByTestId('complement-option-Tomate frito'));

        await user.click(screen.getByTestId('complement-option-Tomate frito'));

        expect(onAccept).toHaveBeenCalledWith(tomate);
        expect(screen.queryByTestId('complementary-chip')).toBeNull();
    });

    it('calls onDismiss and hides on dismiss click', async () => {
        const user = userEvent.setup();
        const onDismiss = vi.fn();
        fetchComplements.mockResolvedValue({
            suggestions: [{ nombre: 'X' }],
            ai_fallback_used: false,
            ai_limit_reached: false,
        });

        render(<ComplementaryChip productName="pasta" listId={1} onDismiss={onDismiss} />);

        await waitFor(() => screen.getByTestId('complementary-chip'));

        await user.click(screen.getByLabelText(/descartar sugerencias/i));

        expect(onDismiss).toHaveBeenCalled();
        expect(screen.queryByTestId('complementary-chip')).toBeNull();
    });

    it('hides on API failure', async () => {
        fetchComplements.mockRejectedValue(new Error('boom'));

        const { container } = render(<ComplementaryChip productName="pasta" listId={1} />);

        await waitFor(() => expect(fetchComplements).toHaveBeenCalled());
        expect(container.querySelector('[data-testid="complementary-chip"]')).toBeNull();
    });
});
