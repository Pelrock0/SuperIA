import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import ItemRow from './ItemRow';

const mockItem = {
    id: 1,
    name: 'Leche entera',
    quantity: '2.00',
    unit: 'L',
    category: 'lacteos_huevos',
    estimated_price: '1.50',
    is_purchased: false,
};

describe('ItemRow', () => {
    const handlers = { onToggle: vi.fn(), onEdit: vi.fn(), onDelete: vi.fn() };

    beforeEach(() => vi.clearAllMocks());

    it('renders item name', () => {
        render(<ItemRow item={mockItem} {...handlers} />);
        expect(screen.getByText('Leche entera')).toBeInTheDocument();
    });

    it('renders quantity, unit, and price', () => {
        render(<ItemRow item={mockItem} {...handlers} />);
        expect(screen.getByText(/2\.00L/)).toBeInTheDocument();
        expect(screen.getByText(/~1\.50€/)).toBeInTheDocument();
    });

    it('shows strikethrough when purchased', () => {
        render(<ItemRow item={{ ...mockItem, is_purchased: true }} {...handlers} />);
        const nameEl = screen.getByText('Leche entera');
        expect(nameEl).toHaveClass('line-through');
    });

    it('calls onToggle when checkbox clicked', async () => {
        const user = userEvent.setup();
        render(<ItemRow item={mockItem} {...handlers} />);

        await user.click(screen.getByRole('checkbox'));
        expect(handlers.onToggle).toHaveBeenCalledWith(1);
    });

    it('calls onEdit when item text clicked', async () => {
        const user = userEvent.setup();
        render(<ItemRow item={mockItem} {...handlers} />);

        await user.click(screen.getByText('Leche entera'));
        expect(handlers.onEdit).toHaveBeenCalledWith(mockItem);
    });

    it('calls onDelete when delete button clicked', async () => {
        const user = userEvent.setup();
        render(<ItemRow item={mockItem} {...handlers} />);

        await user.click(screen.getByLabelText(/eliminar leche entera/i));
        expect(handlers.onDelete).toHaveBeenCalledWith(mockItem);
    });

    it('renders without quantity/price', () => {
        render(<ItemRow item={{ ...mockItem, quantity: null, estimated_price: null }} {...handlers} />);
        expect(screen.getByText('Leche entera')).toBeInTheDocument();
    });
});
