import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, it, expect, vi, beforeEach } from 'vitest';

vi.mock('../../lib/api', () => ({
    default: {
        get: vi.fn().mockResolvedValue({ data: { data: { suggestions: [] } } }),
    },
}));

import AddItemModal from './AddItemModal';

describe('AddItemModal — duplicate detection vs active items only', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    const setup = (existingItems) => {
        const onAdd = vi.fn().mockResolvedValue(true);
        const onClose = vi.fn();
        const onIncrementExisting = vi.fn();
        render(
            <AddItemModal
                listId={1}
                existingItems={existingItems}
                onAdd={onAdd}
                onIncrementExisting={onIncrementExisting}
                onClose={onClose}
            />
        );
        return { onAdd, onClose, onIncrementExisting };
    };

    it('does not show warning when matching item is purchased', async () => {
        const user = userEvent.setup();
        const { onAdd } = setup([{ id: 1, name: 'Pan', is_purchased: true }]);

        await user.type(screen.getByTestId('modal-product-input'), 'Pan');
        await user.click(screen.getByTestId('modal-add-button'));

        expect(screen.queryByTestId('duplicate-warning')).toBeNull();
        expect(onAdd).toHaveBeenCalled();
    });

    it('does not show warning when matching purchased item is plural variant', async () => {
        const user = userEvent.setup();
        const { onAdd } = setup([{ id: 1, name: 'Panes', is_purchased: true }]);

        await user.type(screen.getByTestId('modal-product-input'), 'pan');
        await user.click(screen.getByTestId('modal-add-button'));

        expect(screen.queryByTestId('duplicate-warning')).toBeNull();
        expect(onAdd).toHaveBeenCalled();
    });

    it('shows warning when matching pending item exists', async () => {
        const user = userEvent.setup();
        const { onAdd } = setup([{ id: 1, name: 'Pan', is_purchased: false }]);

        await user.type(screen.getByTestId('modal-product-input'), 'Pan');
        await user.click(screen.getByTestId('modal-add-button'));

        expect(screen.getByTestId('duplicate-warning')).toBeInTheDocument();
        expect(onAdd).not.toHaveBeenCalled();
    });

    it('shows warning when input is plural variant of a pending item', async () => {
        const user = userEvent.setup();
        const { onAdd } = setup([{ id: 1, name: 'Tomate', is_purchased: false }]);

        await user.type(screen.getByTestId('modal-product-input'), 'Tomates');
        await user.click(screen.getByTestId('modal-add-button'));

        expect(screen.getByTestId('duplicate-warning')).toBeInTheDocument();
        expect(onAdd).not.toHaveBeenCalled();
    });

    it('mixed list: warning fires only against pending, ignores purchased homonym', async () => {
        const user = userEvent.setup();
        setup([
            { id: 1, name: 'Panes', is_purchased: true },
            { id: 2, name: 'Pan', is_purchased: false },
        ]);

        await user.type(screen.getByTestId('modal-product-input'), 'Pan');
        await user.click(screen.getByTestId('modal-add-button'));

        const warning = screen.getByTestId('duplicate-warning');
        expect(warning).toBeInTheDocument();
        expect(warning).toHaveTextContent('Pan');
    });

    it('falls back to fuzzy match for typos against pending items', async () => {
        const user = userEvent.setup();
        setup([{ id: 1, name: 'Tomate cherry', is_purchased: false }]);

        await user.type(screen.getByTestId('modal-product-input'), 'Tomate cheery');
        await user.click(screen.getByTestId('modal-add-button'));

        expect(screen.getByTestId('duplicate-warning')).toBeInTheDocument();
    });

    it('does not match unrelated short names (pollo vs polla)', async () => {
        const user = userEvent.setup();
        const { onAdd } = setup([{ id: 1, name: 'Pollo', is_purchased: false }]);

        await user.type(screen.getByTestId('modal-product-input'), 'Polla');
        await user.click(screen.getByTestId('modal-add-button'));

        expect(screen.queryByTestId('duplicate-warning')).toBeNull();
        expect(onAdd).toHaveBeenCalled();
    });

    it('after warning, increment button calls onIncrementExisting with matched id', async () => {
        const user = userEvent.setup();
        const { onIncrementExisting, onClose } = setup([{ id: 42, name: 'Tomate', is_purchased: false }]);

        await user.type(screen.getByTestId('modal-product-input'), 'Tomates');
        await user.click(screen.getByTestId('modal-add-button'));

        expect(screen.getByTestId('duplicate-warning')).toBeInTheDocument();

        await user.click(screen.getByTestId('increment-quantity'));

        expect(onIncrementExisting).toHaveBeenCalledWith(42, expect.any(Number));
        expect(onClose).toHaveBeenCalled();
    });

    it('after warning, add-anyway proceeds with original payload', async () => {
        const user = userEvent.setup();
        const { onAdd } = setup([{ id: 1, name: 'Pan', is_purchased: false }]);

        await user.type(screen.getByTestId('modal-product-input'), 'Pan');
        await user.click(screen.getByTestId('modal-add-button'));

        expect(screen.getByTestId('duplicate-warning')).toBeInTheDocument();

        await user.click(screen.getByTestId('add-anyway'));

        expect(onAdd).toHaveBeenCalled();
    });
});
