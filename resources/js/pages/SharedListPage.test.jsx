import { render, screen, waitFor, act, fireEvent } from '@testing-library/react';
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

const mixedResponse = {
    list: { id: 5, name: 'Compra', emoji: '🛒', owner_name: 'Maria' },
    mode: 'edit',
    items: {
        bebidas: [
            { id: 1, name: 'Agua', quantity: '1.00', unit: null, category: 'bebidas', estimated_price: null, is_purchased: false },
            { id: 2, name: 'Leche', quantity: '1.00', unit: null, category: 'bebidas', estimated_price: null, is_purchased: true },
        ],
    },
    counters: { items_total: 2, items_completed: 1 },
};

const allPurchasedResponse = {
    list: { id: 5, name: 'Compra', emoji: '🛒', owner_name: 'Maria' },
    mode: 'edit',
    items: {
        bebidas: [
            { id: 1, name: 'Agua', quantity: '1.00', unit: null, category: 'bebidas', estimated_price: null, is_purchased: true },
        ],
    },
    counters: { items_total: 1, items_completed: 1 },
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
        await user.click(screen.getByRole('button', { name: /añadir/i }));

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
        await user.click(screen.getByRole('button', { name: /añadir/i }));

        await waitFor(() => {
            expect(screen.getByText(/error al añadir el item/i)).toBeInTheDocument();
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

    // AC-1: pending items rendered above purchased items
    it('AC-1: non-purchased items appear above purchased items in DOM', async () => {
        window.sessionStorage.setItem(`superia:consent:${TOKEN}`, '1');
        fetchSharedList.mockResolvedValue(mixedResponse);
        sendHeartbeat.mockResolvedValue();

        renderPage();

        await waitFor(() => screen.getByText('Agua'));

        const pendingRows = screen.getAllByTestId('shared-item-row');
        const purchasedRows = screen.getAllByTestId('purchased-item-row');

        expect(pendingRows.length).toBe(1);
        expect(purchasedRows.length).toBe(1);
        expect(pendingRows[0].textContent).toMatch(/Agua/);
        expect(purchasedRows[0].textContent).toMatch(/Leche/);

        const pendingPos = document.body.innerHTML.indexOf('Agua');
        const purchasedPos = document.body.innerHTML.indexOf('Leche');
        expect(pendingPos).toBeLessThan(purchasedPos);
    });

    // AC-3: "Ya en el carro" header visible when purchased items exist
    it('AC-3: shows Ya en el carro section header when purchased items exist', async () => {
        window.sessionStorage.setItem(`superia:consent:${TOKEN}`, '1');
        fetchSharedList.mockResolvedValue(mixedResponse);
        sendHeartbeat.mockResolvedValue();

        renderPage();

        await waitFor(() => screen.getByTestId('purchased-section'));
        expect(screen.getByText(/ya en el carro/i)).toBeInTheDocument();
    });

    // AC-4: no purchased section when no items are purchased
    it('AC-4: purchased section not rendered when all items are pending', async () => {
        window.sessionStorage.setItem(`superia:consent:${TOKEN}`, '1');
        fetchSharedList.mockResolvedValue(editResponse);
        sendHeartbeat.mockResolvedValue();

        renderPage();

        await waitFor(() => screen.getByText('Agua'));
        expect(screen.queryByTestId('purchased-section')).toBeNull();
    });

    // AC-5: no pending category section when all items are purchased
    it('AC-5: pending category sections not rendered when all items are purchased', async () => {
        window.sessionStorage.setItem(`superia:consent:${TOKEN}`, '1');
        fetchSharedList.mockResolvedValue(allPurchasedResponse);
        sendHeartbeat.mockResolvedValue();

        renderPage();

        await waitFor(() => screen.getByTestId('purchased-section'));
        expect(screen.queryByTestId('pending-category-section')).toBeNull();
        expect(screen.getByTestId('purchased-section')).toBeInTheDocument();
    });

    // AC-2: purchased item moves to purchased section after toggle (requires 1.5s animation to complete)
    it('AC-2: item moves to purchased section after being toggled', async () => {
        window.sessionStorage.setItem(`superia:consent:${TOKEN}`, '1');

        const afterToggle = {
            ...editResponse,
            items: {
                bebidas: [
                    { id: 1, name: 'Agua', quantity: '6.00', unit: 'L', category: 'bebidas', estimated_price: null, is_purchased: true },
                ],
            },
            counters: { items_total: 1, items_completed: 1 },
        };

        fetchSharedList
            .mockResolvedValueOnce(editResponse)
            .mockResolvedValueOnce(afterToggle);
        toggleSharedItem.mockResolvedValue({});
        sendHeartbeat.mockResolvedValue();

        renderPage();
        await waitFor(() => screen.getByText('Agua'));
        expect(screen.queryByTestId('purchased-section')).toBeNull();

        vi.useFakeTimers({ toFake: ['setTimeout', 'clearTimeout'] });
        fireEvent.click(screen.getByRole('checkbox'));
        // 1.5s green phase — then async chain creates the 300ms exit timer
        await act(async () => { vi.advanceTimersByTime(1500); });
        // 300ms exit animation — then loadList() fires
        await act(async () => { vi.advanceTimersByTime(300); });

        expect(screen.getByTestId('purchased-section')).toBeInTheDocument();
        expect(screen.queryByTestId('pending-category-section')).toBeNull();
        expect(screen.getByTestId('purchased-item-row').textContent).toMatch(/Agua/);
    });

    // AC-6: un-toggling a purchased item moves it back to pending (requires 1.5s animation to complete)
    it('AC-6: un-toggling purchased item moves it back to pending section', async () => {
        window.sessionStorage.setItem(`superia:consent:${TOKEN}`, '1');

        const afterUnToggle = {
            ...mixedResponse,
            items: {
                bebidas: [
                    { id: 1, name: 'Agua', quantity: '1.00', unit: null, category: 'bebidas', estimated_price: null, is_purchased: false },
                    { id: 2, name: 'Leche', quantity: '1.00', unit: null, category: 'bebidas', estimated_price: null, is_purchased: false },
                ],
            },
            counters: { items_total: 2, items_completed: 0 },
        };

        fetchSharedList
            .mockResolvedValueOnce(mixedResponse)
            .mockResolvedValueOnce(afterUnToggle);
        toggleSharedItem.mockResolvedValue({});
        sendHeartbeat.mockResolvedValue();

        renderPage();
        await waitFor(() => screen.getByTestId('purchased-section'));

        vi.useFakeTimers({ toFake: ['setTimeout', 'clearTimeout'] });
        const purchasedCheckbox = screen.getByLabelText(/leche.*comprado/i);
        fireEvent.click(purchasedCheckbox);
        // 1.5s green phase — then async chain creates the 300ms exit timer
        await act(async () => { vi.advanceTimersByTime(1500); });
        // 300ms exit animation — then loadList() fires
        await act(async () => { vi.advanceTimersByTime(300); });

        expect(screen.queryByTestId('purchased-section')).toBeNull();
        const pendingRows = screen.getAllByTestId('shared-item-row');
        expect(pendingRows.length).toBe(2);
    });

    describe('purchase animation', () => {
        afterEach(() => vi.useRealTimers());

        it('shows green background immediately when checking a pending item', async () => {
            window.sessionStorage.setItem(`superia:consent:${TOKEN}`, '1');
            fetchSharedList.mockResolvedValue(editResponse);
            sendHeartbeat.mockResolvedValue();
            toggleSharedItem.mockResolvedValue({});

            renderPage();
            await waitFor(() => screen.getByText('Agua'));

            fireEvent.click(screen.getByRole('checkbox'));

            await waitFor(() => {
                const row = screen.getByTestId('shared-item-row');
                expect(row).toHaveStyle({ background: '#dcfce7' });
            });
        });

        it('calls toggleSharedItem immediately without waiting for the delay', async () => {
            window.sessionStorage.setItem(`superia:consent:${TOKEN}`, '1');
            fetchSharedList.mockResolvedValue(editResponse);
            sendHeartbeat.mockResolvedValue();
            toggleSharedItem.mockResolvedValue({});

            renderPage();
            await waitFor(() => screen.getByText('Agua'));

            fireEvent.click(screen.getByRole('checkbox'));

            expect(toggleSharedItem).toHaveBeenCalledWith(TOKEN, 1);
        });

        it('disables the checkbox during animation to prevent double-toggle', async () => {
            window.sessionStorage.setItem(`superia:consent:${TOKEN}`, '1');
            fetchSharedList.mockResolvedValue(editResponse);
            sendHeartbeat.mockResolvedValue();
            toggleSharedItem.mockResolvedValue({});

            renderPage();
            await waitFor(() => screen.getByText('Agua'));

            fireEvent.click(screen.getByRole('checkbox'));

            await waitFor(() => {
                expect(screen.getByRole('checkbox')).toBeDisabled();
            });
        });

        it('removes green background after animation completes (1.5s delay + 300ms exit)', async () => {
            window.sessionStorage.setItem(`superia:consent:${TOKEN}`, '1');
            fetchSharedList
                .mockResolvedValueOnce(editResponse)
                .mockResolvedValueOnce(editResponse);
            sendHeartbeat.mockResolvedValue();
            toggleSharedItem.mockResolvedValue({});

            renderPage();
            await waitFor(() => screen.getByText('Agua'));

            vi.useFakeTimers({ toFake: ['setTimeout', 'clearTimeout'] });
            const row = screen.getByTestId('shared-item-row');
            fireEvent.click(screen.getByRole('checkbox'));
            expect(row).toHaveStyle({ background: '#dcfce7' });

            // 1.5s green phase, then async chain creates 300ms exit timer
            await act(async () => { vi.advanceTimersByTime(1500); });
            // 300ms exit animation fires — exitingItems cleared, green gone
            await act(async () => { vi.advanceTimersByTime(300); });

            expect(row).not.toHaveStyle({ background: '#dcfce7' });
        });

        it('cleans up timer on unmount without calling setState', async () => {
            window.sessionStorage.setItem(`superia:consent:${TOKEN}`, '1');
            fetchSharedList.mockResolvedValue(editResponse);
            sendHeartbeat.mockResolvedValue();
            toggleSharedItem.mockResolvedValue({});

            const { unmount } = renderPage();
            await waitFor(() => screen.getByText('Agua'));

            vi.useFakeTimers({ toFake: ['setTimeout', 'clearTimeout'] });
            fireEvent.click(screen.getByRole('checkbox'));
            unmount();

            await act(async () => { vi.advanceTimersByTime(2000); });
        });

        it('unchecking a purchased item calls API immediately and disables checkbox during animation', async () => {
            window.sessionStorage.setItem(`superia:consent:${TOKEN}`, '1');
            fetchSharedList.mockResolvedValue(mixedResponse);
            sendHeartbeat.mockResolvedValue();
            toggleSharedItem.mockResolvedValue({});

            renderPage();
            await waitFor(() => screen.getByText('Leche'));

            const purchasedCheckbox = screen.getByLabelText(/leche \(comprado\)/i);
            fireEvent.click(purchasedCheckbox);

            expect(toggleSharedItem).toHaveBeenCalledWith(TOKEN, 2);
            await waitFor(() => {
                expect(screen.getByLabelText(/leche \(comprado\)/i)).toBeDisabled();
            });
        });

        it('clears animation state and shows error when API fails during toggle', async () => {
            window.sessionStorage.setItem(`superia:consent:${TOKEN}`, '1');
            fetchSharedList.mockResolvedValue(editResponse);
            sendHeartbeat.mockResolvedValue();
            toggleSharedItem.mockRejectedValue(new Error('network error'));

            renderPage();
            await waitFor(() => screen.getByText('Agua'));

            fireEvent.click(screen.getByRole('checkbox'));

            await waitFor(() => {
                expect(screen.getByRole('checkbox')).not.toBeDisabled();
            });
        });
    });
});
