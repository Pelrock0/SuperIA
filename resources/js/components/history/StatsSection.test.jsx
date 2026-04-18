import { render, screen } from '@testing-library/react';
import { describe, it, expect, vi } from 'vitest';

// Mock recharts to avoid jsdom SVG issues
vi.mock('recharts', () => ({
    ResponsiveContainer: ({ children }) => <div>{children}</div>,
    BarChart: ({ children }) => <div data-testid="bar-chart">{children}</div>,
    Bar: () => null,
    XAxis: () => null,
    YAxis: () => null,
    Tooltip: () => null,
    PieChart: ({ children }) => <div data-testid="pie-chart">{children}</div>,
    Pie: ({ children }) => <div>{children}</div>,
    Cell: () => null,
}));

import StatsSection from './StatsSection';

const fullStats = {
    has_enough_data: true,
    total_lists_completed: 5,
    monthly_spend: [{ month: '2026-03', total: 120.50 }, { month: '2026-04', total: 95.00 }],
    top_categories: [
        { category: 'frutas_verduras', count: 30, percentage: 25.0 },
        { category: 'lacteos_huevos', count: 20, percentage: 16.7 },
    ],
    top_products: [
        { name: 'Leche', count: 12 },
        { name: 'Pan', count: 10 },
    ],
};

describe('StatsSection', () => {
    it('shows not-enough-data message when has_enough_data is false', () => {
        render(<StatsSection stats={{ has_enough_data: false }} />);
        expect(screen.getByTestId('stats-not-enough')).toBeInTheDocument();
        expect(screen.getByText(/al menos 3 listas/)).toBeInTheDocument();
    });

    it('renders stats when enough data', () => {
        render(<StatsSection stats={fullStats} />);
        expect(screen.getByTestId('stats-section')).toBeInTheDocument();
        expect(screen.getByTestId('monthly-spend-chart')).toBeInTheDocument();
        expect(screen.getByTestId('top-categories')).toBeInTheDocument();
        expect(screen.getByTestId('top-products')).toBeInTheDocument();
    });

    it('renders category labels in Spanish', () => {
        render(<StatsSection stats={fullStats} />);
        expect(screen.getByText('Frutas y verduras')).toBeInTheDocument();
        expect(screen.getByText('Lácteos y huevos')).toBeInTheDocument();
    });

    it('renders product ranking', () => {
        render(<StatsSection stats={fullStats} />);
        expect(screen.getByText('Leche')).toBeInTheDocument();
        expect(screen.getByText('12x')).toBeInTheDocument();
    });

    it('shows disclaimer on estimated amounts', () => {
        render(<StatsSection stats={fullStats} />);
        expect(screen.getByText(/estimaciones salvo confirmación/)).toBeInTheDocument();
    });

    it('handles null stats', () => {
        render(<StatsSection stats={null} />);
        expect(screen.getByTestId('stats-not-enough')).toBeInTheDocument();
    });
});
