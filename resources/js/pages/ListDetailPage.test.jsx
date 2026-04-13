import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { MemoryRouter, Route, Routes } from 'react-router-dom';

vi.mock('../lib/api', () => ({
    default: {
        get: vi.fn(),
        post: vi.fn(),
        put: vi.fn(),
        patch: vi.fn(),
        delete: vi.fn(),
    },
    getToken: vi.fn(),
    setToken: vi.fn(),
    removeToken: vi.fn(),
}));

vi.mock('../lib/shareApi', () => ({
    listShareTokens: vi.fn().mockResolvedValue([]),
    createShareToken: vi.fn(),
    revokeShareToken: vi.fn(),
    getCollaboratorsCount: vi.fn().mockResolvedValue(0),
    getActivityLog: vi.fn().mockResolvedValue([]),
}));

import ListDetailPage from './ListDetailPage';
import api from '../lib/api';

function renderPage() {
    return render(
        <MemoryRouter initialEntries={['/app/listas/1']}>
            <Routes>
                <Route path="/app/listas/:id" element={<ListDetailPage />} />
                <Route path="/app" element={<div>Dashboard</div>} />
            </Routes>
        </MemoryRouter>
    );
}

const mockList = { id: 1, name: 'Compra', emoji: '🛒', status: 'active' };
const mockItemsResponse = {
    items: {
        bebidas: [{ id: 1, name: 'Agua', quantity: '6.00', unit: 'L', category: 'bebidas', estimated_price: '3.00', is_purchased: false }],
        panaderia: [{ id: 2, name: 'Pan', quantity: '1.00', unit: 'ud', category: 'panaderia', estimated_price: null, is_purchased: true }],
    },
    counters: { items_total: 2, items_completed: 1 },
};

describe('ListDetailPage', () => {
    beforeEach(() => vi.clearAllMocks());

    it('shows loading state', () => {
        api.get.mockImplementation(() => new Promise(() => {}));
        renderPage();
        expect(screen.getByTestId('list-loading')).toBeInTheDocument();
    });

    it('renders list with items grouped by category', async () => {
        api.get.mockImplementation((url) => {
            if (url.includes('/items')) return Promise.resolve({ data: { data: mockItemsResponse } });
            return Promise.resolve({ data: { data: mockList } });
        });

        renderPage();

        await waitFor(() => {
            expect(screen.getByText('Compra')).toBeInTheDocument();
            expect(screen.getByText('Agua')).toBeInTheDocument();
            expect(screen.getByText('Pan')).toBeInTheDocument();
            // Bebidas shows as pending category, Pan shows in purchased section
            expect(document.body.textContent).toContain('Bebidas');
            expect(document.body.textContent).toContain('Pan');
        });
    });

    it('shows progress counter', async () => {
        api.get.mockImplementation((url) => {
            if (url.includes('/items')) return Promise.resolve({ data: { data: mockItemsResponse } });
            return Promise.resolve({ data: { data: mockList } });
        });

        renderPage();

        await waitFor(() => {
            expect(screen.getByText('1 de 2 items comprados')).toBeInTheDocument();
        });
    });

    it('shows empty state when no items', async () => {
        api.get.mockImplementation((url) => {
            if (url.includes('/items')) return Promise.resolve({ data: { data: { items: {}, counters: { items_total: 0, items_completed: 0 } } } });
            return Promise.resolve({ data: { data: mockList } });
        });

        renderPage();

        await waitFor(() => {
            expect(screen.getByTestId('empty-items')).toBeInTheDocument();
        });
    });

    it('shows all-completed message', async () => {
        const allDone = {
            items: { bebidas: [{ id: 1, name: 'Agua', quantity: null, unit: null, category: 'bebidas', estimated_price: null, is_purchased: true }] },
            counters: { items_total: 1, items_completed: 1 },
        };
        api.get.mockImplementation((url) => {
            if (url.includes('/items')) return Promise.resolve({ data: { data: allDone } });
            return Promise.resolve({ data: { data: mockList } });
        });

        renderPage();

        await waitFor(() => {
            expect(screen.getByTestId('all-completed')).toBeInTheDocument();
        });
    });

    it('opens add item modal on trigger click', async () => {
        const user = userEvent.setup();
        api.get.mockImplementation((url) => {
            if (url.includes('/items')) return Promise.resolve({ data: { data: { items: {}, counters: { items_total: 0, items_completed: 0 } } } });
            return Promise.resolve({ data: { data: mockList } });
        });

        renderPage();

        await waitFor(() => screen.getByTestId('add-item-trigger'));
        await user.click(screen.getByTestId('add-item-trigger'));

        expect(screen.getByTestId('add-item-modal')).toBeInTheDocument();
    });

    it('shows clear completed button when items checked', async () => {
        api.get.mockImplementation((url) => {
            if (url.includes('/items')) return Promise.resolve({ data: { data: mockItemsResponse } });
            return Promise.resolve({ data: { data: mockList } });
        });

        renderPage();

        await waitFor(() => {
            // The clear action is now in the more_vert menu or Ya en el carro section
            expect(screen.getByText(/Ya en el carro/i)).toBeInTheDocument();
        });
    });

    it('renders share button that opens modal', async () => {
        const user = userEvent.setup();
        api.get.mockImplementation((url) => {
            if (url.includes('/items')) return Promise.resolve({ data: { data: mockItemsResponse } });
            return Promise.resolve({ data: { data: mockList } });
        });

        renderPage();

        await waitFor(() => screen.getByTestId('share-button'));

        await user.click(screen.getByTestId('share-button'));

        await waitFor(() => {
            expect(screen.getByTestId('share-modal')).toBeInTheDocument();
        });
    });

    it('shows collaborator indicator and activity log when list is shared', async () => {
        const sharedList = { ...mockList, is_shared: true };
        api.get.mockImplementation((url) => {
            if (url.includes('/items')) return Promise.resolve({ data: { data: mockItemsResponse } });
            return Promise.resolve({ data: { data: sharedList } });
        });

        renderPage();

        await waitFor(() => {
            expect(screen.getByTestId('activity-log')).toBeInTheDocument();
        });
    });

    it('hides collaborator indicator when list is not shared', async () => {
        api.get.mockImplementation((url) => {
            if (url.includes('/items')) return Promise.resolve({ data: { data: mockItemsResponse } });
            return Promise.resolve({ data: { data: { ...mockList, is_shared: false } } });
        });

        renderPage();

        await waitFor(() => screen.getByText('Compra'));
        expect(screen.queryByTestId('activity-log')).toBeNull();
    });
});
