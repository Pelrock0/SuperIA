import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, it, expect, vi, beforeEach } from 'vitest';

vi.mock('../../lib/profileHistoryApi', () => ({
    fetchHistory: vi.fn(),
    clearHistory: vi.fn(),
    forgetProduct: vi.fn(),
}));

import HistoryList from './HistoryList';
import { fetchHistory, clearHistory, forgetProduct } from '../../lib/profileHistoryApi';

const mockHistory = {
    items: [
        {
            producto_nombre: 'Leche entera',
            total_count: 12,
            last_purchased_at: '2026-04-09T10:00:00Z',
            typical_category: 'lacteos_huevos',
            typical_unit: 'L',
            typical_quantity: 1,
            weighted_score: 24,
        },
        {
            producto_nombre: 'Pan integral',
            total_count: 5,
            last_purchased_at: '2026-04-05T10:00:00Z',
            typical_category: 'panaderia',
            typical_unit: 'ud',
            typical_quantity: 1,
            weighted_score: 10,
        },
    ],
    pagination: { page: 1, per_page: 20, total: 2 },
};

describe('HistoryList', () => {
    beforeEach(() => vi.clearAllMocks());

    it('shows loading state initially', () => {
        fetchHistory.mockImplementation(() => new Promise(() => {}));

        render(<HistoryList />);

        expect(screen.getByTestId('history-loading')).toBeInTheDocument();
    });

    it('renders history items after load', async () => {
        fetchHistory.mockResolvedValue(mockHistory);

        render(<HistoryList />);

        await waitFor(() => {
            expect(screen.getByText('Leche entera')).toBeInTheDocument();
            expect(screen.getByText('Pan integral')).toBeInTheDocument();
            expect(screen.getByText(/comprado 12 veces/i)).toBeInTheDocument();
        });
    });

    it('shows empty state when no items', async () => {
        fetchHistory.mockResolvedValue({ items: [], pagination: { page: 1, per_page: 20, total: 0 } });

        render(<HistoryList />);

        await waitFor(() => {
            expect(screen.getByTestId('history-empty')).toBeInTheDocument();
        });
    });

    it('shows error state when load fails', async () => {
        fetchHistory.mockRejectedValue(new Error('fail'));

        render(<HistoryList />);

        await waitFor(() => {
            expect(screen.getByRole('alert')).toHaveTextContent(/error al cargar/i);
        });
    });

    it('opens confirm modal when clear clicked', async () => {
        const user = userEvent.setup();
        fetchHistory.mockResolvedValue(mockHistory);

        render(<HistoryList />);

        await waitFor(() => screen.getByTestId('clear-history-button'));

        await user.click(screen.getByTestId('clear-history-button'));

        expect(screen.getByTestId('clear-history-modal')).toBeInTheDocument();
    });

    it('cancels clear modal without deleting', async () => {
        const user = userEvent.setup();
        fetchHistory.mockResolvedValue(mockHistory);

        render(<HistoryList />);

        await waitFor(() => screen.getByTestId('clear-history-button'));
        await user.click(screen.getByTestId('clear-history-button'));

        await user.click(screen.getByRole('button', { name: /cancelar/i }));

        expect(screen.queryByTestId('clear-history-modal')).toBeNull();
        expect(clearHistory).not.toHaveBeenCalled();
    });

    it('confirms clear and calls API', async () => {
        const user = userEvent.setup();
        fetchHistory.mockResolvedValue(mockHistory);
        clearHistory.mockResolvedValue({ deleted: 2 });

        render(<HistoryList />);

        await waitFor(() => screen.getByTestId('clear-history-button'));
        await user.click(screen.getByTestId('clear-history-button'));
        await user.click(screen.getByRole('button', { name: /eliminar todo/i }));

        await waitFor(() => {
            expect(clearHistory).toHaveBeenCalled();
            expect(screen.getByTestId('history-empty')).toBeInTheDocument();
        });
    });

    it('forgets individual product', async () => {
        const user = userEvent.setup();
        fetchHistory.mockResolvedValue(mockHistory);
        forgetProduct.mockResolvedValue({ deleted: 12 });

        render(<HistoryList />);

        await waitFor(() => screen.getByText('Leche entera'));

        await user.click(screen.getByLabelText(/olvidar leche entera/i));

        await waitFor(() => {
            expect(forgetProduct).toHaveBeenCalledWith('Leche entera');
            expect(screen.queryByText('Leche entera')).toBeNull();
            expect(screen.getByText('Pan integral')).toBeInTheDocument();
        });
    });

    it('shows error when clear fails', async () => {
        const user = userEvent.setup();
        fetchHistory.mockResolvedValue(mockHistory);
        clearHistory.mockRejectedValue(new Error('fail'));

        render(<HistoryList />);

        await waitFor(() => screen.getByTestId('clear-history-button'));
        await user.click(screen.getByTestId('clear-history-button'));
        await user.click(screen.getByRole('button', { name: /eliminar todo/i }));

        await waitFor(() => {
            expect(screen.getByRole('alert')).toHaveTextContent(/error al eliminar/i);
        });
    });

    it('shows error when forget fails', async () => {
        const user = userEvent.setup();
        fetchHistory.mockResolvedValue(mockHistory);
        forgetProduct.mockRejectedValue(new Error('fail'));

        render(<HistoryList />);

        await waitFor(() => screen.getByText('Leche entera'));

        await user.click(screen.getByLabelText(/olvidar leche entera/i));

        await waitFor(() => {
            expect(screen.getByRole('alert')).toHaveTextContent(/error al olvidar/i);
        });
    });

    it('clear button is hidden when list is empty', async () => {
        fetchHistory.mockResolvedValue({ items: [], pagination: { page: 1, per_page: 20, total: 0 } });

        render(<HistoryList />);

        await waitFor(() => screen.getByTestId('history-empty'));
        expect(screen.queryByTestId('clear-history-button')).toBeNull();
    });
});
