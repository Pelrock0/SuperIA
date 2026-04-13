import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { MemoryRouter } from 'react-router-dom';

vi.mock('../../lib/weeklySummaryApi', () => ({
    fetchLatestSummary: vi.fn(),
    dismissSummary: vi.fn(),
    convertSummaryToList: vi.fn(),
    updateWeeklySummaryEmail: vi.fn(),
}));

import WeeklySummaryBanner from './WeeklySummaryBanner';
import { fetchLatestSummary, dismissSummary } from '../../lib/weeklySummaryApi';

const mockSummary = {
    id: 42,
    week_start_date: '2026-04-13',
    products: [
        { nombre: 'Leche', cantidad_tipica: 1.0, unidad_tipica: 'L', reason: 'Compra habitual' },
        { nombre: 'Pan', cantidad_tipica: 1.0, unidad_tipica: 'ud', reason: null },
    ],
};

const renderBanner = () => {
    return render(
        <MemoryRouter>
            <WeeklySummaryBanner />
        </MemoryRouter>,
    );
};

describe('WeeklySummaryBanner', () => {
    beforeEach(() => vi.clearAllMocks());

    it('renders nothing while loading', () => {
        fetchLatestSummary.mockImplementation(() => new Promise(() => {}));

        const { container } = renderBanner();

        expect(container.querySelector('[data-testid="weekly-summary-banner"]')).toBeNull();
    });

    it('renders nothing when no summary available', async () => {
        fetchLatestSummary.mockRejectedValue({ response: { data: { error: { code: 'NO_SUMMARY_THIS_WEEK' } } } });

        const { container } = renderBanner();

        await waitFor(() => expect(fetchLatestSummary).toHaveBeenCalled());
        expect(container.querySelector('[data-testid="weekly-summary-banner"]')).toBeNull();
    });

    it('renders banner with product count when summary exists', async () => {
        fetchLatestSummary.mockResolvedValue(mockSummary);

        renderBanner();

        await waitFor(() => {
            expect(screen.getByText('Resumen semanal')).toBeInTheDocument();
            expect(screen.getByText('2 productos sugeridos para esta semana.')).toBeInTheDocument();
        });
    });

    it('dismisses banner on X click', async () => {
        fetchLatestSummary.mockResolvedValue(mockSummary);
        dismissSummary.mockResolvedValue();
        const user = userEvent.setup();

        renderBanner();

        await waitFor(() => expect(screen.getByTestId('dismiss-banner')).toBeInTheDocument());

        await user.click(screen.getByTestId('dismiss-banner'));

        await waitFor(() => {
            expect(dismissSummary).toHaveBeenCalled();
            expect(screen.queryByTestId('weekly-summary-banner')).toBeNull();
        });
    });

    it('renders view button that links to /app/resumen', async () => {
        fetchLatestSummary.mockResolvedValue(mockSummary);

        renderBanner();

        await waitFor(() => {
            expect(screen.getByTestId('view-summary')).toBeInTheDocument();
            expect(screen.getByTestId('view-summary')).toHaveTextContent('Ver resumen');
        });
    });

    it('renders singular text for 1 product', async () => {
        fetchLatestSummary.mockResolvedValue({
            ...mockSummary,
            products: [{ nombre: 'Leche' }],
        });

        renderBanner();

        await waitFor(() => {
            expect(screen.getByText('1 producto sugerido para esta semana.')).toBeInTheDocument();
        });
    });
});
