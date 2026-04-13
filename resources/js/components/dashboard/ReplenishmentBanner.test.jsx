import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, it, expect, vi, beforeEach } from 'vitest';

vi.mock('../../lib/replenishmentApi', () => ({
    fetchReplenishmentSuggestions: vi.fn(),
    acceptReplenishment: vi.fn(),
    ignoreReplenishment: vi.fn(),
    silenceReplenishment: vi.fn(),
}));

import ReplenishmentBanner from './ReplenishmentBanner';
import {
    fetchReplenishmentSuggestions,
    acceptReplenishment,
    ignoreReplenishment,
    silenceReplenishment,
} from '../../lib/replenishmentApi';

const suggestion = {
    producto_nombre: 'Leche entera',
    purchase_count: 12,
    last_purchased_at: '2026-04-01T00:00:00Z',
    days_since_last: 10,
    avg_days_between: 7.0,
    urgency_ratio: 1.43,
    frequency_label: 'Sueles comprar Leche entera cada 7 dias',
    source: 'history',
};

const listA = { id: 1, name: 'Compra', emoji: '🛒' };
const listB = { id: 2, name: 'Farmacia', emoji: '💊' };

describe('ReplenishmentBanner', () => {
    beforeEach(() => vi.clearAllMocks());

    it('renders nothing while loading', () => {
        fetchReplenishmentSuggestions.mockImplementation(() => new Promise(() => {}));

        const { container } = render(<ReplenishmentBanner activeLists={[listA]} />);

        expect(container.querySelector('[data-testid="replenishment-banner"]')).toBeNull();
    });

    it('renders nothing when no suggestions', async () => {
        fetchReplenishmentSuggestions.mockResolvedValue([]);

        const { container } = render(<ReplenishmentBanner activeLists={[listA]} />);

        await waitFor(() => expect(fetchReplenishmentSuggestions).toHaveBeenCalled());
        expect(container.querySelector('[data-testid="replenishment-banner"]')).toBeNull();
    });

    it('renders suggestions with frequency text and days', async () => {
        fetchReplenishmentSuggestions.mockResolvedValue([suggestion]);

        render(<ReplenishmentBanner activeLists={[listA]} />);

        await waitFor(() => {
            expect(screen.getByText('Leche entera')).toBeInTheDocument();
            expect(screen.getByText(/cada 7 dias/i)).toBeInTheDocument();
            expect(screen.getByText(/Hace 10 dias/i)).toBeInTheDocument();
        });
    });

    it('accept with single active list posts directly', async () => {
        const user = userEvent.setup();
        const onAction = vi.fn().mockResolvedValue();
        fetchReplenishmentSuggestions.mockResolvedValue([suggestion]);
        acceptReplenishment.mockResolvedValue();

        render(<ReplenishmentBanner activeLists={[listA]} onAction={onAction} />);

        await waitFor(() => screen.getByText('Leche entera'));

        await user.click(screen.getByTestId('accept-Leche entera'));

        await waitFor(() => {
            expect(acceptReplenishment).toHaveBeenCalledWith('Leche entera', 1);
            expect(onAction).toHaveBeenCalled();
            expect(screen.queryByText('Leche entera')).toBeNull();
        });
    });

    it('accept with multiple lists opens SelectListModal', async () => {
        const user = userEvent.setup();
        fetchReplenishmentSuggestions.mockResolvedValue([suggestion]);
        acceptReplenishment.mockResolvedValue();

        render(<ReplenishmentBanner activeLists={[listA, listB]} />);

        await waitFor(() => screen.getByText('Leche entera'));

        await user.click(screen.getByTestId('accept-Leche entera'));

        expect(screen.getByTestId('select-list-modal')).toBeInTheDocument();

        await user.click(screen.getByTestId('select-list-option-2'));

        await waitFor(() => {
            expect(acceptReplenishment).toHaveBeenCalledWith('Leche entera', 2);
        });
    });

    it('select list modal cancel hides modal', async () => {
        const user = userEvent.setup();
        fetchReplenishmentSuggestions.mockResolvedValue([suggestion]);

        render(<ReplenishmentBanner activeLists={[listA, listB]} />);

        await waitFor(() => screen.getByText('Leche entera'));

        await user.click(screen.getByTestId('accept-Leche entera'));
        expect(screen.getByTestId('select-list-modal')).toBeInTheDocument();

        await user.click(screen.getByRole('button', { name: /cancelar/i }));
        expect(screen.queryByTestId('select-list-modal')).toBeNull();
    });

    it('ignore removes card and calls API', async () => {
        const user = userEvent.setup();
        fetchReplenishmentSuggestions.mockResolvedValue([suggestion]);
        ignoreReplenishment.mockResolvedValue();

        render(<ReplenishmentBanner activeLists={[listA]} />);

        await waitFor(() => screen.getByText('Leche entera'));

        await user.click(screen.getByTestId('ignore-Leche entera'));

        await waitFor(() => {
            expect(ignoreReplenishment).toHaveBeenCalledWith('Leche entera');
            expect(screen.queryByText('Leche entera')).toBeNull();
        });
    });

    it('silence removes card and calls API', async () => {
        const user = userEvent.setup();
        fetchReplenishmentSuggestions.mockResolvedValue([suggestion]);
        silenceReplenishment.mockResolvedValue();

        render(<ReplenishmentBanner activeLists={[listA]} />);

        await waitFor(() => screen.getByText('Leche entera'));

        await user.click(screen.getByTestId('silence-Leche entera'));

        await waitFor(() => {
            expect(silenceReplenishment).toHaveBeenCalledWith('Leche entera');
            expect(screen.queryByText('Leche entera')).toBeNull();
        });
    });

    it('shows error when fetch fails', async () => {
        fetchReplenishmentSuggestions.mockRejectedValue(new Error('fail'));

        render(<ReplenishmentBanner activeLists={[listA]} />);

        await waitFor(() => {
            expect(screen.getByRole('alert')).toHaveTextContent(/error al cargar/i);
        });
    });

    it('shows error when accept fails', async () => {
        const user = userEvent.setup();
        fetchReplenishmentSuggestions.mockResolvedValue([suggestion]);
        acceptReplenishment.mockRejectedValue(new Error('fail'));

        render(<ReplenishmentBanner activeLists={[listA]} />);

        await waitFor(() => screen.getByText('Leche entera'));

        await user.click(screen.getByTestId('accept-Leche entera'));

        await waitFor(() => {
            expect(screen.getByRole('alert')).toHaveTextContent(/error al anadir/i);
        });
    });

    it('disables accept button when no active lists', async () => {
        fetchReplenishmentSuggestions.mockResolvedValue([suggestion]);

        render(<ReplenishmentBanner activeLists={[]} />);

        await waitFor(() => screen.getByTestId('accept-Leche entera'));

        expect(screen.getByTestId('accept-Leche entera')).toBeDisabled();
    });
});
