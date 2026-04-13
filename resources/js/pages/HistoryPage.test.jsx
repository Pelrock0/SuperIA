import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { MemoryRouter } from 'react-router-dom';

vi.mock('../lib/historyApi', () => ({
    fetchHistory: vi.fn(),
    duplicateList: vi.fn(),
    fetchStats: vi.fn(),
}));

vi.mock('../components/history/StatsSection', () => ({
    default: ({ stats }) => (
        <div data-testid="stats-section-mock">
            {stats?.has_enough_data ? 'Stats loaded' : 'Not enough data'}
        </div>
    ),
}));

import HistoryPage from './HistoryPage';
import { fetchHistory, duplicateList, fetchStats } from '../lib/historyApi';

const mockLists = [
    { id: 1, name: 'Cena Navidad', emoji: '🎄', updated_at: '2026-04-10T10:00:00Z', items_total: 10, price_total: 45.50 },
    { id: 2, name: 'Semanal', emoji: '🛒', updated_at: '2026-04-05T10:00:00Z', items_total: 5, price_total: null },
];

const mockStats = { has_enough_data: true, monthly_spend: [], top_categories: [], top_products: [], total_lists_completed: 5 };

const renderPage = () => render(<MemoryRouter><HistoryPage /></MemoryRouter>);

describe('HistoryPage', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        fetchHistory.mockResolvedValue({ data: mockLists, meta: { current_page: 1, per_page: 20, total: 2, last_page: 1 } });
        fetchStats.mockResolvedValue(mockStats);
    });

    it('shows loading state', () => {
        fetchHistory.mockImplementation(() => new Promise(() => {}));
        fetchStats.mockImplementation(() => new Promise(() => {}));
        renderPage();
        expect(screen.getByTestId('history-loading')).toBeInTheDocument();
    });

    it('renders history cards', async () => {
        renderPage();
        await waitFor(() => {
            expect(document.body.textContent).toContain('Cena Navidad');
            expect(document.body.textContent).toContain('Semanal');
        });
    });

    it('shows empty state when no lists', async () => {
        fetchHistory.mockResolvedValue({ data: [], meta: { current_page: 1, per_page: 20, total: 0, last_page: 1 } });
        renderPage();
        await waitFor(() => expect(screen.getByTestId('history-empty')).toBeInTheDocument());
    });

    it('duplicate button triggers API and redirects', async () => {
        duplicateList.mockResolvedValue({ id: 99 });
        const user = userEvent.setup();
        renderPage();
        await waitFor(() => expect(screen.getByTestId('duplicate-1')).toBeInTheDocument());
        await user.click(screen.getByTestId('duplicate-1'));
        expect(duplicateList).toHaveBeenCalledWith(1);
    });

    it('shows freemium error on duplicate', async () => {
        duplicateList.mockRejectedValue({ response: { data: { error: { code: 'FREEMIUM_LIMIT' } } } });
        const user = userEvent.setup();
        renderPage();
        await waitFor(() => expect(screen.getByTestId('duplicate-1')).toBeInTheDocument());
        await user.click(screen.getByTestId('duplicate-1'));
        await waitFor(() => expect(screen.getByTestId('history-error')).toBeInTheDocument());
    });

    it('renders stats section', async () => {
        renderPage();
        await waitFor(() => expect(screen.getByTestId('stats-section-mock')).toBeInTheDocument());
    });

    it('shows error on API failure', async () => {
        fetchHistory.mockRejectedValue(new Error('fail'));
        fetchStats.mockRejectedValue(new Error('fail'));
        renderPage();
        await waitFor(() => expect(screen.getByTestId('history-error')).toBeInTheDocument());
    });
});
