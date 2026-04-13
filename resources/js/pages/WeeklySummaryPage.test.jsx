import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { MemoryRouter } from 'react-router-dom';

vi.mock('../lib/weeklySummaryApi', () => ({
    fetchLatestSummary: vi.fn(),
    dismissSummary: vi.fn(),
    convertSummaryToList: vi.fn(),
    updateWeeklySummaryEmail: vi.fn(),
}));

import WeeklySummaryPage from './WeeklySummaryPage';
import { fetchLatestSummary, convertSummaryToList } from '../lib/weeklySummaryApi';

const mockSummary = {
    id: 42,
    week_start_date: '2026-04-13',
    products: [
        { nombre: 'Leche', cantidad_tipica: 1.0, unidad_tipica: 'L', reason: 'Compra habitual' },
        { nombre: 'Pan', cantidad_tipica: 1.0, unidad_tipica: 'ud', reason: null },
    ],
};

const renderPage = () => {
    return render(
        <MemoryRouter>
            <WeeklySummaryPage />
        </MemoryRouter>,
    );
};

describe('WeeklySummaryPage', () => {
    beforeEach(() => vi.clearAllMocks());

    it('shows loading state initially', () => {
        fetchLatestSummary.mockImplementation(() => new Promise(() => {}));

        renderPage();

        expect(screen.getByTestId('summary-loading')).toBeInTheDocument();
    });

    it('shows empty state when no summary', async () => {
        fetchLatestSummary.mockRejectedValue({
            response: { data: { error: { code: 'NO_SUMMARY_THIS_WEEK' } } },
        });

        renderPage();

        await waitFor(() => {
            expect(screen.getByTestId('no-summary')).toBeInTheDocument();
            expect(screen.getByText('No hay resumen disponible esta semana.')).toBeInTheDocument();
        });
    });

    it('renders products when summary loaded', async () => {
        fetchLatestSummary.mockResolvedValue(mockSummary);

        renderPage();

        await waitFor(() => {
            expect(screen.getByText('Leche')).toBeInTheDocument();
            expect(screen.getByText('Pan')).toBeInTheDocument();
            expect(screen.getByText('Compra habitual')).toBeInTheDocument();
            expect(screen.getByTestId('summary-content')).toBeInTheDocument();
        });
    });

    it('shows week start date', async () => {
        fetchLatestSummary.mockResolvedValue(mockSummary);

        renderPage();

        await waitFor(() => {
            expect(screen.getByText('Semana del 2026-04-13')).toBeInTheDocument();
        });
    });

    it('convert to list button triggers API and shows success', async () => {
        fetchLatestSummary.mockResolvedValue(mockSummary);
        convertSummaryToList.mockResolvedValue({ id: 99 });
        const user = userEvent.setup();

        renderPage();

        await waitFor(() => expect(screen.getByTestId('convert-to-list')).toBeInTheDocument());

        await user.click(screen.getByTestId('convert-to-list'));

        await waitFor(() => {
            expect(convertSummaryToList).toHaveBeenCalledWith(42);
            expect(screen.getByTestId('convert-success')).toBeInTheDocument();
        });
    });

    it('convert to list shows freemium error', async () => {
        fetchLatestSummary.mockResolvedValue(mockSummary);
        convertSummaryToList.mockRejectedValue({
            response: { data: { error: { code: 'FREEMIUM_LIMIT', message: 'Limite alcanzado' } } },
        });
        const user = userEvent.setup();

        renderPage();

        await waitFor(() => expect(screen.getByTestId('convert-to-list')).toBeInTheDocument());

        await user.click(screen.getByTestId('convert-to-list'));

        await waitFor(() => {
            expect(screen.getByTestId('summary-error')).toBeInTheDocument();
            expect(screen.getByText(/limite de 3 listas/i)).toBeInTheDocument();
        });
    });

    it('shows error message on API failure', async () => {
        fetchLatestSummary.mockRejectedValue(new Error('Network error'));

        renderPage();

        await waitFor(() => {
            expect(screen.getByTestId('summary-error')).toBeInTheDocument();
        });
    });
});
