import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import PriceBar from './PriceBar';

const mockEstimate = {
    total_min: 12.50,
    total_max: 18.30,
    resolved_count: 3,
    unresolved_count: 1,
    items: [
        { item_id: 1, name: 'Leche', min: 1.80, max: 2.40, source: 'catalog' },
        { item_id: 2, name: 'Pan', min: 0.50, max: 0.80, source: 'catalog' },
        { item_id: 3, name: 'Arroz', min: 10.20, max: 10.20, source: 'history' },
        { item_id: 4, name: 'Salsa rara', min: null, max: null, source: null },
    ],
};

describe('PriceBar', () => {
    it('renders empty state when no estimate and not calculating', () => {
        render(<PriceBar estimate={null} onRecalculate={vi.fn()} isCalculating={false} />);
        expect(screen.getByTestId('price-bar')).toBeInTheDocument();
        expect(screen.getByTestId('no-price-data')).toBeInTheDocument();
        expect(screen.getByTestId('recalculate-button')).toBeInTheDocument();
    });

    it('shows calculating state', () => {
        render(<PriceBar estimate={null} onRecalculate={vi.fn()} isCalculating={true} />);
        expect(screen.getByTestId('price-calculating')).toBeInTheDocument();
    });

    it('shows price range when estimate available', () => {
        render(<PriceBar estimate={mockEstimate} onRecalculate={vi.fn()} isCalculating={false} />);
        expect(screen.getByText(/12,50€ — 18,30€/)).toBeInTheDocument();
        expect(screen.getByText(/3 de 4 productos con precio/)).toBeInTheDocument();
    });

    it('expands breakdown on click', async () => {
        const user = userEvent.setup();
        render(<PriceBar estimate={mockEstimate} onRecalculate={vi.fn()} isCalculating={false} />);
        await user.click(screen.getByTestId('price-toggle'));
        expect(screen.getByTestId('price-breakdown')).toBeInTheDocument();
        expect(screen.getByText('Leche')).toBeInTheDocument();
        expect(screen.getByText('sin datos')).toBeInTheDocument();
    });

    it('shows exact price for history source (min === max)', async () => {
        const user = userEvent.setup();
        render(<PriceBar estimate={mockEstimate} onRecalculate={vi.fn()} isCalculating={false} />);
        await user.click(screen.getByTestId('price-toggle'));
        expect(screen.getByText('10,20€')).toBeInTheDocument();
    });

    it('calls onRecalculate when button clicked', async () => {
        const onRecalculate = vi.fn();
        const user = userEvent.setup();
        render(<PriceBar estimate={mockEstimate} onRecalculate={onRecalculate} isCalculating={false} />);
        await user.click(screen.getByTestId('recalculate-button'));
        expect(onRecalculate).toHaveBeenCalledTimes(1);
    });

    it('shows no-data state when 0 resolved', () => {
        const empty = { ...mockEstimate, resolved_count: 0, total_min: 0, total_max: 0 };
        render(<PriceBar estimate={empty} onRecalculate={vi.fn()} isCalculating={false} />);
        expect(screen.getByTestId('no-price-data')).toBeInTheDocument();
    });
});
