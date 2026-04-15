import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, it, expect, vi, beforeEach } from 'vitest';

vi.mock('../../lib/suggestionsApi', () => ({
    fetchSuggestions: vi.fn().mockResolvedValue({ suggestions: [], ai_limit_reached: false }),
}));

import AddItemInput from './AddItemInput';
import { fetchSuggestions } from '../../lib/suggestionsApi';

describe('AddItemInput', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        fetchSuggestions.mockResolvedValue({ suggestions: [], ai_limit_reached: false });
    });

    it('submits plain name when no suggestion selected', async () => {
        const user = userEvent.setup();
        const onAdd = vi.fn().mockResolvedValue(true);

        render(<AddItemInput onAdd={onAdd} isLoading={false} />);

        await user.type(screen.getByRole('combobox'), 'Manzanas');
        await user.click(screen.getByRole('button', { name: /anadir/i }));

        expect(onAdd).toHaveBeenCalledWith({ name: 'Manzanas' });
    });

    it('pre-fills quantity/unit/category when suggestion is selected', async () => {
        const user = userEvent.setup();
        const onAdd = vi.fn().mockResolvedValue(true);

        const suggestion = { source: 'history', name: 'Leche entera', quantity: 1, unit: 'L', category: 'lacteos_huevos' };
        fetchSuggestions.mockResolvedValue({ suggestions: [suggestion], ai_limit_reached: false });

        render(<AddItemInput onAdd={onAdd} isLoading={false} />);

        await user.type(screen.getByRole('combobox'), 'le');
        await waitFor(() => screen.getByTestId('autocomplete-option-0'));

        await user.click(screen.getByTestId('autocomplete-option-0'));

        expect(screen.getByTestId('prefilled-hint')).toBeInTheDocument();

        await user.click(screen.getByRole('button', { name: /anadir/i }));

        expect(onAdd).toHaveBeenCalledWith({
            name: 'Leche entera',
            quantity: 1,
            unit: 'L',
            category: 'lacteos_huevos',
        });
    });

    it('clears prefilled data when user edits the name after selection', async () => {
        const user = userEvent.setup();
        const onAdd = vi.fn().mockResolvedValue(true);

        const suggestion = { source: 'history', name: 'Leche', quantity: 1, unit: 'L', category: 'lacteos_huevos' };
        fetchSuggestions.mockResolvedValue({ suggestions: [suggestion], ai_limit_reached: false });

        render(<AddItemInput onAdd={onAdd} isLoading={false} />);

        await user.type(screen.getByRole('combobox'), 'le');
        await waitFor(() => screen.getByTestId('autocomplete-option-0'));

        await user.click(screen.getByTestId('autocomplete-option-0'));
        expect(screen.getByTestId('prefilled-hint')).toBeInTheDocument();

        await user.type(screen.getByRole('combobox'), ' con cacao');
        expect(screen.queryByTestId('prefilled-hint')).toBeNull();

        await user.click(screen.getByRole('button', { name: /anadir/i }));

        expect(onAdd).toHaveBeenCalledWith({ name: 'Leche con cacao' });
    });

    it('disables submit when name is empty', () => {
        render(<AddItemInput onAdd={vi.fn()} isLoading={false} />);

        expect(screen.getByRole('button', { name: /anadir producto/i })).toBeDisabled();
    });

    it('disables submit while loading', () => {
        render(<AddItemInput onAdd={vi.fn()} isLoading={true} />);

        expect(screen.getByRole('button', { name: /anadir producto/i })).toBeDisabled();
    });

    it('clears the input after successful submit', async () => {
        const user = userEvent.setup();
        const onAdd = vi.fn().mockResolvedValue(true);

        render(<AddItemInput onAdd={onAdd} isLoading={false} />);

        await user.type(screen.getByRole('combobox'), 'Pan');
        await user.click(screen.getByRole('button', { name: /anadir/i }));

        await waitFor(() => expect(screen.getByRole('combobox')).toHaveValue(''));
    });

    it('does not clear the input if onAdd returns false', async () => {
        const user = userEvent.setup();
        const onAdd = vi.fn().mockResolvedValue(false);

        render(<AddItemInput onAdd={onAdd} isLoading={false} />);

        await user.type(screen.getByRole('combobox'), 'Pan');
        await user.click(screen.getByRole('button', { name: /anadir/i }));

        await waitFor(() => expect(onAdd).toHaveBeenCalled());
        expect(screen.getByRole('combobox')).toHaveValue('Pan');
    });
});
