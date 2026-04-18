import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, it, expect, vi } from 'vitest';
import SelectListModal from './SelectListModal';

const lists = [
    { id: 1, name: 'Compra semanal', emoji: '🛒' },
    { id: 2, name: 'Farmacia', emoji: '💊' },
];

describe('SelectListModal', () => {
    it('renders title and list options', () => {
        render(<SelectListModal lists={lists} onSelect={vi.fn()} onCancel={vi.fn()} />);

        expect(screen.getByRole('heading', { name: /añadir a que lista/i })).toBeInTheDocument();
        expect(screen.getByText('Compra semanal')).toBeInTheDocument();
        expect(screen.getByText('Farmacia')).toBeInTheDocument();
    });

    it('renders product name when provided', () => {
        render(<SelectListModal lists={lists} productName="Leche" onSelect={vi.fn()} onCancel={vi.fn()} />);

        expect(screen.getByText(/Leche/)).toBeInTheDocument();
    });

    it('calls onSelect with the picked list id', async () => {
        const user = userEvent.setup();
        const onSelect = vi.fn();

        render(<SelectListModal lists={lists} onSelect={onSelect} onCancel={vi.fn()} />);

        await user.click(screen.getByTestId('select-list-option-1'));

        expect(onSelect).toHaveBeenCalledWith(1);
    });

    it('calls onCancel when cancel clicked', async () => {
        const user = userEvent.setup();
        const onCancel = vi.fn();

        render(<SelectListModal lists={lists} onSelect={vi.fn()} onCancel={onCancel} />);

        await user.click(screen.getByRole('button', { name: /cancelar/i }));

        expect(onCancel).toHaveBeenCalled();
    });

    it('has modal dialog role', () => {
        render(<SelectListModal lists={lists} onSelect={vi.fn()} onCancel={vi.fn()} />);

        expect(screen.getByRole('dialog')).toHaveAttribute('aria-modal', 'true');
    });
});
