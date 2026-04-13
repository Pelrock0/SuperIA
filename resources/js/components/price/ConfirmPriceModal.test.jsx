import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import ConfirmPriceModal from './ConfirmPriceModal';

const mockItems = [
    { id: 1, name: 'Leche' },
    { id: 2, name: 'Pan' },
];

describe('ConfirmPriceModal', () => {
    it('renders modal with total input', () => {
        render(<ConfirmPriceModal items={mockItems} onConfirm={vi.fn()} onDismiss={vi.fn()} />);
        expect(screen.getByTestId('confirm-price-modal')).toBeInTheDocument();
        expect(screen.getByTestId('total-price-input')).toBeInTheDocument();
    });

    it('dismiss calls onDismiss', async () => {
        const onDismiss = vi.fn();
        const user = userEvent.setup();
        render(<ConfirmPriceModal items={mockItems} onConfirm={vi.fn()} onDismiss={onDismiss} />);
        await user.click(screen.getByTestId('dismiss-prices'));
        expect(onDismiss).toHaveBeenCalled();
    });

    it('submits total-only price', async () => {
        const onConfirm = vi.fn();
        const user = userEvent.setup();
        render(<ConfirmPriceModal items={mockItems} onConfirm={onConfirm} onDismiss={vi.fn()} />);
        await user.type(screen.getByTestId('total-price-input'), '42.50');
        await user.click(screen.getByTestId('submit-prices'));
        expect(onConfirm).toHaveBeenCalledWith(42.50, []);
    });

    it('expands per-item section', async () => {
        const user = userEvent.setup();
        render(<ConfirmPriceModal items={mockItems} onConfirm={vi.fn()} onDismiss={vi.fn()} />);
        await user.click(screen.getByTestId('toggle-per-item'));
        expect(screen.getByTestId('per-item-section')).toBeInTheDocument();
        expect(screen.getByTestId('item-price-1')).toBeInTheDocument();
        expect(screen.getByTestId('item-price-2')).toBeInTheDocument();
    });

    it('submits per-item prices', async () => {
        const onConfirm = vi.fn();
        const user = userEvent.setup();
        render(<ConfirmPriceModal items={mockItems} onConfirm={onConfirm} onDismiss={vi.fn()} />);
        await user.type(screen.getByTestId('total-price-input'), '5');
        await user.click(screen.getByTestId('toggle-per-item'));
        await user.type(screen.getByTestId('item-price-1'), '2.50');
        await user.click(screen.getByTestId('submit-prices'));
        expect(onConfirm).toHaveBeenCalledWith(5, [{ item_id: 1, price: 2.50 }]);
    });

    it('submit disabled when no data entered', () => {
        render(<ConfirmPriceModal items={mockItems} onConfirm={vi.fn()} onDismiss={vi.fn()} />);
        expect(screen.getByTestId('submit-prices')).toBeDisabled();
    });
});
