import { render, screen, waitFor, act } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { MemoryRouter, Route, Routes } from 'react-router-dom';

vi.mock('../lib/sharedListApi', () => ({
    fetchSharedList: vi.fn(),
    addSharedItem: vi.fn(),
    updateSharedItem: vi.fn(),
    toggleSharedItem: vi.fn(),
    deleteSharedItem: vi.fn(),
    sendHeartbeat: vi.fn(),
}));

import SharedListPage from './SharedListPage';
import {
    fetchSharedList,
    addSharedItem,
    toggleSharedItem,
    deleteSharedItem,
    updateSharedItem,
    sendHeartbeat,
} from '../lib/sharedListApi';

const TOKEN = 'abc.sig';

function renderPage() {
    return render(
        <MemoryRouter initialEntries={[`/shared/${TOKEN}`]}>
            <Routes>
                <Route path="/shared/:tokenParam" element={<SharedListPage />} />
                <Route path="/" element={<div>Landing</div>} />
            </Routes>
        </MemoryRouter>
    );
}

const editResponse = {
    list: { id: 5, name: 'Compra', emoji: '🛒', owner_name: 'Maria' },
    mode: 'edit',
    items: {
        bebidas: [{ id: 1, name: 'Agua', quantity: '6.00', unit: 'L', category: 'bebidas', estimated_price: null, is_purchased: false }],
    },
    counters: { items_total: 1, items_completed: 0 },
};

const readOnlyResponse = {
    ...editResponse,
    mode: 'read_only',
};

const emptyResponse = {
    list: { id: 5, name: 'Compra', emoji: null, owner_name: 'Maria' },
    mode: 'edit',
    items: {},
    counters: { items_total: 0, items_completed: 0 },
};

describe('SharedListPage', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        window.sessionStorage.clear();
    });

    afterEach(() => {
        vi.useRealTimers();
    });

    it('shows loading while fetching', () => {
        fetchSharedList.mockImplementation(() => new Promise(() => {}));
        renderPage();
        expect(screen.getByTestId('shared-loading')).toBeInTheDocument();
    });

    it('renders revoked view on 410', async () => {
        fetchSharedList.mockRejectedValue({ response: { status: 410 } });
        renderPage();
        await waitFor(() => {
            expect(screen.getByTestId('revoked-link')).toBeInTheDocument();
        });
    });

    it('shows generic error on non-410 failure', async () => {
        fetchSharedList.mockRejectedValue(new Error('boom'));
        renderPage();
        await waitFor(() => {
            expect(screen.getByText(/error al cargar la lista/i)).toBeInTheDocument();
        });
    });

    it('renders list with consent banner on first visit', async () => {
        fetchSharedList.mockResolvedValue(editResponse);
        renderPage();

        await waitFor(() => {
            expect(screen.getByText('Compra')).toBeInTheDocument();
            expect(screen.getByText('Agua')).toBeInTheDocument();
            expect(screen.getByTestId('consent-banner')).toBeInTheDocument();
            expect(document.body.textContent).toMatch(/lista compartida por maria/i);
        });
    });

    it('hides consent banner after accept and stores flag', async () => {
        const user = userEvent.setup();
        fetchSharedList.mockResolvedValue(editResponse);
        sendHeartbeat.mockResolvedValue();

        renderPage();

        await waitFor(() => screen.getByTestId('consent-banner'));

        await user.click(screen.getByRole('button', { name: /continuar/i }));

        expect(screen.queryByTestId('consent-banner')).toBeNull();
        expect(window.sessionStorage.getItem(`superia:consent:${TOKEN}`)).toBe('1');
    });

    it('skips consent banner when already consented', async () => {
        window.sessionStorage.setItem(`superia:consent:${TOKEN}`, '1');
        fetchSharedList.mockResolvedValue(editResponse);
        sendHeartbeat.mockResolvedValue();

        renderPage();

        await waitFor(() => {
            expect(screen.getByText('Agua')).toBeInTheDocument();
        });
        expect(screen.queryByTestId('consent-banner')).toBeNull();
    });

    it('starts heartbeat loop after consent', async () => {
        window.sessionStorage.setItem(`superia:consent:${TOKEN}`, '1');
        fetchSharedList.mockResolvedValue(editResponse);
        sendHeartbeat.mockResolvedValue();

        renderPage();

        await waitFor(() => expect(sendHeartbeat).toHaveBeenCalledTimes(1));

        const firstUuid = sendHeartbeat.mock.calls[0][1];
        expect(firstUuid).toMatch(/^[0-9a-f-]{36}$/);
        expect(sendHeartbeat.mock.calls[0][0]).toBe(TOKEN);
    });

    it('shows read-only badge and disabled checkboxes for read_only mode', async () => {
        window.sessionStorage.setItem(`superia:consent:${TOKEN}`, '1');
        fetchSharedList.mockResolvedValue(readOnlyResponse);
        sendHeartbeat.mockResolvedValue();

        renderPage();

        await waitFor(() => {
            expect(screen.getByTestId('read-only-badge')).toBeInTheDocument();
        });

        const checkbox = screen.getByRole('checkbox');
        expect(checkbox).toBeDisabled();
        expect(checkbox).toHaveStyle({ opacity: '0.4' });
    });

    it('does not render add input in read_only mode', async () => {
        window.sessionStorage.setItem(`superia:consent:${TOKEN}`, '1');
        fetchSharedList.mockResolvedValue(readOnlyResponse);
        sendHeartbeat.mockResolvedValue();

        renderPage();

        await waitFor(() => screen.getByText('Compra'));
        expect(screen.queryByTestId('add-item-form')).toBeNull();
    });

    it('adds an item via the shared API in edit mode', async () => {
        const user = userEvent.setup();
        window.sessionStorage.setItem(`superia:consent:${TOKEN}`, '1');
        fetchSharedList.mockResolvedValue(editResponse);
        addSharedItem.mockResolvedValue({});
        sendHeartbeat.mockResolvedValue();

        renderPage();

        await waitFor(() => screen.getByTestId('add-item-form'));

        await user.type(screen.getByLabelText(/nombre del producto/i), 'Manzanas');
        await user.click(screen.getByRole('button', { name: /anadir/i }));

        await waitFor(() => {
            expect(addSharedItem).toHaveBeenCalledWith(TOKEN, { name: 'Manzanas' });
        });
    });

    it('toggles an item in edit mode', async () => {
        const user = userEvent.setup();
        window.sessionStorage.setItem(`superia:consent:${TOKEN}`, '1');
        fetchSharedList.mockResolvedValue(editResponse);
        toggleSharedItem.mockResolvedValue({});
        sendHeartbeat.mockResolvedValue();

        renderPage();

        await waitFor(() => screen.getByText('Agua'));

        await user.click(screen.getByRole('checkbox'));

        await waitFor(() => {
            expect(toggleSharedItem).toHaveBeenCalledWith(TOKEN, 1);
        });
    });

    it('deletes an item in edit mode', async () => {
        const user = userEvent.setup();
        window.sessionStorage.setItem(`superia:consent:${TOKEN}`, '1');
        fetchSharedList.mockResolvedValue(editResponse);
        deleteSharedItem.mockResolvedValue({});
        sendHeartbeat.mockResolvedValue();

        renderPage();

        await waitFor(() => screen.getByText('Agua'));

        await user.click(screen.getByLabelText(/eliminar agua/i));

        await waitFor(() => {
            expect(deleteSharedItem).toHaveBeenCalledWith(TOKEN, 1);
        });
    });

    it('opens edit panel and calls updateSharedItem on save', async () => {
        const user = userEvent.setup();
        window.sessionStorage.setItem(`superia:consent:${TOKEN}`, '1');
        fetchSharedList.mockResolvedValue(editResponse);
        updateSharedItem.mockResolvedValue({});
        sendHeartbeat.mockResolvedValue();

        renderPage();

        await waitFor(() => screen.getByText('Agua'));

        await user.click(screen.getByText('Agua'));

        await waitFor(() => {
            expect(screen.getByTestId('edit-panel')).toBeInTheDocument();
        });

        await user.click(screen.getByRole('button', { name: /guardar/i }));

        await waitFor(() => {
            expect(updateSharedItem).toHaveBeenCalled();
            expect(updateSharedItem.mock.calls[0][0]).toBe(TOKEN);
            expect(updateSharedItem.mock.calls[0][1]).toBe(1);
        });
    });

    it('shows empty state when list has no items', async () => {
        window.sessionStorage.setItem(`superia:consent:${TOKEN}`, '1');
        fetchSharedList.mockResolvedValue(emptyResponse);
        sendHeartbeat.mockResolvedValue();

        renderPage();

        await waitFor(() => {
            expect(screen.getByTestId('shared-empty')).toBeInTheDocument();
        });
    });

    it('renders register CTA linking to landing', async () => {
        window.sessionStorage.setItem(`superia:consent:${TOKEN}`, '1');
        fetchSharedList.mockResolvedValue(editResponse);
        sendHeartbeat.mockResolvedValue();

        renderPage();

        await waitFor(() => {
            // The CTA might use different text in the Stitch design
            const links = screen.getAllByRole('link');
            const ctaLink = links.find(l => l.getAttribute('href') === '/');
            expect(ctaLink).toBeTruthy();
        });
    });

    it('surfaces error when addSharedItem fails', async () => {
        const user = userEvent.setup();
        window.sessionStorage.setItem(`superia:consent:${TOKEN}`, '1');
        fetchSharedList.mockResolvedValue(editResponse);
        addSharedItem.mockRejectedValue(new Error('fail'));
        sendHeartbeat.mockResolvedValue();

        renderPage();

        await waitFor(() => screen.getByTestId('add-item-form'));

        await user.type(screen.getByLabelText(/nombre del producto/i), 'Manzanas');
        await user.click(screen.getByRole('button', { name: /anadir/i }));

        await waitFor(() => {
            expect(screen.getByText(/error al anadir el item/i)).toBeInTheDocument();
        });
    });

    it('reuses session uuid across re-renders', async () => {
        window.sessionStorage.setItem(`superia:consent:${TOKEN}`, '1');
        window.sessionStorage.setItem(`superia:session:${TOKEN}`, '550e8400-e29b-41d4-a716-446655440000');
        fetchSharedList.mockResolvedValue(editResponse);
        sendHeartbeat.mockResolvedValue();

        renderPage();

        await waitFor(() => expect(sendHeartbeat).toHaveBeenCalled());

        expect(sendHeartbeat.mock.calls[0][1]).toBe('550e8400-e29b-41d4-a716-446655440000');
    });
});
