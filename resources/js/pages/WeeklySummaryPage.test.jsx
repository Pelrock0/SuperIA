import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { MemoryRouter } from 'react-router-dom';

const navigateMock = vi.fn();

vi.mock('react-router-dom', async () => {
    const actual = await vi.importActual('react-router-dom');
    return {
        ...actual,
        useNavigate: () => navigateMock,
    };
});

vi.mock('../lib/weeklySummaryApi', () => ({
    fetchLatestSummary: vi.fn(),
    dismissSummary: vi.fn(),
    saveSummarySelection: vi.fn(),
    fetchActiveLists: vi.fn(),
    updateWeeklySummaryEmail: vi.fn(),
}));

import WeeklySummaryPage from './WeeklySummaryPage';
import {
    fetchActiveLists,
    fetchLatestSummary,
    saveSummarySelection,
} from '../lib/weeklySummaryApi';

const mockSummary = {
    id: 42,
    week_start_date: '2026-04-13',
    products: [
        { nombre: 'Leche', cantidad_tipica: 1.0, unidad_tipica: 'L', reason: 'Compra habitual' },
        { nombre: 'Pan', cantidad_tipica: 1.0, unidad_tipica: 'ud', reason: null },
    ],
};

const renderPage = () =>
    render(
        <MemoryRouter>
            <WeeklySummaryPage />
        </MemoryRouter>,
    );

describe('WeeklySummaryPage', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        navigateMock.mockReset();
        fetchActiveLists.mockResolvedValue([]);
    });

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
        });
    });

    it('shows empty state when summary dismissed', async () => {
        fetchLatestSummary.mockRejectedValue({
            response: { data: { error: { code: 'DISMISSED' } } },
        });

        renderPage();

        await waitFor(() => {
            expect(screen.getByTestId('no-summary')).toBeInTheDocument();
        });
    });

    it('renders products with all checkboxes checked by default', async () => {
        fetchLatestSummary.mockResolvedValue(mockSummary);

        renderPage();

        await waitFor(() => {
            expect(screen.getByText('Leche')).toBeInTheDocument();
        });
        expect(screen.getByTestId('summary-item-checkbox-0')).toBeChecked();
        expect(screen.getByTestId('summary-item-checkbox-1')).toBeChecked();
        expect(screen.getByTestId('convert-to-list')).toHaveTextContent('Guardar 2 items');
    });

    it('updates the counter when items are toggled', async () => {
        fetchLatestSummary.mockResolvedValue(mockSummary);
        const user = userEvent.setup();

        renderPage();

        await waitFor(() => expect(screen.getByTestId('summary-item-checkbox-0')).toBeChecked());

        await user.click(screen.getByTestId('summary-item-checkbox-0'));

        expect(screen.getByTestId('summary-item-checkbox-0')).not.toBeChecked();
        expect(screen.getByTestId('convert-to-list')).toHaveTextContent('Guardar 1 item');
    });

    it('disables the CTA when no items are selected', async () => {
        fetchLatestSummary.mockResolvedValue(mockSummary);
        const user = userEvent.setup();

        renderPage();

        await waitFor(() => expect(screen.getByTestId('summary-item-checkbox-0')).toBeChecked());

        await user.click(screen.getByTestId('summary-item-checkbox-0'));
        await user.click(screen.getByTestId('summary-item-checkbox-1'));

        const cta = screen.getByTestId('convert-to-list');
        expect(cta).toBeDisabled();
        expect(cta).toHaveTextContent('Selecciona al menos un item');
    });

    it('opens the destination sheet on CTA click', async () => {
        fetchLatestSummary.mockResolvedValue(mockSummary);
        fetchActiveLists.mockResolvedValue([
            { id: 7, name: 'Compra semanal', emoji: '🛒', items_total: 5 },
        ]);
        const user = userEvent.setup();

        renderPage();

        await waitFor(() => expect(screen.getByTestId('convert-to-list')).toBeEnabled());
        await user.click(screen.getByTestId('convert-to-list'));

        await waitFor(() => {
            expect(screen.getByTestId('save-target-sheet')).toBeInTheDocument();
            expect(screen.getByTestId('save-target-list-7')).toBeInTheDocument();
        });
        expect(fetchActiveLists).toHaveBeenCalled();
    });

    it('shows new-list option enabled when fewer than three lists exist', async () => {
        fetchLatestSummary.mockResolvedValue(mockSummary);
        fetchActiveLists.mockResolvedValue([
            { id: 1, name: 'A', items_total: 1 },
            { id: 2, name: 'B', items_total: 2 },
        ]);
        const user = userEvent.setup();

        renderPage();

        await waitFor(() => expect(screen.getByTestId('convert-to-list')).toBeEnabled());
        await user.click(screen.getByTestId('convert-to-list'));

        const newOption = await screen.findByTestId('save-target-new-list');
        expect(newOption).not.toBeDisabled();
    });

    it('disables the new-list option at the freemium limit', async () => {
        fetchLatestSummary.mockResolvedValue(mockSummary);
        fetchActiveLists.mockResolvedValue([
            { id: 1, name: 'A', items_total: 1 },
            { id: 2, name: 'B', items_total: 2 },
            { id: 3, name: 'C', items_total: 3 },
        ]);
        const user = userEvent.setup();

        renderPage();

        await waitFor(() => expect(screen.getByTestId('convert-to-list')).toBeEnabled());
        await user.click(screen.getByTestId('convert-to-list'));

        const newOption = await screen.findByTestId('save-target-new-list');
        expect(newOption).toBeDisabled();
    });

    it('saves selection into an existing list and shows partial-success banner', async () => {
        fetchLatestSummary.mockResolvedValue(mockSummary);
        fetchActiveLists.mockResolvedValue([
            { id: 7, name: 'Cena del finde', emoji: '🥗', items_total: 3 },
        ]);
        saveSummarySelection.mockResolvedValue({
            list: { id: 7, name: 'Cena del finde' },
            summary: {
                id: 42,
                status: 'pending',
                remaining_items: [
                    { nombre: 'Pan', cantidad_tipica: 1.0, unidad_tipica: 'ud', reason: null },
                ],
                is_actioned: false,
            },
        });
        const user = userEvent.setup();

        renderPage();

        await waitFor(() => expect(screen.getByTestId('convert-to-list')).toBeEnabled());
        // Deselect "Pan" so we only save "Leche"
        await user.click(screen.getByTestId('summary-item-checkbox-1'));
        await user.click(screen.getByTestId('convert-to-list'));

        await user.click(await screen.findByTestId('save-target-list-7'));
        await user.click(screen.getByTestId('save-target-confirm'));

        await waitFor(() => {
            expect(saveSummarySelection).toHaveBeenCalledWith(42, {
                selected_indices: [0],
                target_list_id: 7,
            });
        });

        await waitFor(() => {
            expect(screen.getByTestId('convert-success')).toBeInTheDocument();
        });
        expect(screen.getByTestId('convert-success')).toHaveTextContent(/Cena del finde/);
        // Sheet closes after save
        expect(screen.queryByTestId('save-target-sheet')).not.toBeInTheDocument();
        // Page now reflects the remaining item only
        expect(screen.getByText('Pan')).toBeInTheDocument();
        expect(screen.queryByText('Leche')).not.toBeInTheDocument();
    });

    it('saves selection into a new list and redirects when summary is fully consumed', async () => {
        fetchLatestSummary.mockResolvedValue(mockSummary);
        fetchActiveLists.mockResolvedValue([]);
        saveSummarySelection.mockResolvedValue({
            list: { id: 99, name: 'Resumen semanal del 13/04/2026' },
            summary: {
                id: 42,
                status: 'actioned',
                remaining_items: [],
                is_actioned: true,
            },
        });
        const user = userEvent.setup();

        renderPage();

        await waitFor(() => expect(screen.getByTestId('convert-to-list')).toBeEnabled());
        await user.click(screen.getByTestId('convert-to-list'));
        await user.click(await screen.findByTestId('save-target-new-list'));
        await user.click(screen.getByTestId('save-target-confirm'));

        await waitFor(() => {
            expect(saveSummarySelection).toHaveBeenCalledWith(42, {
                selected_indices: [0, 1],
                target_list_id: null,
            });
        });
        // After the success path schedules a 1500ms redirect, navigate should fire.
        await waitFor(() => expect(navigateMock).toHaveBeenCalledWith('/app/listas/99'), { timeout: 3000 });
    });

    it('shows freemium error when API returns 403 FREEMIUM_LIMIT', async () => {
        fetchLatestSummary.mockResolvedValue(mockSummary);
        fetchActiveLists.mockResolvedValue([]);
        saveSummarySelection.mockRejectedValue({
            response: {
                status: 403,
                data: { error: { code: 'FREEMIUM_LIMIT', message: 'limit' } },
            },
        });
        const user = userEvent.setup();

        renderPage();

        await waitFor(() => expect(screen.getByTestId('convert-to-list')).toBeEnabled());
        await user.click(screen.getByTestId('convert-to-list'));
        await user.click(await screen.findByTestId('save-target-new-list'));
        await user.click(screen.getByTestId('save-target-confirm'));

        await waitFor(() => {
            expect(screen.getByTestId('summary-error')).toBeInTheDocument();
        });
        expect(screen.getByTestId('summary-error')).toHaveTextContent(/3 listas activas/i);
    });

    it('shows validation error when API returns 422', async () => {
        fetchLatestSummary.mockResolvedValue(mockSummary);
        fetchActiveLists.mockResolvedValue([]);
        saveSummarySelection.mockRejectedValue({
            response: { status: 422, data: { message: 'invalid' } },
        });
        const user = userEvent.setup();

        renderPage();

        await waitFor(() => expect(screen.getByTestId('convert-to-list')).toBeEnabled());
        await user.click(screen.getByTestId('convert-to-list'));
        await user.click(await screen.findByTestId('save-target-new-list'));
        await user.click(screen.getByTestId('save-target-confirm'));

        await waitFor(() => {
            expect(screen.getByTestId('summary-error')).toHaveTextContent(/Selección inválida/i);
        });
    });

    it('shows 404 message when target list became unavailable', async () => {
        fetchLatestSummary.mockResolvedValue(mockSummary);
        fetchActiveLists.mockResolvedValue([
            { id: 7, name: 'A', items_total: 1 },
        ]);
        saveSummarySelection.mockRejectedValue({ response: { status: 404 } });
        const user = userEvent.setup();

        renderPage();

        await waitFor(() => expect(screen.getByTestId('convert-to-list')).toBeEnabled());
        await user.click(screen.getByTestId('convert-to-list'));
        await user.click(await screen.findByTestId('save-target-list-7'));
        await user.click(screen.getByTestId('save-target-confirm'));

        await waitFor(() => {
            expect(screen.getByTestId('summary-error')).toHaveTextContent(/Lista no disponible/i);
        });
    });

    it('shows generic error on network failure during save', async () => {
        fetchLatestSummary.mockResolvedValue(mockSummary);
        fetchActiveLists.mockResolvedValue([]);
        saveSummarySelection.mockRejectedValue(new Error('Network'));
        const user = userEvent.setup();

        renderPage();

        await waitFor(() => expect(screen.getByTestId('convert-to-list')).toBeEnabled());
        await user.click(screen.getByTestId('convert-to-list'));
        await user.click(await screen.findByTestId('save-target-new-list'));
        await user.click(screen.getByTestId('save-target-confirm'));

        await waitFor(() => {
            expect(screen.getByTestId('summary-error')).toHaveTextContent(/No se pudieron guardar/i);
        });
    });

    it('shows error if active lists cannot be loaded when opening the sheet', async () => {
        fetchLatestSummary.mockResolvedValue(mockSummary);
        fetchActiveLists.mockRejectedValue(new Error('Network'));
        const user = userEvent.setup();

        renderPage();

        await waitFor(() => expect(screen.getByTestId('convert-to-list')).toBeEnabled());
        await user.click(screen.getByTestId('convert-to-list'));

        await waitFor(() => {
            expect(screen.getByTestId('summary-error')).toHaveTextContent(/listas/i);
        });
        expect(screen.queryByTestId('save-target-sheet')).not.toBeInTheDocument();
    });

    it('shows error message on initial summary fetch failure', async () => {
        fetchLatestSummary.mockRejectedValue(new Error('Network error'));

        renderPage();

        await waitFor(() => {
            expect(screen.getByTestId('summary-error')).toBeInTheDocument();
        });
    });

    it('shows week start date', async () => {
        fetchLatestSummary.mockResolvedValue(mockSummary);

        renderPage();

        await waitFor(() => {
            expect(screen.getByText('Semana del 2026-04-13')).toBeInTheDocument();
        });
    });
});
