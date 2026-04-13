import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import EditItemPanel from './EditItemPanel';

const mockItem = {
    id: 1,
    name: 'Leche',
    quantity: 2,
    unit: 'L',
    category: 'lacteos_huevos',
    estimated_price: 1.50,
};

describe('EditItemPanel', () => {
    const mockSave = vi.fn();
    const mockClose = vi.fn();

    beforeEach(() => vi.clearAllMocks());

    function renderPanel(item = mockItem) {
        return render(<EditItemPanel item={item} onSave={mockSave} onClose={mockClose} />);
    }

    it('renders form with pre-filled values', () => {
        renderPanel();
        expect(screen.getByLabelText(/nombre/i)).toHaveValue('Leche');
        expect(screen.getByLabelText(/cantidad/i)).toHaveValue(2);
        expect(screen.getByLabelText(/unidad/i)).toHaveValue('L');
        expect(screen.getByLabelText(/categoria/i)).toHaveValue('lacteos_huevos');
    });

    it('calls onSave with updated data', async () => {
        const user = userEvent.setup();
        mockSave.mockResolvedValueOnce(undefined);
        renderPanel();

        await user.clear(screen.getByLabelText(/nombre/i));
        await user.type(screen.getByLabelText(/nombre/i), 'Leche desnatada');
        await user.click(screen.getByRole('button', { name: /guardar/i }));

        expect(mockSave).toHaveBeenCalledWith(1, expect.objectContaining({ name: 'Leche desnatada' }));
    });

    it('calls onClose when cancel clicked', async () => {
        const user = userEvent.setup();
        renderPanel();

        await user.click(screen.getByRole('button', { name: /cancelar/i }));
        expect(mockClose).toHaveBeenCalledOnce();
    });

    it('closes after save', async () => {
        const user = userEvent.setup();
        mockSave.mockResolvedValueOnce(undefined);
        renderPanel();

        await user.click(screen.getByRole('button', { name: /guardar/i }));
        expect(mockClose).toHaveBeenCalledOnce();
    });
});
