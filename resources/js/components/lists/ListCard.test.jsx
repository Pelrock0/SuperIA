import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { MemoryRouter } from 'react-router-dom';
import ListCard from './ListCard';

const mockList = {
    id: 1,
    name: 'Compra semanal',
    emoji: '🛒',
    category: 'supermercado',
    status: 'active',
    is_shared: false,
    items_total: 5,
    items_completed: 2,
    updated_at: '2026-04-10T12:00:00Z',
};

describe('ListCard', () => {
    const handlers = {
        onArchive: vi.fn(),
        onRestore: vi.fn(),
        onDelete: vi.fn(),
    };

    beforeEach(() => vi.clearAllMocks());

    function renderCard(list = mockList) {
        return render(
            <MemoryRouter>
                <ListCard list={list} {...handlers} />
            </MemoryRouter>
        );
    }

    it('renders list name, emoji, category, and items count', () => {
        renderCard();
        expect(screen.getByText('Compra semanal')).toBeInTheDocument();
        expect(screen.getByText('🛒')).toBeInTheDocument();
        expect(screen.getByText('Supermercado')).toBeInTheDocument();
        expect(screen.getByText('2 de 5 items')).toBeInTheDocument();
    });

    it('renders without emoji and category', () => {
        renderCard({ ...mockList, emoji: null, category: null });
        expect(screen.getByText('Compra semanal')).toBeInTheDocument();
    });

    it('shows archive option for active lists', async () => {
        const user = userEvent.setup();
        renderCard();

        await user.click(screen.getByLabelText(/opciones de lista/i));
        expect(screen.getByText('Archivar')).toBeInTheDocument();
        expect(screen.queryByText('Restaurar')).not.toBeInTheDocument();
    });

    it('shows restore option for archived lists', async () => {
        const user = userEvent.setup();
        renderCard({ ...mockList, status: 'archived' });

        await user.click(screen.getByLabelText(/opciones de lista/i));
        expect(screen.getByText('Restaurar')).toBeInTheDocument();
        expect(screen.queryByText('Archivar')).not.toBeInTheDocument();
    });

    it('calls onArchive when archive clicked', async () => {
        const user = userEvent.setup();
        renderCard();

        await user.click(screen.getByLabelText(/opciones de lista/i));
        await user.click(screen.getByText('Archivar'));
        expect(handlers.onArchive).toHaveBeenCalledWith(1);
    });

    it('calls onRestore when restore clicked', async () => {
        const user = userEvent.setup();
        renderCard({ ...mockList, status: 'archived' });

        await user.click(screen.getByLabelText(/opciones de lista/i));
        await user.click(screen.getByText('Restaurar'));
        expect(handlers.onRestore).toHaveBeenCalledWith(1);
    });

    it('shows delete confirmation before deleting', async () => {
        const user = userEvent.setup();
        renderCard();

        await user.click(screen.getByLabelText(/opciones de lista/i));
        await user.click(screen.getByText('Eliminar'));

        expect(screen.getByTestId('delete-confirm')).toBeInTheDocument();
        expect(screen.getByText(/eliminara la lista permanentemente/i)).toBeInTheDocument();
    });

    it('calls onDelete after confirming', async () => {
        const user = userEvent.setup();
        renderCard();

        await user.click(screen.getByLabelText(/opciones de lista/i));
        await user.click(screen.getByText('Eliminar'));
        await user.click(screen.getByRole('button', { name: 'Eliminar' }));

        expect(handlers.onDelete).toHaveBeenCalledWith(1);
    });

    it('shows shared warning in delete confirm when is_shared', async () => {
        const user = userEvent.setup();
        renderCard({ ...mockList, is_shared: true });

        await user.click(screen.getByLabelText(/opciones de lista/i));
        await user.click(screen.getByText('Eliminar'));

        expect(screen.getByText(/compartida/i)).toBeInTheDocument();
    });

    it('cancels delete confirmation', async () => {
        const user = userEvent.setup();
        renderCard();

        await user.click(screen.getByLabelText(/opciones de lista/i));
        await user.click(screen.getByText('Eliminar'));
        await user.click(screen.getByRole('button', { name: 'Cancelar' }));

        expect(screen.queryByTestId('delete-confirm')).not.toBeInTheDocument();
        expect(handlers.onDelete).not.toHaveBeenCalled();
    });
});
